<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { blogPosts } from '@/services/blogPosts'

const loading = ref(false)
const error = ref('')
const data = ref(null)
const page = ref(1)
const tags = ref([])
const selectedTag = ref('')
const search = ref('')
const searchInput = ref('')

const categoryCards = [
  {
    id: 'basics',
    title: 'Zaklady astronomie',
    description: 'Pojmy, orientacia na oblohe a prve kroky pre zaciatocnikov.',
    icon: 'ZA',
    tagHint: '',
  },
  {
    id: 'observing',
    title: 'Pozorovanie',
    description: 'Prakticke tipy na pozorovanie v meste aj mimo svetelneho smogu.',
    icon: 'PO',
    tagHint: '',
  },
  {
    id: 'moon-planets',
    title: 'Mesiac a planety',
    description: 'Kedy a ako sledovat planety, fazy mesiaca a najzaujimavejsie ukazy.',
    icon: 'MP',
    tagHint: '',
  },
  {
    id: 'deep-sky',
    title: 'Deep sky',
    description: 'Galaxie, hmloviny a hviezdokopy. Ako ich najst a pozorovat.',
    icon: 'DS',
    tagHint: '',
  },
  {
    id: 'gear',
    title: 'Technika',
    description: 'Vyber dalekohladu, okulare, montaz a zaklady astrofotografie.',
    icon: 'TE',
    tagHint: '',
  },
  {
    id: 'faq',
    title: 'FAQ',
    description: 'Najcastejsie otazky komunity a kratke odpovede na jednom mieste.',
    icon: 'FAQ',
    tagHint: '',
  },
]

const featuredPost = computed(() => {
  if (!data.value || page.value !== 1) return null
  return data.value.data?.[0] || null
})

const listPosts = computed(() => {
  if (!data.value) return []
  if (page.value === 1) return data.value.data?.slice(1) || []
  return data.value.data || []
})

const hasAnyPosts = computed(() => {
  return Boolean((data.value?.data || []).length)
})

function setMeta({ title, description }) {
  if (typeof document === 'undefined') return

  document.title = title

  const ensure = (name, property) => {
    let tag = document.querySelector(`meta[${property ? 'property' : 'name'}='${name}']`)
    if (!tag) {
      tag = document.createElement('meta')
      tag.setAttribute(property ? 'property' : 'name', name)
      document.head.appendChild(tag)
    }

    return tag
  }

  ensure('description', false).setAttribute('content', description)
  ensure('og:title', true).setAttribute('content', title)
  ensure('og:description', true).setAttribute('content', description)
}

function formatDate(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('sk-SK', { dateStyle: 'long' })
}

function excerpt(text, limit = 180) {
  if (!text) return ''
  const cleaned = String(text).replace(/\s+/g, ' ').trim()
  if (cleaned.length <= limit) return cleaned
  return `${cleaned.slice(0, limit).trim()}...`
}

function readTime(text) {
  if (!text) return '1 min citania'
  const words = String(text).trim().split(/\s+/).filter(Boolean).length
  const minutes = Math.max(1, Math.round(words / 220))
  return `${minutes} min citania`
}

function highlight(text) {
  if (!text) return ''
  if (!search.value) return text
  const safe = search.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const re = new RegExp(`(${safe})`, 'gi')
  return text.replace(re, '<mark>$1</mark>')
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    data.value = await blogPosts.listPublic({
      page: page.value,
      tag: selectedTag.value || undefined,
      q: search.value || undefined,
    })
  } catch (e) {
    error.value = e?.response?.data?.message || 'Chyba pri nacitani clankov.'
  } finally {
    loading.value = false
  }
}

async function loadTags() {
  try {
    tags.value = await blogPosts.listTagsPublic()
  } catch {
    tags.value = []
  }
}

function selectTag(slug) {
  selectedTag.value = slug
  page.value = 1
  load()
}

function applySearch() {
  search.value = searchInput.value.trim()
  page.value = 1
  load()
}

function clearSearch() {
  search.value = ''
  searchInput.value = ''
  page.value = 1
  load()
}

function prevPage() {
  if (!data.value || page.value <= 1) return
  page.value -= 1
  load()
}

function nextPage() {
  if (!data.value || page.value >= data.value.last_page) return
  page.value += 1
  load()
}

function openCategory(card) {
  if (card?.tagHint) {
    selectTag(card.tagHint)
  }
}

watch(
  () => [selectedTag.value, search.value],
  () => {
    const tagLabel = selectedTag.value
      ? ` - ${tags.value.find((t) => t.slug === selectedTag.value)?.name || 'Tag'}`
      : ''
    const searchLabel = search.value ? ` - Hladanie: ${search.value}` : ''

    setMeta({
      title: `Vzdelavanie${tagLabel}${searchLabel} | Astrokomunita`,
      description: 'Learning hub s clankami o astronomii, pozorovani a nocej oblohe.',
    })
  },
  { immediate: true },
)

onMounted(() => {
  load()
  loadTags()
})
</script>

<template>
  <section class="mx-auto max-w-7xl space-y-6">
    <header class="relative overflow-hidden rounded-3xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[radial-gradient(circle_at_12%_18%,rgb(var(--color-primary-rgb)/0.3),transparent_48%),linear-gradient(120deg,rgb(var(--color-bg-rgb)/0.92),rgb(var(--color-bg-rgb)/0.72))] p-6 sm:p-8">
      <div class="max-w-3xl space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Learning Hub</p>
        <h1 class="text-3xl font-extrabold text-[var(--color-surface)] sm:text-4xl">Vzdelavanie</h1>
        <p class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] sm:text-base">
          Miesto, kde sa naucis zaklady astronomie, prakticke pozorovanie a objavis kvalitne clanky pre dalsi krok.
        </p>
      </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <article v-for="card in categoryCards" :key="card.id" class="group rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.16)] bg-[color:rgb(var(--color-bg-rgb)/0.5)] p-4 transition hover:-translate-y-0.5 hover:bg-[color:rgb(var(--color-bg-rgb)/0.72)]">
        <div class="flex items-start justify-between gap-3">
          <span class="grid h-10 w-10 place-items-center rounded-xl border border-[color:rgb(var(--color-primary-rgb)/0.4)] bg-[color:rgb(var(--color-primary-rgb)/0.16)] text-xs font-bold text-[var(--color-surface)]">{{ card.icon }}</span>
          <button type="button" class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.32)] px-2.5 py-1 text-xs font-semibold text-[var(--color-surface)]" @click="openCategory(card)">
            Otvorit
          </button>
        </div>
        <h2 class="mt-3 text-lg font-semibold text-[var(--color-surface)]">{{ card.title }}</h2>
        <p class="mt-2 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">{{ card.description }}</p>
      </article>
    </section>

    <section class="space-y-4 rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.44)] p-4 sm:p-5">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div v-if="tags.length" class="flex flex-wrap gap-2">
          <button class="rounded-full border px-3 py-1.5 text-xs font-semibold" :class="!selectedTag ? 'border-[color:rgb(var(--color-primary-rgb)/0.5)] bg-[color:rgb(var(--color-primary-rgb)/0.18)] text-[var(--color-surface)]' : 'border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] text-[var(--color-surface)]'" @click="selectTag('')">Vsetko</button>
          <button
            v-for="tag in tags"
            :key="tag.id"
            class="rounded-full border px-3 py-1.5 text-xs font-semibold"
            :class="selectedTag === tag.slug ? 'border-[color:rgb(var(--color-primary-rgb)/0.5)] bg-[color:rgb(var(--color-primary-rgb)/0.18)] text-[var(--color-surface)]' : 'border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] text-[var(--color-surface)]'"
            @click="selectTag(tag.slug)"
          >
            {{ tag.name }}
          </button>
        </div>

        <div class="flex flex-1 flex-wrap gap-2 lg:justify-end">
          <input
            v-model="searchInput"
            type="text"
            placeholder="Hladat temu"
            class="min-w-[180px] flex-1 rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.65)] px-3 py-2 text-sm text-[var(--color-surface)] placeholder:text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]"
            @keyup.enter="applySearch"
          />
          <button class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-3 py-2 text-xs font-semibold text-[var(--color-surface)]" @click="applySearch">Hladat</button>
          <button class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-3 py-2 text-xs font-semibold text-[var(--color-surface)] disabled:opacity-60" :disabled="!search" @click="clearSearch">Reset</button>
        </div>
      </div>

      <div v-if="error" class="rounded-xl border border-[color:rgb(var(--color-danger-rgb)/0.5)] bg-[color:rgb(var(--color-danger-rgb)/0.1)] px-4 py-3 text-sm text-[var(--color-surface)]">{{ error }}</div>

      <div v-if="loading" class="space-y-2">
        <div v-for="row in 5" :key="`learn-loading-${row}`" class="h-12 animate-pulse rounded-lg bg-[color:rgb(var(--color-text-secondary-rgb)/0.15)]"></div>
      </div>

      <template v-else>
        <article v-if="featuredPost" class="overflow-hidden rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.16)] bg-[color:rgb(var(--color-bg-rgb)/0.54)] lg:grid lg:grid-cols-[1.2fr_1fr]">
          <div v-if="featuredPost.cover_image_url" class="min-h-56 bg-cover bg-center" :style="{ backgroundImage: `url(${featuredPost.cover_image_url})` }"></div>
          <div class="space-y-3 p-5">
            <p class="text-xs uppercase tracking-[0.18em] text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">Featured</p>
            <h2 class="text-xl font-bold text-[var(--color-surface)] sm:text-2xl">
              <router-link :to="`/learn/${featuredPost.slug || featuredPost.id}`" class="hover:text-[var(--color-primary)]">{{ featuredPost.title }}</router-link>
            </h2>
            <p class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" v-html="highlight(excerpt(featuredPost.content, 260))"></p>
            <div class="flex flex-wrap gap-2 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
              <span>{{ formatDate(featuredPost.published_at) }}</span>
              <span>•</span>
              <span>{{ featuredPost.user?.name || 'Redakcia' }}</span>
              <span>•</span>
              <span>{{ readTime(featuredPost.content) }}</span>
            </div>
            <router-link :to="`/learn/${featuredPost.slug || featuredPost.id}`" class="inline-flex rounded-xl border border-[color:rgb(var(--color-primary-rgb)/0.5)] bg-[color:rgb(var(--color-primary-rgb)/0.18)] px-3 py-1.5 text-sm font-semibold text-[var(--color-surface)]">
              Otvorit clanok
            </router-link>
          </div>
        </article>

        <div v-if="listPosts.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="post in listPosts" :key="post.id" class="flex h-full flex-col rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.16)] bg-[color:rgb(var(--color-bg-rgb)/0.54)] p-4">
            <p class="text-xs uppercase tracking-[0.15em] text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">Learning</p>
            <h3 class="mt-2 text-lg font-semibold text-[var(--color-surface)]">
              <router-link :to="`/learn/${post.slug || post.id}`" class="hover:text-[var(--color-primary)]">{{ post.title }}</router-link>
            </h3>
            <p class="mt-2 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" v-html="highlight(excerpt(post.content))"></p>
            <div class="mt-auto pt-3 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.85)]">
              {{ formatDate(post.published_at) }} • {{ readTime(post.content) }}
            </div>
          </article>
        </div>

        <div v-if="!hasAnyPosts" class="rounded-2xl border border-dashed border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.35)] p-8 text-center">
          <p class="text-base font-semibold text-[var(--color-surface)]">Obsah pripravujeme</p>
          <p class="mt-1 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Pracujeme na novych temach. Zatial skus vyhladavanie alebo sa vrat neskor.</p>
        </div>
      </template>

      <div v-if="data" class="flex flex-col gap-2 rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.38)] p-3 text-sm sm:flex-row sm:items-center sm:justify-between">
        <p class="text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">Strana {{ data.current_page }} z {{ data.last_page }}</p>
        <div class="flex gap-2">
          <button class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-3 py-1.5 text-xs font-semibold text-[var(--color-surface)] disabled:opacity-50" :disabled="loading || page <= 1" @click="prevPage">Predosla</button>
          <button class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] px-3 py-1.5 text-xs font-semibold text-[var(--color-surface)] disabled:opacity-50" :disabled="loading || page >= data.last_page" @click="nextPage">Dalsia</button>
        </div>
      </div>
    </section>
  </section>
</template>

<style scoped>
mark {
  background: rgb(var(--color-primary-rgb) / 0.3);
  color: inherit;
  border-radius: 3px;
  padding: 0 2px;
}
</style>

