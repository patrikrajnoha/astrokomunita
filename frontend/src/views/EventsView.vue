<template>
  <div class="min-h-screen">
    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-br from-blue-900/20 via-purple-900/10 to-pink-900/20 backdrop-blur-sm">
      <div class="absolute inset-0 opacity-30" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiM5QzkyQUMiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDM0di00aC0ydjRoLTR2Mmg0djRoMnYtNGg0di0yaC00em0wLTMwVjBoLTJ2NGgtNHYyaDR2NGgyVjZoNFY0aC00ek02IDM0di00SDR2NEgwdjJoNHY0aDJ2LTRoNHYtMkg2ek02IDRWMFg0djRIMHYyaDR2NGgyVjZoNFY0SDZ6Ii8+PC9nPjwvZz48L3N2Zz4=');"></div>
      
      <div class="relative px-6 py-16 md:px-8">
        <div class="mx-auto max-w-4xl">
          <div class="text-center">
            <h1 class="bg-gradient-to-r from-blue-400 via-purple-400 to-pink-400 bg-clip-text text-4xl font-bold text-transparent md:text-6xl">
              Astronomické Udalosti
            </h1>
            <p class="mt-4 text-lg text-[var(--color-text-secondary)] md:text-xl">
              Objavujte fascinujúce vesmírne udalosti a astronomické úkazy
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <div class="px-6 py-8 md:px-8">
      <!-- Filter Section -->
      <section class="mb-8">
        <div class="flex flex-wrap justify-center gap-3">
          <button 
            class="filter-btn" 
            :class="{ active: selectedType === 'all' }" 
            @click="selectedType = 'all'"
          >
            <span class="flex items-center gap-2">
              <span class="icon">🌌</span>
              Všetky
            </span>
          </button>
          <button 
            class="filter-btn" 
            :class="{ active: selectedType === 'meteors' }" 
            @click="selectedType = 'meteors'"
          >
            <span class="flex items-center gap-2">
              <span class="icon">☄️</span>
              Meteorické roje
            </span>
          </button>
          <button 
            class="filter-btn" 
            :class="{ active: selectedType === 'eclipses' }" 
            @click="selectedType = 'eclipses'"
          >
            <span class="flex items-center gap-2">
              <span class="icon">🌑</span>
              Zatmenia
            </span>
          </button>
          <button 
            class="filter-btn" 
            :class="{ active: selectedType === 'conjunctions' }" 
            @click="selectedType = 'conjunctions'"
          >
            <span class="flex items-center gap-2">
              <span class="icon">⭐</span>
              Konjunkcie
            </span>
          </button>
          <button 
            class="filter-btn" 
            :class="{ active: selectedType === 'comets' }" 
            @click="selectedType = 'comets'"
          >
            <span class="flex items-center gap-2">
              <span class="icon">🌠</span>
              Kométy
            </span>
          </button>
        </div>
      </section>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center py-12">
        <div class="flex flex-col items-center gap-4">
          <div class="h-12 w-12 animate-spin rounded-full border-4 border-blue-500/20 border-t-blue-500"></div>
          <p class="text-[var(--color-text-secondary)]">Načítavam udalosti...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex justify-center py-12">
        <div class="rounded-2xl border border-red-500/20 bg-red-500/10 p-8 text-center">
          <div class="text-4xl mb-4">🚨</div>
          <h3 class="text-lg font-semibold text-red-400 mb-2">Chyba pri načítaní</h3>
          <p class="text-[var(--color-text-secondary)]">{{ error }}</p>
        </div>
      </div>

      <!-- Events Grid -->
      <section v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <router-link
          v-for="e in filteredEvents"
          :key="e.id"
          :to="`/events/${e.id}`"
          class="event-card group"
        >
          <div class="card-content">
            <!-- Card Header -->
            <div class="flex items-start justify-between gap-3 mb-4">
              <div class="flex-1">
                <h3 class="text-xl font-bold text-[var(--color-surface)] group-hover:text-blue-400 transition-colors">
                  {{ e.title }}
                </h3>
                <div class="flex items-center gap-2 mt-2">
                  <span class="type-badge">{{ typeLabel(e.type) }}</span>
                  <span class="text-xs text-[var(--color-text-secondary)]">
                    {{ formatDateTime(e.max_at) }}
                  </span>
                </div>
              </div>
              
              <button
                class="favorite-btn"
                type="button"
                :disabled="favorites.loading || !auth.isAuthed"
                :aria-pressed="favorites.isFavorite(e.id)"
                :title="auth.isAuthed ? (favorites.isFavorite(e.id) ? 'Odobrať z obľúbených' : 'Pridať do obľúbených') : 'Prihlás sa pre uloženie obľúbených'"
                @click.prevent.stop="toggleFavorite(e.id)"
              >
                <span class="text-xl">{{ favorites.isFavorite(e.id) ? '❤️' : '🤍' }}</span>
              </button>
            </div>

            <!-- Card Body -->
            <p class="text-[var(--color-text-secondary)] text-sm line-clamp-3 mb-4">
              {{ e.short || '—' }}
            </p>

            <!-- Card Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-[var(--color-text-secondary)]/20">
              <div class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)]">
                <span>👁️</span>
                <span>Viditeľnosť: {{ e.visibility ?? '—' }}</span>
              </div>
              <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                <span class="text-blue-400 text-sm font-medium">Zobraziť →</span>
              </div>
            </div>
          </div>
        </router-link>
      </section>

      <!-- Empty State -->
      <div v-if="!loading && !error && filteredEvents.length === 0" class="flex justify-center py-12">
        <div class="text-center">
          <div class="text-6xl mb-4">🔭</div>
          <h3 class="text-xl font-semibold text-[var(--color-surface)] mb-2">Žiadne udalosti</h3>
          <p class="text-[var(--color-text-secondary)]">V tejto kategórii sa nenašli žiadne udalosti.</p>
        </div>
      </div>
    </div>
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
        // ? Laravel paginator -> reálne položky sú v res.data.data
        this.events = Array.isArray(res.data?.data) ? res.data.data : []
      } catch (err) {
        console.error('Failed to fetch events:', err)
        
        // Konkrétne error handling podľa status kódu
        if (err?.response?.status === 429) {
          this.error = 'Príliš veľa požiadaviek. Skús to znova o chvíľu.'
        } else if (err?.response?.status >= 500) {
          this.error = 'Server je dočasne nedostupný. Skús to neskôr.'
        } else if (err?.response?.status === 404) {
          this.error = 'Udalosti neboli nájdené.'
        } else if (err?.code === 'NETWORK_ERROR') {
          this.error = 'Problém s pripojením. Skontroluj internetové pripojenie.'
        } else {
          this.error = err?.response?.data?.message || 'Nepodarilo sa načítať udalosti.'
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
/* CSS Custom Properties pre konzistentné hodnoty */
:root {
  --border-radius-sm: 0.75rem;
  --border-radius-md: 0.9rem;
  --border-radius-lg: 1rem;
  --border-radius-xl: 1.25rem;
  --border-radius-full: 9999px;
  --spacing-xs: 0.25rem;
  --spacing-sm: 0.5rem;
  --spacing-md: 0.75rem;
  --spacing-lg: 1rem;
  --spacing-xl: 1.5rem;
  --spacing-2xl: 2rem;
  --transition-fast: 0.15s;
  --transition-normal: 0.2s;
  --transition-slow: 0.3s;
}

/* Filter Buttons */
.filter-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-md) var(--spacing-xl);
  border-radius: var(--border-radius-full);
  border: 1px solid rgba(var(--color-text-secondary-rgb), 0.3);
  background: rgba(var(--color-bg-rgb), 0.6);
  color: var(--color-surface);
  font-weight: 500;
  transition: all var(--transition-slow) ease-out;
  backdrop-filter: blur(10px);
}

.filter-btn:hover {
  transform: scale(1.05);
  border-color: rgba(59, 130, 246, 0.5);
}

.filter-btn:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.filter-btn.active {
  border-color: rgba(59, 130, 246, 0.5);
  background: linear-gradient(to right, rgba(59, 130, 246, 0.2), rgba(147, 51, 234, 0.2));
  color: #93c5fd;
  box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2);
}

.filter-btn .icon {
  font-size: 1.125rem;
}

/* Event Cards */
.event-card {
  display: block;
  position: relative;
  overflow: hidden;
  border-radius: var(--border-radius-lg);
  border: 1px solid rgba(var(--color-text-secondary-rgb), 0.2);
  background: linear-gradient(to bottom right, rgba(var(--color-bg-rgb), 0.8), rgba(var(--color-bg-rgb), 0.4));
  backdrop-filter: blur(12px);
  transition: all var(--transition-slow) ease-out;
  text-decoration: none;
}

.event-card:hover {
  transform: scale(1.02);
  box-shadow: 0 25px 50px -12px rgba(59, 130, 246, 0.1);
  border-color: rgba(59, 130, 246, 0.3);
}

.event-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom right, rgba(59, 130, 246, 0.05), rgba(147, 51, 234, 0.05));
  opacity: 0;
  transition: opacity 0.3s;
}

.event-card:hover::before {
  opacity: 1;
}

.card-content {
  position: relative;
  padding: var(--spacing-xl);
}

/* Type Badge */
.type-badge {
  display: inline-flex;
  align-items: center;
  padding: var(--spacing-xs) var(--spacing-md);
  border-radius: var(--border-radius-full);
  font-size: 0.75rem;
  font-weight: 600;
  background: linear-gradient(to right, rgba(59, 130, 246, 0.2), rgba(147, 51, 234, 0.2));
  color: #93c5fd;
  border: 1px solid rgba(59, 130, 246, 0.3);
}

/* Favorite Button */
.favorite-btn {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  border: 1px solid rgba(var(--color-text-secondary-rgb), 0.3);
  background: rgba(var(--color-bg-rgb), 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-slow) ease-out;
  cursor: pointer;
}

.favorite-btn:hover:not(:disabled) {
  transform: scale(1.1);
  border-color: rgba(239, 68, 68, 0.5);
  background: rgba(239, 68, 68, 0.1);
}

.favorite-btn:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
}

.favorite-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: scale(1);
  border-color: rgba(var(--color-text-secondary-rgb), 0.3);
}

/* Line Clamp Utility */
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-clamp: 3; /* Štandardná definícia pre kompatibilitu */
}

/* Responsive Grid */
@media (max-width: 640px) {
  .event-card {
    border-radius: var(--border-radius-sm);
  }
  
  .card-content {
    padding: var(--spacing-lg);
  }
}

/* Loading Animation */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}

/* Glassmorphism Effects */
.backdrop-blur-md {
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}

/* Custom Scrollbar */
.event-card::-webkit-scrollbar {
  width: 6px;
}

.event-card::-webkit-scrollbar-track {
  background: transparent;
}

.event-card::-webkit-scrollbar-thumb {
  background: rgba(59, 130, 246, 0.3);
  border-radius: 3px;
}

.event-card::-webkit-scrollbar-thumb:hover {
  background: rgba(59, 130, 246, 0.5);
}
</style>
