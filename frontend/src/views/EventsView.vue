<template>
  <div class="events-page">
    <section class="hero">
      <div class="hero-noise" aria-hidden="true"></div>
      <div class="hero-orb hero-orb-a" aria-hidden="true"></div>
      <div class="hero-orb hero-orb-b" aria-hidden="true"></div>

      <div class="hero-inner">
        <p class="hero-kicker">Astronomy Feed</p>
        <h1 class="hero-title">Astronomicke Udalosti</h1>
        <p class="hero-subtitle">Objav udalosti na oblohe, filtruj podla typu a otvor detail jednym klikom.</p>
      </div>
    </section>

    <main class="content-wrap">
      <section class="filter-panel">
        <div class="filter-row" role="tablist" aria-label="Event type filters">
          <button class="filter-btn" :class="{ active: selectedType === 'all' }" @click="selectedType = 'all'">
            <span class="pill-icon">ALL</span>
            <span>Vsetky</span>
          </button>
          <button class="filter-btn" :class="{ active: selectedType === 'meteors' }" @click="selectedType = 'meteors'">
            <span class="pill-icon">MET</span>
            <span>Meteoricke roje</span>
          </button>
          <button class="filter-btn" :class="{ active: selectedType === 'eclipses' }" @click="selectedType = 'eclipses'">
            <span class="pill-icon">ECL</span>
            <span>Zatmenia</span>
          </button>
          <button class="filter-btn" :class="{ active: selectedType === 'conjunctions' }" @click="selectedType = 'conjunctions'">
            <span class="pill-icon">CNJ</span>
            <span>Konjunkcie</span>
          </button>
          <button class="filter-btn" :class="{ active: selectedType === 'comets' }" @click="selectedType = 'comets'">
            <span class="pill-icon">CMT</span>
            <span>Komety</span>
          </button>
        </div>
        <p class="filter-meta">Zobrazenych udalosti: <strong>{{ filteredEvents.length }}</strong></p>
      </section>

      <div v-if="loading" class="state-card">
        <div class="spinner" aria-hidden="true"></div>
        <h3>Nacitavam udalosti</h3>
        <p>Chvilu strpenia, data sa pripravuju.</p>
      </div>

      <div v-else-if="error" class="state-card state-error">
        <h3>Chyba pri nacitani</h3>
        <p>{{ error }}</p>
      </div>

      <section v-else class="events-grid">
        <router-link
          v-for="e in filteredEvents"
          :key="e.id"
          :to="`/events/${e.id}`"
          class="event-card group"
        >
          <div class="card-content">
            <div class="card-header">
              <div>
                <h3 class="card-title">{{ e.title }}</h3>
                <div class="meta-row">
                  <span class="type-badge">{{ typeLabel(e.type) }}</span>
                  <span class="card-date">{{ formatDateTime(e.max_at) }}</span>
                </div>
              </div>

              <button
                class="favorite-btn"
                type="button"
                :disabled="favorites.loading || !auth.isAuthed"
                :aria-pressed="favorites.isFavorite(e.id)"
                :title="auth.isAuthed ? (favorites.isFavorite(e.id) ? 'Odobrat z oblubenych' : 'Pridat do oblubenych') : 'Prihlas sa pre ulozenie oblubenych'"
                @click.prevent.stop="toggleFavorite(e.id)"
              >
                <span class="favorite-glyph">{{ favorites.isFavorite(e.id) ? 'ON' : 'OFF' }}</span>
              </button>
            </div>

            <p class="card-description">
              {{ e.short || '-' }}
            </p>

            <div class="card-footer">
              <div class="visibility">Viditelnost: {{ e.visibility ?? '-' }}</div>
              <div class="open-label">Zobrazit detail</div>
            </div>
          </div>
        </router-link>
      </section>

      <div v-if="!loading && !error && filteredEvents.length === 0" class="state-card state-empty">
        <h3>Ziadne udalosti</h3>
        <p>V tejto kategorii sa nenasli ziadne udalosti.</p>
      </div>
    </main>
  </div>
</template>

<script>
import api from '../services/api'
import { useFavoritesStore } from '../stores/favorites'
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'EventsView',
  data() {
    return {
      selectedType: 'all',
      events: [],
      loading: true,
      error: null,
      favorites: useFavoritesStore(),
      auth: useAuthStore(),
    }
  },
  computed: {
    filteredEvents() {
      if (this.selectedType === 'all') return this.events

      const groups = {
        meteors: ['meteors', 'meteor_shower'],
        eclipses: ['eclipse', 'eclipse_lunar', 'eclipse_solar'],
        conjunctions: ['conjunction', 'planetary_event'],
        comets: ['other'],
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
        this.events = Array.isArray(res.data?.data) ? res.data.data : []
      } catch (err) {
        console.error('Failed to fetch events:', err)

        if (err?.response?.status === 429) {
          this.error = 'Prilis vela poziadaviek. Skus to znova o chvilu.'
        } else if (err?.response?.status >= 500) {
          this.error = 'Server je docasne nedostupny. Skus to neskor.'
        } else if (err?.response?.status === 404) {
          this.error = 'Udalosti neboli najdene.'
        } else if (err?.code === 'NETWORK_ERROR') {
          this.error = 'Problem s pripojenim. Skontroluj internetove pripojenie.'
        } else {
          this.error = err?.response?.data?.message || 'Nepodarilo sa nacitat udalosti.'
        }
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
        meteor_shower: 'Meteory',
        eclipse: 'Zatmenie',
        eclipse_lunar: 'Zatmenie (L)',
        eclipse_solar: 'Zatmenie (S)',
        conjunction: 'Konjunkcia',
        planetary_event: 'Konjunkcia',
        other: 'Ine',
      }
      return map[type] || type
    },

    formatDateTime(value) {
      if (!value) return '-'
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
.events-page {
  min-height: 100vh;
}

.hero {
  position: relative;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
  border-radius: 1.2rem;
  background:
    radial-gradient(circle at 12% 8%, rgb(56 189 248 / 0.12), transparent 32%),
    radial-gradient(circle at 85% 20%, rgb(244 63 94 / 0.12), transparent 30%),
    linear-gradient(155deg, rgb(var(--color-bg-rgb) / 0.9), rgb(var(--color-bg-rgb) / 0.62));
}

.hero-noise {
  position: absolute;
  inset: 0;
  opacity: 0.2;
  background-image: radial-gradient(rgb(255 255 255 / 0.24) 1px, transparent 1px);
  background-size: 22px 22px;
}

.hero-orb {
  position: absolute;
  border-radius: 999px;
  filter: blur(34px);
}

.hero-orb-a {
  width: 180px;
  height: 180px;
  top: -30px;
  right: -24px;
  background: rgb(59 130 246 / 0.26);
}

.hero-orb-b {
  width: 140px;
  height: 140px;
  left: 18%;
  bottom: -40px;
  background: rgb(236 72 153 / 0.22);
}

.hero-inner {
  position: relative;
  max-width: 820px;
  margin: 0 auto;
  padding: 2.6rem 1.2rem 2.2rem;
  text-align: center;
}

.hero-kicker {
  margin: 0;
  font-size: 0.73rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-weight: 700;
}

.hero-title {
  margin: 0.75rem 0 0;
  font-size: clamp(1.9rem, 5vw, 3.4rem);
  line-height: 1.05;
  color: var(--color-surface);
  text-wrap: balance;
}

.hero-subtitle {
  margin: 0.9rem auto 0;
  max-width: 620px;
  font-size: 0.98rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.content-wrap {
  padding: 1.35rem 0.2rem 0;
}

.filter-panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  border-radius: 1rem;
  padding: 0.95rem;
  background: linear-gradient(155deg, rgb(var(--color-bg-rgb) / 0.8), rgb(var(--color-bg-rgb) / 0.6));
  box-shadow: 0 14px 36px rgb(2 6 23 / 0.18);
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.filter-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.48rem 0.72rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.66);
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 600;
  transition: transform 160ms ease, border-color 160ms ease, background-color 160ms ease;
}

.filter-btn:hover {
  transform: translateY(-1px);
  border-color: rgb(var(--color-primary-rgb) / 0.6);
}

.filter-btn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  background: linear-gradient(145deg, rgb(var(--color-primary-rgb) / 0.28), rgb(var(--color-bg-rgb) / 0.72));
  box-shadow: 0 8px 20px rgb(var(--color-primary-rgb) / 0.2);
}

.pill-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.15rem;
  padding: 0.14rem 0.3rem;
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.85);
  font-size: 0.64rem;
  letter-spacing: 0.08em;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.filter-meta {
  margin: 0.72rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 0.84rem;
}

.state-card {
  margin-top: 1rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  padding: 1.1rem;
  background: rgb(var(--color-bg-rgb) / 0.66);
  text-align: center;
}

.state-card h3 {
  margin: 0;
  font-size: 1.02rem;
  color: var(--color-surface);
}

.state-card p {
  margin: 0.4rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.state-error {
  border-color: rgb(251 113 133 / 0.45);
  background: rgb(190 24 93 / 0.12);
}

.state-empty {
  margin-bottom: 1rem;
}

.spinner {
  width: 2rem;
  height: 2rem;
  margin: 0 auto 0.6rem;
  border-radius: 999px;
  border: 3px solid rgb(var(--color-primary-rgb) / 0.25);
  border-top-color: rgb(var(--color-primary-rgb) / 0.95);
  animation: spin 1s linear infinite;
}

.events-grid {
  margin-top: 1rem;
  display: grid;
  gap: 0.85rem;
  grid-template-columns: repeat(auto-fill, minmax(255px, 1fr));
}

.event-card {
  position: relative;
  overflow: hidden;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: linear-gradient(170deg, rgb(var(--color-bg-rgb) / 0.82), rgb(var(--color-bg-rgb) / 0.62));
  text-decoration: none;
  transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.event-card:hover {
  transform: translateY(-2px);
  border-color: rgb(var(--color-primary-rgb) / 0.45);
  box-shadow: 0 16px 36px rgb(var(--color-primary-rgb) / 0.14);
}

.card-content {
  padding: 0.95rem;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.7rem;
}

.card-title {
  margin: 0;
  font-size: 1.02rem;
  line-height: 1.24;
  color: var(--color-surface);
}

.meta-row {
  margin-top: 0.45rem;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.type-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.38);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: rgb(191 219 254);
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
}

.card-date {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.74rem;
}

.favorite-btn {
  width: 2.05rem;
  height: 2.05rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.84);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: border-color 150ms ease, transform 150ms ease;
}

.favorite-btn:hover:not(:disabled) {
  transform: scale(1.06);
  border-color: rgb(244 63 94 / 0.62);
}

.favorite-btn:disabled {
  opacity: 0.52;
  cursor: not-allowed;
}

.favorite-glyph {
  font-size: 0.62rem;
  letter-spacing: 0.08em;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.card-description {
  margin: 0.72rem 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
  font-size: 0.84rem;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-footer {
  margin-top: 0.9rem;
  padding-top: 0.6rem;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
}

.visibility {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.73rem;
}

.open-label {
  color: rgb(var(--color-primary-rgb) / 0.9);
  font-size: 0.74rem;
  font-weight: 700;
  opacity: 0;
  transform: translateX(-4px);
  transition: opacity 150ms ease, transform 150ms ease;
}

.event-card:hover .open-label {
  opacity: 1;
  transform: translateX(0);
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 640px) {
  .hero {
    border-radius: 0.9rem;
  }

  .hero-inner {
    padding: 1.7rem 0.9rem 1.55rem;
  }

  .content-wrap {
    padding-top: 0.9rem;
  }

  .filter-panel {
    padding: 0.72rem;
  }

  .filter-row {
    gap: 0.45rem;
  }

  .filter-btn {
    padding: 0.42rem 0.62rem;
    font-size: 0.78rem;
  }

  .events-grid {
    gap: 0.7rem;
    grid-template-columns: 1fr;
  }
}
</style>
