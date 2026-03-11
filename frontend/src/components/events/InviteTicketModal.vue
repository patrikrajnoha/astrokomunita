<template src="./inviteTicket/InviteTicketModal.template.html"></template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { EVENT_TIMEZONE, formatEventDate, resolveEventTimeContext } from '@/utils/eventTime'
import { createEventInvite } from '@/services/invites'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  event: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close', 'created'])
const toast = useToast()

const attendeeName = ref('')
const inviteeEmail = ref('')
const message = ref('')
const attendeeNameTouched = ref(false)
const submitAttempted = ref(false)
const showOptionalFields = ref(false)
const showSecondaryActions = ref(false)
const sending = ref(false)
const createdInvite = ref(null)
const submitError = ref('')
const submitSuccess = ref('')

const attendeeNameValidation = computed(() => {
  const value = String(attendeeName.value || '').trim()
  if (!value) return 'Meno na vstupenke je povinne.'
  if (value.length > 80) return 'Meno na vstupenke moze mat najviac 80 znakov.'
  return ''
})

const attendeeNameError = computed(() => {
  if (!attendeeNameTouched.value && !submitAttempted.value) return ''
  return attendeeNameValidation.value
})

const isSubmitDisabled = computed(() => sending.value || !props.event?.id)

const eventTitle = computed(() => props.event?.title || 'Astronomicke podujatie')

const eventPlace = computed(() => {
  const maybePlace = String(props.event?.short || '').trim()
  return maybePlace || ''
})

const eventDateTime = computed(() => {
  const raw = props.event?.start_at || props.event?.max_at || props.event?.end_at
  if (!raw) return 'Termin bude upresneny'

  const dateLabel = formatEventDate(raw, EVENT_TIMEZONE, {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  const context = resolveEventTimeContext(props.event, EVENT_TIMEZONE)

  if (!context.showTimezoneLabel) {
    return `${dateLabel} - ${context.message}`
  }

  return `${dateLabel} - ${context.timeString} (${context.timezoneLabelShort})`
})

const attendeeNamePreview = computed(() => {
  const value = String(attendeeName.value || '').trim()
  return value || 'Tvoje meno'
})

watch(
  () => props.open,
  (open) => {
    if (!open) return
    attendeeNameTouched.value = false
    submitAttempted.value = false
    showOptionalFields.value = false
    showSecondaryActions.value = false
    submitError.value = ''
    submitSuccess.value = ''
  },
)

function close() {
  emit('close')
}

async function submitInvite() {
  submitAttempted.value = true
  attendeeNameTouched.value = true
  submitError.value = ''
  submitSuccess.value = ''

  if (attendeeNameValidation.value) {
    submitError.value = attendeeNameValidation.value
    return
  }

  if (!props.event?.id) {
    submitError.value = 'Chyba event na vytvorenie pozvanky.'
    return
  }

  sending.value = true

  try {
    const payload = {
      attendee_name: String(attendeeName.value).trim(),
      message: message.value ? String(message.value).trim() : undefined,
      invitee_email: inviteeEmail.value ? String(inviteeEmail.value).trim() : undefined,
    }

    const response = await createEventInvite(props.event.id, payload)
    const data = response?.data?.data ?? response?.data ?? null
    createdInvite.value = data

    submitSuccess.value = 'Pozvanka bola odoslana.'
    toast.success('Pozvanka bola odoslana.')
    emit('created', data)
  } catch (error) {
    submitError.value = error?.response?.data?.message || 'Nepodarilo sa odoslat pozvanku.'
  } finally {
    sending.value = false
  }
}

async function shareTicket() {
  const url = resolveShareUrl()
  const shareTitle = 'Vstupenka do Nebeskeho divadla'
  const shareText = `Pozvanka pre ${attendeeNamePreview.value} na podujatie ${eventTitle.value}.`

  if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
    try {
      await navigator.share({
        title: shareTitle,
        text: shareText,
        url,
      })
      return
    } catch (error) {
      if (error?.name === 'AbortError') return
    }
  }

  try {
    await copyText(url)
    toast.info('Link na vstupenku bol skopirovany.')
  } catch {
    toast.warn('Nepodarilo sa skopirovat link.')
  }
}

function printTicket() {
  if (typeof window === 'undefined') return
  window.print()
}

function resolveShareUrl() {
  const token = createdInvite.value?.token
  if (token) {
    const apiBase = String(api?.defaults?.baseURL || '').replace(/\/api\/?$/i, '')
    if (apiBase) {
      return `${apiBase}/api/invites/public/${encodeURIComponent(token)}`
    }
  }

  const origin = typeof window !== 'undefined' ? window.location.origin : ''
  const eventId = props.event?.id
  return eventId ? `${origin}/events/${eventId}` : origin
}

async function copyText(value) {
  const text = String(value || '')
  if (!text) return

  if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text)
    return
  }

  if (typeof document === 'undefined') {
    throw new Error('Clipboard unavailable')
  }

  const area = document.createElement('textarea')
  area.value = text
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.left = '-9999px'
  document.body.appendChild(area)
  area.select()

  try {
    document.execCommand('copy')
  } finally {
    document.body.removeChild(area)
  }
}
</script>

<style scoped src="./inviteTicket/InviteTicketModal.css"></style>
