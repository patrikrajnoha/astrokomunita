<template>
  <div class="page">
    <!-- Top bar -->
    <header class="topbar">
      <button class="iconBtn" @click="goHome">←</button>
      <div class="topmeta">
        <div class="topname">{{ auth.user?.name ?? 'Profil' }}</div>
        <div class="topsmall">{{ stats.favorites }} obľúbených</div>
      </div>
    </header>

    <div v-if="!auth.initialized" class="card muted">Načítavam profil…</div>

    <template v-else>
      <div v-if="!auth.user" class="card err">Nie si prihlásený.</div>

      <template v-else>
        <!-- Cover + avatar -->
        <section class="profileShell">
          <div class="cover">
            <div class="coverGlow"></div>
          </div>

          <div class="profileHead">
            <div class="avatar">
              <span>{{ initials }}</span>
            </div>

            <button class="btn outline" @click="goEdit">
              Upraviť profil
            </button>
          </div>

          <!-- Identity -->
          <div class="identity">
            <div class="nameRow">
              <h1 class="name">{{ auth.user.name }}</h1>
              <span v-if="auth.user.is_admin" class="badge">Admin</span>
            </div>

            <div class="handle">@{{ handle }}</div>

            <p v-if="auth.user.bio" class="bio">
              {{ auth.user.bio }}
            </p>
            <p v-else class="bio muted">
              Zatiaľ bez popisu. Klikni na „Upraviť profil“ a pridaj „O mne“.
            </p>

            <div class="meta">
              <span v-if="auth.user.location" class="metaItem">📍 {{ auth.user.location }}</span>
              <span class="metaItem">✉️ {{ auth.user.email }}</span>
              <span class="metaItem">ID: <span class="mono">{{ auth.user.id }}</span></span>
            </div>
          </div>

          <!-- Stats row -->
          <div class="statsRow">
            <div class="stat">
              <div class="statNum">{{ stats.favorites }}</div>
              <div class="statLabel">Obľúbené</div>
            </div>
            <div class="stat">
              <div class="statNum ok">Aktívny</div>
              <div class="statLabel">Stav</div>
            </div>
            <div class="stat">
              <div class="statNum">{{ auth.user.is_admin ? 'Admin' : 'User' }}</div>
              <div class="statLabel">Rola</div>
            </div>
          </div>

          <!-- Actions -->
          <div class="actionsBar">
            <button class="btn" :disabled="auth.loading" @click="logout">
              {{ auth.loading ? 'Odhlasujem…' : 'Odhlásiť sa' }}
            </button>
          </div>
        </section>

        <!-- MY POSTS -->
        <section class="feedShell">
          <div class="feedHeader">
            <div>
              <div class="feedTitle">Moje príspevky</div>
              <div class="feedSub">Zobrazuje len príspevky, ktoré si vytvoril ty.</div>
            </div>

            <button class="btn outline" :disabled="postsLoading" @click="loadMyPosts(true)">
              {{ postsLoading ? 'Načítavam…' : 'Refresh' }}
            </button>
          </div>

          <div v-if="postsErr" class="msg err">{{ postsErr }}</div>

          <div v-if="postsLoading && myPosts.length === 0" class="muted padTop">
            Načítavam tvoje príspevky…
          </div>

          <div v-else-if="!postsLoading && myPosts.length === 0" class="muted padTop">
            Zatiaľ si nič nepublikoval. Skús pridať príspevok na homepage.
          </div>

          <div class="postList" v-else>
            <article v-for="p in myPosts" :key="p.id" class="postItem">
              <div class="avatar sm">
                <span>{{ initials }}</span>
              </div>

              <div class="postBody">
                <div class="postMeta">
                  <div class="postName">{{ auth.user.name }}</div>
                  <div class="dot">•</div>
                  <div class="postTime">{{ fmt(p.created_at) }}</div>
                  <div v-if="auth.user.location" class="dot">•</div>
                  <div v-if="auth.user.location" class="postLoc">📍 {{ auth.user.location }}</div>
                </div>

                <div class="postContent">{{ p.content }}</div>
              </div>
            </article>
          </div>

          <div class="loadMore">
            <button
              v-if="myNextPageUrl"
              class="btn outline"
              :disabled="postsLoading"
              @click="loadMyPosts(false)"
            >
              {{ postsLoading ? 'Načítavam…' : 'Načítať viac' }}
            </button>
          </div>
        </section>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { http } from '@/lib/http'

const router = useRouter()
const auth = useAuthStore()

const stats = reactive({ favorites: '—' })

// --- my posts state ---
const myPosts = ref([])
const myNextPageUrl = ref(null)
const postsLoading = ref(false)
const postsErr = ref('')

const initials = computed(() => {
  const n = auth.user?.name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
})

// jednoduchý „handle“ z mena alebo emailu
const handle = computed(() => {
  const email = auth.user?.email || ''
  const base = email.split('@')[0] || auth.user?.name || 'user'
  return String(base).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
})

function goHome() {
  router.push({ name: 'home' })
}

function goEdit() {
  router.push({ name: 'profile.edit' })
}

function fmt(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return String(iso)
  }
}

async function loadStats() {
  try {
    const { data } = await http.get('/api/favorites')
    stats.favorites = Array.isArray(data) ? String(data.length) : String(data?.data?.length ?? '0')
  } catch {
    stats.favorites = '—'
  }
}

async function loadMyPosts(reset = true) {
  if (postsLoading.value) return
  postsLoading.value = true
  postsErr.value = ''

  try {
    const url = reset ? '/api/posts?scope=me' : myNextPageUrl.value
    if (!url) return

    const { data } = await http.get(url)
    const rows = data?.data ?? []

    if (reset) myPosts.value = rows
    else myPosts.value = [...myPosts.value, ...rows]

    myNextPageUrl.value = data?.next_page_url ?? null
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) postsErr.value = 'Pre zobrazenie tvojich príspevkov sa prihlás.'
    else postsErr.value = e?.response?.data?.message || 'Načítanie príspevkov zlyhalo.'
  } finally {
    postsLoading.value = false
  }
}

async function logout() {
  try {
    await auth.logout()
  } finally {
    router.push({ name: 'login' })
  }
}

onMounted(async () => {
  if (!auth.initialized) await auth.fetchUser()
  await loadStats()
  await loadMyPosts(true)
})
</script>

<style scoped>
.page {
  max-width: 760px;
  margin: 0 auto;
  padding: 0 1rem 2rem;
}

/* topbar */
.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgba(2, 6, 23, 0.72);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(51, 65, 85, 0.6);
  padding: 0.75rem 0.5rem;
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.iconBtn {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  border: 1px solid rgba(51, 65, 85, 0.8);
  background: rgba(15, 23, 42, 0.35);
  color: rgb(226 232 240);
}
.iconBtn:hover { border-color: rgba(99, 102, 241, 0.85); }

.topmeta { display: grid; line-height: 1.1; }
.topname { font-weight: 900; color: rgb(226 232 240); }
.topsmall { color: rgb(148 163 184); font-size: 0.85rem; }

/* profile shell */
.profileShell {
  border: 1px solid rgba(51, 65, 85, 0.75);
  border-radius: 1.25rem;
  overflow: hidden;
  margin-top: 1rem;
  background: rgba(2, 6, 23, 0.55);
}

.cover {
  height: 160px;
  position: relative;
  background:
    radial-gradient(900px 220px at 20% 20%, rgba(99, 102, 241, 0.25), transparent 60%),
    radial-gradient(700px 220px at 80% 30%, rgba(34, 197, 94, 0.12), transparent 60%),
    linear-gradient(180deg, rgba(15, 23, 42, 0.2), rgba(2, 6, 23, 0.9));
  border-bottom: 1px solid rgba(51, 65, 85, 0.6);
}
.coverGlow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,0.35), transparent 60%),
    radial-gradient(2px 2px at 70% 40%, rgba(255,255,255,0.25), transparent 60%),
    radial-gradient(2px 2px at 50% 70%, rgba(255,255,255,0.2), transparent 60%);
  opacity: 0.6;
}

.profileHead {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding: 0 1rem;
  transform: translateY(-28px);
}

.avatar {
  width: 88px;
  height: 88px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgba(2, 6, 23, 0.95);
  outline: 1px solid rgba(99, 102, 241, 0.55);
  background: rgba(99, 102, 241, 0.16);
  color: white;
  font-weight: 900;
  font-size: 1.25rem;
}
.avatar.sm {
  width: 44px;
  height: 44px;
  font-size: 0.95rem;
  border-width: 1px;
  outline: 1px solid rgba(99, 102, 241, 0.35);
}

/* identity */
.identity {
  padding: 0 1rem 1rem;
  margin-top: -18px;
}
.nameRow { display: flex; align-items: center; gap: 0.5rem; }
.name { margin: 0; font-size: 1.35rem; font-weight: 950; color: rgb(226 232 240); }
.badge {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgba(34, 197, 94, 0.55);
  background: rgba(34, 197, 94, 0.12);
  color: rgb(187 247 208);
}
.handle { color: rgb(148 163 184); margin-top: 0.15rem; }
.bio { margin: 0.75rem 0 0; color: rgb(226 232 240); }
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  margin-top: 0.75rem;
  color: rgb(148 163 184);
  font-size: 0.9rem;
}
.metaItem { white-space: nowrap; }
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

/* stats row */
.statsRow {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-top: 1px solid rgba(51, 65, 85, 0.55);
  border-bottom: 1px solid rgba(51, 65, 85, 0.55);
}
.stat { padding: 0.85rem 1rem; }
.statNum {
  font-weight: 950;
  font-size: 1.05rem;
  color: rgb(226 232 240);
}
.statNum.ok { color: rgb(167 243 208); }
.statLabel { color: rgb(148 163 184); font-size: 0.85rem; margin-top: 0.25rem; }

.actionsBar {
  padding: 1rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.btn {
  padding: 0.6rem 0.9rem;
  border-radius: 999px;
  border: 1px solid rgba(99, 102, 241, 0.85);
  background: rgba(99, 102, 241, 0.15);
  color: white;
  font-weight: 800;
}
.btn:hover { background: rgba(99, 102, 241, 0.25); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn.outline {
  background: rgba(15, 23, 42, 0.2);
  border-color: rgba(51, 65, 85, 0.85);
  color: rgb(226 232 240);
}
.btn.outline:hover { border-color: rgba(99, 102, 241, 0.85); }

.card {
  border: 1px solid rgba(51, 65, 85, 0.85);
  background: rgba(2, 6, 23, 0.55);
  border-radius: 1.25rem;
  padding: 1rem;
  margin-top: 1rem;
}
.muted { color: rgb(148 163 184); }
.err { color: rgb(254 202 202); }

/* ---- My posts section ---- */
.feedShell {
  margin-top: 1rem;
  border: 1px solid rgba(51, 65, 85, 0.75);
  border-radius: 1.25rem;
  background: rgba(2, 6, 23, 0.55);
  padding: 1rem;
}

.feedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}
.feedTitle {
  font-size: 1.05rem;
  font-weight: 950;
  color: rgb(226 232 240);
}
.feedSub {
  margin-top: 0.25rem;
  color: rgb(148 163 184);
  font-size: 0.9rem;
}

.padTop { margin-top: 0.75rem; }

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
.postItem:first-child { border-top: 0; }

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
  font-weight: 950;
}
.dot { opacity: 0.6; }
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

.msg {
  margin-top: 0.75rem;
  padding: 0.6rem 0.8rem;
  border-radius: 1rem;
  font-size: 0.95rem;
}
.msg.err {
  border: 1px solid rgba(239, 68, 68, 0.45);
  background: rgba(239, 68, 68, 0.1);
  color: rgb(254 202 202);
}
</style>
