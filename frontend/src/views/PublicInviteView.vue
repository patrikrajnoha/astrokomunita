<template>
  <main class="publicInvitePage">
    <section v-if="loading" class="publicInviteState">
      <h1>Načítavam pozvánku</h1>
      <p>Prosím chvíľu počkaj.</p>
    </section>

    <section v-else-if="error" class="publicInviteState publicInviteState--error">
      <h1>Pozvánka nie je dostupná</h1>
      <p>{{ error }}</p>
      <div class="publicInviteActions">
        <RouterLink class="publicInviteBtn publicInviteBtn--ghost" to="/">Domov</RouterLink>
        <button type="button" class="publicInviteBtn publicInviteBtn--primary" @click="loadInvite">
          Skúsiť znova
        </button>
      </div>
    </section>

    <section v-else class="publicInviteShell">
      <div class="publicInviteHead">
        <h1>Tvoja pozvánka</h1>
        <p>Vstupenka do nebeského divadla</p>
      </div>

      <article class="ticketPreview" aria-label="Náhľad pozvánky">
        <div class="ticketHeader" aria-hidden="true">
          <span class="ticketBrand">Astrokomunita</span>
          <span class="ticketHeaderIcon">*</span>
        </div>

        <div class="ticketBody">
          <p class="ticketKicker">Vstupenka do nebeského divadla</p>
          <h2 class="ticketTitle">{{ eventTitle }}</h2>
          <p class="ticketMeta">{{ eventDateTime }}</p>
          <p v-if="eventPlace" class="ticketMeta ticketMeta--place">{{ eventPlace }}</p>
        </div>

        <div class="ticketPerforated" aria-hidden="true"></div>

        <div class="ticketFooter">
          <div class="nameRow">
            <span class="nameRow__label">Meno pozorovateľa</span>
            <strong class="nameRow__value">{{ attendeeName }}</strong>
          </div>
          <p v-if="inviterName" class="ticketHint">Pozýva: {{ inviterName }}</p>
        </div>
      </article>

      <div class="publicInviteActions">
        <button type="button" class="publicInviteBtn publicInviteBtn--primary" @click="printTicket">
          Vytlačiť / uložiť ako PDF
        </button>
        <RouterLink
          v-if="eventId"
          class="publicInviteBtn publicInviteBtn--ghost"
          :to="`/events/${eventId}`"
        >
          Otvoriť udalosť
        </RouterLink>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { fetchPublicInviteByToken } from '@/services/invites'
import { EVENT_TIMEZONE, formatEventDate, resolveEventTimeContext } from '@/utils/eventTime'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const invite = ref(null)

const token = computed(() => String(route.params.token || '').trim())
const event = computed(() => invite.value?.event || null)
const eventId = computed(() => Number(event.value?.id || 0) || null)
const eventTitle = computed(() => String(event.value?.title || 'Astronomické podujatie'))
const attendeeName = computed(() => String(invite.value?.attendee_name || 'Host'))
const eventPlace = computed(() => String(event.value?.short || '').trim())
const inviterName = computed(() => {
  const inviter = invite.value?.inviter || null
  return String(inviter?.name || inviter?.username || '').trim()
})
const eventDateTime = computed(() => {
  const raw = event.value?.start_at || event.value?.max_at || event.value?.end_at
  if (!raw) return 'Termín bude upresnený'
  const dateLabel = formatEventDate(raw, EVENT_TIMEZONE, {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  const context = resolveEventTimeContext(event.value, EVENT_TIMEZONE)
  if (!context.showTimezoneLabel) return `${dateLabel} - ${context.message}`
  return `${dateLabel} - ${context.timeString} (${context.timezoneLabelShort})`
})

async function loadInvite() {
  loading.value = true
  error.value = ''

  try {
    const response = await fetchPublicInviteByToken(token.value)
    invite.value = response?.data?.data || response?.data || null
  } catch (err) {
    const status = Number(err?.response?.status || 0)
    error.value = status === 404
      ? 'Platnosť odkazu vypršala alebo pozvánka neexistuje.'
      : (err?.response?.data?.message || 'Nepodarilo sa načítať pozvánku.')
  } finally {
    loading.value = false
  }
}

function printTicket() {
  if (typeof window === 'undefined') return
  window.print()
}

onMounted(() => {
  void loadInvite()
})
</script>

<style scoped>
.publicInvitePage {
  width: min(100%, 640px);
  margin: 0 auto;
  padding: 1rem;
  display: grid;
  gap: 1rem;
  color: #abb8c9;
  background: #151d28;
}

.publicInviteShell {
  display: grid;
  gap: 1rem;
}

.publicInviteHead h1 {
  margin: 0;
  font-size: clamp(1.4rem, 4.6vw, 2rem);
  color: #ffffff;
}

.publicInviteHead p {
  margin: 0.3rem 0 0;
  color: #abb8c9;
}

.publicInviteState {
  border-radius: 1rem;
  border: 0;
  background: #1c2736;
  padding: 1rem;
}

.publicInviteState h1 {
  margin: 0;
  font-size: 1.15rem;
  color: #ffffff;
}

.publicInviteState p {
  margin: 0.45rem 0 0;
  color: #abb8c9;
}

.publicInviteState--error h1 {
  color: #eb2452;
}

.ticketPreview {
  overflow: hidden;
  border-radius: 1rem;
  border: 0;
  background: #1c2736;
  display: grid;
}

.ticketHeader {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.72rem 1rem;
  background: #222e3f;
  border-bottom: 1px solid rgb(34 46 63 / 0.95);
}

.ticketBrand {
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: #abb8c9;
}

.ticketHeaderIcon {
  font-size: 1.1rem;
  line-height: 1;
  color: #0f73ff;
}

.ticketBody {
  padding: 1rem 1rem 0.9rem;
  display: grid;
  gap: 0.46rem;
}

.ticketKicker {
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  font-size: 0.68rem;
  font-weight: 700;
  color: #abb8c9;
}

.ticketTitle {
  margin: 0;
  font-size: clamp(1.06rem, 3.6vw, 1.3rem);
  line-height: 1.26;
  color: #ffffff;
}

.ticketMeta {
  margin: 0;
  color: #abb8c9;
  font-size: 0.83rem;
  line-height: 1.45;
}

.ticketMeta--place {
  font-size: 0.79rem;
}

.ticketPerforated {
  margin: 0 0.6rem;
  height: 1px;
  background: repeating-linear-gradient(
    to right,
    rgb(34 46 63 / 0.95) 0,
    rgb(34 46 63 / 0.95) 6px,
    transparent 6px,
    transparent 12px
  );
}

.ticketFooter {
  padding: 0.88rem 1rem 1rem;
  display: grid;
  gap: 0.62rem;
}

.nameRow {
  border: 1px solid rgb(34 46 63 / 0.95);
  border-radius: 0.68rem;
  padding: 0.66rem 0.8rem;
  background: #151d28;
  display: grid;
  gap: 0.28rem;
}

.nameRow__label {
  font-size: 0.66rem;
  font-weight: 700;
  color: #abb8c9;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.nameRow__value {
  color: #ffffff;
}

.ticketHint {
  margin: 0;
  color: #abb8c9;
  font-size: 0.8rem;
}

.publicInviteActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.58rem;
}

.publicInviteBtn {
  min-height: 42px;
  border-radius: 999px;
  padding: 0 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  border: 0;
  box-shadow: none;
  font-weight: 650;
  transition: background-color 0.18s ease;
}

.publicInviteBtn--primary {
  background: #0f73ff;
  color: #ffffff;
}

.publicInviteBtn--ghost {
  background: #222e3f;
  color: #abb8c9;
}

.publicInviteBtn:hover,
.publicInviteBtn:focus-visible {
  background: #1c2736;
}

.publicInviteBtn--primary:hover,
.publicInviteBtn--primary:focus-visible {
  background: #0f73ff;
  color: #ffffff;
  filter: brightness(1.04);
}

.publicInviteBtn:focus-visible {
  outline: 2px solid #0f73ff;
  outline-offset: 2px;
}

@media (max-width: 768px) {
  .publicInvitePage {
    width: 100%;
    padding: 0.9rem;
    gap: 0.9rem;
  }

  .publicInviteActions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .publicInviteBtn {
    width: 100%;
    min-height: 44px;
    padding: 0 0.9rem;
  }
}

@media (max-width: 420px) {
  .ticketBody,
  .ticketFooter,
  .ticketHeader {
    padding-left: 0.82rem;
    padding-right: 0.82rem;
  }

  .ticketTitle {
    font-size: 1rem;
  }

  .ticketMeta {
    font-size: 0.78rem;
  }
}

@media print {
  :global(body *) {
    visibility: hidden !important;
  }

  .ticketPreview,
  .ticketPreview * {
    visibility: visible !important;
  }

  .ticketPreview {
    position: fixed;
    top: 12mm;
    left: 50%;
    transform: translateX(-50%);
    width: min(180mm, calc(100vw - 20mm));
    border: 1px solid rgb(221 221 221);
    background: #ffffff;
    color: rgb(17 17 17);
    box-shadow: none;
    break-inside: avoid;
    page-break-inside: avoid;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .ticketHeader {
    background: rgb(240 245 255);
    border-bottom-color: rgb(200 215 240);
  }

  .ticketBrand,
  .ticketKicker {
    color: rgb(40 80 160);
  }

  .ticketTitle,
  .nameRow__value {
    color: rgb(17 17 17);
  }

  .ticketMeta,
  .ticketHint,
  .nameRow__label {
    color: rgb(80 80 80);
  }

  .nameRow {
    border-color: rgb(200 215 240);
    background: rgb(245 248 255);
  }

  .ticketPerforated {
    background: repeating-linear-gradient(
      to right,
      rgb(180 200 230) 0,
      rgb(180 200 230) 6px,
      transparent 6px,
      transparent 12px
    );
  }

  .ticketPerforated::before,
  .ticketPerforated::after {
    background: #ffffff;
    border-color: rgb(200 215 240);
  }
}
</style>
