<template>
  <div class="space-y-6">
    <header class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-indigo-400">Detail udalosti</h1>

        <div class="mt-2 flex items-center gap-2" v-if="event">
          <p class="text-slate-200 text-sm font-medium">
            {{ event.title }}
          </p>

          <span class="badge">{{ typeLabel(event.type) }}</span>

          <!-- ⭐ -->
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

      <router-link to="/events" class="text-indigo-300 hover:underline text-sm">
        ← späť na Udalosti
      </router-link>
    </header>

    <div v-if="loading" class="text-slate-300">Načítavam detail…</div>
    <div v-else-if="error" class="text-red-300">Chyba: {{ error }}</div>

    <section
      v-else
      class="rounded-2xl border border-slate-700 bg-slate-900/60 p-5"
    >
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold text-white">
            {{ event?.title || '—' }}
          </h2>
          <p class="mt-1 text-sm text-slate-400">
            ID: {{ event?.id ?? '—' }}
          </p>
        </div>

        <!-- ⭐ aj pri názve -->
        <button
          v-if="event"
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

      <!-- Hlavné info -->
      <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm text-slate-300">
        <div class="info">
          <div class="label">Typ</div>
          <div class="value">{{ typeLabel(event?.type) }}</div>
        </div>

        <div class="info">
          <div class="label">Viditeľnosť</div>
          <div class="value">{{ event?.visibility ?? '—' }}</div>
        </div>

        <div class="info">
          <div class="label">Max</div>
          <div class="value">{{ formatDateTime(event?.max_at) }}</div>
        </div>

        <div class="info">
          <div class="label">Začiatok</div>
          <div class="value">{{ formatDateTime(event?.start_at) }}</div>
        </div>

        <div class="info">
          <div class="label">Koniec</div>
          <div class="value">{{ formatDateTime(event?.end_at) }}</div>
        </div>

        <div class="info">
          <div class="label">Zdroj</div>
          <div class="value">
            <span v-if="event?.source?.name">{{ event.source.name }}</span>
            <span v-else>—</span>
          </div>
        </div>
      </div>

      <!-- Popis -->
      <div class="mt-4">
        <div class="label">Popis</div>
        <p class="mt-1 text-slate-200 text-sm leading-relaxed">
          {{ event?.description || event?.short || '—' }}
        </p>
      </div>

      <!-- Technické (UID/hash) -->
      <div v-if="event?.source?.uid || event?.source?.hash" class="mt-4">
        <div class="label">Technické</div>
        <div class="mt-1 text-xs text-slate-400 space-y-1">
          <div v-if="event?.source?.uid">
            <span class="text-slate-500">UID:</span>
            <span class="mono">{{ event.source.uid }}</span>
          </div>
          <div v-if="event?.source?.hash">
            <span class="text-slate-500">HASH:</span>
            <span class="mono">{{ event.source.hash }}</span>
          </div>
        </div>
      </div>

      <!-- Akcie -->
      <div class="mt-6 flex flex-wrap gap-2">
        <button class="actionbtn" @click="addToCalendar">
          Pridať do kalendára
        </button>

        <button
          class="actionbtn"
          :disabled="favorites.loading || !event"
          @click="toggleFavorite(event.id)"
        >
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
      return Number(this.$route.params.id)
    },
  },
  methods: {
    async fetchEvent() {
      this.loading = true
      this.error = null

      try {
        const res = await api.get(`/events/${this.eventId}`)

        // ✅ EventResource často vracia { data: {...} }
        const payload = res.data?.data ?? res.data
        this.event = payload
      } catch (err) {
        this.error = err?.response?.data?.message || err?.message || 'Nepodarilo sa načítať detail.'
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
      return map[type] || type || '—'
    },

    formatDateTime(value) {
      if (!value) return '—'
      const d = new Date(value)
      if (isNaN(d.getTime())) return String(value)
      return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
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

.badge {
  font-size: 0.75rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.15);
  color: rgb(199, 210, 254);
  border: 1px solid rgba(99, 102, 241, 0.35);
}

.label {
  color: rgb(148 163 184);
  font-size: 0.8rem;
  margin-bottom: 0.15rem;
}
.value {
  color: rgb(226 232 240);
}

.info {
  border: 1px solid rgba(51, 65, 85, 0.6);
  border-radius: 0.9rem;
  padding: 0.65rem 0.75rem;
  background: rgba(15, 23, 42, 0.35);
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  word-break: break-all;
}
</style>
