import { describe, it, expect, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import SearchResults from '@/components/search/SearchResults.vue'

describe('SearchResults', () => {
  beforeEach(() => {
    window.localStorage.clear()
    window.localStorage.setItem('search_recent_queries', JSON.stringify({
      items: ['mars', 'lunar eclipse'],
      savedAt: new Date().toISOString(),
    }))
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

  it('clears expired recent search history based on TTL', async () => {
    const expiredSavedAt = new Date(Date.now() - (31 * 24 * 60 * 60 * 1000)).toISOString()
    window.localStorage.setItem('search_recent_queries', JSON.stringify({
      items: ['expired-query'],
      savedAt: expiredSavedAt,
    }))

    const wrapper = mount(SearchResults, {
      props: {
        mode: 'users',
        query: '',
        recommendedUsers: [],
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

    expect(wrapper.text()).not.toContain('expired-query')
    expect(window.localStorage.getItem('search_recent_queries')).toBeNull()
  })

  it('clears recent history when user clicks clear control', async () => {
    const wrapper = mount(SearchResults, {
      props: {
        mode: 'users',
        query: '',
        recommendedUsers: [],
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

    const clearButton = wrapper.findAll('button').find((btn) => btn.text().includes('Vymaza'))
    expect(clearButton).toBeDefined()
    await clearButton.trigger('click')
    await nextTick()

    expect(window.localStorage.getItem('search_recent_queries')).toBeNull()
    expect(wrapper.text()).toContain('Zatial nemas ziadne nedavne hladania.')
  })
})
