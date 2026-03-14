<template>
  <section class="timelineWrap">
    <div class="timelineColumn">
      <FeedList ref="feed" :key="$route.fullPath">
        <template #composer="{ activeTab }">
          <button
            v-if="auth?.isAuthed && activeTab === 'for_you'"
            type="button"
            class="composerTrigger"
            aria-label="Novy prispevok"
            @click="onComposerTriggerClick"
          >
            <span class="triggerAvatar" aria-hidden="true">
              <UserAvatar class="triggerAvatarImg" :user="auth?.user" :size="40" :alt="auth?.user?.name || 'avatar'" />
            </span>
            <span class="triggerText">Čo je nové na oblohe?</span>
            <span class="triggerCta" data-shortcut-image aria-label="Pridat obrazok">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="3.5" y="4.5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7" />
                <path d="m7 15 3.2-3.2a1 1 0 0 1 1.4 0L14 14l2-2a1 1 0 0 1 1.4 0L20 14.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                <circle cx="9" cy="9" r="1.2" fill="currentColor" />
              </svg>
            </span>
          </button>
          <input
            ref="composerImageInput"
            class="composerImageInput"
            type="file"
            accept="image/*"
            @change="onComposerImageShortcutChange"
          />
        </template>
      </FeedList>
    </div>
  </section>
</template>

<script>
import UserAvatar from '@/components/UserAvatar.vue'
import FeedList from '@/components/FeedList.vue'
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'HomeView',
  components: { UserAvatar, FeedList },
  data() {
    return {
      auth: useAuthStore(),
    }
  },
  methods: {
    onGlobalPostCreated(event) {
      const createdPost = event?.detail
      if (!createdPost?.id) return
      this.$refs.feed?.prepend?.(createdPost)
    },
    openComposer() {
      if (typeof window === 'undefined') return
      window.dispatchEvent(new CustomEvent('post:composer:open'))
    },
    onComposerTriggerClick(event) {
      const clickedShortcut = Boolean(event?.target?.closest?.('[data-shortcut-image]'))
      if (clickedShortcut) {
        this.openImageShortcut()
        return
      }

      this.openComposer()
    },
    openImageShortcut() {
      this.$refs.composerImageInput?.click?.()
    },
    onComposerImageShortcutChange(event) {
      const pickedFile = event?.target?.files?.[0] || null
      if (!pickedFile) return

      if (this.$refs.composerImageInput) {
        this.$refs.composerImageInput.value = ''
      }

      if (typeof pickedFile?.type !== 'string' || !pickedFile.type.startsWith('image/')) return
      if (typeof window === 'undefined') return

      window.dispatchEvent(new CustomEvent('post:composer:open', {
        detail: {
          action: 'post',
          attachmentFile: pickedFile,
        },
      }))
    },
  },
  mounted() {
    if (typeof window !== 'undefined') {
      window.addEventListener('post:created', this.onGlobalPostCreated)
    }
  },
  beforeUnmount() {
    if (typeof window !== 'undefined') {
      window.removeEventListener('post:created', this.onGlobalPostCreated)
    }
  },
}
</script>

<style scoped>
.timelineWrap {
  width: 100%;
  display: flex;
  justify-content: center;
}

.timelineColumn {
  width: 100%;
  max-width: var(--content-max-width);
  min-width: 0;
  border: 0;
  border-right: 1px solid var(--divider-color);
  border-radius: 0;
  overflow: hidden;
  background: var(--color-card);
  box-shadow: none;
}

.composerTrigger {
  width: 100%;
  border: 0;
  min-height: 58px;
  border-bottom: 1px solid var(--divider-color);
  background: transparent;
  color: var(--color-text-primary);
  display: grid;
  grid-template-columns: 40px 1fr auto;
  align-items: center;
  gap: var(--space-3);
  text-align: left;
  padding: 0.78rem var(--space-4);
  transition: background-color var(--motion-fast);
}

.composerTrigger:hover {
  background: var(--interactive-hover);
}

.composerTrigger:focus-visible {
  outline: none;
  box-shadow: inset 0 0 0 2px rgb(var(--primary-rgb) / 0.55);
}

.triggerAvatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  border: 1px solid rgb(var(--primary-rgb) / 0.5);
  background: rgb(var(--primary-rgb) / 0.16);
  display: grid;
  place-items: center;
  font-size: 0.85rem;
  font-weight: 800;
  overflow: hidden;
}

.triggerAvatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.triggerText {
  color: var(--color-text-secondary);
  font-size: var(--font-size-base);
}

.triggerCta {
  width: 2.4rem;
  height: 2.2rem;
  border: 1px solid rgb(var(--color-accent-rgb) / 0.72);
  border-radius: 999px;
  background: rgb(var(--color-accent-rgb) / 0.14);
  color: rgb(var(--color-accent-rgb) / 1);
  display: grid;
  place-items: center;
  transition: background-color var(--motion-fast), border-color var(--motion-fast), box-shadow var(--motion-fast);
}

.triggerCta svg {
  width: 1.05rem;
  height: 1.05rem;
}

.composerTrigger:hover .triggerCta {
  background: rgb(var(--color-accent-rgb) / 0.2);
  border-color: rgb(var(--color-accent-rgb) / 0.8);
}

.composerTrigger:focus-visible .triggerCta {
  box-shadow: 0 0 0 3px rgb(var(--color-accent-rgb) / 0.26);
}

.composerImageInput {
  display: none;
}

@media (max-width: 720px) {
  .timelineColumn {
    border: 0;
    border-radius: 0;
  }
}

</style>
