<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-bold text-[var(--color-primary)]">Obľúbené</h1>
      <p class="mt-1 text-[var(--color-text-secondary)] text-sm">
        Udalosti, ktoré máš uložené medzi obľúbenými.
      </p>
    </header>

    <div v-if="favorites.loading" class="text-[var(--color-text-secondary)]">
      Načítavam obľúbené…
    </div>

    <div v-else-if="favoriteEvents.length === 0" class="text-[var(--color-text-secondary)]">
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
          <h2 class="text-lg font-semibold text-[var(--color-surface)]">
            {{ e.title }}
          </h2>

          <!-- ? -->
          <button
            class="favbtn"
            type="button"
            title="Odobrať z obľúbených"
            @click.prevent.stop="toggleFavorite(e.id)"
          >
            ?
          </button>
        </div>

        <p class="mt-2 text-sm text-[var(--color-text-secondary)]">
          <span class="text-[var(--color-surface)] font-medium">Max:</span>
          {{ formatDateTime(e.max_at) }}
        </p>

        <p class="mt-2 text-sm text-[var(--color-text-secondary)] line-clamp-2">
          {{ e.short || '—' }}
        </p>

        <p class="mt-3 text-xs text-[var(--color-text-secondary)]">
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
      this.events = Array.isArray(res.data?.data)
        ? res.data.data
        : (Array.isArray(res.data) ? res.data : [])
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
  border: 1px solid var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.6);
}
.card:hover {
  border-color: var(--color-primary);
}

/* ? */
.favbtn {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  border: 1px solid var(--color-text-secondary);
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: var(--color-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  font-size: 1.05rem;
}
.favbtn:hover {
  border-color: var(--color-primary);
  color: var(--color-surface);
}
</style>

