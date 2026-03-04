<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="panelLoading" aria-live="polite">
      <div v-for="index in 4" :key="index" class="skeletonRow">
        <div class="skeleton skeletonDate"></div>
        <div class="skeleton skeletonTitle"></div>
      </div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa nacitat</div>
      <div class="stateText">{{ error }}</div>
    </div>

    <div v-else class="eventsViewport">
      <transition-group tag="ul" name="fade" class="eventsList">
        <li v-for="event in items" :key="event.id" class="eventItem">
          <div class="eventDate">{{ formatDate(event.start_at) }}</div>
          <div class="eventTitle">{{ event.title }}</div>
        </li>
      </transition-group>
    </div>

    <div class="panelActions">
      <router-link class="showMoreLink" to="/events">Show more</router-link>
    </div>
  </section>
</template>

<script>
import { onMounted, ref } from 'vue'
import { getUpcomingEventsWidget } from '@/services/widgets'
import { EVENT_TIMEZONE, formatEventDate } from '@/utils/eventTime'

export default {
  name: 'UpcomingEventsWidget',
  props: {
    title: {
      type: String,
      default: 'Co sa deje',
    },
  },
  setup() {
    const items = ref([])
    const loading = ref(true)
    const error = ref('')

    const fetchItems = async () => {
      loading.value = true
      error.value = ''

      try {
        const payload = await getUpcomingEventsWidget()
        items.value = Array.isArray(payload?.items) ? payload.items.slice(0, 4) : []
      } catch (err) {
        error.value = err?.response?.data?.message || err?.message || 'Skus to neskor.'
      } finally {
        loading.value = false
      }
    }

    const formatDate = (value) => {
      return formatEventDate(value, EVENT_TIMEZONE, {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
      })
    }

    onMounted(() => {
      fetchItems()
    })

    return {
      items,
      loading,
      error,
      formatDate,
    }
  },
}
</script>

<style scoped>
.card {
  position: relative;
  border: 0;
  background: transparent;
  border-radius: 0;
  padding: 0;
  overflow: hidden;
}

.panel {
  display: grid;
  gap: var(--sb-gap-sm, 0.5rem);
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

.eventsViewport {
  min-height: 6.25rem;
}

.eventsList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0;
}

.eventItem {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  column-gap: 0.48rem;
  row-gap: 0;
  border-bottom: 1px solid var(--divider-color);
  padding: 0.46rem 0;
}

.eventItem:last-child {
  border-bottom: none;
}

.eventDate {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  line-height: 1.15;
  white-space: nowrap;
}

.eventTitle {
  color: var(--color-surface);
  font-size: 0.84rem;
  font-weight: 700;
  line-height: 1.24;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.panelActions {
  display: flex;
  padding-top: 0.35rem;
}

.showMoreLink {
  color: var(--color-primary);
  font-size: 0.78rem;
  font-weight: 600;
  text-decoration: none;
  line-height: 1.2;
}

.showMoreLink:hover {
  text-decoration: underline;
}

.panelLoading {
  min-height: 6.25rem;
  display: grid;
  gap: var(--sb-gap-sm, 0.5rem);
}

.skeletonRow {
  display: grid;
  gap: 0.2rem;
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0.75rem;
}

.skeletonDate {
  width: 36%;
  height: 0.7rem;
}

.skeletonTitle {
  width: 84%;
  height: 1rem;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.stateTitle {
  font-size: 0.86rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.24;
}

.stateText {
  margin-top: 0.2rem;
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  line-height: 1.32;
}

.stateError .stateTitle,
.stateError .stateText {
  color: var(--color-danger);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
