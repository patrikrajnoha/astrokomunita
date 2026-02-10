<template>
  <section class="centerCol">
    <PostComposer
      v-if="auth?.isAuthed"
      @created="onPostCreated"
    />

    <FeedList ref="feed" :key="$route.fullPath" />
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
.centerCol {
  display: grid;
  gap: 1rem;
  width: 100%;
  min-width: 0;
}

@media (max-width: 480px) {
  .centerCol {
    gap: 0.75rem;
  }
}
</style>
