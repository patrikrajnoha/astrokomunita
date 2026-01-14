<template>
  <section class="card">
    <div class="feedHeader">
      <div class="feedTitle">{{ title }}</div>
      <button class="btn ghost" :disabled="loading" @click="load(true)">
        {{ loading ? 'Načítavam…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading && items.length === 0" class="muted">Načítavam…</div>
    <div v-if="err" class="msg err">{{ err }}</div>

    <div class="list">
      <article v-for="p in items" :key="p.id" class="post">
        <div class="avatar sm"><span>{{ getInitials(p.user?.name) }}</span></div>

        <div class="body">
          <div class="meta">
            <div class="name">{{ p.user?.name ?? 'User' }}</div>
            <div class="dot">•</div>
            <div class="time">{{ formatTime(p.created_at) }}</div>
            <div v-if="p.user?.location" class="dot">•</div>
            <div v-if="p.user?.location" class="loc">📍 {{ p.user.location }}</div>
          </div>

          <div class="content">{{ p.content }}</div>
        </div>
      </article>
    </div>

    <div class="moreBar">
      <button v-if="nextPageUrl" class="btn outline" :disabled="loading" @click="load(false)">
        {{ loading ? 'Načítavam…' : 'Načítať viac' }}
      </button>
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { http } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  scope: { type: String, default: 'all' }, // 'all' | 'me'
})

const auth = useAuthStore()

const items = reactive([])
const nextPageUrl = ref(null)
const loading = ref(false)
const err = ref('')

const title = computed(() => (props.scope === 'me' ? 'Moje príspevky' : 'Feed'))

function getInitials(name) {
  const n = name || ''
  const parts = n.trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] || 'U'
  const b = parts[1]?.[0] || ''
  return (a + b).toUpperCase()
}

function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString()
}

async function load(reset = true) {
  loading.value = true
  err.value = ''

  try {
    const url = reset ? `/api/posts${props.scope === 'me' ? '?scope=me' : ''}` : nextPageUrl.value
    if (!url) return

    // pri scope=me sa môže stať 401, ak user nie je prihlásený
    const { data } = await http.get(url)
    const rows = data?.data ?? []

    if (reset) items.splice(0, items.length, ...rows)
    else items.push(...rows)

    nextPageUrl.value = data?.next_page_url ?? null
  } catch (e) {
    const status = e?.response?.status
    if (status === 401 && props.scope === 'me') {
      err.value = 'Pre zobrazenie tvojich príspevkov sa prihlás.'
    } else {
      err.value = e?.response?.data?.message || 'Načítanie zlyhalo.'
    }
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!auth.initialized) await auth.fetchUser()
  await load(true)
})

// umožníme parentovi refresh po publikovaní
defineExpose({ load })
</script>

<style scoped>
.card {
  border: 1px solid rgba(51, 65, 85, 0.75);
  border-radius: 1.25rem;
  background: rgba(2, 6, 23, 0.55);
  padding: 1rem;
  margin-top: 1rem;
}
.muted { color: rgb(148 163 184); }

.feedHeader { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.feedTitle { font-weight: 900; color: rgb(226 232 240); }

.list { display: grid; }
.post {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: 0.75rem;
  padding: 0.85rem 0.25rem;
  border-top: 1px solid rgba(51, 65, 85, 0.45);
}
.post:first-child { border-top: 0; }

.avatar.sm {
  width: 40px; height: 40px; border-radius: 999px;
  display: grid; place-items: center;
  border: 1px solid rgba(99, 102, 241, 0.6);
  background: rgba(99, 102, 241, 0.12);
  color: white; font-weight: 900; font-size: 0.9rem;
}

.meta { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; color: rgb(148 163 184); font-size: 0.9rem; }
.name { color: rgb(226 232 240); font-weight: 800; }
.dot { opacity: 0.6; }
.content { margin-top: 0.25rem; color: rgb(226 232 240); white-space: pre-wrap; }

.moreBar { display: flex; justify-content: center; margin-top: 1rem; }

.btn {
  padding: 0.6rem 0.95rem;
  border-radius: 999px;
  border: 1px solid rgba(99, 102, 241, 0.85);
  background: rgba(99, 102, 241, 0.15);
  color: white;
  font-weight: 800;
}
.btn:hover { background: rgba(99, 102, 241, 0.25); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn.ghost {
  border-color: rgba(51, 65, 85, 0.95);
  background: rgba(15, 23, 42, 0.2);
  color: rgb(203 213 225);
}
.btn.outline {
  background: rgba(15, 23, 42, 0.2);
  border-color: rgba(51, 65, 85, 0.85);
  color: rgb(226 232 240);
}

.msg { margin-top: 0.75rem; padding: 0.6rem 0.8rem; border-radius: 1rem; font-size: 0.95rem; }
.msg.err { border: 1px solid rgba(239, 68, 68, 0.45); background: rgba(239, 68, 68, 0.1); color: rgb(254 202 202); }
</style>
