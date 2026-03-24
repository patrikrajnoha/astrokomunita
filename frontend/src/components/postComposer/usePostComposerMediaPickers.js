import { nextTick, ref } from 'vue'
import api from '@/services/api'
import { addFavorite } from '@/services/favorites'
import {
  getNotificationPreferences,
  updateNotificationPreferences,
} from '@/services/notificationPreferences'

export const GIF_MIN_QUERY_LENGTH = 2

export function usePostComposerMediaPickers({
  auth,
  err,
  pollAttachmentDisabledHint,
  pollEnabled,
  removeFile,
}) {
  const selectedGif = ref(null)
  const showGifModal = ref(false)
  const gifQuery = ref('')
  const gifResults = ref([])
  const gifLoading = ref(false)
  const gifError = ref('')
  const gifInputRef = ref(null)
  let gifDebounceTimer = null

  const selectedEvent = ref(null)
  const showEventModal = ref(false)
  const eventQuery = ref('')
  const eventResults = ref([])
  const eventLoading = ref(false)
  const eventError = ref('')
  const eventFollowed = ref(false)
  const eventFollowLoading = ref(false)
  let eventDebounceTimer = null

  function openGifModal() {
    if (pollEnabled.value) {
      err.value = pollAttachmentDisabledHint
      return
    }

    showGifModal.value = true
    gifError.value = ''
    gifQuery.value = ''
    gifResults.value = []
    nextTick(() => gifInputRef.value?.focus())
  }

  function closeGifModal() {
    showGifModal.value = false
    gifQuery.value = ''
    gifResults.value = []
    gifError.value = ''
    if (gifDebounceTimer !== null) {
      clearTimeout(gifDebounceTimer)
      gifDebounceTimer = null
    }
  }

  function onGifQueryChange() {
    gifError.value = ''
    if (gifDebounceTimer !== null) {
      clearTimeout(gifDebounceTimer)
    }

    const query = gifQuery.value.trim()
    if (query.length < GIF_MIN_QUERY_LENGTH) {
      gifLoading.value = false
      gifResults.value = []
      return
    }

    gifDebounceTimer = window.setTimeout(() => {
      void fetchGifResults(query)
    }, 500)
  }

  async function fetchGifResults(query) {
    gifLoading.value = true
    gifError.value = ''
    try {
      const res = await api.get('/integrations/gifs/search', {
        params: { q: query, limit: 18, offset: 0 },
        meta: { skipErrorToast: true },
      })
      const list = Array.isArray(res?.data?.data) ? res.data.data : []
      gifResults.value = list
    } catch (e) {
      const status = Number(e?.response?.status || 0)
      gifError.value = status === 429
        ? 'GIF vyhladavanie je dočasne pretazene. Skus neskor.'
        : (e?.response?.data?.message || 'GIF vyhladavanie zlyhalo.')
    } finally {
      gifLoading.value = false
    }
  }

  function selectGif(gif) {
    if (!gif || pollEnabled.value) return
    removeFile()
    selectedGif.value = {
      id: String(gif.id || ''),
      title: String(gif.title || ''),
      preview_url: String(gif.preview_url || ''),
      original_url: String(gif.original_url || ''),
      width: Number(gif.width || 0) || null,
      height: Number(gif.height || 0) || null,
    }
    closeGifModal()
  }

  function removeGif() {
    selectedGif.value = null
  }

  function openEventModal() {
    showEventModal.value = true
    eventError.value = ''
    if (eventResults.value.length === 0) {
      void fetchEventResults('')
    }
  }

  function closeEventModal() {
    showEventModal.value = false
    eventQuery.value = ''
    eventError.value = ''
    if (eventDebounceTimer !== null) {
      clearTimeout(eventDebounceTimer)
      eventDebounceTimer = null
    }
  }

  function onEventQueryChange() {
    if (eventDebounceTimer !== null) clearTimeout(eventDebounceTimer)
    eventDebounceTimer = window.setTimeout(() => {
      void fetchEventResults(eventQuery.value.trim())
    }, 500)
  }

  async function fetchEventResults(query) {
    eventLoading.value = true
    eventError.value = ''
    try {
      const res = await api.get('/events', {
        params: {
          q: query || undefined,
          per_page: 12,
        },
        meta: { skipErrorToast: true },
      })
      const payload = res?.data?.data
      eventResults.value = Array.isArray(payload) ? payload : []
    } catch (e) {
      eventError.value = e?.response?.data?.message || 'Nepodarilo sa načítať udalosti.'
    } finally {
      eventLoading.value = false
    }
  }

  function selectEvent(eventItem) {
    if (!eventItem?.id) return
    selectedEvent.value = eventItem
    eventFollowed.value = false
    closeEventModal()
  }

  function removeEvent() {
    selectedEvent.value = null
    eventFollowed.value = false
  }

  async function markCalendar() {
    const eventId = Number(selectedEvent.value?.id || 0)
    if (!eventId || eventFollowLoading.value) return
    if (!auth.isAuthed) {
      const redirect = encodeURIComponent(window.location.pathname + window.location.search)
      window.location.assign(`/login?redirect=${redirect}`)
      return
    }

    eventFollowLoading.value = true
    try {
      await addFavorite(eventId)
      const prefRes = await getNotificationPreferences()
      const current = prefRes?.data || {}
      const inApp = { ...(current.in_app || {}), event_reminder: true }
      const emailMap = { ...(current.email || {}), event_reminder: true }
      await updateNotificationPreferences({
        in_app: inApp,
        email_enabled: true,
        email: emailMap,
      })
      eventFollowed.value = true
    } catch (e) {
      err.value = e?.response?.data?.message || 'Nepodarilo sa zapnúť sledovanie udalosti.'
    } finally {
      eventFollowLoading.value = false
    }
  }

  function cleanup() {
    if (gifDebounceTimer !== null) {
      clearTimeout(gifDebounceTimer)
      gifDebounceTimer = null
    }
    if (eventDebounceTimer !== null) {
      clearTimeout(eventDebounceTimer)
      eventDebounceTimer = null
    }
  }

  return {
    GIF_MIN_QUERY_LENGTH,
    cleanupMediaPickers: cleanup,
    closeEventModal,
    closeGifModal,
    eventError,
    eventFollowLoading,
    eventFollowed,
    eventLoading,
    eventQuery,
    eventResults,
    fetchEventResults,
    gifError,
    gifInputRef,
    gifLoading,
    gifQuery,
    gifResults,
    markCalendar,
    onEventQueryChange,
    onGifQueryChange,
    openEventModal,
    openGifModal,
    removeEvent,
    removeGif,
    selectEvent,
    selectGif,
    selectedEvent,
    selectedGif,
    showEventModal,
    showGifModal,
  }
}
