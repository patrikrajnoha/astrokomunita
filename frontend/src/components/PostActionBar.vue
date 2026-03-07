<template>
  <div class="post-actions" :class="{ 'post-actions--menu-open': isMenuOpen }" @click.stop>
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
              ? 'Zrušiť páči sa mi'
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
              ? 'Odstrániť zo záložiek'
              : 'Uložiť do záložiek'
            : 'Prihlás sa pre záložky'
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
        aria-label="Zdieľať príspevok"
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
          label="Ďalšie akcie"
          :menu-label="menuLabel"
          @open="isMenuOpen = true"
          @close="isMenuOpen = false"
          @select="(menuItem) => $emit('menu-select', menuItem)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
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
    default: 'Akcie príspevku',
  },
  replyTitle: {
    type: String,
    default: 'Reagovať',
  },
})

defineEmits(['reply', 'like', 'bookmark', 'share', 'menu-select'])

const isMenuOpen = ref(false)
const replyCount = computed(() => Number(props.replyCount ?? 0))
const likeCount = computed(() => Number(props.likeCount ?? 0))
</script>

<style scoped>
.post-actions {
  margin-top: 0.28rem;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.38rem;
  padding-top: 0.14rem;
  border-top: 0;
  min-width: 0;
  position: relative;
  z-index: 4;
}

.post-actions--menu-open {
  z-index: 40;
}

.post-actions-left,
.post-actions-right {
  display: flex;
  align-items: center;
  gap: 0.24rem;
  min-width: 0;
}

.post-actions-right {
  justify-content: flex-end;
  flex-shrink: 0;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.22rem;
  padding: 3px 5px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--color-text-secondary);
  border-radius: var(--radius-pill);
  font-size: 0.7rem;
  font-weight: 500;
  cursor: pointer;
  transition: border-color var(--motion-fast), background-color var(--motion-fast), color var(--motion-fast), transform var(--motion-fast);
  min-height: 26px;
  min-width: 26px;
  text-decoration: none;
}

.action-btn :is(svg) {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.action-btn:hover:not(:disabled) {
  border-color: var(--color-border);
  background: var(--interactive-hover);
  color: var(--color-text-primary);
}

.action-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.action-btn:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.action-btn:disabled {
  opacity: 0.62;
  cursor: not-allowed;
}

.action-btn--like {
  color: var(--color-text-secondary);
  position: relative;
}

.action-btn--like.action-btn--liked {
  color: var(--color-danger);
  font-weight: 600;
}

.action-btn--like.action-btn--liked:hover:not(:disabled) {
  color: var(--color-danger);
}

.action-btn--like.action-btn--bump {
  animation: likePop 220ms ease;
}

.action-btn--bookmark.action-btn--bookmarked {
  color: var(--color-accent);
  font-weight: 600;
}

.action-btn--bookmark.action-btn--bookmarked:hover:not(:disabled) {
  color: var(--color-accent);
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
  min-height: 26px;
  min-width: 26px;
  padding: 3px 5px;
  border-radius: 999px;
  color: var(--color-text-secondary);
  transition: all var(--motion-fast);
}

.post-actions-more :deep(.dropdownTrigger:hover:not(:disabled)) {
  border-color: var(--color-border);
  background: var(--interactive-hover);
  color: var(--color-text-primary);
}

.action-count {
  font-size: 0.65rem;
  font-weight: 500;
  min-width: 11px;
  text-align: center;
  line-height: 1;
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

@media (max-width: 640px) {
  .post-actions {
    gap: 0.26rem;
  }

  .action-btn {
    padding: 2px 4px;
    min-height: 24px;
    min-width: 24px;
  }

  .action-count {
    font-size: 0.62rem;
  }

  .action-btn :is(svg) {
    width: 14px;
    height: 14px;
  }

  .post-actions-more :deep(.dropdownTrigger) {
    min-height: 24px;
    min-width: 24px;
    padding: 2px 4px;
  }
}

@media (max-width: 430px) {
  .post-actions {
    grid-template-columns: 1fr;
  }

  .post-actions-right {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
