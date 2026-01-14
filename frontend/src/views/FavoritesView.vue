<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-bold text-indigo-400">Obľúbené ⭐</h1>
      <p class="mt-1 text-slate-300 text-sm">
        Udalosti, ktoré máš uložené medzi obľúbenými.
      </p>
    </header>

    <div v-if="favorites.loading" class="text-slate-300">
      Načítavam obľúbené…
    </div>

    <div v-else-if="favoriteEvents.length === 0" class="text-slate-300">
      Zatiaľ nemáš žiadne obľúbené udalosti.
    </div>

    <section v-else class="grid gap-4 sm:grid-cols-2">
      <router-link
        v-for="e in favoriteEvents"
        :key="e.id"
        :to="`/events/${e.id}`"
        class="card"
      >
        <div class="flex items-start justify-between gap-3">
          <h2 class="text-lg font-semibold text-white">
            {{ e.title }}
          </h2>

          <!-- ⭐ -->
          <button
            class="favbtn"
            type="button"
            title="Odobrať z obľúbených"
            @click.prevent.stop="toggleFavorite(e.id)"
          >
            ★
          </button>
        </div>

        <p class="mt-2 text-sm text-slate-300">
          <span class="text-slate-200 font-medium">Max:</span>
          {{ formatDateTime(e.max_at) }}
        </p>

        <p class="mt-2 text-sm text-slate-300 line-clamp-2">
          {{ e.short || '—' }}
        </p>

        <p class="mt-3 text-xs text-slate-400">
          Viditeľnosť: {{ e.visibility || '—' }}
        </p>
      </router-link>
    </section>
  </div>
</template>

<script>
import api from '../services/api'
import { useFavoritesStore } from '../stores/favorites'

export default {
  name: 'FavoritesView',
  data() {
    return {
      events: [], // všetky eventy (len na premapovanie)
      favorites: useFavoritesStore(),
    }
  },
  computed: {
    favoriteEvents() {
      return this.events.filter((e) => this.favorites.isFavorite(e.id))
    },
  },
  methods: {
    async fetchEvents() {
      const res = await api.get('/events')
      this.events = res.data || []
    },

    async toggleFavorite(eventId) {
      return this.favorites.toggle(eventId)
    },

    formatDateTime(value) {
      if (!value) return '—'
      return value.replace('T', ' ').slice(0, 16)
    },
  },

  async created() {
    await Promise.all([
      this.fetchEvents(),
      this.favorites.fetch(),
    ])
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
}
.card:hover {
  border-color: rgb(99 102 241);
}

/* ⭐ */
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
</style>
