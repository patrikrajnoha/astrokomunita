<template>
  <div class="homeLayout">
    <section class="centerCol">
      <PostComposer
        v-if="auth?.isAuthed"
        @created="onPostCreated"
      />

      <FeedList ref="feed" :key="$route.fullPath" />
    </section>

    <DynamicSidebar />
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth'
import PostComposer from '@/components/PostComposer.vue'
import FeedList from '@/components/FeedList.vue'
import DynamicSidebar from '@/components/DynamicSidebar.vue'

export default {
  name: 'HomeView',
  components: { PostComposer, FeedList, DynamicSidebar },
  data() {
    return {
      auth: useAuthStore(),
    }
  },
  methods: {
    onPostCreated(createdPost) {
      this.$refs.feed?.prepend?.(createdPost)
    },
  },
}
</script>

<style scoped>
.homeLayout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 1.5rem;
  align-items: start;
}

.centerCol {
  display: grid;
  gap: 1.25rem;
  max-width: 680px;
  width: 100%;
  margin: 0 auto;
}

/* Responsive: on mobile, hide sidebar column completely */
@media (max-width: 1023px) {
  .homeLayout {
    grid-template-columns: minmax(0, 1fr);
  }
}

</style>
