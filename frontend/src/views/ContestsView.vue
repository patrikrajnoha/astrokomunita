<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { getActiveContests, getContestParticipants } from '@/services/contests'

const loading = ref(false)
const error = ref('')
const activeContests = ref([])
const latestFinished = ref(null)
const winnerParticipants = ref([])
const nowTick = ref(Date.now())
let timerId = null

const activeContest = computed(() => activeContests.value[0] || null)
const finishedContest = computed(() => latestFinished.value || null)

const instructionText = computed(() => {
  if (!activeContest.value) return 'Aktuálne neprebieha ziadna súťaž.'
  return `Pre ucast pouzite hashtag #${activeContest.value.hashtag}`
})

const countdownText = computed(() => {
  if (!activeContest.value?.ends_at) return ''
  const diffMs = new Date(activeContest.value.ends_at).getTime() - nowTick.value
  if (diffMs <= 0) return 'Súťaž je ukončená.'

  const totalSeconds = Math.floor(diffMs / 1000)
  const days = Math.floor(totalSeconds / 86400)
  const hours = Math.floor((totalSeconds % 86400) / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)

  if (days > 0) return `${days}d ${hours}h ${minutes}m`
  return `${hours}h ${minutes}m`
})

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

async function loadPage() {
  loading.value = true
  error.value = ''

  try {
    const payload = await getActiveContests()
    activeContests.value = Array.isArray(payload?.data) ? payload.data : []
    latestFinished.value = payload?.latest_finished || null

    if (latestFinished.value?.id) {
      const participants = await getContestParticipants(latestFinished.value.id, 100)
      winnerParticipants.value = Array.isArray(participants?.data) ? participants.data : []
    } else {
      winnerParticipants.value = []
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať súťaž.'
  } finally {
    loading.value = false
  }
}

const winnerUsername = computed(() => {
  const winnerPostId = finishedContest.value?.winner_post_id
  if (!winnerPostId) return null

  const match = winnerParticipants.value.find((item) => Number(item.post_id) === Number(winnerPostId))
  return match?.username || null
})

onMounted(() => {
  loadPage()
  timerId = window.setInterval(() => {
    nowTick.value = Date.now()
  }, 60000)
})

onBeforeUnmount(() => {
  if (timerId) {
    window.clearInterval(timerId)
    timerId = null
  }
})
</script>

<template>
  <section class="contestsPage">
    <header>
      <h1>Súťaž</h1>
      <p class="lead">Minimalny sutazny feed zalozeny na ucasti cez hashtagy.</p>
    </header>

    <article class="card" v-if="loading">
      <p>Načítavam súťaž...</p>
    </article>

    <article class="card error" v-else-if="error">
      <p>{{ error }}</p>
    </article>

    <article class="card" v-else-if="activeContest">
      <p class="badge">Aktivna</p>
      <h2>{{ activeContest.name }}</h2>
      <p>{{ activeContest.description || 'Zapojte sa postom s hashtagom.' }}</p>
      <p class="instruction">{{ instructionText }}</p>
      <p class="meta">Od {{ formatDate(activeContest.starts_at) }} do {{ formatDate(activeContest.ends_at) }}</p>
      <p class="countdown" v-if="countdownText">Koniec o: {{ countdownText }}</p>
    </article>

    <article class="card" v-else>
      <p>Zatiaľ nie je aktívna súťaž.</p>
    </article>

    <article class="card" v-if="finishedContest">
      <p class="badge done">Posledna ukončená</p>
      <h3>{{ finishedContest.name }}</h3>
      <p v-if="finishedContest.winner_post_id">
        Vyherny post: #{{ finishedContest.winner_post_id }}
        <span v-if="winnerUsername">od @{{ winnerUsername }}</span>
      </p>
      <p v-else>Vyherca ešte nebol zverejneny.</p>
    </article>
  </section>
</template>

<style scoped>
.contestsPage {
  width: min(840px, 100%);
  margin: 0 auto;
  display: grid;
  gap: 12px;
}

h1 {
  margin: 0;
  font-size: 1.8rem;
}

.lead {
  margin: 6px 0 0;
  opacity: 0.8;
}

.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.4);
  padding: 14px;
}

.card h2,
.card h3 {
  margin: 0 0 8px;
}

.badge {
  display: inline-flex;
  margin: 0 0 8px;
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 0.72rem;
  background: rgb(34 197 94 / 0.2);
  border: 1px solid rgb(34 197 94 / 0.45);
}

.badge.done {
  background: rgb(59 130 246 / 0.2);
  border-color: rgb(59 130 246 / 0.45);
}

.instruction {
  font-weight: 700;
}

.meta,
.countdown {
  margin: 8px 0 0;
  opacity: 0.8;
}

.error {
  color: var(--color-danger);
}
</style>
