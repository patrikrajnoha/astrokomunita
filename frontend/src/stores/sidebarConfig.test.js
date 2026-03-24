import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

vi.mock('@/sidebar/engine', () => ({
  getEnabledSidebarSections: (items) => items,
}))

describe('sidebarConfig store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    api.get.mockReset()
  })

  it('returns empty config and does not request API for undefined scope', async () => {
    const store = useSidebarConfigStore()

    const items = await store.fetchScope(undefined)

    expect(api.get).not.toHaveBeenCalled()
    expect(items).toEqual([])
  })

  it('returns empty config and does not request API for null-like scopes', async () => {
    const store = useSidebarConfigStore()

    await store.fetchScope(null)
    await store.fetchScope('undefined')
    await store.fetchScope('null')
    await store.fetchScope('   ')

    expect(api.get).not.toHaveBeenCalled()
  })

  it('requests sidebar config with normalized valid scope', async () => {
    const store = useSidebarConfigStore()
    api.get.mockResolvedValue({
      data: {
        data: [
          { kind: 'builtin', section_key: 'search', title: 'Search', order: 0, is_enabled: true },
        ],
      },
    })

    await store.fetchScope('search')

    expect(api.get).toHaveBeenCalledWith('/sidebar-config', {
      params: { scope: 'search' },
      meta: { skipErrorToast: true },
    })
  })
})
