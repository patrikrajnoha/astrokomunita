import { defineStore } from 'pinia'
import api from '@/services/api'

export const useBookmarksStore = defineStore('bookmarks', {
  state: () => ({
    ids: new Set(),
    loadingIds: new Set(),
  }),

  getters: {
    isBookmarked: (state) => (postId) => state.ids.has(Number(postId)),
    isLoading: (state) => (postId) => state.loadingIds.has(Number(postId)),
  },

  actions: {
    setBookmarked(postId, bookmarked) {
      const id = Number(postId)
      if (!Number.isFinite(id)) return

      const next = new Set(this.ids)
      if (bookmarked) next.add(id)
      else next.delete(id)
      this.ids = next
    },

    hydrateFromPosts(posts = []) {
      if (!Array.isArray(posts)) return

      const next = new Set(this.ids)
      for (const post of posts) {
        const id = Number(post?.id)
        if (!Number.isFinite(id)) continue

        if (post?.is_bookmarked) next.add(id)
        else if (post?.is_bookmarked === false) next.delete(id)
      }
      this.ids = next
    },

    setLoading(postId, on) {
      const id = Number(postId)
      if (!Number.isFinite(id)) return

      const next = new Set(this.loadingIds)
      if (on) next.add(id)
      else next.delete(id)
      this.loadingIds = next
    },

    async toggleBookmark(postId, currentlyBookmarked) {
      const id = Number(postId)
      if (!Number.isFinite(id)) {
        throw new Error('Invalid post ID')
      }

      if (this.loadingIds.has(id)) {
        return currentlyBookmarked
      }

      this.setLoading(id, true)
      try {
        if (currentlyBookmarked) {
          await api.delete(`/posts/${id}/bookmark`)
          this.setBookmarked(id, false)
          return false
        }

        await api.post(`/posts/${id}/bookmark`)
        this.setBookmarked(id, true)
        return true
      } finally {
        this.setLoading(id, false)
      }
    },

    async fetchBookmarks(params = {}) {
      const res = await api.get('/me/bookmarks', { params })
      const rows = Array.isArray(res?.data?.data) ? res.data.data : []
      this.hydrateFromPosts(rows)
      return res.data
    },
  },
})
