<template>
  <section class="observation-detail-page">
    <AsyncState
      v-if="loading"
      mode="loading"
      title="Načítavam pozorovanie..."
      loading-style="skeleton"
      :skeleton-rows="4"
      compact
    />
    <AsyncState
      v-else-if="error"
      mode="error"
      title="Nastala chyba"
      :message="error"
      action-label="Skúsiť znova"
      @action="loadObservation"
    />

    <template v-else-if="observation">
      <header class="detail-header">
        <div>
          <h1>Pozorovanie</h1>
          <p>ID #{{ observation.id }}</p>
          <RouterLink
            v-if="eventLinkId > 0"
            class="event-chip"
            :to="`/events/${eventLinkId}`"
          >
            {{ eventLinkLabel }}
          </RouterLink>
        </div>
        <div class="detail-actions">
          <button type="button" class="ui-pill ui-pill--secondary" @click="router.push('/observations')">
            Zoznam
          </button>
          <button v-if="isOwner" type="button" class="ui-pill ui-pill--secondary" @click="toggleEdit">
            {{ editing ? 'Zrušiť úpravu' : 'Upraviť' }}
          </button>
          <button v-if="isOwner" type="button" class="ui-pill ui-pill--danger" :disabled="deleting" @click="removeObservation">
            {{ deleting ? 'Mažem...' : 'Zmazať' }}
          </button>
        </div>
      </header>

      <InlineStatus
        v-if="saveSuccess"
        variant="success"
        :message="saveSuccess"
      />

      <ObservationCard :observation="observation" :clickable="false" />

      <section v-if="editing" class="edit-card">
        <h2>Úprava pozorovania</h2>

        <label class="field">
          <span>Názov *</span>
          <input v-model="form.title" type="text" maxlength="255" required>
        </label>

        <label class="field">
          <span>Popis</span>
          <textarea v-model="form.description" rows="4" maxlength="5000"></textarea>
        </label>

        <label class="field">
          <span>Pozorované *</span>
          <input v-model="form.observedAt" type="datetime-local" required>
        </label>

        <label class="field">
          <span>Prepojená udalosť</span>
          <select v-model="form.eventId">
            <option value="">Bez udalosti</option>
            <option v-for="eventItem in events" :key="eventItem.id" :value="String(eventItem.id)">
              {{ eventItem.title }}
            </option>
          </select>
        </label>

        <div class="field-grid">
          <label class="field">
            <span>Lokalita</span>
            <input v-model="form.locationName" type="text" maxlength="255">
          </label>
          <label class="field">
            <span>Zemepisná šírka</span>
            <input v-model="form.locationLat" type="number" step="0.0000001" min="-90" max="90">
          </label>
          <label class="field">
            <span>Zemepisná dĺžka</span>
            <input v-model="form.locationLng" type="number" step="0.0000001" min="-180" max="180">
          </label>
        </div>

        <div class="field-grid">
          <label class="field">
            <span>Seeing (1-5)</span>
            <input v-model="form.visibilityRating" type="number" min="1" max="5">
          </label>
          <label class="field">
            <span>Vybavenie</span>
            <input v-model="form.equipment" type="text" maxlength="2000">
          </label>
        </div>

        <label class="field field-check">
          <input v-model="form.isPublic" type="checkbox">
          <span>Zobrazit vo verejnom feede</span>
        </label>

        <div v-if="Array.isArray(observation.media) && observation.media.length > 0" class="existing-media">
          <h3>Existujúce fotky</h3>
          <label v-for="mediaItem in observation.media" :key="mediaItem.id" class="media-toggle">
            <input
              type="checkbox"
              :checked="removeMediaIds.has(mediaItem.id)"
              @change="toggleRemoveMedia(mediaItem.id)"
            >
            <img :src="mediaItem.url" alt="Observation image" loading="lazy">
            <span>Odstrániť</span>
          </label>
        </div>

        <label class="field">
          <span>Pridať nové fotografie</span>
          <input type="file" accept="image/*" multiple @change="onFilesChange">
        </label>

        <div v-if="newImagePreviews.length > 0" class="preview-grid">
          <img v-for="preview in newImagePreviews" :key="preview" :src="preview" alt="Preview" class="preview-image">
        </div>

        <InlineStatus v-if="saveError" variant="error" :message="saveError" />

        <div class="edit-actions">
          <button type="button" class="ui-pill ui-pill--secondary" :disabled="saving" @click="toggleEdit">Zrušiť</button>
          <button type="button" class="ui-pill ui-pill--primary" :disabled="saving" @click="saveChanges">
            {{ saving ? 'Ukladám...' : 'Uložiť zmeny' }}
          </button>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import { deleteObservation, getObservation, updateObservation } from '@/services/observations'
import { getEvents } from '@/services/events'
import { fromDateTimeLocal, toDateTimeLocal } from '@/utils/dateUtils'
import { extractObservationError } from '@/utils/observationErrors'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { success: toastSuccess } = useToast()
const { confirm } = useConfirm()

const observation = ref(null)
const loading = ref(false)
const error = ref('')
const editing = ref(false)
const deleting = ref(false)
const saving = ref(false)
const saveError = ref('')
const saveSuccess = ref('')

const events = ref([])
const removeMediaIds = ref(new Set())
const newImages = ref([])
const newImagePreviews = ref([])

const form = reactive({
  title: '',
  description: '',
  observedAt: '',
  eventId: '',
  locationName: '',
  locationLat: '',
  locationLng: '',
  visibilityRating: '',
  equipment: '',
  isPublic: true,
})

const isOwner = computed(() => {
  const currentUserId = Number(auth?.user?.id || 0)
  const ownerId = Number(observation.value?.user_id || 0)
  return currentUserId > 0 && ownerId > 0 && currentUserId === ownerId
})
const eventLinkId = computed(() => Number(observation.value?.event?.id || observation.value?.event_id || 0))
const eventLinkLabel = computed(() => {
  const title = String(observation.value?.event?.title || '').trim()
  return title ? `Udalosť: ${title}` : 'Otvoriť udalosť'
})

async function loadObservation() {
  loading.value = true
  error.value = ''

  try {
    const id = Number(route.params.id || 0)
    const response = await getObservation(id)
    observation.value = response?.data || null
    hydrateFormFromObservation()
  } catch (requestError) {
    error.value = extractObservationError(requestError, 'Nacitavanie zlyhalo.')
  } finally {
    loading.value = false
  }
}

async function loadEvents() {
  try {
    const response = await getEvents({ per_page: 30 })
    events.value = Array.isArray(response?.data?.data) ? response.data.data : []
  } catch {
    events.value = []
  }
}

function hydrateFormFromObservation() {
  const item = observation.value
  if (!item) return

  form.title = String(item.title || '')
  form.description = String(item.description || '')
  form.observedAt = toDateTimeLocal(item.observed_at)
  form.eventId = item.event_id ? String(item.event_id) : ''
  form.locationName = String(item.location_name || '')
  form.locationLat = item.location_lat ?? ''
  form.locationLng = item.location_lng ?? ''
  form.visibilityRating = item.visibility_rating ?? ''
  form.equipment = String(item.equipment || '')
  form.isPublic = Boolean(item.is_public)

  removeMediaIds.value = new Set()
  newImages.value = []
  revokePreviews()
  newImagePreviews.value = []
  saveError.value = ''
  saveSuccess.value = ''
}

function toggleEdit() {
  if (!isOwner.value) return
  saveSuccess.value = ''
  editing.value = !editing.value
  if (editing.value) {
    hydrateFormFromObservation()
  }
}

function toggleRemoveMedia(mediaId) {
  const normalizedId = Number(mediaId || 0)
  if (!Number.isInteger(normalizedId) || normalizedId <= 0) return

  const next = new Set(removeMediaIds.value)
  if (next.has(normalizedId)) {
    next.delete(normalizedId)
  } else {
    next.add(normalizedId)
  }
  removeMediaIds.value = next
}

function onFilesChange(event) {
  const files = Array.from(event?.target?.files || [])
  newImages.value = files

  revokePreviews()
  newImagePreviews.value = files.map((file) => URL.createObjectURL(file))
}

async function saveChanges() {
  if (!isOwner.value || saving.value || !observation.value) return

  saveError.value = ''
  saveSuccess.value = ''
  const observedAtIso = fromDateTimeLocal(form.observedAt)
  if (!observedAtIso) {
    saveError.value = 'Zadaj platny dátum a cas pozorovania.'
    return
  }

  saving.value = true

  try {
    await auth.csrf()

    const payload = {
      title: form.title,
      description: form.description,
      observed_at: observedAtIso,
      event_id: form.eventId,
      location_name: form.locationName,
      location_lat: form.locationLat,
      location_lng: form.locationLng,
      visibility_rating: form.visibilityRating,
      equipment: form.equipment,
      is_public: form.isPublic,
      images: newImages.value,
      remove_media_ids: Array.from(removeMediaIds.value),
    }

    const response = await updateObservation(observation.value.id, payload)
    observation.value = response?.data || observation.value
    editing.value = false
    hydrateFormFromObservation()
    saveSuccess.value = 'Pozorovanie bolo aktualizované.'
    toastSuccess('Pozorovanie bolo aktualizované.')
  } catch (requestError) {
    saveError.value = extractObservationError(requestError, 'Ulozenie zlyhalo.')
  } finally {
    saving.value = false
  }
}

async function removeObservation() {
  if (!isOwner.value || deleting.value || !observation.value) return

  const ok = await confirm({
    title: 'Zmazať pozorovanie?',
    message: 'Túto akciu už nie je možné vrátiť.',
    confirmText: 'Zmazať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!ok) return

  deleting.value = true
  try {
    await auth.csrf()
    await deleteObservation(observation.value.id)
    toastSuccess('Pozorovanie bolo zmazané.')
    router.push('/observations')
  } catch (requestError) {
    error.value = extractObservationError(requestError, 'Mazanie zlyhalo.')
  } finally {
    deleting.value = false
  }
}

function revokePreviews() {
  newImagePreviews.value.forEach((previewUrl) => {
    try {
      URL.revokeObjectURL(previewUrl)
    } catch {
      // no-op
    }
  })
}

onMounted(() => {
  loadObservation()
  loadEvents()
})

onBeforeUnmount(() => {
  revokePreviews()
})
</script>

<style scoped>
.observation-detail-page {
  max-width: 900px;
  margin: 0 auto;
  padding: 1rem;
  display: grid;
  gap: 0.9rem;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.8rem;
}

.detail-header h1 {
  margin: 0;
  color: var(--color-surface);
  font-size: 1.45rem;
}

.detail-header p {
  margin: 0.25rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.85rem;
}

.event-chip {
  margin-top: 0.45rem;
  display: inline-flex;
  align-items: center;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.45);
  border-radius: 999px;
  padding: 0.18rem 0.52rem;
  color: var(--color-primary);
  text-decoration: none;
  font-size: 0.74rem;
}

.event-chip:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.75);
}

.detail-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.45rem;
}

.edit-card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  border-radius: 0.95rem;
  padding: 0.9rem;
  background: rgb(var(--color-bg-rgb) / 0.36);
  display: grid;
  gap: 0.65rem;
}

.edit-card h2,
.existing-media h3 {
  margin: 0;
  color: var(--color-surface);
  font-size: 1rem;
}

.field {
  display: grid;
  gap: 0.32rem;
  color: var(--color-text-secondary);
  font-size: 0.84rem;
}

.field input,
.field textarea,
.field select {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.34);
  border-radius: 0.6rem;
  padding: 0.5rem 0.55rem;
  background: rgb(var(--color-bg-rgb) / 0.45);
  color: var(--color-surface);
}

.field textarea {
  resize: vertical;
}

.field-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.65rem;
}

.field-check {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.existing-media {
  display: grid;
  gap: 0.45rem;
}

.media-toggle {
  display: grid;
  grid-template-columns: auto 88px 1fr;
  gap: 0.5rem;
  align-items: center;
  color: var(--color-text-secondary);
  font-size: 0.79rem;
}

.media-toggle img {
  width: 88px;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: 0.55rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
}

.preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 0.45rem;
}

.preview-image {
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: 0.65rem;
  object-fit: cover;
}

.error-message {
  margin: 0;
  color: rgb(var(--color-danger-rgb) / 0.98);
}

.edit-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

@media (max-width: 720px) {
  .detail-header {
    flex-direction: column;
  }

  .detail-actions {
    justify-content: flex-start;
  }

  .edit-actions {
    justify-content: stretch;
  }

  .edit-actions button {
    flex: 1;
  }
}
</style>
