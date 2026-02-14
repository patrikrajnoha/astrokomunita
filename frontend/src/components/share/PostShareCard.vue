<template>
  <div class="share-canvas" role="img" aria-label="Share card post preview">
    <div class="share-card">
      <header class="share-head">
        <div class="author-wrap">
          <div class="avatar-wrap">
            <img
              v-if="avatarToUse"
              :src="avatarToUse"
              alt="Author avatar"
              crossorigin="anonymous"
              referrerpolicy="no-referrer"
              class="avatar-image"
              @error="onAvatarError"
            />
            <div v-else class="avatar-fallback">{{ authorInitials }}</div>
          </div>
          <div class="author-name">{{ authorName }}</div>
        </div>
        <time class="post-date">{{ formattedDate }}</time>
      </header>

      <main class="share-body">
        <h2 v-if="headline" class="headline">{{ headline }}</h2>
        <p class="snippet">{{ bodySnippet }}</p>
      </main>

      <footer class="share-foot">
        <div class="tags">
          <span v-for="tag in visibleTags" :key="tag" class="tag-pill">{{ tag }}</span>
        </div>
        <div class="brand">Nebesky sprievodca</div>
      </footer>

      <div class="watermark">{{ watermarkText }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  post: { type: Object, required: true },
  author: { type: Object, default: null },
  brandDomain: { type: String, default: 'nebesky-sprievodca.sk' },
  forcePlaceholderAvatar: { type: Boolean, default: false },
})

const avatarBroken = ref(false)

const resolvedAuthor = computed(() => props.author || props.post?.user || {})
const authorName = computed(() => resolvedAuthor.value?.name || 'Astrokomunita')
const authorInitials = computed(() => {
  const parts = String(authorName.value || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
  const first = parts[0]?.[0] || 'A'
  const second = parts[1]?.[0] || ''
  return (first + second).toUpperCase()
})

const avatarToUse = computed(() => {
  if (props.forcePlaceholderAvatar || avatarBroken.value) return ''
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

const bodySnippet = computed(() => {
  const baseContent = rawContent.value
  if (!baseContent) return ''

  if (props.post?.title) {
    return baseContent
  }

  const withoutFirstLine = baseContent.replace(firstLine.value, '').trim()
  return withoutFirstLine || baseContent
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

const watermarkText = computed(() => `${props.brandDomain}  •  #${props.post?.id ?? 'post'}`)

function onAvatarError() {
  avatarBroken.value = true
}
</script>

<style scoped>
.share-canvas {
  width: 1080px;
  height: 1350px;
  padding: 96px;
  box-sizing: border-box;
  background: #0b0f14;
  color: #eef2ff;
  font-family: 'Space Grotesk', 'Segoe UI', sans-serif;
}

.share-card {
  width: 100%;
  height: 100%;
  border-radius: 48px;
  padding: 72px;
  box-sizing: border-box;
  background: #111827;
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

.avatar-image,
.avatar-fallback {
  width: 100%;
  height: 100%;
}

.avatar-image {
  object-fit: cover;
  display: block;
}

.avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  font-weight: 600;
  color: #d1d5db;
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
  color: #f8fafc;
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
