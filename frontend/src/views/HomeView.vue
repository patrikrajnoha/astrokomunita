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
            @click="openComposer"
          >
            <span class="triggerAvatar" aria-hidden="true">
              <UserAvatar class="triggerAvatarImg" :user="auth?.user" :size="40" :alt="auth?.user?.name || 'avatar'" />
            </span>
            <span class="triggerText">Čo je nové na oblohe?</span>
            <span class="triggerCta">Pridat</span>
          </button>
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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  overflow: hidden;
  background: var(--color-card);
  box-shadow: var(--shadow-soft);
}

.composerTrigger {
  width: 100%;
  border: 0;
  min-height: 58px;
  border-bottom: 1px solid var(--color-divider);
  background: rgb(var(--bg-app-rgb) / 0.34);
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
  border: 1px solid rgb(var(--color-accent-rgb) / 0.62);
  border-radius: 999px;
  background: rgb(var(--color-accent-rgb) / 0.2);
  color: var(--color-text-primary);
  font-size: var(--font-size-xs);
  font-weight: 600;
  padding: 0.35rem 0.8rem;
}

@media (max-width: 720px) {
  .timelineColumn {
    border: 0;
    border-radius: 0;
  }
}

</style>
