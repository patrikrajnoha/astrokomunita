<template>
  <div class="customRoot">
    <header class="customHeader">
      <div>
        <h2>Vlastne komponenty</h2>
        <p>Vytvor si vlastne widgety do sidebaru.</p>
      </div>
      <button type="button" class="primaryBtn" :disabled="saving" @click="startCreate">
        Novy komponent
      </button>
    </header>

    <div class="customGrid">
      <SidebarComponentList
        v-model="searchQuery"
        :items="filteredComponents"
        :selected-id="formState.id"
        :loading="listLoading"
        :error-message="listError"
        :busy="saving"
        @select="openEditor"
        @toggle-active="toggleActive"
        @delete-item="deleteComponent"
      />

      <SidebarComponentForm
        v-model="formState"
        :saving="saving"
        :validation-errors="validationErrors"
        @submit="saveComponent"
        @reset="startCreate"
      />

      <section class="previewPanel">
        <h3>Zivy nahlad</h3>
        <p>Nahlad pouziva rovnaky renderer ako produkcny sidebar.</p>
        <SidebarWidgetRenderer :widget="previewWidget" preview />
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { sidebarCustomComponentsAdminApi } from '@/services/api/admin/sidebarConfig'
import SidebarWidgetRenderer from '@/components/widgets/SidebarWidgetRenderer.vue'
import SidebarComponentList from '@/components/admin/sidebar/SidebarComponentList.vue'
import SidebarComponentForm from '@/components/admin/sidebar/SidebarComponentForm.vue'
import {
  SIDEBAR_WIDGET_TYPES,
  cloneWidgetFormState,
  createEmptyWidgetFormState,
  normalizeSidebarCustomComponent,
  normalizeWidgetConfig,
  normalizeWidgetType,
} from '@/sidebar/customWidgets/types'

const emit = defineEmits(['components-changed', 'dirty-change'])

const components = ref([])
const listLoading = ref(false)
const listError = ref('')
const searchQuery = ref('')
const formState = ref(createEmptyWidgetFormState())
const saving = ref(false)
const validationErrors = ref({})
const originalSnapshot = ref(JSON.stringify(cloneWidgetFormState(formState.value)))

const { confirm } = useConfirm()
const { showToast } = useToast()

const filteredComponents = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return components.value

  return components.value.filter((item) => {
    return (
      String(item?.name || '').toLowerCase().includes(query)
      || String(item?.type || '').toLowerCase().includes(query)
    )
  })
})

const previewWidget = computed(() => {
  return {
    type: normalizeWidgetType(formState.value.type),
    is_active: Boolean(formState.value.is_active),
    config_json: normalizeWidgetConfig(formState.value.type, formState.value.config_json),
  }
})

const hasUnsavedChanges = computed(() => {
  const current = JSON.stringify(cloneWidgetFormState(formState.value))
  return current !== originalSnapshot.value
})

watch(
  () => hasUnsavedChanges.value,
  (value) => {
    emit('dirty-change', value)
  },
  { immediate: true },
)

const setFormFromComponent = (payload) => {
  const normalized = normalizeSidebarCustomComponent(payload)

  formState.value = {
    id: normalized.id,
    name: normalized.name,
    type: normalized.type,
    is_active: normalized.is_active,
    config_json: normalizeWidgetConfig(normalized.type, normalized.config_json),
  }

  originalSnapshot.value = JSON.stringify(cloneWidgetFormState(formState.value))
  validationErrors.value = {}
}

const loadComponents = async ({ selectedId = formState.value.id, preserveSelection = true } = {}) => {
  listLoading.value = true
  listError.value = ''

  try {
    const payload = await sidebarCustomComponentsAdminApi.list()
    const rows = Array.isArray(payload?.data) ? payload.data : []
    components.value = rows.map((item) => normalizeSidebarCustomComponent(item))

    if (preserveSelection && Number.isFinite(Number(selectedId)) && Number(selectedId) > 0) {
      const selected = components.value.find((item) => Number(item.id) === Number(selectedId))
      if (selected) {
        setFormFromComponent(selected)
      }
    }
  } catch (error) {
    components.value = []
    listError.value = error?.response?.data?.message || 'Nepodarilo sa nacitat komponenty.'
  } finally {
    listLoading.value = false
  }
}

const startCreate = () => {
  formState.value = createEmptyWidgetFormState()
  originalSnapshot.value = JSON.stringify(cloneWidgetFormState(formState.value))
  validationErrors.value = {}
}

const openEditor = async (itemOrId) => {
  const candidateId = Number(typeof itemOrId === 'object' ? itemOrId?.id : itemOrId)
  if (!Number.isFinite(candidateId) || candidateId < 1) {
    startCreate()
    return
  }

  const local = components.value.find((item) => Number(item.id) === candidateId)
  if (local) {
    setFormFromComponent(local)
    return
  }

  try {
    const payload = await sidebarCustomComponentsAdminApi.get(candidateId)
    const component = normalizeSidebarCustomComponent(payload?.data)
    const hasExisting = components.value.some((item) => Number(item.id) === Number(component.id))
    if (!hasExisting) {
      components.value = [component, ...components.value]
    }
    setFormFromComponent(component)
  } catch (error) {
    showToast(error?.response?.data?.message || 'Nepodarilo sa nacitat komponent.', 'error')
  }
}

const isValidUrlOrPath = (value) => {
  if (!value) return false
  const text = String(value).trim()
  if (!text) return false

  if (text.startsWith('/')) {
    return true
  }

  return /^https?:\/\/.+/i.test(text)
}

const validateForm = () => {
  const errors = {}
  const name = String(formState.value.name || '').trim()
  const type = normalizeWidgetType(formState.value.type)
  const config = normalizeWidgetConfig(type, formState.value.config_json)

  if (name.length < 2) {
    errors.name = 'Nazov je povinny (min. 2 znaky).'
  }

  if (type === SIDEBAR_WIDGET_TYPES.CTA) {
    if (!String(config.headline || '').trim()) errors['config_json.headline'] = 'Headline je povinny.'
    if (!String(config.body || '').trim()) errors['config_json.body'] = 'Body je povinny.'
    if (!String(config.buttonText || '').trim()) errors['config_json.buttonText'] = 'Text tlacidla je povinny.'

    if (!String(config.buttonHref || '').trim()) {
      errors['config_json.buttonHref'] = 'URL/cesta tlacidla je povinna.'
    } else if (!isValidUrlOrPath(config.buttonHref)) {
      errors['config_json.buttonHref'] = 'Pouzi platnu URL alebo absolutnu cestu (/...).'
    }

    if (String(config.imageUrl || '').trim() && !isValidUrlOrPath(config.imageUrl)) {
      errors['config_json.imageUrl'] = 'Image URL musi byt platna URL alebo absolutna cesta.'
    }
  }

  if (type === SIDEBAR_WIDGET_TYPES.INFO_CARD) {
    if (!String(config.title || '').trim()) errors['config_json.title'] = 'Titulok je povinny.'
    if (!String(config.content || '').trim()) errors['config_json.content'] = 'Obsah je povinny.'
  }

  if (type === SIDEBAR_WIDGET_TYPES.LINK_LIST) {
    if (!String(config.title || '').trim()) errors['config_json.title'] = 'Nadpis je povinny.'

    const links = Array.isArray(config.links) ? config.links : []
    if (links.length === 0) {
      errors['config_json.links'] = 'Pridaj aspon jeden odkaz.'
    }

    links.forEach((link, index) => {
      if (!String(link?.label || '').trim()) {
        errors[`config_json.links.${index}.label`] = 'Label je povinny.'
      }
      if (!String(link?.href || '').trim()) {
        errors[`config_json.links.${index}.href`] = 'Href je povinne.'
      } else if (!isValidUrlOrPath(link.href)) {
        errors[`config_json.links.${index}.href`] = 'Pouzi platnu URL alebo absolutnu cestu (/...).'
      }
    })
  }

  if (type === SIDEBAR_WIDGET_TYPES.HTML) {
    if (!String(config.html || '').trim()) {
      errors['config_json.html'] = 'HTML obsah je povinny.'
    }
  }

  return errors
}

const mapApiValidationErrors = (error) => {
  const serverErrors = error?.response?.data?.errors
  if (!serverErrors || typeof serverErrors !== 'object') {
    return false
  }

  const mapped = {}
  for (const [key, messages] of Object.entries(serverErrors)) {
    mapped[key] = Array.isArray(messages) ? String(messages[0] || '') : String(messages || '')
  }

  validationErrors.value = mapped
  return true
}

const buildPayload = () => {
  const type = normalizeWidgetType(formState.value.type)
  return {
    name: String(formState.value.name || '').trim(),
    type,
    is_active: Boolean(formState.value.is_active),
    config_json: normalizeWidgetConfig(type, formState.value.config_json),
  }
}

const saveComponent = async () => {
  validationErrors.value = validateForm()
  if (Object.keys(validationErrors.value).length > 0) {
    showToast('Skontroluj formular a oprav chyby.', 'error')
    return
  }

  saving.value = true

  const payload = buildPayload()
  const isEdit = Number.isFinite(Number(formState.value.id)) && Number(formState.value.id) > 0

  try {
    const response = isEdit
      ? await sidebarCustomComponentsAdminApi.update(formState.value.id, payload)
      : await sidebarCustomComponentsAdminApi.create(payload)

    const saved = normalizeSidebarCustomComponent(response?.data)

    await loadComponents({ selectedId: saved.id, preserveSelection: true })
    showToast(isEdit ? 'Komponent bol upraveny.' : 'Komponent bol vytvoreny.', 'success')
    emit('components-changed')
  } catch (error) {
    if (!mapApiValidationErrors(error)) {
      showToast(error?.response?.data?.message || 'Nepodarilo sa ulozit komponent.', 'error')
    }
  } finally {
    saving.value = false
  }
}

const toggleActive = async (item) => {
  const normalized = normalizeSidebarCustomComponent(item)
  if (!normalized.id) return

  saving.value = true
  try {
    await sidebarCustomComponentsAdminApi.update(normalized.id, {
      name: normalized.name,
      type: normalized.type,
      is_active: !normalized.is_active,
      config_json: normalized.config_json,
    })

    await loadComponents({
      selectedId: Number(formState.value.id) === Number(normalized.id) ? normalized.id : formState.value.id,
      preserveSelection: true,
    })

    showToast(!normalized.is_active ? 'Komponent bol aktivovany.' : 'Komponent bol deaktivovany.', 'success')
    emit('components-changed')
  } catch (error) {
    showToast(error?.response?.data?.message || 'Nepodarilo sa zmenit stav komponentu.', 'error')
  } finally {
    saving.value = false
  }
}

const deleteComponent = async (item) => {
  const normalized = normalizeSidebarCustomComponent(item)
  if (!normalized.id) return

  const approved = await confirm({
    title: 'Zmazat komponent',
    message: `Naozaj zmazat widget "${normalized.name}"?`,
    confirmText: 'Zmazat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!approved) return

  saving.value = true
  try {
    await sidebarCustomComponentsAdminApi.remove(normalized.id)

    const nextSelected = Number(formState.value.id) === Number(normalized.id)
      ? null
      : formState.value.id

    await loadComponents({
      selectedId: nextSelected,
      preserveSelection: Number.isFinite(Number(nextSelected)) && Number(nextSelected) > 0,
    })

    if (!nextSelected) {
      startCreate()
    }

    showToast('Komponent bol zmazany.', 'success')
    emit('components-changed')
  } catch (error) {
    showToast(error?.response?.data?.message || 'Nepodarilo sa zmazat komponent.', 'error')
  } finally {
    saving.value = false
  }
}

defineExpose({
  startCreate,
  openEditor,
  refreshList: loadComponents,
})

onMounted(async () => {
  await loadComponents({ preserveSelection: false })

  const hasSelectedComponent = Number.isFinite(Number(formState.value.id)) && Number(formState.value.id) > 0
  if (!hasSelectedComponent) {
    startCreate()
  }
})
</script>

<style scoped>
.customRoot {
  display: grid;
  gap: 0.9rem;
}

.customHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.8rem;
}

.customHeader h2 {
  margin: 0;
  font-size: 1.15rem;
  color: var(--color-surface);
}

.customHeader p {
  margin: 0.25rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.84rem;
}

.primaryBtn {
  border-radius: 0.76rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.58);
  background: rgb(var(--color-primary-rgb) / 0.24);
  color: var(--color-surface);
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.5rem 0.78rem;
}

.primaryBtn:disabled {
  opacity: 0.6;
}

.customGrid {
  display: grid;
  gap: 0.8rem;
  grid-template-columns: 1.22fr 1fr 0.92fr;
}

.customGrid > * {
  min-width: 0;
  border-radius: 0.95rem;
  background: rgb(var(--color-bg-rgb) / 0.24);
  box-shadow: inset 0 0 0 1px rgb(var(--color-text-secondary-rgb) / 0.14);
  padding: 0.72rem;
}

.previewPanel {
  display: grid;
  gap: 0.62rem;
  align-content: start;
}

.previewPanel h3 {
  margin: 0;
  font-size: 0.95rem;
}

.previewPanel p {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

@media (max-width: 1320px) {
  .customGrid {
    grid-template-columns: 1fr 1fr;
  }

  .previewPanel {
    grid-column: 1 / -1;
  }
}

@media (max-width: 1080px) {
  .customHeader {
    flex-direction: column;
    align-items: flex-start;
  }

  .customGrid {
    grid-template-columns: 1fr;
  }
}
</style>
