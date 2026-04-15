<template>
  <section v-if="hasPreviewContent" class="sharedPostPreview" @click.stop>
    <header class="sharedPostPreview__header">
      <span class="sharedPostPreview__eyebrow">Zdielany prispevok</span>
      <span v-if="sharedPost.user?.username" class="sharedPostPreview__author">
        @{{ sharedPost.user.username }}
      </span>
    </header>

    <HashtagText
      v-if="sharedContent"
      class="sharedPostPreview__content"
      :content="sharedContent"
    />

    <PollCard
      v-if="sharedPost.poll"
      :poll="sharedPost.poll"
      :post-id="sharedPost.id"
      :is-authed="isAuthed"
      @updated="updateNestedPoll"
      @login-required="$emit('login-required')"
    />
  </section>
</template>

<script setup>
import { computed } from 'vue'
import HashtagText from '@/components/HashtagText.vue'
import PollCard from '@/components/PollCard.vue'

const props = defineProps({
  post: {
    type: Object,
    required: true,
  },
  isAuthed: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['login-required'])

const sharedPost = computed(() => {
  const candidates = [
    props.post?.shared_post,
    props.post?.sharedPost,
    props.post?.original_post,
    props.post?.originalPost,
    props.post?.reposted_post,
    props.post?.repostedPost,
    props.post?.meta?.shared_post,
    props.post?.meta?.sharedPost,
    props.post?.meta?.original_post,
    props.post?.meta?.originalPost,
  ]

  return candidates.find((candidate) => candidate && typeof candidate === 'object') || null
})

const sharedContent = computed(() => String(sharedPost.value?.content || '').trim())
const hasPreviewContent = computed(() => Boolean(sharedContent.value || sharedPost.value?.poll))

function updateNestedPoll(nextPoll) {
  if (!sharedPost.value || !nextPoll) return
  sharedPost.value.poll = nextPoll
}
</script>

<style scoped>
.sharedPostPreview {
  display: grid;
  gap: 0.75rem;
  margin-top: 0.9rem;
  padding: 0.95rem;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 1rem;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.88), rgba(248, 250, 252, 0.96));
}

.sharedPostPreview__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  font-size: 0.8rem;
}

.sharedPostPreview__eyebrow {
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #475569;
}

.sharedPostPreview__author {
  color: #64748b;
}

.sharedPostPreview__content {
  color: #0f172a;
}
</style>
