<template>
  <div class="share-canvas" role="img" aria-label="Nahlad zdielacej karty prispevku">
    <div class="share-card">
      <header class="share-head">
        <div class="author-wrap">
          <div class="avatar-wrap">
            <UserAvatar
              class="avatar-fallback"
              :user="resolvedAuthor"
              :avatar-url="avatarToUse"
              :size="72"
              :alt="authorName"
            />
          </div>
          <div class="author-name">{{ authorName }}</div>
        </div>
        <time class="post-date">{{ formattedDate }}</time>
      </header>

      <main class="share-body">
        <h2 v-if="headline" class="headline">{{ headline }}</h2>
        <p v-if="bodySnippet" class="snippet" :class="{ 'snippet--with-media': hasMedia }">{{ bodySnippet }}</p>
      </main>

      <figure v-if="hasMedia" class="share-media">
        <img :src="mediaUrl" alt="Fotografia prispevku" loading="eager" />
      </figure>

      <footer class="share-foot">
        <div class="tags">
          <span v-for="tag in visibleTags" :key="tag" class="tag-pill">{{ tag }}</span>
        </div>
        <div class="brand">{{ brandLabel }}</div>
      </footer>

      <div class="watermark">{{ watermarkText }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import UserAvatar from '@/components/UserAvatar.vue'

const props = defineProps({
  post: { type: Object, required: true },
  author: { type: Object, default: null },
  brandDomain: { type: String, default: 'astrokomunita.sk' },
  forcePlaceholderAvatar: { type: Boolean, default: false },
  mediaUrl: { type: String, default: '' },
})

const resolvedAuthor = computed(() => props.author || props.post?.user || {})
const authorName = computed(() => resolvedAuthor.value?.name || 'Astrokomunita')
const hasMedia = computed(() => String(props.mediaUrl || '').trim() !== '')
const brandLabel = computed(() => 'Astrokomunita')

const avatarToUse = computed(() => {
  if (props.forcePlaceholderAvatar) return ''
  return resolvedAuthor.value?.avatar_url || ''
})

const formattedDate = computed(() => {
  const raw = props.post?.created_at || props.post?.published_at
  if (!raw) return ''
  const date = new Date(raw)
  if (Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('sk-SK', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
})

const rawContent = computed(() => String(props.post?.content || '').trim())
const firstLine = computed(() => rawContent.value.split(/\r?\n/).map((line) => line.trim()).find(Boolean) || '')
const headline = computed(() => String(props.post?.title || '').trim() || firstLine.value)

function normalizeComparable(value) {
  return String(value || '')
    .trim()
    .replace(/\s+/g, ' ')
    .toLowerCase()
}

const bodySnippet = computed(() => {
  const baseContent = rawContent.value
  if (!baseContent) return ''

  if (props.post?.title) {
    const normalizedTitle = normalizeComparable(props.post.title)
    const normalizedFirstLine = normalizeComparable(firstLine.value)
    const withoutFirstLine = normalizedTitle !== '' && normalizedTitle === normalizedFirstLine
      ? baseContent.replace(firstLine.value, '').trim()
      : baseContent

    if (normalizeComparable(withoutFirstLine) === normalizeComparable(headline.value)) {
      return ''
    }

    return withoutFirstLine
  }

  const withoutFirstLine = baseContent.replace(firstLine.value, '').trim()
  const fallback = withoutFirstLine || baseContent
  if (normalizeComparable(fallback) === normalizeComparable(headline.value)) {
    return ''
  }

  return fallback
})

const visibleTags = computed(() => {
  const tags = Array.isArray(props.post?.tags)
    ? props.post.tags
        .map((tag) =>
          typeof tag === 'string' ? tag.trim() : String(tag?.name || tag?.slug || '').trim(),
        )
        .filter(Boolean)
    : []

  if (!tags.length && props.post?.source_name) {
    tags.push(String(props.post.source_name).toUpperCase())
  }

  return tags.slice(0, 3)
})

const watermarkText = computed(() => props.brandDomain)
</script>

<style scoped>
.share-canvas {
  width: 1080px;
  height: 1350px;
  padding: 96px;
  box-sizing: border-box;
  background: var(--color-bg-main, rgb(11 15 20));
  color: var(--color-text-primary, rgb(238 242 255));
  font-family: InterVariable, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
    'Liberation Sans', Helvetica, Arial, sans-serif;
}

.share-card {
  width: 100%;
  height: 100%;
  border-radius: 48px;
  padding: 72px;
  box-sizing: border-box;
  background: var(--color-bg-surface, rgb(17 24 39));
  box-shadow: 0 28px 90px rgba(0, 0, 0, 0.35);
  display: flex;
  flex-direction: column;
  position: relative;
}

.share-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 28px;
}

.author-wrap {
  display: flex;
  align-items: center;
  gap: 22px;
  min-width: 0;
}

.avatar-wrap {
  width: 72px;
  height: 72px;
  border-radius: 999px;
  overflow: hidden;
  flex-shrink: 0;
  background: rgba(148, 163, 184, 0.16);
  border: 1px solid rgba(148, 163, 184, 0.45);
}

.avatar-fallback {
  width: 100%;
  height: 100%;
}

.avatar-fallback {
  display: block;
}

.author-name {
  font-size: 42px;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.post-date {
  font-size: 28px;
  color: rgba(226, 232, 240, 0.62);
  flex-shrink: 0;
}

.share-body {
  margin-top: 56px;
  flex: 1;
  min-height: 0;
}

.headline {
  margin: 0;
  font-size: 54px;
  font-weight: 600;
  line-height: 1.16;
  color: var(--color-text-primary, rgb(248 250 252));
}

.snippet {
  margin: 28px 0 0;
  font-size: 40px;
  font-weight: 400;
  line-height: 1.3;
  color: rgba(226, 232, 240, 0.9);
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 8;
  overflow: hidden;
}

.snippet--with-media {
  -webkit-line-clamp: 5;
}

.share-media {
  margin: 0;
  border-radius: 26px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  overflow: hidden;
  background: rgba(15, 23, 42, 0.44);
  max-height: 460px;
}

.share-media img {
  display: block;
  width: 100%;
  height: 100%;
  max-height: 460px;
  object-fit: cover;
}

.share-foot {
  margin-top: 44px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  min-width: 0;
}

.tag-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 12px 24px;
  font-size: 30px;
  line-height: 1;
  color: rgba(226, 232, 240, 0.88);
  background: rgba(148, 163, 184, 0.13);
  border: 1px solid rgba(148, 163, 184, 0.32);
}

.brand {
  font-size: 30px;
  color: rgba(226, 232, 240, 0.72);
  white-space: nowrap;
}

.watermark {
  position: absolute;
  right: 72px;
  bottom: 52px;
  font-size: 26px;
  color: rgba(226, 232, 240, 0.35);
}
</style>
