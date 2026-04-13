<template>
  <RouterLink
    :to="to"
    class="searchResultCard"
  >
    <div class="searchResultCard__row">
      <span class="searchResultCard__icon" aria-hidden="true">
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

      <div class="searchResultCard__content">
        <p class="searchResultCard__title">
          <template v-for="(part, index) in titleParts" :key="`title-${index}`">
            <mark
              v-if="part.match"
              class="searchResultCard__mark"
            >
              {{ part.text }}
            </mark>
            <span v-else>{{ part.text }}</span>
          </template>
        </p>

        <p v-if="excerpt" class="searchResultCard__excerpt">
          <template v-for="(part, index) in excerptParts" :key="`excerpt-${index}`">
            <mark
              v-if="part.match"
              class="searchResultCard__mark"
            >
              {{ part.text }}
            </mark>
            <span v-else>{{ part.text }}</span>
          </template>
        </p>

        <p v-if="meta" class="searchResultCard__meta">{{ meta }}</p>
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

<style scoped>
.searchResultCard {
  display: block;
  border: none;
  border-radius: 1rem;
  background: #151d28;
  padding: 0.72rem;
  color: inherit;
  text-decoration: none;
  transition: background 140ms ease;
}

.searchResultCard:hover {
  background: #1c2736;
}

.searchResultCard:focus-visible {
  outline: 2px solid rgb(15 115 255 / 55%);
  outline-offset: 0;
}

.searchResultCard__row {
  display: flex;
  align-items: flex-start;
  gap: 0.62rem;
  min-width: 0;
}

.searchResultCard__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 0.75rem;
  color: #ABB8C9;
  background: #222e3f;
}

.searchResultCard__content {
  display: grid;
  gap: 0.24rem;
  min-width: 0;
}

.searchResultCard__title {
  margin: 0;
  color: #ffffff;
  font-size: 0.875rem;
  font-weight: 650;
  line-height: 1.34;
}

.searchResultCard__excerpt {
  margin: 0;
  color: #ABB8C9;
  font-size: 0.875rem;
  line-height: 1.45;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.searchResultCard__meta {
  margin: 0;
  color: rgba(171, 184, 201, 0.82);
  font-size: 0.72rem;
  line-height: 1.3;
}

.searchResultCard__mark {
  border-radius: 0.28rem;
  background: rgba(15, 115, 255, 0.16);
  padding-inline: 0.2rem;
  color: inherit;
}
</style>
