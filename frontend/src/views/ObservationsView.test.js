import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import ObservationsView from './ObservationsView.vue'

const authMock = vi.hoisted(() => ({
  isAuthed: true,
}))

const listObservationsMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/observations', () => ({
  listObservations: listObservationsMock,
}))

vi.mock('@/services/events', () => ({
  getEvents: getEventsMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
      { path: '/observations', name: 'observations', component: ObservationsView },
      { path: '/observations/:id', name: 'observations.detail', component: { template: '<div>detail</div>' } },
      { path: '/observations/new', name: 'observations.create', component: { template: '<div>create</div>' } },
    ],
  })
}

describe('ObservationsView filters', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getEventsMock.mockResolvedValue({
      data: {
        data: [
          { id: 5, title: 'Meteor Shower Meetup' },
        ],
      },
    })

    listObservationsMock.mockResolvedValue({
      data: {
        data: [],
        current_page: 1,
        last_page: 1,
      },
    })
  })

  it('loads observations with mine filter by default and applies filter changes', async () => {
    const router = makeRouter()
    await router.push('/observations')
    await router.isReady()

    const wrapper = mount(ObservationsView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    expect(listObservationsMock).toHaveBeenCalledWith(expect.objectContaining({
      mine: 1,
      sort: 'newest',
      page: 1,
      per_page: 12,
    }))

    await wrapper.get('.toggle-filter').trigger('click')
    await flush()
    expect(listObservationsMock.mock.calls.at(-1)?.[0]).toEqual(expect.objectContaining({
      mine: 0,
      sort: 'newest',
    }))

    const selects = wrapper.findAll('select')
    await selects[1].setValue('oldest')
    await flush()
    expect(listObservationsMock.mock.calls.at(-1)?.[0]).toEqual(expect.objectContaining({
      sort: 'oldest',
    }))

    await selects[0].setValue('5')
    await flush()
    expect(listObservationsMock.mock.calls.at(-1)?.[0]).toEqual(expect.objectContaining({
      event_id: '5',
    }))
  })

  it('dedupes items when paginating', async () => {
    listObservationsMock
      .mockResolvedValueOnce({
        data: {
          data: [
            { id: 11, title: 'A', observed_at: '2026-03-05T10:00:00Z', media: [] },
            { id: 12, title: 'B', observed_at: '2026-03-05T09:00:00Z', media: [] },
          ],
          current_page: 1,
          last_page: 2,
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [
            { id: 12, title: 'B', observed_at: '2026-03-05T09:00:00Z', media: [] },
            { id: 13, title: 'C', observed_at: '2026-03-05T08:00:00Z', media: [] },
          ],
          current_page: 2,
          last_page: 2,
        },
      })

    const router = makeRouter()
    await router.push('/observations')
    await router.isReady()

    const wrapper = mount(ObservationsView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await wrapper.get('.load-more-btn').trigger('click')
    await flush()

    expect(wrapper.findAll('.observation-card')).toHaveLength(3)
    expect(listObservationsMock).toHaveBeenNthCalledWith(2, expect.objectContaining({
      page: 2,
    }))
  })
})
