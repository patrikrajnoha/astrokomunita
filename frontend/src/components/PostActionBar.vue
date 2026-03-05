<template>
  <div class="post-actions" @click.stop>
    <div class="post-actions-left">
      <button class="action-btn action-btn--reply" type="button" @click.stop="$emit('reply')">
        <span class="action-text">Odpovedat</span>
        <span v-if="safeReplyCount > 0" class="action-count">{{ safeReplyCount }}</span>
      </button>

      <button
        class="action-btn action-btn--like"
        :class="{
          'action-btn--liked': isLiked,
          'action-btn--bump': likeBump,
        }"
        type="button"
        :disabled="likeLoading"
        @click.stop="$emit('like')"
      >
        <span class="action-text">Pacim</span>
        <span class="action-count">{{ safeLikeCount }}</span>
      </button>

      <button
        class="action-btn action-btn--bookmark"
        :class="{ 'action-btn--bookmarked': isBookmarked }"
        type="button"
        :disabled="bookmarkLoading"
        @click.stop="$emit('bookmark')"
      >
        <span class="action-text">Zalozit</span>
      </button>

      <button class="action-btn action-btn--share" type="button" @click.stop="$emit('share')">
        <span class="action-text">Zdielat</span>
      </button>
    </div>

    <div class="post-actions-right">
      <div v-if="safeMenuItems.length > 0" class="post-actions-more">
        <DropdownMenu :items="safeMenuItems" @select="(item) => $emit('menu-select', item)" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'

const props = defineProps({
  item: {
    type: Object,
    default: () => ({}),
  },
  replyCount: {
    type: Number,
    default: 0,
  },
  likeCount: {
    type: Number,
    default: 0,
  },
  likeLoading: {
    type: Boolean,
    default: false,
  },
  bookmarkLoading: {
    type: Boolean,
    default: false,
  },
  likeBump: {
    type: Boolean,
    default: false,
  },
  isAuthed: {
    type: Boolean,
    default: false,
  },
  menuItems: {
    type: Array,
    default: () => [],
  },
})

defineEmits(['reply', 'like', 'bookmark', 'share', 'menu-select'])

const safeLikeCount = computed(() => {
  const value = Number(props.likeCount ?? props.item?.likes_count ?? 0)
  return Number.isFinite(value) ? value : 0
})

const safeReplyCount = computed(() => {
  const value = Number(props.replyCount ?? props.item?.replies_count ?? 0)
  return Number.isFinite(value) ? value : 0
})

const isLiked = computed(() => Boolean(props.item?.liked_by_me))
const isBookmarked = computed(() => Boolean(props.item?.is_bookmarked))
const safeMenuItems = computed(() => (Array.isArray(props.menuItems) ? props.menuItems : []))
</script>
