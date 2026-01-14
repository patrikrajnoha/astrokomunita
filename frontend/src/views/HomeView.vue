<template>
  <div class="space-y-8">
    <!-- Header -->
    <header class="flex items-start justify-between gap-4">
      <div>
        <h1 class="title">
          Astro<span class="titleAccent">komunita</span>
        </h1>
        <p class="subtitle">
          Najbližšia astronomická udalosť a rýchly prístup k sekciám.
        </p>
      </div>

      <router-link class="pill" to="/events">
        Prejsť na udalosti <span class="pillArrow">→</span>
      </router-link>
    </header>

    <!-- HERO -->
    <section class="hero card">
      <div class="heroBg" aria-hidden="true"></div>

      <!-- Loading skeleton -->
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
        <div class="stateText">Skús pridať udalosť alebo sa pozri do kalendára.</div>
        <div class="pt-2 flex flex-wrap gap-2">
          <router-link class="actionbtn" to="/events/create">Pridať udalosť</router-link>
          <router-link class="ghostbtn" to="/calendar">Otvoriť kalendár</router-link>
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

    <!-- Quick links -->
    <section class="grid gap-3 sm:grid-cols-2">
      <router-link class="quickcard" to="/events">
        <div class="qcTop">
          <div class="qcIcon">🌌</div>
          <div class="qcArrow">→</div>
        </div>
        <div class="qcTitle">Udalosti</div>
        <div class="qcDesc">Prehľad všetkých javov, filtre a detaily.</div>
      </router-link>

      <router-link class="quickcard" to="/calendar">
        <div class="qcTop">
          <div class="qcIcon">📅</div>
          <div class="qcArrow">→</div>
        </div>
        <div class="qcTitle">Kalendár</div>
        <div class="qcDesc">Mesačný/týždenný prehľad a plánovanie.</div>
      </router-link>

      <router-link class="quickcard" to="/observations">
        <div class="qcTop">
          <div class="qcIcon">📸</div>
          <div class="qcArrow">→</div>
        </div>
        <div class="qcTitle">Pozorovania</div>
        <div class="qcDesc">Pridávaj fotky, poznámky a reporty.</div>
      </router-link>

      <router-link class="quickcard" to="/learn">
        <div class="qcTop">
          <div class="qcIcon">📚</div>
          <div class="qcArrow">→</div>
        </div>
        <div class="qcTitle">Vzdelávanie</div>
        <div class="qcDesc">Články, návody a vysvetlenia pojmov.</div>
      </router-link>
    </section>

    <!-- FEED: Composer (len pre prihlásených) -->
    <section v-if="auth?.isAuthed" class="card feedCard">
      <div class="feedHeader">
        <div>
          <div class="feedTitle">Zdieľaj pozorovanie</div>
          <div class="feedSub">Krátky príspevok do komunitného feedu (max 280 znakov).</div>
        </div>
      </div>

      <div class="composerRow">
        <div class="avatar">
          <span>{{ initials }}</span>
        </div>

        <div class="composerBody">
          <textarea
            v-model="postContent"
            class="feedInput"
            rows="3"
            maxlength="280"
            placeholder="Napíš pozorovanie…"
          ></textarea>

          <div class="composerBar">
            <div class="hint">{{ (postContent || '').length }}/280</div>

            <button class="actionbtn" :disabled="posting || !postContent.trim()" @click="createPost">
              {{ posting ? 'Publikujem…' : 'Publikovať' }}
            </button>
          </div>

          <div v-if="postErr" class="state stateError compact">
            <div class="stateText">{{ postErr }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- FEED: Global -->
    <section class="card feedCard">
      <div class="feedHeader">
        <div>
          <div class="feedTitle">Komunitný feed</div>
          <div class="feedSub">Najnovšie príspevky od používateľov.</div>
        </div>

        <button class="ghostbtn" :disabled="feedLoading" @click="fetchFeed(true)">
          {{ feedLoading ? 'Načítavam…' : 'Refresh' }}
        </button>
      </div>

      <div v-if="feedErr" class="state stateError compact">
        <div class="stateText">{{ feedErr }}</div>
      </div>

      <div v-if="feedLoading && posts.length === 0" class="muted mt-2">
        Načítavam feed…
      </div>

      <div class="postList">
        <article v-for="p in posts" :key="p.id" class="postItem">
          <div class="avatar sm">
            <span>{{ initialsOf(p?.user?.name) }}</span>
          </div>

          <div class="postBody">
            <div class="postMeta">
              <div class="postName">{{ p?.user?.name ?? 'User' }}</div>
              <div class="dot">•</div>
              <div class="postTime">{{ fmt(p?.created_at) }}</div>
              <div v-if="p?.user?.location" class="dot">•</div>
              <div v-if="p?.user?.location" class="postLoc">📍 {{ p.user.location }}</div>
            </div>

            <div class="postContent">{{ p.content }}</div>
          </div>
        </article>
      </div>

      <div class="loadMore">
        <button
          v-if="nextPageUrl"
          class="ghostbtn"
          :disabled="feedLoading"
          @click="fetchFeed(false)"
        >
          {{ feedLoading ? 'Načítavam…' : 'Načítať viac' }}
        </button>
      </div>
    </section>
  </div>
</template>

<script>
import api from '../services/api'
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'HomeView',
  data() {
    return {
      // hero
      nextEvent: null,
      loading: true,
      error: null,

      // auth
      auth: useAuthStore(),

      // feed
      posts: [],
      feedLoading: false,
      feedErr: null,
      nextPageUrl: null,

      // composer
      postContent: '',
      posting: false,
      postErr: null,
    }
  },
  computed: {
    initials() {
      const n = this.auth?.user?.name || ''
      const parts = n.trim().split(/\s+/).filter(Boolean)
      const a = parts[0]?.[0] || 'U'
      const b = parts[1]?.[0] || ''
      return (a + b).toUpperCase()
    },
  },
  methods: {
    // ----- HERO -----
    async fetchNextEvent() {
      this.loading = true
      this.error = null

      try {
        const res = await api.get('/events/next')
        this.nextEvent = res.data || null
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

    // ----- FEED helpers -----
    initialsOf(name) {
      const n = name || ''
      const parts = n.trim().split(/\s+/).filter(Boolean)
      const a = parts[0]?.[0] || 'U'
      const b = parts[1]?.[0] || ''
      return (a + b).toUpperCase()
    },

    fmt(iso) {
      if (!iso) return ''
      try {
        return new Date(iso).toLocaleString()
      } catch {
        return String(iso)
      }
    },

    // ----- FEED load -----
    async fetchFeed(reset = true) {
      if (this.feedLoading) return
      this.feedLoading = true
      this.feedErr = null

      try {
        const url = reset ? '/posts' : this.nextPageUrl
        if (!url) return

        // Laravel paginate() vracia { data: [...], next_page_url: ... }
        const res = await api.get(url)
        const payload = res.data || {}
        const rows = payload.data || []

        if (reset) this.posts = rows
        else this.posts = [...this.posts, ...rows]

        this.nextPageUrl = payload.next_page_url || null
      } catch (err) {
        this.feedErr =
          err?.response?.data?.message ||
          err?.message ||
          'Načítanie feedu zlyhalo.'
      } finally {
        this.feedLoading = false
      }
    },

    // ----- FEED create post -----
    async createPost() {
      this.postErr = null
      this.posting = true

      try {
        // ak máš v api service automaticky CSRF flow, ok.
        // ak nie, nechaj to zatiaľ, lebo router guard už často volá /me a CSRF máš v auth store inde.
        await api.post('/posts', { content: this.postContent })

        this.postContent = ''
        // refresh feed po publikovaní
        await this.fetchFeed(true)
      } catch (err) {
        const status = err?.response?.status
        if (status === 401) {
          this.postErr = 'Pre publikovanie sa prihlás.'
        } else if (status === 422) {
          this.postErr = 'Text musí mať 1–280 znakov.'
        } else {
          this.postErr =
            err?.response?.data?.message ||
            err?.message ||
            'Publikovanie zlyhalo.'
        }
      } finally {
        this.posting = false
      }
    },
  },
  async created() {
    this.fetchNextEvent()
    // globálny feed na homepage
    await this.fetchFeed(true)
  },
}
</script>

<style scoped>
/* Header */
.title {
  font-size: 1.875rem; /* text-3xl */
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

/* Common card look */
.card {
  position: relative;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.55);
  border-radius: 1.5rem;
  padding: 1.25rem;
  overflow: hidden;
}

/* Top pill link */
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

/* Hero */
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
  filter: blur(0px);
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

/* Buttons */
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

/* States */
.state {
  position: relative;
  padding: 0.25rem 0;
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
.stateError .stateTitle {
  color: rgb(254 202 202);
}
.state.compact .stateText {
  margin-top: 0;
}

/* Quick cards */
.quickcard {
  display: block;
  padding: 1rem 1rem;
  border-radius: 1.25rem;
  border: 1px solid rgb(51 65 85);
  background: rgba(15, 23, 42, 0.45);
  color: rgb(226 232 240);
  transition: transform 120ms ease, border-color 120ms ease, background 120ms ease;
}
.quickcard:hover {
  border-color: rgb(99 102 241);
  background: rgba(99, 102, 241, 0.06);
  transform: translateY(-1px);
}
.qcTop {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.qcIcon {
  font-size: 1.25rem;
}
.qcArrow {
  color: rgb(148 163 184);
}
.qcTitle {
  margin-top: 0.6rem;
  font-weight: 800;
  font-size: 1.05rem;
}
.qcDesc {
  margin-top: 0.25rem;
  color: rgb(148 163 184);
  font-size: 0.9rem;
  line-height: 1.4;
}

/* Skeleton */
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

/* --- FEED styles --- */
.feedCard {
  padding: 1.15rem;
}
.feedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}
.feedTitle {
  font-size: 1.05rem;
  font-weight: 900;
  color: rgb(226 232 240);
}
.feedSub {
  margin-top: 0.25rem;
  color: rgb(148 163 184);
  font-size: 0.9rem;
}

.composerRow {
  display: grid;
  grid-template-columns: 56px 1fr;
  gap: 0.85rem;
  margin-top: 0.85rem;
}
.composerBody {
  display: grid;
  gap: 0.5rem;
}

.avatar {
  width: 56px;
  height: 56px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 1px solid rgba(99, 102, 241, 0.6);
  background: rgba(99, 102, 241, 0.12);
  color: white;
  font-weight: 900;
  font-size: 1.05rem;
}
.avatar.sm {
  width: 44px;
  height: 44px;
  font-size: 0.95rem;
}

.feedInput {
  width: 100%;
  padding: 0.75rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid rgba(51, 65, 85, 0.9);
  background: rgba(2, 6, 23, 0.25);
  color: rgb(226 232 240);
  outline: none;
  resize: vertical;
}
.feedInput:focus {
  border-color: rgba(99, 102, 241, 0.9);
}

.composerBar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.hint {
  color: rgb(100 116 139);
  font-size: 0.85rem;
}

.postList {
  margin-top: 0.75rem;
  display: grid;
}
.postItem {
  display: grid;
  grid-template-columns: 56px 1fr;
  gap: 0.85rem;
  padding: 0.9rem 0.1rem;
  border-top: 1px solid rgba(51, 65, 85, 0.55);
}
.postItem:first-child {
  border-top: 0;
}
.postBody {
  display: grid;
}
.postMeta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  color: rgb(148 163 184);
  font-size: 0.9rem;
}
.postName {
  color: rgb(226 232 240);
  font-weight: 900;
}
.dot {
  opacity: 0.6;
}
.postContent {
  margin-top: 0.25rem;
  color: rgb(226 232 240);
  white-space: pre-wrap;
  line-height: 1.55;
}
.loadMore {
  display: flex;
  justify-content: center;
  padding-top: 0.75rem;
}
</style>
