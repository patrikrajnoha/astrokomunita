<template>
  <div v-if="open" class="inviteModalRoot" @click.self="close">
    <section class="inviteModalCard" role="dialog" aria-modal="true" aria-labelledby="invite-ticket-title">
      <header class="modalHead">
        <div>
          <h2 id="invite-ticket-title">Pozvánka / Vstupenka</h2>
          <p>Darčeková pozvánka na astronomické podujatie</p>
        </div>
        <button type="button" class="ghostBtn" @click="close">Zavrieť</button>
      </header>

      <div class="modalBody">
        <section class="formPanel">
          <label class="field">
            <span>Meno na vstupenke</span>
            <input
              v-model.trim="attendeeName"
              type="text"
              maxlength="80"
              placeholder="Napr. Ján Novák"
              data-testid="attendee-name-input"
            />
          </label>
          <p v-if="attendeeNameError" class="fieldError">{{ attendeeNameError }}</p>

          <label class="field">
            <span>Email pozvaného (voliteľné)</span>
            <input v-model.trim="inviteeEmail" type="email" placeholder="meno@email.com" />
          </label>

          <label class="field">
            <span>Správa (voliteľné)</span>
            <textarea v-model.trim="message" rows="3" maxlength="240" placeholder="Krátky odkaz k pozvánke" />
          </label>

          <p v-if="submitError" class="fieldError">{{ submitError }}</p>
          <p v-if="submitSuccess" class="fieldSuccess">{{ submitSuccess }}</p>

          <div class="actions">
            <button
              type="button"
              class="primaryBtn"
              :disabled="isSubmitDisabled"
              data-testid="send-invite-btn"
              @click="submitInvite"
            >
              {{ sending ? 'Odosielam...' : 'Odoslať pozvánku' }}
            </button>
            <button type="button" class="secondaryBtn" data-testid="share-ticket-btn" @click="shareTicket">
              Zdieľať
            </button>
            <button type="button" class="secondaryBtn" data-testid="print-ticket-btn" @click="printTicket">
              Vytlačiť vstupenku
            </button>
          </div>
        </section>

        <div class="ticketPrintRoot">
          <article class="ticketPreview">
            <div class="watermarkLayer" aria-hidden="true">
              <span v-for="idx in 8" :key="`wm-${idx}`">Astrokomunita • Astrokomunita • Astrokomunita</span>
            </div>

            <p class="ticketKicker">Vstupenka do Nebeského divadla</p>
            <h3>{{ eventTitle }}</h3>
            <p class="ticketAltTitle">Vstupenka do Astronomického divadla</p>
            <p class="ticketMeta">{{ eventDateTime }}</p>
            <p class="ticketMeta" v-if="eventPlace">{{ eventPlace }}</p>

            <div class="nameRow">
              <span>Meno návštevníka</span>
              <strong>{{ attendeeNamePreview }}</strong>
            </div>

            <p class="ticketSubtitle">Darčeková pozvánka na astronomické podujatie</p>
            <p class="ticketCta">Darček pre fanúšika astronómie</p>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { createEventInvite } from '@/services/invites'
import { useToast } from '@/composables/useToast'

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
const sending = ref(false)
const createdInvite = ref(null)
const submitError = ref('')
const submitSuccess = ref('')

const attendeeNameError = computed(() => {
  const value = String(attendeeName.value || '').trim()
  if (!value) return 'Meno na vstupenke je povinné.'
  if (value.length > 80) return 'Meno na vstupenke môže mať najviac 80 znakov.'
  return ''
})

const isSubmitDisabled = computed(() => sending.value || Boolean(attendeeNameError.value) || !props.event?.id)

const eventTitle = computed(() => props.event?.title || 'Astronomické podujatie')

const eventPlace = computed(() => {
  const maybePlace = String(props.event?.short || '').trim()
  return maybePlace || ''
})

const eventDateTime = computed(() => {
  const raw = props.event?.start_at || props.event?.max_at || props.event?.end_at
  if (!raw) return 'Termín bude upresnený'
  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return 'Termín bude upresnený'
  return parsed.toLocaleString('sk-SK', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
})

const attendeeNamePreview = computed(() => {
  const value = String(attendeeName.value || '').trim()
  return value || 'Tvoje meno'
})

watch(
  () => props.open,
  (open) => {
    if (!open) return
    submitError.value = ''
    submitSuccess.value = ''
  },
)

function close() {
  emit('close')
}

async function submitInvite() {
  submitError.value = ''
  submitSuccess.value = ''

  if (attendeeNameError.value) {
    submitError.value = attendeeNameError.value
    return
  }

  if (!props.event?.id) {
    submitError.value = 'Chýba event na vytvorenie pozvánky.'
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
  const shareText = `Darčeková pozvánka na astronomické podujatie pre ${attendeeNamePreview.value}.`

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

<style scoped>
.inviteModalRoot {
  position: fixed;
  inset: 0;
  z-index: 1200;
  background: rgb(10 16 26 / 0.72);
  backdrop-filter: blur(3px);
  display: grid;
  place-items: center;
  padding: 1rem;
}

.inviteModalCard {
  width: min(920px, 100%);
  max-height: calc(100vh - 2rem);
  overflow: auto;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  background:
    radial-gradient(circle at top right, rgb(var(--color-primary-rgb) / 0.22), transparent 46%),
    rgb(var(--color-bg-rgb) / 0.96);
  box-shadow: 0 28px 62px rgb(2 6 12 / 0.5);
}

.modalHead {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1rem 0.8rem;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.modalHead h2 {
  margin: 0;
  font-size: 1.08rem;
}

.modalHead p {
  margin: 0.3rem 0 0;
  color: rgb(var(--color-surface-rgb) / 0.68);
  font-size: 0.86rem;
}

.modalBody {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 1rem;
  padding: 1rem;
}

.formPanel {
  display: grid;
  gap: 0.75rem;
  align-content: start;
}

.field {
  display: grid;
  gap: 0.35rem;
}

.field span {
  font-size: 0.82rem;
  color: rgb(var(--color-surface-rgb) / 0.74);
}

.field input,
.field textarea {
  width: 100%;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.74);
  color: var(--color-surface);
  padding: 0.62rem 0.72rem;
  outline: none;
}

.field input:focus,
.field textarea:focus {
  border-color: rgb(var(--color-primary-rgb) / 0.72);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.18);
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
  padding-top: 0.35rem;
}

.primaryBtn,
.secondaryBtn,
.ghostBtn {
  border-radius: 999px;
  border: 1px solid transparent;
  padding: 0.56rem 0.9rem;
  font-size: 0.82rem;
  font-weight: 700;
}

.primaryBtn {
  background: rgb(var(--color-primary-rgb) / 0.9);
  color: rgb(6 14 24);
}

.primaryBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.secondaryBtn,
.ghostBtn {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.3);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.72);
}

.fieldError {
  color: #ff8ea7;
  font-size: 0.78rem;
  margin: 0;
}

.fieldSuccess {
  color: #91efcc;
  font-size: 0.78rem;
  margin: 0;
}

.ticketPrintRoot {
  display: grid;
  align-content: start;
}

.ticketPreview {
  position: relative;
  overflow: hidden;
  min-height: 360px;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  background:
    linear-gradient(140deg, rgb(9 22 36 / 0.95), rgb(12 30 48 / 0.98)),
    linear-gradient(45deg, rgb(var(--color-primary-rgb) / 0.18), transparent 55%);
  padding: 1rem;
  display: grid;
  gap: 0.7rem;
  box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.15);
}

.watermarkLayer {
  position: absolute;
  inset: -28% -30%;
  display: grid;
  gap: 1.3rem;
  transform: rotate(-24deg);
  opacity: 0.1;
  pointer-events: none;
  mix-blend-mode: screen;
}

.watermarkLayer span {
  white-space: nowrap;
  letter-spacing: 0.2rem;
  font-size: 1rem;
  color: rgb(184 214 255 / 0.75);
}

.ticketKicker {
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  font-size: 0.76rem;
  color: rgb(186 219 255 / 0.9);
}

.ticketPreview h3 {
  margin: 0;
  font-size: 1.28rem;
  line-height: 1.2;
}

.ticketAltTitle {
  margin: -0.2rem 0 0;
  color: rgb(210 231 255 / 0.9);
  font-size: 0.82rem;
}

.ticketMeta {
  margin: 0;
  color: rgb(220 233 250 / 0.84);
  font-size: 0.86rem;
}

.nameRow {
  margin-top: 0.45rem;
  border: 1px solid rgb(160 200 255 / 0.3);
  border-radius: 0.7rem;
  padding: 0.72rem;
  background: rgb(6 20 33 / 0.42);
  display: grid;
  gap: 0.3rem;
}

.nameRow span {
  font-size: 0.76rem;
  color: rgb(197 223 255 / 0.78);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.nameRow strong {
  font-size: 1.1rem;
}

.ticketSubtitle,
.ticketCta {
  margin: 0;
  font-size: 0.84rem;
  color: rgb(218 236 255 / 0.84);
}

.ticketCta {
  font-weight: 700;
}

@media (max-width: 860px) {
  .modalBody {
    grid-template-columns: 1fr;
  }

  .ticketPreview {
    min-height: 320px;
  }
}

@media print {
  :global(body *) {
    visibility: hidden !important;
  }

  .ticketPrintRoot,
  .ticketPrintRoot * {
    visibility: visible !important;
  }

  .ticketPrintRoot {
    position: fixed;
    inset: 0;
    padding: 14mm;
    background: #fff;
    z-index: 9999;
    align-items: center;
    justify-items: center;
  }

  .ticketPreview {
    width: 100%;
    max-width: 180mm;
    min-height: auto;
    color: #111;
    background: #fff;
    border: 1px solid #ddd;
    box-shadow: none;
  }

  .ticketKicker,
  .ticketMeta,
  .ticketSubtitle,
  .ticketCta,
  .nameRow span {
    color: #333;
  }
}
</style>
