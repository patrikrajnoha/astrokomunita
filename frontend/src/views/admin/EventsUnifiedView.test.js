import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import EventsUnifiedView from '@/views/admin/EventsUnifiedView.vue'
import api from '@/services/api'

const refreshMock = vi.hoisted(() => vi.fn())
const getAdminAiConfigMock = vi.hoisted(() => vi.fn())
const generateAdminEventDescriptionMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api/admin/ai', () => ({
  getAdminAiConfig: (...args) => getAdminAiConfigMock(...args),
  generateAdminEventDescription: (...args) => generateAdminEventDescriptionMock(...args),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}))

vi.mock('@/composables/useAdminTable', () => ({
  useAdminTable: () => ({
    loading: ref(false),
    error: ref(''),
    data: ref({
      data: [
        {
          id: 7,
          title: 'Mars Opposition',
          description: 'Povodny opis',
          short: 'Povodny short',
          type: 'other',
          start_at: '2026-02-24T20:00:00Z',
          end_at: '2026-02-24T21:00:00Z',
          visibility: 1,
        },
      ],
    }),
    pagination: ref({ currentPage: 1, lastPage: 1, total: 1 }),
    hasNextPage: ref(false),
    hasPrevPage: ref(false),
    nextPage: vi.fn(),
    prevPage: vi.fn(),
    perPage: ref(20),
    setPerPage: vi.fn(),
    refresh: (...args) => refreshMock(...args),
  }),
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

function findBodyButton(text) {
  return Array.from(document.body.querySelectorAll('button'))
    .find((button) => (button.textContent || '').includes(text))
}

describe('EventsUnifiedView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    refreshMock.mockResolvedValue(undefined)
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
        data: {
          description: 'AI opis udalosti.',
          short: 'AI short',
          fallback_used: false,
        },
        last_run: {
          status: 'success',
          updated_at: '2026-02-24T10:00:00Z',
        },
      },
    })
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('does not render title AI panel in edit mode', async () => {
    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Upravi'))
      .trigger('click')
    await flush()

    expect(document.body.textContent || '').not.toContain('AI: Zlep')
    expect(findBodyButton('Navrhn')).toBeFalsy()
    expect(document.body.querySelector('[data-testid="events-unified-title-apply-btn"]')).toBeFalsy()
  })

  it('still allows description AI panel via advanced toggle', async () => {
    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Upravi'))
      .trigger('click')
    await flush()

    const toggleButton = findBodyButton('AI opis')
    expect(toggleButton).toBeTruthy()
    toggleButton.click()
    await flush()
    expect(document.body.textContent || '').toContain('AI pomoc')
  })

  it('submits selected icon_emoji when creating a manual event', async () => {
    api.post.mockResolvedValue({ data: { data: { id: 55 } } })

    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => /Nova|Nová/.test(button.text()))
      .trigger('click')
    await flush()

    const bodyInputs = Array.from(document.body.querySelectorAll('input'))
    const titleInput = bodyInputs.find((node) => node.type === 'text')
    const startInput = bodyInputs.find((node) => node.type === 'datetime-local')
    expect(titleInput).toBeTruthy()
    expect(startInput).toBeTruthy()

    titleInput.value = 'Test event with icon'
    titleInput.dispatchEvent(new Event('input'))
    startInput.value = '2026-04-01T20:30'
    startInput.dispatchEvent(new Event('input'))
    await flush()

    const iconSelect = Array.from(document.body.querySelectorAll('select'))
      .find((select) => Array.from(select.options).some((option) => option.textContent?.includes('Automaticky')))
    expect(iconSelect).toBeTruthy()

    iconSelect.value = '\u{1F319}'
    iconSelect.dispatchEvent(new Event('change'))
    await flush()

    const submit = Array.from(document.body.querySelectorAll('button'))
      .find((button) => button.getAttribute('type') === 'submit')
    expect(submit).toBeTruthy()
    submit.click()
    await flush()

    expect(api.post).toHaveBeenCalledWith('/admin/events', expect.objectContaining({
      icon_emoji: '\u{1F319}',
    }))
  })
})
