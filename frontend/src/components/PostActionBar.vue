<template>
  <div class="post-actions" @click.stop>
    <div class="post-actions-left">
      <button class="action-btn action-btn--reply" type="button" :title="replyTitle" @click.stop="$emit('reply')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path
            d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"
          />
        </svg>
        <span class="action-count">{{ replyCount }}</span>
      </button>

      <button
        class="action-btn action-btn--like"
        type="button"
        :class="{
          'action-btn--liked': !!item?.liked_by_me,
          'action-btn--bump': likeBump,
        }"
        :disabled="!isAuthed || likeLoading"
        :title="
          isAuthed
            ? item?.liked_by_me
              ? 'Zrušiť like'
              : 'Páči sa mi'
            : 'Prihlás sa pre lajkovanie'
        "
        @click.stop="$emit('like')"
      >
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          :fill="item?.liked_by_me ? 'currentColor' : 'none'"
          stroke="currentColor"
          stroke-width="1.9"
        >
          <path
            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
          />
        </svg>
        <span class="action-count">{{ likeCount }}</span>
      </button>
    </div>

    <div class="post-actions-right">
      <button
        class="action-btn action-btn--bookmark"
        type="button"
        :class="{ 'action-btn--bookmarked': !!item?.is_bookmarked }"
        :disabled="!isAuthed || bookmarkLoading"
        :title="
          isAuthed
            ? item?.is_bookmarked
              ? 'Odstranit zo zaloziek'
              : 'Ulozit do zaloziek'
            : 'Prihlas sa pre zalozky'
        "
        @click.stop="$emit('bookmark')"
      >
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          :fill="item?.is_bookmarked ? 'currentColor' : 'none'"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
        </svg>
      </button>

      <button
        class="action-btn action-btn--share"
        type="button"
        title="Zdieľať"
        aria-label="Zdieľať prispevok"
        @click.stop="$emit('share')"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8" />
          <polyline points="16 6 12 2 8 6" />
          <line x1="12" y1="2" x2="12" y2="15" />
        </svg>
      </button>

      <div v-if="menuItems.length" class="post-actions-more" @click.stop>
        <DropdownMenu
          :items="menuItems"
          label="More actions"
          :menu-label="menuLabel"
          @select="(menuItem) => $emit('menu-select', menuItem)"
        />
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
    default: null,
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
  menuLabel: {
    type: String,
    default: 'Post actions',
  },
  replyTitle: {
    type: String,
    default: 'Reagovať',
  },
})

defineEmits(['reply', 'like', 'bookmark', 'share', 'menu-select'])

const replyCount = computed(() => Number(props.replyCount ?? 0))
const likeCount = computed(() => Number(props.likeCount ?? 0))
</script>

<style scoped>
.post-actions {
  margin-top: 0.48rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 0.35rem;
  border-top: 0;
  min-width: 0;
}

.post-actions-left,
.post-actions-right {
  display: flex;
  align-items: center;
  gap: 0.82rem;
}

.post-actions-right {
  margin-left: auto;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  padding: 0.3rem 0.42rem;
  border: none;
  background: transparent;
  color: #5d6e87;
  border-radius: 999px;
  font-size: 0.77rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 30px;
  min-width: 30px;
  text-decoration: none;
}

.action-btn:hover:not(:disabled) {
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
  color: #5d6e87;
}

.action-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.action-btn:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn--like {
  color: #5d6e87;
  position: relative;
}

.action-btn--like.action-btn--liked {
  color: #ec4899;
  font-weight: 600;
}

.action-btn--like.action-btn--liked:hover:not(:disabled) {
  color: #ec4899;
}

.action-btn--like.action-btn--bump {
  animation: likePop 220ms ease;
}

.action-btn--bookmark.action-btn--bookmarked {
  color: #0f73ff;
  font-weight: 600;
}

.action-btn--bookmark.action-btn--bookmarked:hover:not(:disabled) {
  color: #0f73ff;
}

.post-actions-more {
  display: inline-flex;
  align-items: center;
}

.post-actions-more :deep(.dropdownRoot) {
  display: inline-flex;
  align-items: center;
}

.post-actions-more :deep(.dropdownTrigger) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  min-width: 30px;
  padding: 0.3rem 0.42rem;
  border-radius: 999px;
  color: #5d6e87;
  transition: all 0.2s ease;
}

.post-actions-more :deep(.dropdownTrigger:hover:not(:disabled)) {
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
  color: #5d6e87;
}

.action-count {
  font-size: 0.72rem;
  font-weight: 500;
  min-width: 16px;
  text-align: center;
}

@keyframes likePop {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.15);
  }
  100% {
    transform: scale(1);
  }
}

@media (max-width: 480px) {
  .action-btn {
    padding: 0.24rem 0.34rem;
    font-size: 0.72rem;
    min-height: 28px;
    min-width: 28px;
  }

  .action-count {
    font-size: 0.68rem;
  }
}

@media (min-width: 481px) and (max-width: 768px) {
  .action-btn {
    padding: 0.28rem 0.4rem;
    font-size: 0.74rem;
    min-height: 29px;
    min-width: 29px;
  }
}
</style>
