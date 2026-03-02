<template>
  <section class="timelineWrap">
    <div class="timelineColumn">
      <FeedList ref="feed" :key="$route.fullPath">
        <template #composer="{ activeTab }">
          <PostComposer
            v-if="auth?.isAuthed && activeTab === 'for_you'"
            @created="onPostCreated"
          />
        </template>
      </FeedList>
    </div>
  </section>
</template>

<script>
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import PostComposer from '@/components/PostComposer.vue'
import FeedList from '@/components/FeedList.vue'

const { showToast } = useToast()

export default {
  name: 'HomeView',
  components: { PostComposer, FeedList },
  data() {
    return {
      auth: useAuthStore(),
    }
  },
  methods: {
    onPostCreated(createdPost) {
      this.$refs.feed?.prepend?.(createdPost)
      showToast('Prispevok bol publikovany.', 'success')
    },
    onGlobalPostCreated(event) {
      const createdPost = event?.detail
      if (!createdPost?.id) return
      this.$refs.feed?.prepend?.(createdPost)
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

@media (max-width: 720px) {
  .timelineColumn {
    border: 0;
    border-radius: 0;
  }
}

</style>
