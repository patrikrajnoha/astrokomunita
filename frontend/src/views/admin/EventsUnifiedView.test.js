import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import EventsUnifiedView from '@/views/admin/EventsUnifiedView.vue'

const refreshMock = vi.hoisted(() => vi.fn())
const getAdminAiConfigMock = vi.hoisted(() => vi.fn())
const generateAdminEventDescriptionMock = vi.hoisted(() => vi.fn())
const postEditAdminEventTitleMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api/admin/ai', () => ({
  getAdminAiConfig: (...args) => getAdminAiConfigMock(...args),
  generateAdminEventDescription: (...args) => generateAdminEventDescriptionMock(...args),
  postEditAdminEventTitle: (...args) => postEditAdminEventTitleMock(...args),
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

    postEditAdminEventTitleMock.mockResolvedValue({
      data: {
        status: 'success',
        mode: 'preview',
        suggested_title_sk: 'Mars v opozicii',
        fallback_used: false,
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

  it('renders title panel and Navrhnut action in edit mode', async () => {
    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Upravi'))
      .trigger('click')
    await flush()

    const suggestButton = findBodyButton('Navrhn')

    expect(document.body.textContent || '').toContain('AI: Zlep')
    expect(suggestButton).toBeTruthy()
  })

  it('preview success shows proposal and apply button', async () => {
    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Upravi'))
      .trigger('click')
    await flush()

    const suggestButton = findBodyButton('Navrhn')
    expect(suggestButton).toBeTruthy()
    suggestButton.click()
    await flush()
    await flush()

    expect(postEditAdminEventTitleMock).toHaveBeenCalledWith(7, { mode: 'preview' })
    expect(document.body.textContent || '').toContain('Mars Opposition')
    expect(document.body.textContent || '').toContain('Mars v opozicii')
    expect(document.body.querySelector('[data-testid="events-unified-title-apply-btn"]')).toBeTruthy()
  })

  it('apply updates title input and undo restores original value', async () => {
    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Upravi'))
      .trigger('click')
    await flush()

    const titleInput = document.body.querySelector('input[type="text"]')
    expect(titleInput).toBeTruthy()
    expect(titleInput.value).toBe('Mars Opposition')

    const suggestButton = findBodyButton('Navrhn')
    expect(suggestButton).toBeTruthy()
    suggestButton.click()
    await flush()
    await flush()

    const applyButton = document.body.querySelector('[data-testid="events-unified-title-apply-btn"]')
    expect(applyButton).toBeTruthy()
    applyButton.click()
    await flush()

    const updatedTitleInput = document.body.querySelector('input[type="text"]')
    expect(updatedTitleInput).toBeTruthy()
    expect(updatedTitleInput.value).toBe('Mars v opozicii')

    const undoButton = findBodyButton('Undo')
    expect(undoButton).toBeTruthy()
    undoButton.click()
    await flush()

    const restoredTitleInput = document.body.querySelector('input[type="text"]')
    expect(restoredTitleInput).toBeTruthy()
    expect(restoredTitleInput.value).toBe('Mars Opposition')
  })

  it('shows fallback badge when preview uses fallback', async () => {
    postEditAdminEventTitleMock.mockResolvedValueOnce({
      data: {
        status: 'fallback',
        mode: 'preview',
        suggested_title_sk: 'Mars Opposition',
        fallback_used: true,
        last_run: {
          status: 'fallback',
          updated_at: '2026-02-24T10:00:00Z',
        },
      },
    })

    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Upravi'))
      .trigger('click')
    await flush()

    const suggestButton = findBodyButton('Navrhn')
    expect(suggestButton).toBeTruthy()
    suggestButton.click()
    await flush()
    await flush()

    expect((document.body.textContent || '').toLowerCase()).toContain('fallback')
  })
})
