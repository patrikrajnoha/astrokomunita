import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import CandidateDetailView from './CandidateDetailView.vue'

const getCandidateMock = vi.fn()
const retranslateMock = vi.fn()

vi.mock('@/services/eventCandidates', () => ({
  eventCandidates: {
    get: (...args) => getCandidateMock(...args),
    approve: vi.fn(),
    reject: vi.fn(),
    retranslate: (...args) => retranslateMock(...args),
    updateTranslation: vi.fn(),
  },
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
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

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/candidates/:id',
        name: 'admin.candidate.detail',
        meta: { adminSection: 'events', adminTab: 'candidates' },
        component: CandidateDetailView,
      },
      {
        path: '/admin/events/candidates',
        name: 'admin.event-candidates',
        meta: { adminSection: 'events', adminTab: 'candidates' },
        component: { template: '<div>list</div>' },
      },
      {
        path: '/admin/events/crawling',
        name: 'admin.event-sources',
        meta: { adminSection: 'events', adminTab: 'crawling' },
        component: { template: '<div>sources</div>' },
      },
      {
        path: '/admin/events/published',
        name: 'admin.events',
        meta: { adminSection: 'events', adminTab: 'published' },
        component: { template: '<div>events</div>' },
      },
    ],
  })
}

function mountCandidateDetail(router) {
  return mount(CandidateDetailView, {
    global: {
      plugins: [createPinia(), router],
    },
  })
}

describe('CandidateDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    retranslateMock.mockResolvedValue({ ok: true })
    getCandidateMock.mockResolvedValue({
      id: 44,
      source_name: 'astropixels',
      source_url: 'https://astropixels.test/row',
      source_uid: 'astropixels:lyrids:2026',
      source_hash: 'hash',
      title: 'Lyrids',
      translated_title: 'Lyridy',
      status: 'pending',
      raw_type: 'meteor_shower',
      type: 'meteor_shower',
      canonical_key: 'meteor shower|2026-04-22|lyrids lyr',
      confidence_score: '1.00',
      matched_sources: ['astropixels', 'imo'],
      max_at: '2026-04-22T20:00:00Z',
      start_at: '2026-04-22T20:00:00Z',
      end_at: null,
      short: 'Maximum roja',
      translated_description: 'Slovensky finalny popis',
      description: 'English description',
      translation_status: 'pending',
      translation_error: null,
      translated_at: null,
      reviewed_by: null,
      reviewed_at: null,
      reject_reason: null,
      created_at: '2026-02-20T12:00:00Z',
      updated_at: '2026-02-20T12:00:00Z',
      visibility: null,
      raw_payload: '{"zhr":18}',
      published_event_id: null,
    })
  })

  it('renders confidence and matched source badges', async () => {
    const router = makeRouter()
    await router.push('/admin/candidates/44?page=2&search=lyrids')
    await router.isReady()

    const wrapper = mountCandidateDetail(router)

    await flush()
    await flush()

    const text = wrapper.text()
    expect(text).toContain('1.00')
    expect(text).toContain('meteor shower|2026-04-22|lyrids lyr')
    expect(text).toContain('AstroPixels')
    expect(text).toContain('IMO')
    expect(text).toContain('Udalosti')
    expect(wrapper.find('.adminSectionTabs__tab.active').text()).toContain('Kandidáti')

    const back = wrapper.get('[data-testid="admin-section-back-link"]')
    expect(back.attributes('href')).toContain('/admin/events/candidates?page=2&search=lyrids')
  })

  it('prefers translated title over original title', async () => {
    const router = makeRouter()
    await router.push('/admin/candidates/44')
    await router.isReady()

    const wrapper = mountCandidateDetail(router)

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Lyridy')
    expect(wrapper.text()).toContain('Slovensky finalny popis')
  })

  it('shows loading and success state when retranslate is triggered', async () => {
    let resolveRetranslate
    retranslateMock.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveRetranslate = resolve
        }),
    )

    const router = makeRouter()
    await router.push('/admin/candidates/44')
    await router.isReady()

    const wrapper = mountCandidateDetail(router)

    await flush()
    await flush()

    const button = wrapper
      .findAll('button')
      .find((node) => normalizeText(node.text()).includes('generovat ai popis'))
    expect(button).toBeTruthy()

    await button.trigger('click')
    await flush()
    expect(normalizeText(wrapper.text())).toContain('pracujem')

    resolveRetranslate({ ok: true, candidate: { translation_status: 'done' } })
    await flush()
    await flush()

    expect(retranslateMock).toHaveBeenCalledWith(44, { mode: 'ai' })
    expect(normalizeText(wrapper.text())).toContain('spustene')
  })
})
