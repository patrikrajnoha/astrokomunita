<template>
  <section class="card panel">
    <div class="panelTitle">{{ title }}</div>

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
      if (!value) return '-'
      const date = new Date(value)
      if (Number.isNaN(date.getTime())) return '-'

      return new Intl.DateTimeFormat('sk-SK', {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
      }).format(date)
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
  border: 1px solid var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.55);
  border-radius: 1.5rem;
  padding: 1.25rem;
  overflow: hidden;
}

.panel {
  display: grid;
  gap: 0.75rem;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.95rem;
}

.eventsViewport {
  min-height: 8rem;
}

.eventsList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.7rem;
}

.eventItem {
  display: grid;
  gap: 0.2rem;
}

.eventDate {
  color: var(--color-text-secondary);
  font-size: 0.78rem;
  line-height: 1.2;
}

.eventTitle {
  color: var(--color-surface);
  font-size: 0.94rem;
  font-weight: 700;
  line-height: 1.35;
}

.panelActions {
  display: flex;
}

.showMoreLink {
  color: var(--color-primary);
  font-size: 0.86rem;
  font-weight: 600;
  text-decoration: none;
}

.showMoreLink:hover {
  text-decoration: underline;
}

.panelLoading {
  min-height: 8rem;
  display: grid;
  gap: 0.7rem;
}

.skeletonRow {
  display: grid;
  gap: 0.25rem;
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
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--color-surface);
}

.stateText {
  margin-top: 0.35rem;
  color: var(--color-text-secondary);
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
