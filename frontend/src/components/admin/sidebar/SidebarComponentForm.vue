<template>
  <section class="panel">
    <div class="panelHead">
      <h3>{{ isEdit ? 'Upravit komponent' : 'Novy komponent' }}</h3>
      <label class="activeToggle">
        <input :checked="Boolean(formState.is_active)" type="checkbox" :disabled="saving" @change="setRoot('is_active', $event.target.checked)" />
        Aktivny
      </label>
    </div>

    <form class="form" @submit.prevent="$emit('submit')">
      <label class="field">
        <span>Nazov widgetu</span>
        <input :value="formState.name" type="text" :disabled="saving" placeholder="Napriklad: Promo panel" @input="setRoot('name', $event.target.value)" />
        <small v-if="fieldError('name')" class="errorText">{{ fieldError('name') }}</small>
      </label>

      <label class="field">
        <span>Typ widgetu</span>
        <select :value="formState.type" :disabled="saving" @change="setType($event.target.value)">
          <option v-for="option in SIDEBAR_WIDGET_TYPE_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <small v-if="fieldError('type')" class="errorText">{{ fieldError('type') }}</small>
      </label>

      <template v-if="formState.type === SIDEBAR_WIDGET_TYPES.CTA">
        <label class="field">
          <span>Headline</span>
          <input :value="ctaConfig.headline" type="text" :disabled="saving" placeholder="Objav vesmir s nami" @input="setConfig('headline', $event.target.value)" />
          <small v-if="fieldError('config_json.headline')" class="errorText">{{ fieldError('config_json.headline') }}</small>
        </label>

        <label class="field">
          <span>Body</span>
          <textarea :value="ctaConfig.body" rows="3" :disabled="saving" placeholder="Kratsi text pre CTA widget." @input="setConfig('body', $event.target.value)"></textarea>
          <small v-if="fieldError('config_json.body')" class="errorText">{{ fieldError('config_json.body') }}</small>
        </label>

        <div class="inlineFields">
          <label class="field">
            <span>Text tlacidla</span>
            <input :value="ctaConfig.buttonText" type="text" :disabled="saving" placeholder="Otvorit" @input="setConfig('buttonText', $event.target.value)" />
            <small v-if="fieldError('config_json.buttonText')" class="errorText">{{ fieldError('config_json.buttonText') }}</small>
          </label>

          <label class="field">
            <span>URL alebo cesta</span>
            <input :value="ctaConfig.buttonHref" type="text" :disabled="saving" placeholder="/events alebo https://..." @input="setConfig('buttonHref', $event.target.value)" />
            <small v-if="fieldError('config_json.buttonHref')" class="errorText">{{ fieldError('config_json.buttonHref') }}</small>
          </label>
        </div>

        <div class="inlineFields">
          <label class="field">
            <span>Image URL (volitelne)</span>
            <input :value="ctaConfig.imageUrl" type="text" :disabled="saving" placeholder="https://..." @input="setConfig('imageUrl', $event.target.value)" />
            <small v-if="fieldError('config_json.imageUrl')" class="errorText">{{ fieldError('config_json.imageUrl') }}</small>
          </label>

          <label class="field">
            <span>Ikona (volitelne)</span>
            <input :value="ctaConfig.icon" type="text" :disabled="saving" placeholder="star" @input="setConfig('icon', $event.target.value)" />
          </label>
        </div>
      </template>

      <template v-else-if="formState.type === SIDEBAR_WIDGET_TYPES.INFO_CARD">
        <label class="field">
          <span>Titulok</span>
          <input :value="infoCardConfig.title" type="text" :disabled="saving" placeholder="Info karta" @input="setConfig('title', $event.target.value)" />
          <small v-if="fieldError('config_json.title')" class="errorText">{{ fieldError('config_json.title') }}</small>
        </label>

        <label class="field">
          <span>Obsah</span>
          <textarea :value="infoCardConfig.content" rows="4" :disabled="saving" placeholder="Text info karty" @input="setConfig('content', $event.target.value)"></textarea>
          <small v-if="fieldError('config_json.content')" class="errorText">{{ fieldError('config_json.content') }}</small>
        </label>

        <label class="field">
          <span>Ikona (volitelne)</span>
          <input :value="infoCardConfig.icon" type="text" :disabled="saving" placeholder="moon" @input="setConfig('icon', $event.target.value)" />
        </label>
      </template>

      <template v-else-if="formState.type === SIDEBAR_WIDGET_TYPES.LINK_LIST">
        <label class="field">
          <span>Nadpis</span>
          <input :value="linkListConfig.title" type="text" :disabled="saving" placeholder="Uzitocne odkazy" @input="setConfig('title', $event.target.value)" />
          <small v-if="fieldError('config_json.title')" class="errorText">{{ fieldError('config_json.title') }}</small>
        </label>

        <div class="linksBox">
          <div class="linksHead">
            <span>Odkazy</span>
            <button type="button" class="ghostBtn" :disabled="saving" @click="addLink">Pridat riadok</button>
          </div>

          <div v-for="(link, index) in linkRows" :key="`row-${index}`" class="linkRow">
            <input :value="link.label" type="text" :disabled="saving" placeholder="Label" @input="updateLink(index, 'label', $event.target.value)" />
            <input :value="link.href" type="text" :disabled="saving" placeholder="/events alebo https://..." @input="updateLink(index, 'href', $event.target.value)" />
            <button type="button" class="ghostBtn danger" :disabled="saving || linkRows.length <= 1" @click="removeLink(index)">
              Zmazat
            </button>
            <small v-if="fieldError(`config_json.links.${index}.label`)" class="errorText fullWidth">{{ fieldError(`config_json.links.${index}.label`) }}</small>
            <small v-if="fieldError(`config_json.links.${index}.href`)" class="errorText fullWidth">{{ fieldError(`config_json.links.${index}.href`) }}</small>
          </div>

          <small v-if="fieldError('config_json.links')" class="errorText">{{ fieldError('config_json.links') }}</small>
        </div>
      </template>

      <template v-else>
        <label class="field">
          <span>HTML obsah</span>
          <textarea :value="htmlConfig.html" rows="8" :disabled="saving" placeholder="<p>Vlastny HTML obsah widgetu</p>" @input="setConfig('html', $event.target.value)"></textarea>
          <small v-if="fieldError('config_json.html')" class="errorText">{{ fieldError('config_json.html') }}</small>
        </label>
      </template>

      <div class="actions">
        <button type="submit" class="primaryBtn" :disabled="saving">
          {{ saving ? 'Ukladam...' : isEdit ? 'Ulozit zmeny' : 'Vytvorit komponent' }}
        </button>
        <button v-if="isEdit" type="button" class="ghostBtn" :disabled="saving" @click="$emit('reset')">Novy komponent</button>
      </div>
    </form>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import {
  SIDEBAR_WIDGET_TYPE_OPTIONS,
  SIDEBAR_WIDGET_TYPES,
  normalizeWidgetConfig,
  normalizeWidgetType,
} from '@/sidebar/customWidgets/types'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  validationErrors: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'reset'])

const formState = computed(() => props.modelValue)

const isEdit = computed(() => Number.isFinite(Number(formState.value?.id)) && Number(formState.value.id) > 0)

const ctaConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.CTA, formState.value.config_json))
const infoCardConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.INFO_CARD, formState.value.config_json))
const linkListConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.LINK_LIST, formState.value.config_json))
const htmlConfig = computed(() => normalizeWidgetConfig(SIDEBAR_WIDGET_TYPES.HTML, formState.value.config_json))

const linkRows = computed(() => {
  const rows = Array.isArray(linkListConfig.value.links) ? linkListConfig.value.links : []
  return rows.length > 0 ? rows : [{ label: '', href: '' }]
})

const emitModel = (next) => {
  emit('update:modelValue', {
    ...next,
    type: normalizeWidgetType(next.type),
    config_json: normalizeWidgetConfig(next.type, next.config_json),
  })
}

const setRoot = (key, value) => {
  emitModel({
    ...formState.value,
    [key]: value,
  })
}

const setConfig = (key, value) => {
  emitModel({
    ...formState.value,
    config_json: {
      ...(formState.value?.config_json || {}),
      [key]: value,
    },
  })
}

const setType = (nextType) => {
  const normalizedType = normalizeWidgetType(nextType)
  emitModel({
    ...formState.value,
    type: normalizedType,
    config_json: normalizeWidgetConfig(normalizedType, formState.value?.config_json || {}),
  })
}

const addLink = () => {
  const next = [...linkRows.value, { label: '', href: '' }]
  setConfig('links', next)
}

const removeLink = (index) => {
  const next = linkRows.value.filter((_, rowIndex) => rowIndex !== index)
  setConfig('links', next.length > 0 ? next : [{ label: '', href: '' }])
}

const updateLink = (index, key, value) => {
  const next = linkRows.value.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: value } : row))
  setConfig('links', next)
}

const fieldError = (fieldPath) => {
  return props.validationErrors?.[fieldPath] || ''
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.7rem;
  min-width: 0;
}

.panelHead {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.6rem;
}

.panelHead h3 {
  margin: 0;
  font-size: 0.95rem;
}

.activeToggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.74rem;
  color: var(--color-text-secondary);
}

.form {
  display: grid;
  gap: 0.65rem;
}

.field {
  display: grid;
  gap: 0.32rem;
}

.field span {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
}

.field input,
.field select,
.field textarea,
.linkRow input {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 0.7rem;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  padding: 0.46rem 0.56rem;
  font-size: 0.8rem;
}

.field textarea {
  resize: vertical;
}

.inlineFields {
  display: grid;
  gap: 0.55rem;
  grid-template-columns: 1fr 1fr;
}

.linksBox {
  display: grid;
  gap: 0.5rem;
}

.linksHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.75rem;
  color: var(--color-text-secondary);
}

.linkRow {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 0.4rem;
  align-items: center;
}

.fullWidth {
  grid-column: 1 / -1;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.primaryBtn,
.ghostBtn {
  border-radius: 0.72rem;
  font-size: 0.78rem;
  padding: 0.48rem 0.7rem;
}

.primaryBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.56);
  background: rgb(var(--color-primary-rgb) / 0.24);
  color: var(--color-surface);
}

.ghostBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  background: transparent;
  color: var(--color-surface);
}

.ghostBtn.danger {
  color: var(--color-danger);
  border-color: rgb(var(--color-danger-rgb) / 0.36);
}

.errorText {
  margin: 0;
  color: var(--color-danger);
  font-size: 0.72rem;
}

@media (max-width: 1120px) {
  .inlineFields,
  .linkRow {
    grid-template-columns: 1fr;
  }
}
</style>
