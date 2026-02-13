import { describe, it, expect, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import SearchResults from '@/components/search/SearchResults.vue'

describe('SearchResults', () => {
  beforeEach(() => {
    window.localStorage.clear()
    window.localStorage.setItem('search_recent_queries', JSON.stringify(['mars', 'lunar eclipse']))
  })

  it('renders recent searches and recommended users when query is empty', async () => {
    const wrapper = mount(SearchResults, {
      props: {
        mode: 'users',
        query: '',
        recommendedUsers: [
          { id: 1, name: 'Marek Nova', username: 'marek' },
          { id: 2, name: 'Luna Sky', username: 'luna' },
        ],
        recommendedPosts: [],
        recommendedLoading: false,
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })

    await nextTick()

    expect(wrapper.text()).toContain('Nedavne hladania')
    expect(wrapper.text()).toContain('mars')
    expect(wrapper.text()).toContain('Odporucane ucty')
    expect(wrapper.text()).toContain('Marek Nova')
  })
})
