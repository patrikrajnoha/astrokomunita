<template>
  <div class="pollCard" @click.stop>
    <div class="pollOptions">
      <button
        v-for="option in localPoll.options || []"
        :key="option.id"
        type="button"
        class="pollOption"
        :class="optionClasses(option)"
        :disabled="optionDisabled"
        @click.stop="onOptionClick(option)"
      >
        <span v-if="showResults" class="pollFill" :style="{ width: `${safePercent(option.percent)}%` }" />
        <span class="pollLabel">{{ option.text }}</span>
        <span v-if="showResults" class="pollMeta">
          <span v-if="isChosen(option)" class="pollCheck">✓</span>
          <span class="pollPercent">{{ safePercent(option.percent) }}%</span>
          <span v-if="isClosed && option.is_winner" class="pollWinner">Víťaz</span>
        </span>
      </button>
    </div>

    <div class="pollFooter">
      <span>Počet hlasov: {{ totalVotes }}</span>
      <span>•</span>
      <span>{{ footerTimeLabel }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { formatPollRemainingSk } from '@/utils/pollTime'

const props = defineProps({
  poll: { type: Object, required: true },
  postId: { type: Number, default: null },
  isAuthed: { type: Boolean, default: false },
})

const emit = defineEmits(['updated', 'login-required'])

const localPoll = ref(clonePoll(props.poll))
const loading = ref(false)
const tickSeconds = ref(Number(props.poll?.ends_in_seconds ?? 0))
let timerId = null

const isClosed = computed(() => Boolean(localPoll.value?.is_closed) || tickSeconds.value <= 0)
const hasVoted = computed(() => Number(localPoll.value?.my_vote_option_id || 0) > 0)
const showResults = computed(() => hasVoted.value || isClosed.value)
const optionDisabled = computed(() => loading.value || hasVoted.value || isClosed.value)
const totalVotes = computed(() => Number(localPoll.value?.total_votes ?? 0))

const footerTimeLabel = computed(() => {
  if (isClosed.value) return 'Ukončené'
  return `Zostáva: ${formatPollRemainingSk(tickSeconds.value)}`
})

watch(
  () => props.poll,
  (next) => {
    localPoll.value = clonePoll(next)
    tickSeconds.value = Number(next?.ends_in_seconds ?? 0)
  },
  { deep: true },
)

onMounted(() => {
  timerId = window.setInterval(() => {
    if (tickSeconds.value > 0) {
      tickSeconds.value -= 1
    }
  }, 1000)
})

onBeforeUnmount(() => {
  if (timerId) {
    window.clearInterval(timerId)
    timerId = null
  }
})

function safePercent(value) {
  const n = Number(value ?? 0)
  if (!Number.isFinite(n)) return 0
  return Math.max(0, Math.min(100, Math.round(n)))
}

function isChosen(option) {
  return Number(localPoll.value?.my_vote_option_id) === Number(option?.id)
}

function optionClasses(option) {
  return {
    'pollOption--result': showResults.value,
    'pollOption--chosen': isChosen(option),
    'pollOption--winner': isClosed.value && !!option?.is_winner,
  }
}

async function onOptionClick(option) {
  if (!option || optionDisabled.value) return

  if (!props.isAuthed) {
    emit('login-required')
    redirectToLogin()
    return
  }

  loading.value = true
  try {
    const response = await api.vote(localPoll.value.id, option.id)
    const nextPoll = response?.data || null
    if (nextPoll) {
      localPoll.value = clonePoll(nextPoll)
      tickSeconds.value = Number(nextPoll.ends_in_seconds ?? 0)
      emit('updated', nextPoll)
    }
  } finally {
    loading.value = false
  }
}

function clonePoll(value) {
  if (!value || typeof value !== 'object') {
    return {
      id: null,
      is_closed: false,
      total_votes: 0,
      ends_in_seconds: 0,
      my_vote_option_id: null,
      options: [],
    }
  }

  return {
    ...value,
    options: Array.isArray(value.options) ? value.options.map((x) => ({ ...x })) : [],
  }
}

function redirectToLogin() {
  if (typeof window === 'undefined') return
  const redirect = encodeURIComponent(window.location.pathname + window.location.search)
  window.location.assign(`/login?redirect=${redirect}`)
}
</script>

<style scoped>
.pollCard {
  margin-top: 10px;
}

.pollOptions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pollOption {
  position: relative;
  width: 100%;
  min-height: 46px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.24);
  color: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color 0.16s ease, background-color 0.16s ease;
}

.pollOption:hover:enabled {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.1);
}

.pollOption:disabled {
  cursor: default;
}

.pollFill {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  background: rgb(var(--color-primary-rgb) / 0.16);
  pointer-events: none;
}

.pollLabel,
.pollMeta {
  position: relative;
  z-index: 1;
}

.pollLabel {
  font-weight: 600;
  text-align: left;
}

.pollMeta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.pollPercent {
  min-width: 36px;
  text-align: right;
}

.pollCheck {
  color: var(--color-success);
  font-weight: 800;
}

.pollWinner {
  font-weight: 800;
  color: var(--color-warning);
}

.pollOption--chosen {
  border-color: rgb(var(--color-success-rgb) / 0.55);
}

.pollOption--winner {
  border-width: 2px;
  border-color: rgb(var(--color-warning-rgb) / 0.7);
}

.pollFooter {
  margin-top: 8px;
  color: var(--color-text-secondary);
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
</style>
