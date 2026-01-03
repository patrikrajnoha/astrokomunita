<template>
  <div class="space-y-8">
    <header>
      <h1 class="text-3xl font-extrabold text-indigo-400">Astrokomunita</h1>
      <p class="mt-2 text-slate-300">
        Najbližšia astronomická udalosť + rýchly prístup k sekciám.
      </p>
    </header>

    <!-- HERO: najbližšia udalosť -->
    <section class="hero">
      <div v-if="loading" class="text-slate-200">Načítavam najbližšiu udalosť…</div>

      <div v-else-if="error" class="text-red-200">
        Chyba: {{ error }}
      </div>

      <div v-else-if="!nextEvent" class="text-slate-200">
        Zatiaľ nemáme žiadnu budúcu udalosť.
      </div>

      <div v-else class="space-y-3">
        <div class="flex items-start justify-between gap-4">
          <h2 class="text-2xl font-bold text-white">
            {{ nextEvent.title }}
          </h2>
          <span class="badge">{{ typeLabel(nextEvent.type) }}</span>
        </div>

        <p class="text-slate-200">
          <span class="text-slate-300">Max:</span>
          <span class="font-semibold">{{ formatDateTime(nextEvent.max_at) }}</span>
        </p>

        <p class="text-slate-200">
          {{ nextEvent.short || '—' }}
        </p>

        <p class="text-slate-300 text-sm">
          Viditeľnosť: <span class="text-slate-200 font-medium">{{ nextEvent.visibility || '—' }}</span>
        </p>

        <div class="pt-2 flex flex-wrap gap-2">
          <router-link class="actionbtn" :to="`/events/${nextEvent.id}`">
            Detail udalosti
          </router-link>

          <router-link class="ghostbtn" to="/events">
            Všetky udalosti →
          </router-link>
        </div>
      </div>
    </section>

    <!-- Quick links -->
    <section class="grid gap-3 sm:grid-cols-2">
      <router-link class="quickcard" to="/events">🌌 Udalosti</router-link>
      <router-link class="quickcard" to="/calendar">📅 Kalendár</router-link>
      <router-link class="quickcard" to="/observations">📸 Pozorovania</router-link>
      <router-link class="quickcard" to="/learn">📚 Vzdelávanie</router-link>
    </section>
  </div>
</template>

<script>
import api from '../services/api'

export default {
  name: 'HomeView',
  data() {
    return {
      nextEvent: null,
      loading: true,
      error: null,
    }
  },
  methods: {
    async fetchNextEvent() {
      this.loading = true
      this.error = null

      try {
        const res = await api.get('/events/next')
        this.nextEvent = res.data || null
      } catch (err) {
        this.error = err?.message || 'Nepodarilo sa načítať najbližšiu udalosť.'
      } finally {
        this.loading = false
      }
    },
    typeLabel(type) {
      const map = {
        meteors: 'Meteory',
        eclipse: 'Zatmenie',
        conjunction: 'Konjunkcia',
        comet: 'Kométa',
      }
      return map[type] || type
    },
    formatDateTime(value) {
      if (!value) return '—'
      return value.replace('T', ' ').slice(0, 16)
    },
  },
  created() {
    this.fetchNextEvent()
  },
}
</script>

<style scoped>
.hero {
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.65);
  border-radius: 1.5rem;
  padding: 1.25rem;
}
.badge {
  font-size: 0.75rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.15);
  color: rgb(199, 210, 254);
  border: 1px solid rgba(99, 102, 241, 0.35);
}
.actionbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(99 102 241);
  background: rgba(99, 102, 241, 0.15);
  color: white;
}
.actionbtn:hover {
  background: rgba(99, 102, 241, 0.25);
}
.ghostbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(51 65 85);
  color: rgb(203 213 225);
  background: rgba(15, 23, 42, 0.2);
}
.ghostbtn:hover {
  border-color: rgb(99 102 241);
  color: white;
}
.quickcard {
  display: block;
  padding: 0.9rem 1rem;
  border-radius: 1.25rem;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.45);
  color: rgb(226 232 240);
}
.quickcard:hover {
  border-color: rgb(99 102 241);
}
</style>
