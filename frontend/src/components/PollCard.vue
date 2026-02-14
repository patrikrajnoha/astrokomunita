<template>
  <section class="pollCard" @click.stop>
    <div v-if="showHero" class="pollHero" data-testid="poll-hero">
      <img :src="heroImageUrl" alt="Poll preview" class="pollHeroImage" />
    </div>

    <div class="pollOptions">
      <button
        v-for="option in localPoll.options || []"
        :key="option.id"
        type="button"
        class="pollOption"
        :class="optionClasses(option)"
        :disabled="optionDisabled"
        :aria-label="optionAriaLabel(option)"
        :aria-pressed="isChosen(option) ? 'true' : 'false'"
        @mouseenter="onOptionHover(option)"
        @focus="onOptionHover(option)"
        @mouseleave="onOptionLeave"
        @blur="onOptionLeave"
        @touchstart="onOptionHover(option)"
        @click.stop="onOptionClick(option)"
      >
        <span v-if="showResults" class="pollFill" :style="{ width: `${safePercent(option.percent)}%` }" />

        <span class="pollContent">
          <span v-if="option.image_url" class="pollThumbWrap" aria-hidden="true">
            <img :src="option.image_url" alt="" class="pollThumb" />
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
      <span>Pocet hlasov: {{ totalVotes }}</span>
      <span>·</span>
      <span>{{ footerTimeLabel }}</span>
    </div>
  </section>
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
const activeHeroOptionId = ref(null)
let timerId = null

const isClosed = computed(() => Boolean(localPoll.value?.is_closed) || tickSeconds.value <= 0)
const hasVoted = computed(() => {
  if (Number(localPoll.value?.my_vote_option_id || 0) > 0) return true
  return Boolean(localPoll.value?.user_has_voted)
})
const showResults = computed(() => hasVoted.value || isClosed.value)
const optionDisabled = computed(() => loading.value || hasVoted.value || isClosed.value)
const totalVotes = computed(() => Number(localPoll.value?.total_votes ?? 0))

const optionsWithImages = computed(() => {
  const options = Array.isArray(localPoll.value?.options) ? localPoll.value.options : []
  return options.filter((option) => typeof option?.image_url === 'string' && option.image_url.trim() !== '')
})

const defaultHeroImageUrl = computed(() => optionsWithImages.value[0]?.image_url || '')

const chosenHeroImageUrl = computed(() => {
  if (!showResults.value) return ''

  const chosenId = Number(localPoll.value?.my_vote_option_id || localPoll.value?.chosen_option_id || 0)
  if (!chosenId) return ''

  const chosen = (localPoll.value?.options || []).find((option) => Number(option?.id) === chosenId)
  return typeof chosen?.image_url === 'string' ? chosen.image_url : ''
})

const hoveredHeroImageUrl = computed(() => {
  if (showResults.value) return ''
  const activeId = Number(activeHeroOptionId.value || 0)
  if (!activeId) return ''

  const option = (localPoll.value?.options || []).find((item) => Number(item?.id) === activeId)
  return typeof option?.image_url === 'string' ? option.image_url : ''
})

const heroImageUrl = computed(() => {
  if (chosenHeroImageUrl.value) return chosenHeroImageUrl.value
  if (hoveredHeroImageUrl.value) return hoveredHeroImageUrl.value
  return defaultHeroImageUrl.value
})

const showHero = computed(() => typeof heroImageUrl.value === 'string' && heroImageUrl.value.trim() !== '')

const footerTimeLabel = computed(() => {
  if (isClosed.value) return 'Ukoncene'
  return `Zostava: ${formatPollRemainingSk(tickSeconds.value)}`
})

watch(
  () => props.poll,
  (next) => {
    localPoll.value = clonePoll(next)
    tickSeconds.value = Number(next?.ends_in_seconds ?? 0)
    activeHeroOptionId.value = null
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

function onOptionHover(option) {
  if (showResults.value) return
  if (!option?.image_url) return
  activeHeroOptionId.value = option.id
}

function onOptionLeave() {
  if (showResults.value) return
  activeHeroOptionId.value = null
}

function optionAriaLabel(option) {
  if (!showResults.value) {
    return `Hlasovat za moznost: ${option?.text || ''}`
  }

  return `Moznost ${option?.text || ''}, ${safePercent(option?.percent)} percent`
}

async function onOptionClick(option) {
  if (!option || optionDisabled.value) return

  onOptionHover(option)

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
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 16px;
  background: rgb(var(--color-bg-rgb) / 0.38);
  padding: 0.65rem;
}

.pollHero {
  width: 100%;
  aspect-ratio: 1.91 / 1;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  box-shadow: 0 10px 28px rgb(0 0 0 / 0.2);
  margin-bottom: 0.58rem;
}

.pollHeroImage {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
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
  min-height: 46px;
  border-radius: 9999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.36);
  background: rgb(var(--color-bg-rgb) / 0.26);
  color: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  overflow: hidden;
  cursor: pointer;
  text-align: left;
  transition: border-color 160ms ease, background-color 160ms ease;
}

.pollOption:hover:enabled {
  border-color: rgb(var(--color-primary-rgb) / 0.52);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.pollOption:focus-visible {
  outline: 2px solid rgb(var(--color-primary-rgb) / 0.8);
  outline-offset: 2px;
}

.pollOption:disabled {
  cursor: default;
}

.pollFill {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  background: rgb(var(--color-primary-rgb) / 0.19);
  pointer-events: none;
  transition: width 260ms ease;
}

.pollContent {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.pollThumbWrap {
  width: 26px;
  height: 26px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.pollThumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pollLabel,
.pollMeta {
  position: relative;
  z-index: 1;
}

.pollLabel {
  font-weight: 600;
  text-align: left;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pollMeta {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.pollPercent {
  min-width: 36px;
  text-align: right;
  font-weight: 700;
}

.pollCheck {
  color: rgb(var(--color-primary-rgb) / 0.95);
  font-weight: 800;
}

.pollOption--chosen {
  border-color: rgb(var(--color-primary-rgb) / 0.8);
}

.pollFooter {
  margin-top: 8px;
  color: var(--color-text-secondary);
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
</style>
