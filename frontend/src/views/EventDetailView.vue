<template>
  <div class="space-y-6">
    <header class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-indigo-400">Detail udalosti</h1>

        <div class="mt-1 flex items-center gap-2" v-if="event">
          <p class="text-slate-300 text-sm">
            {{ event.title }}
          </p>

          <!-- ⭐ (stav ide zo store) -->
          <button
            class="favbtn"
            type="button"
            :disabled="favorites.loading"
            :title="favorites.isFavorite(event.id) ? 'Odobrať z obľúbených' : 'Pridať do obľúbených'"
            @click="toggleFavorite(event.id)"
          >
            <span v-if="favorites.isFavorite(event.id)">★</span>
            <span v-else>☆</span>
          </button>
        </div>
      </div>

      <router-link to="/events" class="text-indigo-400 hover:underline text-sm">
        ← späť na Udalosti
      </router-link>
    </header>

    <div v-if="loading" class="text-slate-300">Načítavam detail…</div>
    <div v-else-if="error" class="text-red-300">Chyba: {{ error }}</div>

    <section v-else class="rounded-2xl border border-slate-700 bg-slate-900/60 p-5">
      <div class="flex items-start justify-between gap-3">
        <h2 class="text-xl font-semibold text-white">
          {{ event.title }}
        </h2>

        <!-- ⭐ aj pri názve -->
        <button
          class="favbtn"
          type="button"
          :disabled="favorites.loading"
          :title="favorites.isFavorite(event.id) ? 'Odobrať z obľúbených' : 'Pridať do obľúbených'"
          @click="toggleFavorite(event.id)"
        >
          <span v-if="favorites.isFavorite(event.id)">★</span>
          <span v-else>☆</span>
        </button>
      </div>

      <div class="mt-3 space-y-2 text-sm text-slate-300">
        <p><span class="text-slate-200 font-medium">Typ:</span> {{ typeLabel(event.type) }}</p>
        <p><span class="text-slate-200 font-medium">Čas maxima:</span> {{ formatDateTime(event.max_at) }}</p>
        <p><span class="text-slate-200 font-medium">Viditeľnosť:</span> {{ event.visibility || '—' }}</p>
        <p><span class="text-slate-200 font-medium">Popis:</span> {{ event.description || event.short || '—' }}</p>
      </div>

      <div class="mt-5 flex flex-wrap gap-2">
        <button class="actionbtn" @click="addToCalendar">Pridať do kalendára</button>

        <button class="actionbtn" :disabled="favorites.loading || !event" @click="toggleFavorite(event.id)">
          {{ event && favorites.isFavorite(event.id) ? 'Odobrať z obľúbených' : 'Pridať medzi obľúbené' }}
        </button>
      </div>
    </section>
  </div>
</template>

<script>
import api from '../services/api'
import { useFavoritesStore } from '../stores/favorites'

export default {
  name: 'EventDetailView',
  data() {
    return {
      event: null,
      loading: true,
      error: null,
      favorites: useFavoritesStore(),
    }
  },
  computed: {
    eventId() {
      return this.$route.params.id
    },
  },
  methods: {
    async fetchEvent() {
      this.loading = true
      this.error = null

      try {
        const res = await api.get(`/events/${this.eventId}`)
        this.event = res.data
      } catch (err) {
        this.error = err?.message || 'Nepodarilo sa načítať detail.'
      } finally {
        this.loading = false
      }
    },

    async toggleFavorite(eventId) {
      return this.favorites.toggle(eventId)
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

    addToCalendar() {
      alert('MVP: neskôr napojíme na kalendár.')
    },
  },

  async created() {
    await this.fetchEvent()
    await this.favorites.fetch()
  },

  watch: {
    async '$route.params.id'() {
      await this.fetchEvent()
      await this.favorites.fetch()
    },
  },
}
</script>

<style scoped>
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
.actionbtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ⭐ */
.favbtn {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 999px;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.5);
  color: rgb(199, 210, 254);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  font-size: 1.15rem;
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
