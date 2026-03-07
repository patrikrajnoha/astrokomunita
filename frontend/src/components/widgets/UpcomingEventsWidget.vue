<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <AsyncState
      v-if="loading"
      mode="loading"
      title="Nacitavam udalosti"
      loading-style="skeleton"
      :skeleton-rows="4"
      compact
    />

    <AsyncState
      v-else-if="error"
      mode="error"
      :title="loadErrorTitle"
      :message="error"
      action-label="Skusit znova"
      compact
      @action="fetchItems"
    />

    <AsyncState
      v-else-if="!items.length"
      mode="empty"
      title="Ziadne blizke udalosti"
      message="Skus pozriet kalendar alebo obnovit data neskor."
      compact
    />

    <div v-else class="eventsViewport">
      <transition-group tag="ul" name="fade" class="eventsList">
        <li v-for="event in items" :key="event.id" class="eventItem">
          <div class="eventDate">{{ formatDate(event.start_at) }}</div>
          <div class="eventTitle">{{ event.title }}</div>
        </li>
      </transition-group>
    </div>

    <div class="panelActions">
      <router-link class="showMoreLink" :to="showMoreTo">{{ showMoreLabel }}</router-link>
    </div>
  </section>
</template>

<script>
import { onMounted, ref } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import { getUpcomingEventsWidget } from '@/services/widgets'
import { EVENT_TIMEZONE, formatEventDate } from '@/utils/eventTime'

export default {
  name: 'UpcomingEventsWidget',
  components: {
    AsyncState,
  },
  props: {
    title: {
      type: String,
      default: 'Co sa deje',
    },
    showMoreLabel: {
      type: String,
      default: 'Show more',
    },
    showMoreTo: {
      type: String,
      default: '/events',
    },
    loadErrorTitle: {
      type: String,
      default: 'Nepodarilo sa nacitat',
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
      fetchItems,
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
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
}

.eventsViewport {
  min-height: 5.3rem;
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
  grid-template-columns: 4.6rem minmax(0, 1fr);
  align-items: start;
  column-gap: 0.44rem;
  row-gap: 0;
  border-bottom: 1px solid var(--divider-color);
  padding: 0.38rem 0;
  border-radius: var(--radius-sm);
}

.eventItem:last-child {
  border-bottom: none;
}

.eventItem:hover {
  background: var(--interactive-hover);
}

.eventDate {
  color: var(--color-text-secondary);
  font-size: 0.69rem;
  line-height: 1.15;
  white-space: nowrap;
}

.eventTitle {
  color: var(--color-surface);
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1.2;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.panelActions {
  display: flex;
  padding-top: 0.18rem;
}

.showMoreLink {
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 600;
  text-decoration: none;
  line-height: 1.2;
  border-radius: var(--radius-pill);
  padding: 0.15rem 0.36rem;
  transition: background-color var(--motion-fast), color var(--motion-fast);
}

.showMoreLink:hover {
  color: var(--color-text-primary);
  background: var(--interactive-hover);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 180ms ease, transform 180ms ease-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(2px);
}
</style>
