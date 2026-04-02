import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EventFormView from '@/views/admin/EventFormView.vue'

const apiGetMock = vi.hoisted(() => vi.fn())
const apiPostMock = vi.hoisted(() => vi.fn())
const apiPutMock = vi.hoisted(() => vi.fn())
const routerPushMock = vi.hoisted(() => vi.fn())
const getAdminAiConfigMock = vi.hoisted(() => vi.fn())
const generateAdminEventDescriptionMock = vi.hoisted(() => vi.fn())

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
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function normalizeText(value = '') {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
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
          features: {
            event_description_generate: {
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
  })

  it('does not render title post-edit panel', async () => {
    const wrapper = mount(EventFormView)
    await flush()
    await flush()

    expect(wrapper.text()).not.toContain('AI: Zlép')
    expect(wrapper.findAll('button').find((button) => button.text().includes('Navrhn'))).toBeFalsy()
    expect(wrapper.find('[data-testid="event-title-apply-btn"]').exists()).toBe(false)
  })

  it('still allows AI description action', async () => {
    const wrapper = mount(EventFormView)
    await flush()
    await flush()

    const aiDescriptionButton = wrapper
      .findAll('button')
      .find((button) => normalizeText(button.text()).includes('pouzit navrh'))

    expect(aiDescriptionButton).toBeTruthy()
    await aiDescriptionButton.trigger('click')
    await flush()
    await flush()

    expect(generateAdminEventDescriptionMock).toHaveBeenCalledWith(12, {
      sync: true,
      mode: 'ollama',
      fallback: 'base',
      force: true,
    })
  })

  it('supports dry-run AI preview action', async () => {
    const wrapper = mount(EventFormView)
    await flush()
    await flush()

    const previewButton = wrapper
      .findAll('button')
      .find((button) => normalizeText(button.text()).includes('otestovat navrh'))

    expect(previewButton).toBeTruthy()
    await previewButton.trigger('click')
    await flush()
    await flush()

    expect(generateAdminEventDescriptionMock).toHaveBeenCalledWith(12, {
      sync: true,
      mode: 'ollama',
      fallback: 'skip',
      force: false,
      dry_run: true,
    })
  })
})
