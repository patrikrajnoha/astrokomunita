<template>
  <section class="card activityCard" aria-live="polite">
    <h2 class="activityTitle">Aktivita</h2>

    <div v-if="loading" class="activitySkeleton" data-testid="activity-loading">
      <div class="skeletonLine"></div>
      <div class="skeletonLine"></div>
      <div class="skeletonLine"></div>
    </div>

    <dl v-else class="activityList" data-testid="activity-values">
      <div class="activityRow">
        <dt>Posledné prihlásenie</dt>
        <dd data-testid="last-login">{{ formattedLastLogin }}</dd>
      </div>
      <div class="activityRow">
        <dt>Počet postov</dt>
        <dd data-testid="posts-count">{{ formattedPostsCount }}</dd>
      </div>
      <div class="activityRow">
        <dt>Ucast na eventoch</dt>
        <dd data-testid="participations-count">{{ formattedParticipationsCount }}</dd>
      </div>
    </dl>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  activity: {
    type: Object,
    default: null,
  },
})

const formattedLastLogin = computed(() => formatLastLogin(props.activity?.last_login_at))
const formattedPostsCount = computed(() => formatCount(props.activity?.posts_count))
const formattedParticipationsCount = computed(() => formatCount(props.activity?.event_participations_count))

function formatLastLogin(value) {
  if (!value) return 'Zatiaľ nezaznamenané'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)

  return new Intl.DateTimeFormat('sk-SK', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(parsed)
}

function formatCount(value) {
  const count = Number(value)
  const safeCount = Number.isFinite(count) && count >= 0 ? Math.floor(count) : 0

  return new Intl.NumberFormat('sk-SK').format(safeCount)
}
</script>

<style scoped>
.activityCard {
  margin-top: 1rem;
}

.activityTitle {
  margin: 0;
  font-size: 1rem;
  font-weight: 900;
  color: var(--color-surface);
}

.activityList {
  margin-top: 0.75rem;
  display: grid;
  gap: 0.55rem;
}

.activityRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  border-radius: 0.9rem;
  background: rgb(var(--color-bg-rgb) / 0.34);
  padding: 0.6rem 0.75rem;
}

.activityRow dt {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.activityRow dd {
  margin: 0;
  font-weight: 800;
  color: var(--color-surface);
  text-align: right;
}

.activitySkeleton {
  margin-top: 0.75rem;
  display: grid;
  gap: 0.55rem;
}

.skeletonLine {
  height: 42px;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background:
    linear-gradient(
      110deg,
      rgb(var(--color-text-secondary-rgb) / 0.12) 8%,
      rgb(var(--color-text-secondary-rgb) / 0.28) 18%,
      rgb(var(--color-text-secondary-rgb) / 0.12) 33%
    );
  background-size: 200% 100%;
  animation: activityPulse 1.2s linear infinite;
}

@keyframes activityPulse {
  to {
    background-position-x: -200%;
  }
}
</style>
