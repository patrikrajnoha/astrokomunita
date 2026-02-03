<template>
  <section class="card panel">
    <div class="panelTitle">{{ title }}</div>

    <div v-if="loading" class="panelLoading">
      <div class="skeleton h-4 w-2/3"></div>
      <div class="skeleton h-4 w-1/2"></div>
      <div class="skeleton h-8 w-full"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <div class="stateTitle">Nepodarilo sa načítať</div>
      <div class="stateText">{{ error }}</div>
      <button class="ghostbtn" @click="fetchNextEvent">Skúsiť znova</button>
    </div>

    <div v-else-if="!nextEvent" class="state">
      <div class="stateTitle">Zatiaľ žiadna udalosť</div>
      <div class="stateText">Pozri kalendár alebo udalosti.</div>
      <div class="panelActions">
        <router-link class="ghostbtn" to="/events">Všetky udalosti</router-link>
      </div>
    </div>

    <div v-else class="eventCard">
      <div class="eventTitle">{{ nextEvent.title }}</div>
      <div class="eventMeta">{{ formatDateTime(nextEvent.max_at) }}</div>
      <router-link class="actionbtn" :to="`/events/${nextEvent.id}`">
        Detail
      </router-link>
    </div>
  </section>
</template>

<script>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

export default {
  name: 'NextEventWidget',
  props: {
    title: {
      type: String,
      default: 'Najbližšia udalosť'
    }
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
        } else {
          if (!ev.title || !ev.id) {
            nextEvent.value = null
          } else {
            nextEvent.value = ev
          }
        }
      } catch (err) {
        error.value =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať najbližšiu udalosť.'
      } finally {
        loading.value = false
      }
    }

    const formatDateTime = (value) => {
      if (!value) return '—'
      return value.replace('T', ' ').slice(0, 16)
    }

    onMounted(() => {
      fetchNextEvent()
    })

    return {
      nextEvent,
      loading,
      error,
      fetchNextEvent,
      formatDateTime
    }
  }
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

.panelLoading {
  display: grid;
  gap: 0.5rem;
}

.eventCard {
  display: grid;
  gap: 0.6rem;
}

.eventTitle {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--color-surface);
}

.eventMeta {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.panelActions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.actionbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
}

.actionbtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.28);
}

.ghostbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid var(--color-text-secondary);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.2);
}

.ghostbtn:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.08);
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
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-4 { height: 1rem; }
.w-2\/3 { width: 66.666667%; }
.w-1\/2 { width: 50%; }
.w-full { width: 100%; }
</style>
