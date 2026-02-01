<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const error = ref('')
const success = ref('')

const isEdit = computed(() => typeof route.params.id !== 'undefined')
const eventId = computed(() => Number(route.params.id))

const types = [
  { value: 'meteor_shower', label: 'Meteory' },
  { value: 'eclipse_lunar', label: 'Zatmenie (L)' },
  { value: 'eclipse_solar', label: 'Zatmenie (S)' },
  { value: 'planetary_event', label: 'Konjunkcia' },
  { value: 'other', label: 'Iné' },
]

const form = reactive({
  title: '',
  description: '',
  type: 'meteor_shower',
  start_at: '',
  end_at: '',
  visibility: 1,
})

function toLocalInput(value) {
  if (!value) return ''
  const d = new Date(value)
  if (isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function loadEvent() {
  if (!isEdit.value) return
  loading.value = true
  error.value = ''

  try {
    const res = await api.get(`/admin/events/${eventId.value}`)
    const ev = res.data?.data ?? res.data

    form.title = ev?.title || ''
    form.description = ev?.description || ''
    form.type = ev?.type || 'meteor_shower'
    form.start_at = toLocalInput(ev?.start_at || ev?.starts_at || ev?.max_at)
    form.end_at = toLocalInput(ev?.end_at || ev?.ends_at)
    form.visibility = typeof ev?.visibility === 'number' ? ev.visibility : 1
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load event.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  loading.value = true
  error.value = ''
  success.value = ''

  const payload = {
    title: form.title,
    description: form.description || null,
    type: form.type,
    start_at: form.start_at,
    end_at: form.end_at || null,
    visibility: form.visibility,
  }

  try {
    if (isEdit.value) {
      await api.put(`/admin/events/${eventId.value}`, payload)
      success.value = 'Event updated.'
    } else {
      await api.post('/admin/events', payload)
      success.value = 'Event created.'
    }

    window.setTimeout(() => {
      router.push('/admin/events')
    }, 600)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Save failed.'
  } finally {
    loading.value = false
  }
}

onMounted(loadEvent)
</script>

<template>
  <div style="max-width: 880px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
      <div>
        <h1 style="margin:0 0 6px;">{{ isEdit ? 'Edit event' : 'Create event' }}</h1>
        <div style="opacity:.8; font-size: 14px;">
          {{ isEdit ? 'Update existing event data.' : 'Add a manual event.' }}
        </div>
      </div>
    </div>

    <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
      {{ error }}
    </div>
    <div v-if="success" style="margin-top: 12px; color: var(--color-success);">
      {{ success }}
    </div>

    <div style="margin-top: 16px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px; padding: 16px;">
      <div style="display:grid; gap:12px;">
        <label style="display:block;">
          <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Title</div>
          <input v-model="form.title" type="text" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;" />
        </label>

        <label style="display:block;">
          <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Description</div>
          <textarea v-model="form.description" rows="4" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"></textarea>
        </label>

        <div style="display:grid; grid-template-columns: repeat(12, 1fr); gap:12px;">
          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Event type</div>
            <select v-model="form.type" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;">
              <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </label>

          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Visibility</div>
            <select v-model.number="form.visibility" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;">
              <option :value="1">Public</option>
              <option :value="0">Hidden</option>
            </select>
          </label>
        </div>

        <div style="display:grid; grid-template-columns: repeat(12, 1fr); gap:12px;">
          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Starts at</div>
            <input v-model="form.start_at" type="datetime-local" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;" />
          </label>
          <label style="grid-column: span 6;">
            <div style="font-size:12px; opacity:.8; margin-bottom:6px;">Ends at (optional)</div>
            <input v-model="form.end_at" type="datetime-local" :disabled="loading" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;" />
          </label>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
          <button
            @click="submit"
            :disabled="loading"
            style="padding:10px 14px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            {{ loading ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
