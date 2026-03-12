import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminNewsletterView from '@/views/admin/AdminNewsletterView.vue'

const getNewsletterPreviewMock = vi.hoisted(() => vi.fn())
const getNewsletterRunsMock = vi.hoisted(() => vi.fn())
const sendNewsletterPreviewMock = vi.hoisted(() => vi.fn())
const sendNewsletterMock = vi.hoisted(() => vi.fn())
const updateNewsletterFeaturedEventsMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())
const getAdminAiConfigMock = vi.hoisted(() => vi.fn())
const primeNewsletterInsightsMock = vi.hoisted(() => vi.fn())
const draftNewsletterCopyMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api/admin/newsletter', () => ({
  getNewsletterPreview: (...args) => getNewsletterPreviewMock(...args),
  getNewsletterRuns: (...args) => getNewsletterRunsMock(...args),
  sendNewsletterPreview: (...args) => sendNewsletterPreviewMock(...args),
  sendNewsletter: (...args) => sendNewsletterMock(...args),
  updateNewsletterFeaturedEvents: (...args) => updateNewsletterFeaturedEventsMock(...args),
}))

vi.mock('@/services/api/admin/events', () => ({
  getEvents: (...args) => getEventsMock(...args),
}))

vi.mock('@/services/api/admin/ai', () => ({
  getAdminAiConfig: (...args) => getAdminAiConfigMock(...args),
  primeNewsletterInsights: (...args) => primeNewsletterInsightsMock(...args),
  draftNewsletterCopy: (...args) => draftNewsletterCopyMock(...args),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('AdminNewsletterView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getNewsletterPreviewMock.mockResolvedValue({
      data: {
        data: {
          week: { start: '2026-02-23', end: '2026-03-01' },
          top_events: [{ id: 11, title: 'Lunar eclipse' }],
          top_articles: [{ id: 22, title: 'Sky guide', views: 120 }],
          astronomical_tip: 'Use darker skies.',
        },
        meta: { max_featured_events: 10 },
      },
    })

    getNewsletterRunsMock.mockResolvedValue({
      data: {
        data: [],
      },
    })

    getEventsMock.mockResolvedValue({
      data: {
        data: [{ id: 11, title: 'Lunar eclipse', start_at: '2026-02-24T19:00:00Z' }],
      },
    })

    getAdminAiConfigMock.mockResolvedValue({
      data: {
        data: {
          events_ai_humanized_enabled: true,
          insights_cache_ttl_seconds: 2592000,
          features: {
            newsletter_prime_insights: {
              last_run: null,
            },
            newsletter_copy_draft: {
              enabled: true,
              last_run: null,
            },
          },
        },
      },
    })

    sendNewsletterPreviewMock.mockResolvedValue({
      data: {
        ok: true,
        data: {
          email: 'preview@example.com',
          events_count: 1,
          articles_count: 1,
        },
      },
    })
  })

  it('loads preview payload and renders exactly one AI panel', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    expect(getNewsletterPreviewMock).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Lunar eclipse')
    expect(wrapper.text()).toContain('Sky guide')
    expect(wrapper.findAll('.aiPanel')).toHaveLength(1)
  })

  it('renders preview send form', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    expect(wrapper.get('input[type="email"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('preview')
  })

  it('keeps newsletter page usable when AI config endpoint is forbidden', async () => {
    getAdminAiConfigMock.mockRejectedValue({
      response: {
        status: 403,
      },
    })

    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    expect(getNewsletterPreviewMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('.alert-error').exists()).toBe(false)
    expect(wrapper.text()).toContain('Lunar eclipse')
    expect(wrapper.findAll('.aiPanel')).toHaveLength(1)
  })

  it('sends preview email via admin endpoint', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    await wrapper.get('input[type="email"]').setValue('preview@example.com')

    const previewButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('preview'))
    expect(previewButton).toBeTruthy()

    await previewButton.trigger('click')
    await flush()

    expect(sendNewsletterPreviewMock).toHaveBeenCalledWith({
      email: 'preview@example.com',
      subject_override: 'Nebesky sprievodca: Tyzdenny newsletter',
      intro_override: 'Prehlad na tyzden 2026-02-23 az 2026-03-01.',
      tip_override: 'Use darker skies.',
    })
  })

  it('runs AI insights priming and reloads preview payload', async () => {
    primeNewsletterInsightsMock.mockResolvedValue({
      data: {
        status: 'done',
        data: {
          requested_limit: 5,
          processed: 1,
          primed: 1,
          fallback: 0,
          failed: 0,
        },
        last_run: {
          status: 'success',
          latency_ms: 120,
          updated_at: '2026-02-20T10:00:00Z',
        },
      },
    })

    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    const aiRunButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('pripravi'))

    expect(aiRunButton).toBeTruthy()
    await aiRunButton.trigger('click')
    await flush()
    await flush()

    expect(primeNewsletterInsightsMock).toHaveBeenCalledWith({ limit: 5 })
    expect(getNewsletterPreviewMock).toHaveBeenCalledTimes(2)
  })

  it('shows lock message with retry seconds', async () => {
    primeNewsletterInsightsMock.mockRejectedValue({
      response: {
        status: 409,
        data: {
          status: 'locked',
          message: 'Insights priming is already running. Retry shortly.',
          retry_after_seconds: 42,
        },
      },
    })

    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    const aiRunButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('pripravi'))

    expect(aiRunButton).toBeTruthy()
    await aiRunButton.trigger('click')
    await flush()
    await flush()

    expect(wrapper.text()).toContain('Skus znova o 42 s.')
  })

  it('renders draft copy subjects with default first option selected', async () => {
    draftNewsletterCopyMock.mockResolvedValue({
      data: {
        status: 'success',
        fallback_used: false,
        subjects: ['Subject A', 'Subject B', 'Subject C'],
        intro: 'Intro text.',
        tip_text: 'Tip text.',
        last_run: {
          status: 'success',
          updated_at: '2026-02-20T10:00:00Z',
          latency_ms: 80,
          retry_count: 0,
        },
      },
    })

    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    const draftButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('navrhnut predmet'))
    expect(draftButton).toBeTruthy()

    await draftButton.trigger('click')
    await flush()
    await flush()

    const subjectRadios = wrapper.findAll('input[type="radio"][name="ai-subject-choice"]')
    expect(subjectRadios).toHaveLength(3)
    expect(subjectRadios[0].element.checked).toBe(true)
    expect(wrapper.text()).toContain('Subject A')
    expect(wrapper.text()).toContain('Subject B')
    expect(wrapper.text()).toContain('Subject C')
  })

  it('applies selected draft copy to subject and intro fields', async () => {
    draftNewsletterCopyMock.mockResolvedValue({
      data: {
        status: 'success',
        fallback_used: false,
        subjects: ['Subject A', 'Subject B', 'Subject C'],
        intro: 'Intro text.',
        tip_text: 'Tip text.',
        last_run: {
          status: 'success',
          updated_at: '2026-02-20T10:00:00Z',
          latency_ms: 80,
          retry_count: 0,
        },
      },
    })

    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    const draftButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('navrhnut predmet'))
    expect(draftButton).toBeTruthy()
    await draftButton.trigger('click')
    await flush()
    await flush()

    const subjectRadios = wrapper.findAll('input[type="radio"][name="ai-subject-choice"]')
    await subjectRadios[1].setValue()
    await flush()

    const applyButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase() === 'pouzit')
    expect(applyButton).toBeTruthy()
    await applyButton.trigger('click')
    await flush()

    const subjectInput = wrapper.find('input.copyInput')
    const introTextarea = wrapper.findAll('textarea.copyTextarea')[0]

    expect(subjectInput.element.value).toBe('Subject B')
    expect(introTextarea.element.value).toBe('Intro text.')
  })

  it('sends applied AI copy as preview overrides', async () => {
    draftNewsletterCopyMock.mockResolvedValue({
      data: {
        status: 'success',
        fallback_used: false,
        subjects: ['Subject A', 'Subject B', 'Subject C'],
        intro: 'AI Intro.',
        tip_text: 'AI Tip.',
        last_run: {
          status: 'success',
          updated_at: '2026-02-20T10:00:00Z',
          latency_ms: 80,
          retry_count: 0,
        },
      },
    })

    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    const draftButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('navrhnut predmet'))
    expect(draftButton).toBeTruthy()
    await draftButton.trigger('click')
    await flush()
    await flush()

    const applyButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase() === 'pouzit')
    expect(applyButton).toBeTruthy()
    await applyButton.trigger('click')
    await flush()

    await wrapper.get('input[type="email"]').setValue('preview@example.com')
    const previewButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('preview'))
    expect(previewButton).toBeTruthy()

    await previewButton.trigger('click')
    await flush()

    expect(sendNewsletterPreviewMock).toHaveBeenCalledWith({
      email: 'preview@example.com',
      subject_override: 'Subject A',
      intro_override: 'AI Intro.',
      tip_override: 'AI Tip.',
    })
  })
})
