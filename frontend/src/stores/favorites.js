import { defineStore } from 'pinia'
import { getFavorites, addFavorite, removeFavorite } from '@/services/favorites'

export const useFavoritesStore = defineStore('favorites', {
  state: () => ({
    ids: new Set(),
    loading: false,
  }),

  getters: {
    isFavorite: (state) => (eventId) => state.ids.has(Number(eventId)),
  },

  actions: {
    async fetch() {
      this.loading = true
      try {
        const res = await getFavorites()
        const ids = (res.data || [])
          .map((f) => Number(f.event_id))
          .filter((n) => Number.isFinite(n))
        this.ids = new Set(ids)
      } catch (err) {
        console.warn('Favorites store fetch failed:', err?.message || err)
      } finally {
        this.loading = false
      }
    },

    async add(eventId) {
      const id = Number(eventId)
      if (!Number.isFinite(id)) return
      if (this.loading) return
      if (this.ids.has(id)) return

      const prev = this.ids
      const next = new Set(prev)
      next.add(id)
      this.ids = next

      try {
        await addFavorite(id)
      } catch (err) {
        // rollback
        this.ids = prev
        console.warn('Favorites store add failed:', err?.message || err)
      }
    },

    async remove(eventId) {
      const id = Number(eventId)
      if (!Number.isFinite(id)) return
      if (this.loading) return
      if (!this.ids.has(id)) return

      const prev = this.ids
      const next = new Set(prev)
      next.delete(id)
      this.ids = next

      try {
        await removeFavorite(id)
      } catch (err) {
        // rollback
        this.ids = prev
        console.warn('Favorites store remove failed:', err?.message || err)
      }
    },

    async toggle(eventId) {
      const id = Number(eventId)
      if (this.isFavorite(id)) return this.remove(id)
      return this.add(id)
    },
  },
})
