import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CreatePostModal from '@/components/CreatePostModal.vue'

const getMock = vi.fn()
const pushMock = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => getMock(...args),
    defaults: { baseURL: 'http://localhost/api' },
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: {
      name: 'Test User',
      avatar_url: null,
    },
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    info: vi.fn(),
    warn: vi.fn(),
    error: vi.fn(),
  }),
}))

vi.mock('@/services/posts', () => ({
  createPost: vi.fn(),
}))

describe('CreatePostModal GIF selection', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    getMock.mockReset()
    pushMock.mockReset()
    getMock.mockResolvedValue({
      data: {
        data: [
          {
            id: 'gif-1',
            title: 'Space GIF',
            preview_url: 'https://example.test/preview.gif',
            original_url: 'https://example.test/original.gif',
            width: 320,
            height: 180,
          },
        ],
      },
    })
  })

  afterEach(() => {
    vi.runOnlyPendingTimers()
    vi.useRealTimers()
  })

  it('keeps parent modal open when selecting a gif from picker', async () => {
    const wrapper = mount(CreatePostModal, {
      props: { open: true },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
        },
      },
    })

    await wrapper.find('button[aria-label="GIF"]').trigger('click')

    const searchInput = wrapper.find('input[placeholder="Hladaj GIF..."]')
    expect(searchInput.exists()).toBe(true)
    await searchInput.setValue('spa')
    await vi.advanceTimersByTimeAsync(500)
    await flushPromises()

    const gifTile = wrapper.find('button.gifTile')
    expect(gifTile.exists()).toBe(true)
    await gifTile.trigger('click')
    await flushPromises()

    expect(wrapper.emitted('close')).toBeFalsy()
    expect(wrapper.find('.subBackdrop').exists()).toBe(false)
    expect(wrapper.find('.contentCol .mediaCard .mediaImg').exists()).toBe(true)

    wrapper.unmount()
  })

  it('contains observation action in more menu and routes to observation create', async () => {
    const wrapper = mount(CreatePostModal, {
      props: { open: true },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
        },
      },
    })

    await wrapper.find('button[aria-label="Viac"]').trigger('click')

    const menuButtons = wrapper.findAll('button.menuBtn')
    const observationButton = menuButtons.find((button) => button.text().includes('Pridať pozorovanie'))
    expect(Boolean(observationButton)).toBe(true)
    if (!observationButton) {
      throw new Error('Observation action button not found')
    }

    await observationButton.trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith('/observations/new')
    expect(wrapper.emitted('close')).toBeTruthy()

    wrapper.unmount()
  })
})
