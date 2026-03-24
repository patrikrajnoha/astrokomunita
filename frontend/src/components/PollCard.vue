<template>
  <div class="pollCard" @click.stop>
    <div class="pollDesktop" data-testid="poll-desktop">
      <div class="pollOptions">
        <button
          v-for="option in localPoll.options || []"
          :key="`desktop-${option.id}`"
          type="button"
          class="pollOption"
          :class="optionClasses(option)"
          :disabled="optionDisabled"
          :aria-label="optionAriaLabel(option)"
          :aria-pressed="isChosen(option) ? 'true' : 'false'"
          @click.stop="onOptionClick(option)"
        >
          <span v-if="showResults" class="pollFill" :style="{ width: `${safePercent(option.percent)}%` }" />

          <span class="pollContent">
            <span v-if="option.image_url" class="pollThumbWrap" aria-hidden="true">
              <img :src="option.image_url" alt="" class="pollThumb" />
            </span>
            <span v-else class="pollThumbPlaceholder" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.6"/>
                <circle cx="9" cy="10" r="1.5" fill="currentColor"/>
                <path d="m6.5 16 3.5-3 2.4 2 2.1-1.8 3 2.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <span class="pollLabel">{{ option.text }}</span>
          </span>

          <span v-if="showResults" class="pollMeta" aria-hidden="true">
            <span v-if="isChosen(option)" class="pollCheck">?</span>
            <span class="pollPercent">{{ safePercent(option.percent) }}%</span>
          </span>
        </button>
      </div>

      <div class="pollFooter">
        <span>Počet hlasov: {{ totalVotes }}</span>
        <span>&middot;</span>
        <span>{{ footerTimeLabel }}</span>
      </div>
    </div>

    <section class="pollMobileCard" data-testid="poll-mobile-card">
      <div class="mPollOptions">
        <button
          v-for="option in localPoll.options || []"
          :key="`mobile-${option.id}`"
          type="button"
          class="mPollOption"
          :class="optionClasses(option)"
          :disabled="optionDisabled"
          :aria-label="optionAriaLabel(option)"
          :aria-pressed="isChosen(option) ? 'true' : 'false'"
          @click.stop="onOptionClick(option)"
        >
          <span v-if="showResults" class="mPollFill" :style="{ width: `${safePercent(option.percent)}%` }" />

          <span class="mPollContent">
            <span v-if="option.image_url" class="mPollThumbWrap" aria-hidden="true">
              <img :src="option.image_url" alt="" class="mPollThumb" />
            </span>
            <span class="mPollLabel">{{ option.text }}</span>
          </span>

          <span v-if="showResults" class="mPollMeta" aria-hidden="true">
            <span class="mPollPercent">{{ safePercent(option.percent) }}%</span>
          </span>
        </button>
      </div>

      <div class="mPollFooter">
        <span>Počet hlasov: {{ totalVotes }}</span>
        <span>&middot;</span>
        <span>{{ footerTimeLabel }}</span>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { formatPollRemainingSk } from '@/utils/pollTime'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  poll: { type: Object, required: true },
  postId: { type: Number, default: null },
  isAuthed: { type: Boolean, default: false },
})

const emit = defineEmits(['updated', 'login-required'])

const toast = useToast()
const localPoll = ref(clonePoll(props.poll))
const loading = ref(false)
const tickSeconds = ref(Number(props.poll?.ends_in_seconds ?? 0))
let timerId = null

const isClosed = computed(() => Boolean(localPoll.value?.is_closed) || tickSeconds.value <= 0)
const hasVoted = computed(() => {
  if (Number(localPoll.value?.my_vote_option_id || 0) > 0) return true
  return Boolean(localPoll.value?.user_has_voted)
})
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
  return Number(localPoll.value?.my_vote_option_id || localPoll.value?.chosen_option_id) === Number(option?.id)
}

function optionClasses(option) {
  return {
    'pollOption--result': showResults.value,
    'pollOption--chosen': isChosen(option),
  }
}

function optionAriaLabel(option) {
  if (!showResults.value) {
    return `Hlasovat za moznost: ${option?.text || ''}`
  }

  return `Moznost ${option?.text || ''}, ${safePercent(option?.percent)} percent`
}

async function onOptionClick(option) {
  if (!option || optionDisabled.value) return

  if (!props.isAuthed) {
    toast.warn('Prihlas sa pre hlasovanie.')
    emit('login-required')
    return
  }

  const beforeVote = clonePoll(localPoll.value)
  applyOptimisticVote(option.id)
  loading.value = true

  try {
    const response = await api.vote(localPoll.value.id, option.id)
    const nextPoll = response?.data || null
    if (nextPoll) {
      localPoll.value = clonePoll(nextPoll)
      tickSeconds.value = Number(nextPoll.ends_in_seconds ?? 0)
      emit('updated', nextPoll)
    }
  } catch (error) {
    localPoll.value = beforeVote
    tickSeconds.value = Number(beforeVote.ends_in_seconds ?? tickSeconds.value)
    toast.error(error?.response?.data?.message || 'Hlasovanie zlyhalo.')
  } finally {
    loading.value = false
  }
}

function applyOptimisticVote(optionId) {
  const options = Array.isArray(localPoll.value?.options) ? localPoll.value.options.map((item) => ({ ...item })) : []
  const votedOption = options.find((item) => Number(item.id) === Number(optionId))
  if (!votedOption) return

  votedOption.votes_count = Number(votedOption.votes_count || 0) + 1
  const totalVotesNext = Number(localPoll.value?.total_votes || 0) + 1

  options.forEach((item) => {
    const votes = Number(item.votes_count || 0)
    item.percent = totalVotesNext > 0 ? Math.round((votes / totalVotesNext) * 100) : 0
  })

  localPoll.value = {
    ...localPoll.value,
    options,
    total_votes: totalVotesNext,
    my_vote_option_id: Number(optionId),
    chosen_option_id: Number(optionId),
    user_has_voted: true,
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
      chosen_option_id: null,
      user_has_voted: false,
      options: [],
    }
  }

  return {
    ...value,
    options: Array.isArray(value.options) ? value.options.map((x) => ({ ...x })) : [],
  }
}
</script>

<style scoped>
.pollCard {
  margin-top: 10px;
}

.pollDesktop {
  display: block;
}

.pollOptions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pollOption {
  position: relative;
  width: 100%;
  min-height: 56px;
  border-radius: 16px;
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
  text-align: left;
}

.pollOption:hover:enabled {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.1);
}

.pollOption:focus-visible,
.mPollOption:focus-visible {
  outline: 2px solid rgb(var(--color-primary-rgb) / 0.82);
  outline-offset: 2px;
}

.pollOption:disabled,
.mPollOption:disabled {
  cursor: default;
  opacity: 0.9;
}

.pollFill,
.mPollFill {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  background: rgb(var(--color-primary-rgb) / 0.16);
  pointer-events: none;
  transition: width 200ms ease;
}

.pollContent {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}

.pollThumbWrap,
.pollThumbPlaceholder {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.pollThumb,
.mPollThumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pollThumbPlaceholder {
  color: var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.35);
}

.pollThumbPlaceholder svg {
  width: 16px;
  height: 16px;
}

.pollLabel,
.pollMeta {
  position: relative;
  z-index: 1;
}

.pollLabel {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pollMeta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.pollPercent,
.mPollPercent {
  min-width: 36px;
  text-align: right;
  font-weight: 700;
}

.pollCheck {
  color: rgb(var(--color-primary-rgb) / 0.9);
  font-weight: 800;
}

.pollOption--chosen {
  border-color: rgb(var(--color-primary-rgb) / 0.78);
}

.pollFooter {
  margin-top: 8px;
  color: var(--color-text-secondary);
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.pollMobileCard {
  display: none;
}

@media (max-width: 767.98px) {
  .pollDesktop {
    display: none;
  }

  .pollMobileCard {
    display: grid;
    gap: 10px;
    border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
    border-radius: 16px;
    background: rgb(var(--color-bg-rgb) / 0.36);
    padding: 12px;
  }

  .mPollOptions {
    display: grid;
    gap: 8px;
  }

  .mPollOption {
    position: relative;
    width: 100%;
    min-height: 46px;
    border-radius: 9999px;
    border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
    background: rgb(var(--color-bg-rgb) / 0.24);
    color: var(--color-surface);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
    overflow: hidden;
    text-align: left;
  }

  .mPollOption.pollOption--chosen {
    border-color: rgb(var(--color-primary-rgb) / 0.78);
  }

  .mPollContent {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }

  .mPollThumbWrap {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .mPollLabel,
  .mPollMeta {
    position: relative;
    z-index: 1;
  }

  .mPollLabel {
    font-size: 0.92rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    min-width: 0;
  }

  .mPollMeta {
    display: inline-flex;
    align-items: center;
    color: var(--color-text-secondary);
    font-size: 0.82rem;
  }

  .mPollFooter {
    color: var(--color-text-secondary);
    font-size: 0.77rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
}
</style>
