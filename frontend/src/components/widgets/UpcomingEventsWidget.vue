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
      <router-link class="upcomingMoreLink" :to="showMoreTo">{{ showMoreLabel }}</router-link>
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
  overflow: visible;
}

.panel {
  display: grid;
  gap: 0.24rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
}

.eventsViewport {
  min-height: 4.6rem;
  min-width: 0;
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
  grid-template-columns: 4.2rem minmax(0, 1fr);
  align-items: start;
  column-gap: 0.36rem;
  row-gap: 0;
  border-bottom: 1px solid var(--divider-color);
  padding: 0.3rem 0;
  border-radius: 0;
}

.eventItem:last-child {
  border-bottom: none;
}

.eventItem:hover {
  background: transparent;
}

.eventDate {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  line-height: 1.15;
  white-space: nowrap;
}

.eventTitle {
  color: var(--color-surface);
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1.16;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.panelActions {
  display: block;
  width: 100%;
  min-width: 0;
  padding-top: 0;
  margin-top: 0.04rem;
}

.upcomingMoreLink {
  display: block;
  width: 100%;
  max-width: 100%;
  color: var(--color-primary);
  font-size: 0.72rem;
  font-weight: 600;
  text-decoration: none;
  line-height: 1.12;
  text-align: center;
  padding: 0.24rem 0.48rem;
  min-height: 1.68rem;
  border-radius: 0 !important;
  box-sizing: border-box;
  background: rgb(var(--color-bg-rgb) / 0.2);
  box-shadow: inset 0 0 0 1px var(--color-text-secondary);
  transition: background-color var(--motion-fast), color var(--motion-fast), box-shadow var(--motion-fast);
}

.upcomingMoreLink:hover {
  color: var(--color-text-primary);
  background: rgb(var(--color-primary-rgb) / 0.08);
  box-shadow: inset 0 0 0 1px var(--color-primary);
  transform: none;
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

.eventsViewport,
.eventsList,
.eventItem,
.upcomingMoreLink {
  border-radius: 0 !important;
}
</style>
