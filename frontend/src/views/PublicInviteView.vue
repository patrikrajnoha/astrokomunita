<template>
  <main class="publicInvitePage">
    <section v-if="loading" class="publicInviteState">
      <h1>Nacitavam pozvanku</h1>
      <p>Prosim chvilu pockaj.</p>
    </section>

    <section v-else-if="error" class="publicInviteState publicInviteState--error">
      <h1>Pozvanka nie je dostupna</h1>
      <p>{{ error }}</p>
      <div class="publicInviteActions">
        <RouterLink class="publicInviteBtn publicInviteBtn--ghost" to="/">Domov</RouterLink>
        <button type="button" class="publicInviteBtn publicInviteBtn--primary" @click="loadInvite">
          Skusit znova
        </button>
      </div>
    </section>

    <section v-else class="publicInviteShell">
      <div class="publicInviteHead">
        <h1>Tvoja pozvanka</h1>
        <p>Vstupenka do nebeskeho divadla</p>
      </div>

      <article class="ticketPreview" aria-label="Nahlad pozvanky">
        <div class="ticketHeader" aria-hidden="true">
          <span class="ticketBrand">Astrokomunita</span>
          <span class="ticketHeaderIcon">*</span>
        </div>

        <div class="ticketBody">
          <p class="ticketKicker">Vstupenka do nebeskeho divadla</p>
          <h2 class="ticketTitle">{{ eventTitle }}</h2>
          <p class="ticketMeta">{{ eventDateTime }}</p>
          <p v-if="eventPlace" class="ticketMeta ticketMeta--place">{{ eventPlace }}</p>
        </div>

        <div class="ticketPerforated" aria-hidden="true"></div>

        <div class="ticketFooter">
          <div class="nameRow">
            <span class="nameRow__label">Meno pozorovatela</span>
            <strong class="nameRow__value">{{ attendeeName }}</strong>
          </div>
          <p v-if="inviterName" class="ticketHint">Pozyva: {{ inviterName }}</p>
        </div>
      </article>

      <div class="publicInviteActions">
        <button type="button" class="publicInviteBtn publicInviteBtn--primary" @click="printTicket">
          Vytlacit / ulozit ako PDF
        </button>
        <RouterLink
          v-if="eventId"
          class="publicInviteBtn publicInviteBtn--ghost"
          :to="`/events/${eventId}`"
        >
          Otvorit udalost
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
const eventTitle = computed(() => String(event.value?.title || 'Astronomicke podujatie'))
const attendeeName = computed(() => String(invite.value?.attendee_name || 'Host'))
const eventPlace = computed(() => String(event.value?.short || '').trim())
const inviterName = computed(() => {
  const inviter = invite.value?.inviter || null
  return String(inviter?.name || inviter?.username || '').trim()
})
const eventDateTime = computed(() => {
  const raw = event.value?.start_at || event.value?.max_at || event.value?.end_at
  if (!raw) return 'Termin bude upresneny'
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
      ? 'Platnost odkazu vyprsala alebo pozvanka neexistuje.'
      : (err?.response?.data?.message || 'Nepodarilo sa nacitat pozvanku.')
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
}

.publicInviteShell {
  display: grid;
  gap: 1rem;
}

.publicInviteHead h1 {
  margin: 0;
  font-size: clamp(1.4rem, 4.6vw, 2rem);
}

.publicInviteHead p {
  margin: 0.3rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.publicInviteState {
  border-radius: 1rem;
  border: 1px solid var(--border-default);
  background: rgb(var(--color-bg-rgb) / 0.84);
  padding: 1rem;
}

.publicInviteState h1 {
  margin: 0;
  font-size: 1.15rem;
}

.publicInviteState p {
  margin: 0.45rem 0 0;
}

.ticketPreview {
  overflow: hidden;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
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
  padding: 0 0.95rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  border: 1px solid transparent;
  font-weight: 650;
}

.publicInviteBtn--primary {
  background: #0f73ff;
  color: #ffffff;
}

.publicInviteBtn--ghost {
  background: transparent;
  border-color: rgb(var(--color-text-secondary-rgb) / 0.44);
  color: rgb(var(--color-text-rgb) / 0.92);
}

@media print {
  .publicInvitePage {
    width: 100%;
    max-width: none;
    padding: 0;
  }

  .publicInviteActions {
    display: none;
  }
}
</style>
