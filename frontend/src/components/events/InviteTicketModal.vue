<template src="./inviteTicket/InviteTicketModal.template.html"></template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { EVENT_TIMEZONE, formatEventDate, resolveEventTimeContext } from '@/utils/eventTime'
import { createEventInvite } from '@/services/invites'
import { eventDisplayTitle, repairUtf8Mojibake } from '@/utils/translatedFields'

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
const sending = ref(false)
const createdInvite = ref(null)
const submitError = ref('')
const submitSuccess = ref('')

const attendeeNameValidation = computed(() => {
  const value = String(attendeeName.value || '').trim()
  if (!value) return 'Meno pozorovateľa je povinné.'
  if (value.length > 80) return 'Meno pozorovateľa môže mať najviac 80 znakov.'
  return ''
})

const attendeeNameError = computed(() => {
  if (!attendeeNameTouched.value && !submitAttempted.value) return ''
  return attendeeNameValidation.value
})

const isSubmitDisabled = computed(() => sending.value || !props.event?.id)

const eventTitle = computed(() => {
  const normalized = eventDisplayTitle(props.event)
  if (normalized && normalized !== '-') return normalized
  return 'Astronomické podujatie'
})

const eventPlace = computed(() => {
  const maybePlace = repairUtf8Mojibake(String(props.event?.short || '').trim())
  return maybePlace || ''
})

const eventDateTime = computed(() => {
  const raw = props.event?.start_at || props.event?.max_at || props.event?.end_at
  if (!raw) return 'Termín bude upresnený'

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
  const value = repairUtf8Mojibake(String(attendeeName.value || '').trim())
  return value || 'Tvoje meno'
})

watch(
  () => props.open,
  (open) => {
    if (!open) return
    attendeeNameTouched.value = false
    submitAttempted.value = false
    showOptionalFields.value = false
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
    submitError.value = 'Chýba udalosť na vytvorenie pozvánky.'
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

    submitSuccess.value = 'Pozvánka bola odoslaná.'
    toast.success('Pozvánka bola odoslaná.')
    emit('created', data)
  } catch (error) {
    submitError.value = error?.response?.data?.message || 'Nepodarilo sa odoslať pozvánku.'
  } finally {
    sending.value = false
  }
}

async function shareTicket() {
  const url = resolveShareUrl()
  const shareTitle = 'Vstupenka do Nebeského divadla'
  const shareText = `Pozvánka pre ${attendeeNamePreview.value} na podujatie ${eventTitle.value}.`

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
    toast.info('Link na vstupenku bol skopírovaný.')
  } catch {
    toast.warn('Nepodarilo sa skopírovať link.')
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
      return `${apiBase}/invites/public/${encodeURIComponent(token)}`
    }
  }

  const origin = typeof window !== 'undefined' ? window.location.origin : ''
  const eventId = props.event?.id
  return eventId ? `${origin}/events/${eventId}` : origin
}

async function copyText(value) {
  const text = String(value || '')
  if (!text) return
  await navigator.clipboard.writeText(text)
}
</script>

<style scoped src="./inviteTicket/InviteTicketModal.css"></style>
