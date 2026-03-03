<template>
  <section class="timelineWrap">
    <div class="timelineColumn">
      <FeedList ref="feed" :key="$route.fullPath">
        <template #composer="{ activeTab }">
          <button
            v-if="auth?.isAuthed && activeTab === 'for_you'"
            type="button"
            class="composerTrigger"
            aria-label="Nový príspevok"
            @click="openComposer"
          >
            <span class="triggerAvatar" aria-hidden="true">
              <img
                v-if="avatarUrl"
                class="triggerAvatarImg"
                :src="avatarUrl"
                :alt="auth?.user?.name || 'avatar'"
              />
              <span v-else>{{ initials }}</span>
            </span>
            <span class="triggerText">Čo máš nové?</span>
            <span class="triggerCta">Pridať</span>
          </button>
        </template>
      </FeedList>
    </div>
  </section>
</template>

<script>
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import FeedList from '@/components/FeedList.vue'

export default {
  name: 'HomeView',
  components: { FeedList },
  data() {
    return {
      auth: useAuthStore(),
    }
  },
  computed: {
    initials() {
      const name = String(this.auth?.user?.name || '').trim()
      if (!name) return 'U'
      const parts = name.split(/\s+/).filter(Boolean)
      const first = parts[0]?.[0] || 'U'
      const second = parts[1]?.[0] || ''
      return (first + second).toUpperCase()
    },
    avatarUrl() {
      const raw = this.auth?.user?.avatar_url || this.auth?.user?.avatarUrl || ''
      if (!raw) return ''
      if (/^https?:\/\//i.test(raw)) return raw

      const base = api?.defaults?.baseURL || ''
      const origin = base.replace(/\/api\/?$/, '')
      if (raw.startsWith('/')) return origin + raw
      return origin + '/' + raw
    },
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
  max-width: 680px;
  min-width: 0;
  border: var(--divider);
  border-radius: 16px;
  overflow: hidden;
  background: rgb(var(--color-bg-rgb) / 0.26);
}

.composerTrigger {
  width: 100%;
  border: 0;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: rgb(var(--color-surface-rgb) / 0.96);
  display: grid;
  grid-template-columns: 40px 1fr auto;
  align-items: center;
  gap: 0.6rem;
  text-align: left;
  padding: 0.72rem;
}

.composerTrigger:hover {
  background: rgb(var(--color-bg-rgb) / 0.62);
}

.composerTrigger:focus-visible {
  outline: 2px solid rgb(var(--color-primary-rgb) / 0.9);
  outline-offset: -2px;
}

.triggerAvatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.16);
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
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 0.97rem;
}

.triggerCta {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.65);
  border-radius: 999px;
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: rgb(var(--color-surface-rgb) / 0.96);
  font-size: 0.76rem;
  font-weight: 700;
  padding: 0.35rem 0.8rem;
}

@media (max-width: 720px) {
  .timelineColumn {
    border: 0;
    border-radius: 0;
  }
}

</style>
