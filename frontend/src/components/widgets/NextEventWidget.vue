<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-1/2"></div>
      <div class="skeleton h-8 w-full"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa nacitat</div>
      <div class="stateText">{{ error }}</div>
      <button class="ghostbtn" @click="fetchNextEvent">Skusit znova</button>
    </div>

    <div v-else-if="!nextEvent" class="state">
      <div class="stateTitle">Zatial ziadna udalost</div>
      <div class="stateText">Pozri kalendar alebo udalosti.</div>
      <div class="panelActions">
        <router-link class="ghostbtn" to="/events">Vsetky udalosti</router-link>
      </div>
    </div>

    <div v-else class="eventCard">
      <div class="eventTitle">{{ nextEvent.title }}</div>
      <div class="eventMeta">{{ formatDateTime(nextEvent) }}</div>
      <router-link class="actionbtn" :to="`/events/${nextEvent.id}`">
        Detail
      </router-link>
    </div>
  </section>
</template>

<script>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { EVENT_TIMEZONE, formatEventDate, resolveEventTimeContext } from '@/utils/eventTime'

export default {
  name: 'NextEventWidget',
  props: {
    title: {
      type: String,
      default: 'Najblizsia udalost',
    },
  },
  setup() {
    const nextEvent = ref(null)
    const loading = ref(true)
    const error = ref(null)

    const fetchNextEvent = async () => {
      loading.value = true
      error.value = null
      nextEvent.value = null

      try {
        const res = await api.get('/events/next')
        const payload = res?.data

        const ev = payload?.data ?? payload?.event ?? payload

        const isEmptyObject =
          ev && typeof ev === 'object' && !Array.isArray(ev) && Object.keys(ev).length === 0

        const isEmptyArray = Array.isArray(ev) && ev.length === 0

        if (!ev || isEmptyObject || isEmptyArray) {
          nextEvent.value = null
        } else if (Array.isArray(ev)) {
          nextEvent.value = ev[0] ?? null
        } else if (!ev.title || !ev.id) {
          nextEvent.value = null
        } else {
          nextEvent.value = ev
        }
      } catch (err) {
        error.value =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa nacitat najblizsiu udalost.'
      } finally {
        loading.value = false
      }
    }

    const formatDateTime = (event) => {
      const raw = event?.start_at || event?.max_at || event?.end_at
      if (!raw) return 'Termin bude upresneny'

      const dateLabel = formatEventDate(raw, EVENT_TIMEZONE, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
      const context = resolveEventTimeContext(event, EVENT_TIMEZONE)

      if (!context.showTimezoneLabel) {
        return `${dateLabel} · ${context.message}`
      }

      return `${dateLabel} · ${context.timeString} (${context.timezoneLabelShort})`
    }

    onMounted(() => {
      fetchNextEvent()
    })

    return {
      nextEvent,
      loading,
      error,
      fetchNextEvent,
      formatDateTime,
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
  font-size: 0.88rem;
  line-height: 1.22;
}

.panelLoading {
  display: grid;
  gap: var(--sb-gap-xs, 0.3rem);
}

.eventCard {
  display: grid;
  gap: 0.38rem;
  min-width: 0;
}

.eventTitle {
  font-size: 0.89rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.2;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.eventMeta {
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  line-height: 1.26;
}

.panelActions {
  display: flex;
  gap: var(--sb-gap-xs, 0.3rem);
  flex-wrap: wrap;
}

.actionbtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  justify-self: start;
  width: auto;
  min-height: 1.95rem;
  padding: 0.34rem 0.64rem;
  border-radius: 0.64rem;
  border: 1px solid var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
  font-size: 0.76rem;
  line-height: 1.15;
}

.actionbtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.28);
}

.ghostbtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  justify-self: start;
  width: auto;
  min-height: 1.95rem;
  padding: 0.34rem 0.64rem;
  border-radius: 0.64rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
  font-size: 0.76rem;
  line-height: 1.15;
}

.ghostbtn:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

.stateTitle {
  font-size: 0.82rem;
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.24;
}

.stateText {
  margin-top: 0.2rem;
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  line-height: 1.32;
}

.stateError .stateTitle,
.stateError .stateText {
  color: var(--color-danger);
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

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.h-4 {
  height: 1rem;
}

.w-2\/3 {
  width: 66.666667%;
}

.w-1\/2 {
  width: 50%;
}

.w-full {
  width: 100%;
}
</style>
