<template>
  <section class="observation-create-page" :class="{ 'observation-create-page--embedded': embedded }">
    <header v-if="!embedded">
      <h1>Nove pozorovanie</h1>
      <p>Vytvor zaznam pozorovania, pridaj fotografie a publikuj ho do feedu.</p>
    </header>

    <div v-if="!auth.isAuthed" class="state-card">
      <InlineStatus variant="info" message="Prihlas sa pre vytvorenie pozorovania." />
    </div>

    <form v-else class="form-card" :class="{ 'form-card--embedded': embedded }" @submit.prevent="submit">
      <label class="field">
        <span>Nazov *</span>
        <input v-model="form.title" type="text" maxlength="255" required>
      </label>

      <label class="field">
        <span>Popis</span>
        <textarea v-model="form.description" rows="4" maxlength="5000"></textarea>
      </label>

      <label class="field">
        <span>Pozorovane *</span>
        <input v-model="form.observedAt" type="datetime-local" required>
      </label>

      <label class="field">
        <span>Prepojena udalost</span>
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
          <span>Zemepisna sirka</span>
          <input v-model="form.locationLat" type="number" step="0.0000001" min="-90" max="90">
        </label>
        <label class="field">
          <span>Zemepisna dlzka</span>
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
        <span>Publikovat vo verejnom feede</span>
      </label>
      <p class="field-help">
        Verejne pozorovanie sa automaticky vytvori aj ako prispevok vo feede.
      </p>

      <label v-if="form.isPublic" class="field field-check field-check--nested">
        <input v-model="openPostAfterCreate" type="checkbox">
        <span>Po ulozeni otvorit vytvoreny prispevok</span>
      </label>

      <label class="field">
        <span>Fotografie *</span>
        <input type="file" accept="image/*" multiple required @change="onFilesChange">
      </label>

      <div v-if="imagePreviews.length > 0" class="preview-grid">
        <img v-for="preview in imagePreviews" :key="preview" :src="preview" alt="Preview" class="preview-image">
      </div>

      <InlineStatus v-if="error" variant="error" :message="error" />

      <div class="actions">
        <button type="button" class="ui-pill ui-pill--secondary" :disabled="saving" @click="onCancelClick">
          {{ cancelLabel }}
        </button>
        <button type="submit" class="ui-pill ui-pill--primary" :disabled="saving">
          {{ submitLabel }}
        </button>
      </div>
    </form>
  </section>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { createObservation } from '@/services/observations'
import { getEvents } from '@/services/events'
import { fromDateTimeLocal, toDateTimeLocal } from '@/utils/dateUtils'
import { extractObservationError } from '@/utils/observationErrors'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})
const emit = defineEmits(['submitted', 'cancel'])

const router = useRouter()
const auth = useAuthStore()
const { success: toastSuccess } = useToast()

const form = reactive({
  title: '',
  description: '',
  observedAt: toDateTimeLocal(new Date()),
  eventId: '',
  locationName: '',
  locationLat: '',
  locationLng: '',
  visibilityRating: '',
  equipment: '',
  isPublic: true,
})

const selectedImages = ref([])
const imagePreviews = ref([])
const events = ref([])
const saving = ref(false)
const error = ref('')
const openPostAfterCreate = ref(true)
const submitLabel = computed(() => {
  if (saving.value) return 'Ukladam...'
  if (form.isPublic && openPostAfterCreate.value) return 'Vytvorit a otvorit prispevok'
  return 'Vytvorit pozorovanie'
})
const cancelLabel = computed(() => (props.embedded ? 'Spat na prispevok' : 'Spat'))

watch(
  () => form.isPublic,
  (isPublic, wasPublic) => {
    if (!isPublic) {
      openPostAfterCreate.value = false
      return
    }

    if (wasPublic === false) {
      openPostAfterCreate.value = true
    }
  },
)

async function loadEvents() {
  try {
    const response = await getEvents({ per_page: 30 })
    const rows = Array.isArray(response?.data?.data) ? response.data.data : []
    events.value = rows
  } catch {
    events.value = []
  }
}

function onFilesChange(event) {
  const files = Array.from(event?.target?.files || [])
  selectedImages.value = files

  revokePreviews()
  imagePreviews.value = files.map((file) => URL.createObjectURL(file))
}

async function submit() {
  if (!auth.isAuthed || saving.value) return

  error.value = ''
  const observedAtIso = fromDateTimeLocal(form.observedAt)
  if (!observedAtIso) {
    error.value = 'Zadaj platny datum a cas pozorovania.'
    return
  }

  if (selectedImages.value.length === 0) {
    error.value = 'Pridaj aspon jednu fotografiu.'
    return
  }

  saving.value = true

  try {
    await auth.csrf()

    const response = await createObservation({
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
      images: selectedImages.value,
    })

    const observationId = Number(response?.data?.id || 0)
    const feedPostId = Number(response?.data?.feed_post_id || 0)

    if (form.isPublic && openPostAfterCreate.value && feedPostId > 0) {
      toastSuccess('Pozorovanie bolo vytvorene a publikovane vo feede.')
      if (props.embedded) {
        emit('submitted', {
          observationId,
          feedPostId,
          isPublic: form.isPublic,
          openPostAfterCreate: openPostAfterCreate.value,
        })
        return
      }
      router.push(`/posts/${feedPostId}`)
      return
    }

    toastSuccess('Pozorovanie bolo vytvorene.')
    if (props.embedded) {
      emit('submitted', {
        observationId,
        feedPostId,
        isPublic: form.isPublic,
        openPostAfterCreate: openPostAfterCreate.value,
      })
      return
    }
    if (observationId > 0) {
      router.push(`/observations/${observationId}`)
      return
    }

    router.push('/observations')
  } catch (requestError) {
    error.value = extractObservationError(requestError, 'Ulozenie zlyhalo.')
  } finally {
    saving.value = false
  }
}

function onCancelClick() {
  if (saving.value) return
  if (props.embedded) {
    emit('cancel')
    return
  }
  router.push('/observations')
}

function revokePreviews() {
  imagePreviews.value.forEach((previewUrl) => {
    try {
      URL.revokeObjectURL(previewUrl)
    } catch {
      // no-op
    }
  })
}

onMounted(() => {
  loadEvents()
})

onBeforeUnmount(() => {
  revokePreviews()
})
</script>

<style scoped>
.observation-create-page {
  max-width: 860px;
  margin: 0 auto;
  padding: 1rem;
  display: grid;
  gap: 0.9rem;
}

.observation-create-page--embedded {
  max-width: 100%;
  margin: 0;
  padding: 0;
  gap: 0.45rem;
}

.observation-create-page h1 {
  margin: 0;
  color: var(--color-surface);
}

.observation-create-page p {
  margin: 0.3rem 0 0;
  color: var(--color-text-secondary);
}

.state-card,
.form-card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 0.95rem;
  padding: 0.95rem;
  background: rgb(var(--color-bg-rgb) / 0.38);
}

.form-card {
  display: grid;
  gap: 0.75rem;
}

.form-card--embedded {
  border-radius: 0.75rem;
  padding: 0.6rem;
  gap: 0.58rem;
}

.observation-create-page--embedded .field {
  gap: 0.24rem;
  font-size: 0.78rem;
}

.observation-create-page--embedded .field input,
.observation-create-page--embedded .field textarea,
.observation-create-page--embedded .field select {
  padding: 0.42rem 0.5rem;
  border-radius: 0.55rem;
}

.observation-create-page--embedded .field textarea {
  min-height: 84px;
}

.observation-create-page--embedded .field-grid {
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.5rem;
}

.observation-create-page--embedded .field-help {
  margin-top: -0.35rem;
  font-size: 0.74rem;
}

.observation-create-page--embedded .preview-grid {
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 0.38rem;
}

.observation-create-page--embedded .preview-image {
  border-radius: 0.55rem;
}

.observation-create-page--embedded .actions {
  gap: 0.42rem;
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

.field-check--nested {
  margin-top: -0.4rem;
}

.field-help {
  margin: -0.45rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
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

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

@media (max-width: 720px) {
  .actions {
    justify-content: stretch;
  }

  .actions button {
    flex: 1;
  }
}
</style>
