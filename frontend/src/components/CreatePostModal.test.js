import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CreatePostModal from '@/components/CreatePostModal.vue'

const getMock = vi.fn()
const pushMock = vi.fn()
const createPostMock = vi.fn()

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
  createPost: (...args) => createPostMock(...args),
}))

describe('CreatePostModal GIF selection', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    getMock.mockReset()
    pushMock.mockReset()
    createPostMock.mockReset()
    URL.createObjectURL = vi.fn(() => 'blob:preview-image')
    URL.revokeObjectURL = vi.fn()
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
    createPostMock.mockResolvedValue({ data: { id: 1 } })
  })

  afterEach(() => {
    vi.runOnlyPendingTimers()
    vi.useRealTimers()
  })

  it('does not render GIF picker trigger in current toolbar', async () => {
    const wrapper = mount(CreatePostModal, {
      props: { open: true },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
          ObservationCreateView: { template: '<div data-testid="observation-create-stub"></div>' },
        },
      },
    })

    expect(wrapper.find('button[aria-label="GIF"]').exists()).toBe(false)
    expect(wrapper.emitted('close')).toBeFalsy()
    expect(wrapper.find('.subBackdrop').exists()).toBe(false)

    wrapper.unmount()
  })

  it('shows grouped emoji picker and inserts selected emoji into composer', async () => {
    const wrapper = mount(CreatePostModal, {
      props: { open: true },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
          ObservationCreateView: { template: '<div data-testid="observation-create-stub"></div>' },
        },
      },
    })

    await wrapper.find('button[aria-label="Emoji"]').trigger('click')

    const categoryButtons = wrapper.findAll('button.emojiGroupBtn')
    expect(categoryButtons.length).toBeGreaterThanOrEqual(4)

    const spaceCategory = categoryButtons.find((button) => button.text().includes('Vesmír'))
    expect(Boolean(spaceCategory)).toBe(true)
    if (!spaceCategory) {
      throw new Error('Vesmír category button not found in emoji picker')
    }

    await spaceCategory.trigger('click')
    await flushPromises()

    const emojiButtons = wrapper.findAll('.emojiGrid .emojiBtn')
    expect(emojiButtons.length).toBeGreaterThan(10)

    const textarea = wrapper.get('#composer-textarea')
    expect(textarea.element.value).toBe('')

    await emojiButtons[0].trigger('click')
    await flushPromises()

    expect(textarea.element.value.length).toBeGreaterThan(0)
    expect(wrapper.find('.emojiMenu').exists()).toBe(false)

    wrapper.unmount()
  })

  it('contains observation action in more menu and switches to embedded observation form', async () => {
    const wrapper = mount(CreatePostModal, {
      props: { open: true },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
          ObservationCreateView: { template: '<div data-testid="observation-create-stub"></div>' },
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

    expect(pushMock).not.toHaveBeenCalled()
    expect(wrapper.emitted('close')).toBeFalsy()
    expect(wrapper.find('[data-testid="observation-create-stub"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('closes modal and routes to feed post when embedded observation submit requests it', async () => {
    const wrapper = mount(CreatePostModal, {
      props: { open: true },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
          ObservationCreateView: {
            template: `
              <button
                type="button"
                data-testid="observation-submit-trigger"
                @click="$emit('submitted', { observationId: 12, feedPostId: 44, isPublic: true, openPostAfterCreate: true })"
              >
                submit
              </button>
            `,
          },
        },
      },
    })

    await wrapper.find('button[aria-label="Viac"]').trigger('click')
    const menuButtons = wrapper.findAll('button.menuBtn')
    const observationButton = menuButtons.at(-1)
    if (!observationButton) {
      throw new Error('Observation action button not found')
    }

    await observationButton.trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="observation-submit-trigger"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith('/posts/44')
    expect(wrapper.emitted('close')).toBeTruthy()

    wrapper.unmount()
  })

  it('prefills selected image when opened with initial attachment file', async () => {
    const shortcutFile = new File(['image-bytes'], 'shortcut.png', { type: 'image/png' })
    const wrapper = mount(CreatePostModal, {
      props: {
        open: true,
        initialAttachmentFile: shortcutFile,
      },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
          ObservationCreateView: { template: '<div data-testid="observation-create-stub"></div>' },
        },
      },
    })

    await flushPromises()

    const previewImage = wrapper.find('.contentCol .mediaCard .mediaImg')
    expect(previewImage.exists()).toBe(true)
    expect(previewImage.attributes('src')).toBe('blob:preview-image')

    wrapper.unmount()
  })

  it('shows upload-specific userMessage instead of generic submit error', async () => {
    createPostMock.mockRejectedValueOnce({
      userMessage: 'Nahravanie zlyhalo. Upload bol odmietnuty alebo je prilis velky.',
    })

    const wrapper = mount(CreatePostModal, {
      props: { open: true },
      attachTo: document.body,
      global: {
        stubs: {
          teleport: true,
          PollComposerPanel: true,
          ObservationCreateView: { template: '<div data-testid="observation-create-stub"></div>' },
        },
      },
    })

    await wrapper.get('#composer-textarea').setValue('Test príspevok')
    await wrapper.find('button.primary').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Nahravanie zlyhalo. Upload bol odmietnuty alebo je prilis velky.')

    wrapper.unmount()
  })
})
