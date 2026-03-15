import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick, reactive } from 'vue'
import HomeView from './HomeView.vue'

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    isAuthed: true,
    user: { id: 1, name: 'Test User', username: 'test' },
  }),
}))

describe('HomeView', () => {
  it('does not remount FeedList on query-only route changes', async () => {
    const route = reactive({ fullPath: '/' })
    let mountCount = 0

    const FeedListStub = defineComponent({
      name: 'FeedListStub',
      mounted() {
        mountCount += 1
      },
      template: '<div class="feed-list-stub"><slot name="composer" :active-tab="\'for_you\'" /></div>',
    })

    const wrapper = mount(HomeView, {
      global: {
        mocks: {
          $route: route,
        },
        stubs: {
          FeedList: FeedListStub,
          UserAvatar: true,
        },
      },
    })

    expect(mountCount).toBe(1)
    expect(wrapper.text()).toContain('Čo je nové na oblohe?')

    route.fullPath = '/?view=latest'
    await nextTick()

    expect(mountCount).toBe(1)
  })
})
