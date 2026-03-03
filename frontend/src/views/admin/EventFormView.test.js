import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EventFormView from '@/views/admin/EventFormView.vue'

const apiGetMock = vi.hoisted(() => vi.fn())
const apiPostMock = vi.hoisted(() => vi.fn())
const apiPutMock = vi.hoisted(() => vi.fn())
const routerPushMock = vi.hoisted(() => vi.fn())
const getAdminAiConfigMock = vi.hoisted(() => vi.fn())
const generateAdminEventDescriptionMock = vi.hoisted(() => vi.fn())
const postEditAdminEventTitleMock = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { id: '12' },
  }),
  useRouter: () => ({
    push: (...args) => routerPushMock(...args),
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    post: (...args) => apiPostMock(...args),
    put: (...args) => apiPutMock(...args),
  },
}))

vi.mock('@/services/api/admin/ai', () => ({
  getAdminAiConfig: (...args) => getAdminAiConfigMock(...args),
  generateAdminEventDescription: (...args) => generateAdminEventDescriptionMock(...args),
  postEditAdminEventTitle: (...args) => postEditAdminEventTitleMock(...args),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('EventFormView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    apiGetMock.mockResolvedValue({
      data: {
        data: {
          id: 12,
          title: 'First Quarter Moon',
          description: 'Povodny popis',
          type: 'other',
          start_at: '2026-02-24T20:00:00Z',
          end_at: '2026-02-24T21:00:00Z',
          visibility: 1,
        },
      },
    })

    getAdminAiConfigMock.mockResolvedValue({
      data: {
        data: {
          events_ai_humanized_enabled: true,
          events_ai_title_postedit_enabled: true,
          features: {
            event_description_generate: {
              last_run: null,
            },
            event_title_postedit: {
              last_run: null,
            },
          },
        },
      },
    })

    generateAdminEventDescriptionMock.mockResolvedValue({
      data: {
        status: 'done',
        data: {
          description: 'AI popis udalosti.',
          short: 'AI short.',
          fallback_used: false,
        },
        last_run: {
          status: 'success',
          latency_ms: 90,
          updated_at: '2026-02-21T10:00:00Z',
        },
      },
    })

    postEditAdminEventTitleMock.mockResolvedValue({
      data: {
        status: 'success',
        mode: 'preview',
        suggested_title_sk: 'Prva stvrt Mesiaca',
        fallback_used: false,
        last_run: {
          status: 'success',
          latency_ms: 40,
          updated_at: '2026-02-21T11:00:00Z',
        },
      },
    })
  })

  it('renders title post-edit panel with Navrhnut action', async () => {
    const wrapper = mount(EventFormView)
    await flush()
    await flush()

    const suggestButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Navrhn'))

    expect(wrapper.text()).toContain('AI: Zlep')
    expect(suggestButton).toBeTruthy()
  })

  it('preview success shows proposal and apply button', async () => {
    const wrapper = mount(EventFormView)
    await flush()
    await flush()

    const suggestButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Navrhn'))

    expect(suggestButton).toBeTruthy()
    await suggestButton.trigger('click')
    await flush()
    await flush()

    expect(postEditAdminEventTitleMock).toHaveBeenCalledWith(12, {
      mode: 'preview',
    })
    expect(wrapper.text()).toContain('First Quarter Moon')
    expect(wrapper.text()).toContain('Prva stvrt Mesiaca')
    expect(wrapper.find('[data-testid="event-title-apply-btn"]').exists()).toBe(true)
  })

  it('apply updates title input and undo restores the original title', async () => {
    const wrapper = mount(EventFormView)
    await flush()
    await flush()

    expect(wrapper.find('input[type="text"]').element.value).toBe('First Quarter Moon')

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Navrhn'))
      .trigger('click')
    await flush()
    await flush()

    await wrapper.find('[data-testid="event-title-apply-btn"]').trigger('click')
    await flush()

    expect(wrapper.find('input[type="text"]').element.value).toBe('Prva stvrt Mesiaca')

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Undo'))
      .trigger('click')
    await flush()

    expect(wrapper.find('input[type="text"]').element.value).toBe('First Quarter Moon')
  })

  it('shows fallback badge when preview returns fallback', async () => {
    postEditAdminEventTitleMock.mockResolvedValueOnce({
      data: {
        status: 'fallback',
        mode: 'preview',
        suggested_title_sk: 'First Quarter Moon',
        fallback_used: true,
        last_run: {
          status: 'fallback',
          latency_ms: 32,
          updated_at: '2026-02-21T11:00:00Z',
        },
      },
    })

    const wrapper = mount(EventFormView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Navrhn'))
      .trigger('click')
    await flush()
    await flush()

    expect(wrapper.text().toLowerCase()).toContain('fallback')
  })
})
