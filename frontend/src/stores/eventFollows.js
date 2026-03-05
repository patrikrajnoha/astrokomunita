import { defineStore } from 'pinia'

export const useEventFollowsStore = defineStore('eventFollows', {
  state: () => ({
    revision: 0,
    followedById: {},
  }),

  actions: {
    hydrateFromEvents(events) {
      const rows = Array.isArray(events) ? events : []
      const next = {}

      rows.forEach((eventItem) => {
        const id = Number(eventItem?.id || 0)
        if (!Number.isInteger(id) || id <= 0) return
        const followed = Boolean(eventItem?.is_followed || eventItem?.followed_by_me)
        next[id] = followed
      })

      this.followedById = next
      this.revision += 1
    },
  },
})
