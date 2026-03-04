<template>
  <RouterLink
    :to="to"
    class="group block rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.62)] p-4 shadow-sm transition hover:-translate-y-px hover:bg-[color:rgb(var(--color-bg-rgb)/0.78)] hover:shadow-[0_10px_20px_rgb(0_0_0/0.16)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgb(var(--color-primary-rgb)/0.34)]"
  >
    <div class="flex items-start gap-3">
      <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.88)] text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            v-for="(path, index) in iconPaths"
            :key="`${kind}-${index}`"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.8"
            :d="path"
          />
        </svg>
      </span>

      <div class="min-w-0 flex-1 space-y-1.5">
        <p class="text-sm font-semibold text-[var(--color-surface)]">
          <template v-for="(part, index) in titleParts" :key="`title-${index}`">
            <mark
              v-if="part.match"
              class="rounded-sm bg-[color:rgb(var(--color-primary-rgb)/0.26)] px-0.5 text-[var(--color-surface)]"
            >
              {{ part.text }}
            </mark>
            <span v-else>{{ part.text }}</span>
          </template>
        </p>

        <p v-if="excerpt" class="line-clamp-2 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.94)]">
          <template v-for="(part, index) in excerptParts" :key="`excerpt-${index}`">
            <mark
              v-if="part.match"
              class="rounded-sm bg-[color:rgb(var(--color-primary-rgb)/0.24)] px-0.5 text-[color:rgb(var(--color-text-secondary-rgb)/0.98)]"
            >
              {{ part.text }}
            </mark>
            <span v-else>{{ part.text }}</span>
          </template>
        </p>

        <p v-if="meta" class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.86)]">{{ meta }}</p>
      </div>
    </div>
  </RouterLink>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  to: {
    type: [String, Object],
    required: true,
  },
  kind: {
    type: String,
    default: 'post',
  },
  title: {
    type: String,
    default: '',
  },
  excerpt: {
    type: String,
    default: '',
  },
  meta: {
    type: String,
    default: '',
  },
  query: {
    type: String,
    default: '',
  },
})

const escapeRegExp = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const toParts = (text, query) => {
  const source = String(text || '')
  const needle = String(query || '').trim()

  if (!source || !needle) {
    return [{ text: source, match: false }]
  }

  const matcher = new RegExp(`(${escapeRegExp(needle)})`, 'ig')
  const chunks = source.split(matcher)
  return chunks
    .filter((chunk) => chunk !== '')
    .map((chunk) => ({
      text: chunk,
      match: chunk.toLowerCase() === needle.toLowerCase(),
    }))
}

const titleParts = computed(() => toParts(props.title, props.query))
const excerptParts = computed(() => toParts(props.excerpt, props.query))

const iconPaths = computed(() => {
  if (props.kind === 'user') {
    return [
      'M12 13.5a4 4 0 100-8 4 4 0 000 8z',
      'M5 20a7 7 0 0114 0',
    ]
  }

  if (props.kind === 'event') {
    return [
      'M8 2v3',
      'M16 2v3',
      'M4 9h16',
      'M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
    ]
  }

  if (props.kind === 'article') {
    return [
      'M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z',
      'M9 8h6',
      'M9 12h6',
      'M9 16h4',
    ]
  }

  if (props.kind === 'hashtag') {
    return [
      'M9 3L7 21',
      'M17 3l-2 18',
      'M4 9h16',
      'M3 15h16',
    ]
  }

  return [
    'M4 6h16',
    'M4 12h16',
    'M4 18h10',
  ]
})
</script>
