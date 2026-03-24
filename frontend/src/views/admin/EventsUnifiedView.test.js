import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import EventsUnifiedView from '@/views/admin/EventsUnifiedView.vue'
import api from '@/services/api'

const refreshMock = vi.hoisted(() => vi.fn())
const setSearchMock = vi.hoisted(() => vi.fn())
const setFilterMock = vi.hoisted(() => vi.fn())
const setFiltersMock = vi.hoisted(() => vi.fn())

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
    setSearch: (...args) => setSearchMock(...args),
    setFilter: (...args) => setFilterMock(...args),
    setFilters: (...args) => setFiltersMock(...args),
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

    expect(document.body.textContent || '').not.toContain('AI: Zlép')
    expect(findBodyButton('Navrhn')).toBeFalsy()
    expect(document.body.querySelector('[data-testid="events-unified-title-apply-btn"]')).toBeFalsy()
  })

  it('does not show AI description panel in published edit form', async () => {
    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Upravi'))
      .trigger('click')
    await flush()

    expect(findBodyButton('AI opis')).toBeFalsy()
    expect(document.body.textContent || '').not.toContain('AI asistent')
  })

  it('submits selected icon_emoji when creating a manual event', async () => {
    api.post.mockResolvedValue({ data: { data: { id: 55 } } })

    const wrapper = mount(EventsUnifiedView)
    await flush()
    await flush()

    const createButton = wrapper
      .findAll('button')
      .find((button) => String(button.text() || '').toLowerCase().includes('nov'))
    expect(createButton).toBeTruthy()
    await createButton.trigger('click')
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
