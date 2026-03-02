import { defineStore } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { getEventFollowState, followEvent, getFollowedEvents, unfollowEvent } from '@/services/eventFollows'

export const useEventFollowsStore = defineStore('eventFollows', {
  state: () => ({
    ids: new Set(),
    loadingIds: new Set(),
    loaded: false,
    revision: 0,
  }),

  getters: {
    isFollowed: (state) => (eventId) => state.ids.has(Number(eventId)),
    isLoading: (state) => (eventId) => state.loadingIds.has(Number(eventId)),
  },

  actions: {
    reset() {
      this.ids = new Set()
      this.loadingIds = new Set()
      this.loaded = false
      this.revision += 1
    },

    setLoading(eventId, on) {
      const id = Number(eventId)
      if (!Number.isFinite(id)) return

      const next = new Set(this.loadingIds)
      if (on) next.add(id)
      else next.delete(id)
      this.loadingIds = next
    },

    setFollowed(eventId, followed) {
      const id = Number(eventId)
      if (!Number.isFinite(id)) return

      const next = new Set(this.ids)
      if (followed) next.add(id)
      else next.delete(id)
      this.ids = next
    },

    hydrateFromEvents(events = []) {
      if (!Array.isArray(events)) return

      const next = new Set(this.ids)
      for (const event of events) {
        const id = Number(event?.id)
        if (!Number.isFinite(id)) continue

        if (event?.followed_at || event?.is_followed === true) {
          next.add(id)
        } else if (event?.is_followed === false) {
          next.delete(id)
        }
      }

      this.ids = next
    },

    async syncFollowState(eventId) {
      const auth = useAuthStore()
      const id = Number(eventId)

      if (!Number.isFinite(id)) {
        throw new Error('Invalid event ID')
      }

      if (!auth.isAuthed) {
        this.setFollowed(id, false)
        return false
      }

      const res = await getEventFollowState(id)
      const followed = Boolean(res?.data?.followed)
      this.setFollowed(id, followed)
      return followed
    },

    async fetchFollowedEvents(params = {}) {
      const auth = useAuthStore()

      if (!auth.isAuthed) {
        this.reset()
        return {
          data: [],
          next_page_url: null,
          total: 0,
        }
      }

      const res = await getFollowedEvents(params)
      const rows = Array.isArray(res?.data?.data) ? res.data.data : []
      this.hydrateFromEvents(rows)
      this.loaded = true
      return res.data
    },

    async toggle(eventId, options = {}) {
      const auth = useAuthStore()
      const id = Number(eventId)

      if (!Number.isFinite(id)) {
        throw new Error('Invalid event ID')
      }

      if (!auth.isAuthed) {
        throw new Error('AUTH_REQUIRED')
      }

      if (this.loadingIds.has(id)) {
        return this.isFollowed(id)
      }

      const nextFollowed = options.followed ?? !this.isFollowed(id)
      const previous = this.isFollowed(id)

      if (nextFollowed === previous) {
        return previous
      }

      this.setLoading(id, true)
      this.setFollowed(id, nextFollowed)

      try {
        await auth.csrf()
        if (nextFollowed) {
          await followEvent(id)
        } else {
          await unfollowEvent(id)
        }
        this.revision += 1
        return nextFollowed
      } catch (error) {
        this.setFollowed(id, previous)
        throw error
      } finally {
        this.setLoading(id, false)
      }
    },
  },
})
