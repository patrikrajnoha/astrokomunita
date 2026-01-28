<template>
  <div class="space-y-8">
    <!-- Header -->
    <header class="flex items-start justify-between gap-4">
      <div>
        <h1 class="title">
          Astro<span class="titleAccent">komunita</span>
        </h1>
        <p class="subtitle">
          Najbližšia astronomická udalosť a komunitný feed.
        </p>
      </div>

      <router-link class="pill" to="/events">
        Udalosti <span class="pillArrow">→</span>
      </router-link>
    </header>

    <!-- HERO (najbližšia udalosť) -->
    <section class="hero card">
      <div class="heroBg" aria-hidden="true"></div>

      <!-- Loading -->
      <div v-if="loading" class="space-y-4">
        <div class="flex items-start justify-between gap-4">
          <div class="skeleton h-7 w-2/3"></div>
          <div class="skeleton h-6 w-20 rounded-full"></div>
        </div>
        <div class="skeleton h-5 w-56"></div>
        <div class="skeleton h-12 w-full"></div>
        <div class="skeleton h-4 w-72"></div>

        <div class="pt-2 flex flex-wrap gap-2">
          <div class="skeleton h-10 w-36 rounded-xl"></div>
          <div class="skeleton h-10 w-40 rounded-xl"></div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="state stateError">
        <div class="stateTitle">Nepodarilo sa načítať najbližšiu udalosť</div>
        <div class="stateText">{{ error }}</div>
        <button class="ghostbtn" @click="fetchNextEvent">Skúsiť znova</button>
      </div>

      <!-- Empty -->
      <div v-else-if="!nextEvent" class="state">
        <div class="stateTitle">Zatiaľ nemáme žiadnu budúcu udalosť</div>
        <div class="stateText">Pozri udalosti alebo pridaj novú.</div>
        <div class="pt-2 flex flex-wrap gap-2">
          <router-link class="actionbtn" to="/events/create">Pridať udalosť</router-link>
          <router-link class="ghostbtn" to="/events">Všetky udalosti</router-link>
        </div>
      </div>

      <!-- Content -->
      <div v-else class="space-y-4 relative">
        <div class="flex items-start justify-between gap-4">
          <div class="space-y-1">
            <div class="kicker">Najbližšie</div>
            <h2 class="eventTitle">{{ nextEvent.title }}</h2>
          </div>

          <span class="badge">
            {{ iconForType(nextEvent.type) }} {{ typeLabel(nextEvent.type) }}
          </span>
        </div>

        <div class="metaGrid">
          <div class="metaItem">
            <div class="metaLabel">Max</div>
            <div class="metaValue">{{ formatDateTime(nextEvent.max_at) }}</div>
          </div>

          <div class="metaItem">
            <div class="metaLabel">Viditeľnosť</div>
            <div class="metaValue">
              {{ nextEvent.visibility || '—' }}
            </div>
          </div>
        </div>

        <p class="eventDesc">
          {{ nextEvent.short || '—' }}
        </p>

        <div class="pt-2 flex flex-wrap gap-2">
          <router-link class="actionbtn" :to="`/events/${nextEvent.id}`">
            Detail udalosti
          </router-link>

          <router-link class="ghostbtn" to="/events">
            Všetky udalosti <span class="pillArrow">→</span>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Composer -->
    <PostComposer
      v-if="auth?.isAuthed"
      @created="onPostCreated"
    />

    <!-- Feed -->
    <FeedList ref="feed" />
  </div>
</template>

<script>
import api from '../services/api'
import { useAuthStore } from '@/stores/auth'
import PostComposer from '@/components/PostComposer.vue'
import FeedList from '@/components/FeedList.vue'

export default {
  name: 'HomeView',
  components: { PostComposer, FeedList },
  data() {
    return {
      auth: useAuthStore(),
      nextEvent: null,
      loading: true,
      error: null,
    }
  },
  methods: {
    // 🔧 FIX: robustné parsovanie response, plus detekcia prázdneho objektu
    async fetchNextEvent() {
      this.loading = true
      this.error = null
      this.nextEvent = null

      try {
        const res = await api.get('/events/next')
        const payload = res?.data

        // podporíme viac tvarov odpovede:
        // 1) event priamo: { id, title, ... }
        // 2) wrapper: { data: { ... } }
        // 3) wrapper: { event: { ... } }
        const ev = payload?.data ?? payload?.event ?? payload

        // ak je ev null/undefined alebo prázdny objekt -> berieme ako "žiadna udalosť"
        const isEmptyObject =
          ev && typeof ev === 'object' && !Array.isArray(ev) && Object.keys(ev).length === 0

        // ak ti backend náhodou vracia [] alebo {data: []}, tiež berieme ako empty
        const isEmptyArray = Array.isArray(ev) && ev.length === 0

        if (!ev || isEmptyObject || isEmptyArray) {
          this.nextEvent = null
        } else if (Array.isArray(ev)) {
          // keby náhodou prišiel array, zoberieme prvý
          this.nextEvent = ev[0] ?? null
        } else {
          // základná kontrola: keď nemá title/id, nechceme renderovať "— — —"
          if (!ev.title || !ev.id) {
            this.nextEvent = null
          } else {
            this.nextEvent = ev
          }
        }
      } catch (err) {
        this.error =
          err?.response?.data?.message ||
          err?.message ||
          'Nepodarilo sa načítať najbližšiu udalosť.'
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

    iconForType(type) {
      const map = {
        meteors: '✨',
        eclipse: '🌑',
        conjunction: '🪐',
        comet: '☄️',
      }
      return map[type] || '🔭'
    },

    formatDateTime(value) {
      if (!value) return '—'
      return value.replace('T', ' ').slice(0, 16)
    },

    onPostCreated(createdPost) {
      this.$refs.feed?.prepend?.(createdPost)
    },
  },
  created() {
    this.fetchNextEvent()
  },
}
</script>

<style scoped>
.title {
  font-size: 1.875rem;
  line-height: 1.2;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: rgb(226 232 240);
}
.titleAccent {
  color: rgb(129 140 248);
}
.subtitle {
  margin-top: 0.5rem;
  color: rgb(148 163 184);
}

.card {
  position: relative;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.55);
  border-radius: 1.5rem;
  padding: 1.25rem;
  overflow: hidden;
}

.pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 0.85rem;
  border-radius: 999px;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.35);
  color: rgb(226 232 240);
  font-size: 0.9rem;
  white-space: nowrap;
}
.pill:hover {
  border-color: rgb(99 102 241);
  background: rgba(99, 102, 241, 0.08);
}
.pillArrow {
  opacity: 0.85;
}

.hero {
  padding: 1.35rem;
}
.heroBg {
  position: absolute;
  inset: -2px;
  background:
    radial-gradient(900px 260px at 15% 10%, rgba(99, 102, 241, 0.22), transparent 60%),
    radial-gradient(700px 240px at 80% 0%, rgba(56, 189, 248, 0.16), transparent 55%),
    radial-gradient(900px 320px at 50% 120%, rgba(168, 85, 247, 0.12), transparent 60%);
  pointer-events: none;
}

.kicker {
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgb(148 163 184);
}
.eventTitle {
  font-size: 1.6rem;
  font-weight: 800;
  color: rgb(255 255 255);
  line-height: 1.15;
}

.badge {
  font-size: 0.78rem;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.14);
  color: rgb(199, 210, 254);
  border: 1px solid rgba(99, 102, 241, 0.35);
  white-space: nowrap;
}

.metaGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}
@media (max-width: 520px) {
  .metaGrid {
    grid-template-columns: 1fr;
  }
}

.metaItem {
  border: 1px solid rgba(51, 65, 85, 0.9);
  background: rgba(2, 6, 23, 0.25);
  border-radius: 1rem;
  padding: 0.75rem 0.85rem;
}
.metaLabel {
  font-size: 0.75rem;
  color: rgb(148 163 184);
}
.metaValue {
  margin-top: 0.2rem;
  font-weight: 700;
  color: rgb(226 232 240);
}

.eventDesc {
  color: rgb(226 232 240);
  opacity: 0.95;
  line-height: 1.55;
}

.actionbtn {
  padding: 0.6rem 0.9rem;
  border-radius: 0.9rem;
  border: 1px solid rgb(99 102 241);
  background: rgba(99, 102, 241, 0.16);
  color: white;
}
.actionbtn:hover {
  background: rgba(99, 102, 241, 0.28);
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
  background: rgba(99, 102, 241, 0.08);
}

.stateTitle {
  font-size: 1.1rem;
  font-weight: 800;
  color: rgb(226 232 240);
}
.stateText {
  margin-top: 0.35rem;
  color: rgb(148 163 184);
}
.stateError .stateTitle,
.stateError .stateText {
  color: rgb(254 202 202);
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgba(148, 163, 184, 0.08),
    rgba(148, 163, 184, 0.16),
    rgba(148, 163, 184, 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0.75rem;
}
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
