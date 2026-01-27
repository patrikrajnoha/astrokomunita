<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-bold text-indigo-400">Udalosti</h1>
      <p class="mt-1 text-slate-300 text-sm">
        Zoznam astronomických udalostí (načítané z API).
      </p>
    </header>

    <section class="flex flex-wrap gap-2">
      <button class="filterbtn" :class="{ active: selectedType === 'all' }" @click="selectedType = 'all'">
        Všetky
      </button>
      <button class="filterbtn" :class="{ active: selectedType === 'meteors' }" @click="selectedType = 'meteors'">
        Meteorické roje
      </button>
      <button class="filterbtn" :class="{ active: selectedType === 'eclipses' }" @click="selectedType = 'eclipses'">
        Zatmenia
      </button>
      <button class="filterbtn" :class="{ active: selectedType === 'conjunctions' }" @click="selectedType = 'conjunctions'">
        Konjunkcie
      </button>
      <button class="filterbtn" :class="{ active: selectedType === 'comets' }" @click="selectedType = 'comets'">
        Kométy
      </button>
    </section>

    <div v-if="loading" class="text-slate-300">Načítavam udalosti…</div>
    <div v-else-if="error" class="text-red-300">
      Chyba: {{ error }}
    </div>

    <section v-else class="grid gap-4 sm:grid-cols-2">
      <router-link
        v-for="e in filteredEvents"
        :key="e.id"
        :to="`/events/${e.id}`"
        class="card"
      >
        <div class="flex items-start justify-between gap-3">
          <h2 class="text-lg font-semibold text-white">{{ e.title }}</h2>

          <div class="flex items-center gap-2">
            <span class="badge">{{ typeLabel(e.type) }}</span>

            <button
              class="favbtn"
              type="button"
              :disabled="favorites.loading"
              :aria-pressed="favorites.isFavorite(e.id)"
              :title="favorites.isFavorite(e.id) ? 'Odobrať z obľúbených' : 'Pridať do obľúbených'"
              @click.prevent.stop="toggleFavorite(e.id)"
            >
              <span v-if="favorites.isFavorite(e.id)">★</span>
              <span v-else>☆</span>
            </button>
          </div>
        </div>

        <p class="mt-2 text-sm text-slate-300">
          <span class="text-slate-200 font-medium">Max:</span>
          {{ formatDateTime(e.max_at) }}
        </p>

        <p class="mt-2 text-sm text-slate-300 line-clamp-2">
          {{ e.short || '—' }}
        </p>

        <p class="mt-3 text-xs text-slate-400">
          Viditeľnosť: {{ e.visibility ?? '—' }}
        </p>
      </router-link>
    </section>
  </div>
</template>

<script>
import api from '../services/api'
import { useFavoritesStore } from '../stores/favorites'

export default {
  name: 'EventsView',
  data() {
    return {
      selectedType: 'all',
      events: [],
      loading: true,
      error: null,
      favorites: useFavoritesStore(),
    }
  },
  computed: {
    filteredEvents() {
      if (this.selectedType === 'all') return this.events

      // UI filter -> API types
      const groups = {
        meteors: ['meteor_shower'],
        eclipses: ['eclipse_lunar', 'eclipse_solar'],
        conjunctions: ['planetary_event'], // ak neskôr rozlíšiš, upravíš len tu
        comets: ['other'], // zatiaľ nemáš "comet" typ v backende
      }

      const allowed = groups[this.selectedType] || []
      return this.events.filter((e) => allowed.includes(e.type))
    },
  },
  methods: {
    async fetchEvents() {
      this.loading = true
      this.error = null

      try {
        const res = await api.get('/events')
        // ✅ Laravel paginator -> reálne položky sú v res.data.data
        this.events = Array.isArray(res.data?.data) ? res.data.data : []
      } catch (err) {
        this.error = err?.message || 'Nepodarilo sa načítať udalosti.'
      } finally {
        this.loading = false
      }
    },

    async toggleFavorite(eventId) {
      return this.favorites.toggle(eventId)
    },

    typeLabel(type) {
      const map = {
        meteor_shower: 'Meteory',
        eclipse_lunar: 'Zatmenie (L)',
        eclipse_solar: 'Zatmenie (S)',
        planetary_event: 'Konjunkcia',
        other: 'Iné',
      }
      return map[type] || type
    },

    formatDateTime(value) {
      if (!value) return '—'
      const d = new Date(value)
      if (isNaN(d.getTime())) return String(value)
      return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
    },
  },

  async created() {
    await this.fetchEvents()
    await this.favorites.fetch()
  },
}
</script>

<style scoped>
.card {
  display: block;
  padding: 1rem;
  border-radius: 1.25rem;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.6);
  position: relative;
}
.card:hover {
  border-color: rgb(99 102 241);
}
.badge {
  font-size: 0.75rem;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.15);
  color: rgb(199, 210, 254);
  border: 1px solid rgba(99, 102, 241, 0.35);
}
.filterbtn {
  padding: 0.4rem 0.7rem;
  border-radius: 999px;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.5);
  color: rgb(203 213 225);
  font-size: 0.875rem;
}
.filterbtn.active {
  border-color: rgb(99 102 241);
  color: white;
}
.favbtn {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.5);
  color: rgb(199, 210, 254);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  font-size: 1.05rem;
}
.favbtn:hover {
  border-color: rgb(99 102 241);
  color: white;
}
.favbtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
