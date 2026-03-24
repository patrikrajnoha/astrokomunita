import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminNewsletterView from '@/views/admin/AdminNewsletterView.vue'

const getNewsletterPreviewMock = vi.hoisted(() => vi.fn())
const getNewsletterRunsMock = vi.hoisted(() => vi.fn())
const sendNewsletterPreviewMock = vi.hoisted(() => vi.fn())
const sendNewsletterMock = vi.hoisted(() => vi.fn())
const updateNewsletterFeaturedEventsMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())

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

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('AdminNewsletterView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.localStorage.clear()

    getNewsletterPreviewMock.mockResolvedValue({
      data: {
        data: {
          week: { start: '2026-02-23', end: '2026-03-01' },
          selection: {
            mode: 'manual',
            admin_selected_event_ids: [11],
          },
          top_events: [{ id: 11, title: 'Lunar eclipse', start_at: '2026-02-24T19:00:00Z' }],
          top_articles: [{ id: 22, title: 'Sky guide', views: 120 }],
          astronomical_tip: 'Use darker skies.',
          cta: {
            calendar_url: 'https://example.com/calendar',
            events_url: 'https://example.com/events',
          },
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

  it('renders clean linear flow without payload section', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    expect(getNewsletterPreviewMock).toHaveBeenCalledTimes(1)
    expect(getNewsletterRunsMock).toHaveBeenCalledTimes(1)
    expect(getEventsMock).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('1. Obsah')
    expect(wrapper.text()).toContain('2. Text')
    expect(wrapper.text()).toContain('3. Nahlad')
    expect(wrapper.text()).toContain('4. Akcie')
    expect(wrapper.text()).toContain('Lunar eclipse')
    expect(wrapper.text()).toContain('Sky guide')
    expect(wrapper.text()).not.toContain('Nahlad payloadu')
  })

  it('renders text defaults and test email action', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    expect(wrapper.get('input[type="email"]').exists()).toBe(true)
    expect(wrapper.get('input.copyInput').element.value).toBe('Nebesky sprievodca: Tyzdenny newsletter')

    const textareas = wrapper.findAll('textarea.copyTextarea')
    expect(textareas).toHaveLength(2)
    expect(textareas[0].element.value).toBe('Prehlad na tyzden 2026-02-23 az 2026-03-01.')
    expect(textareas[1].element.value).toBe('Use darker skies.')
  })

  it('sends test email with default overrides', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    await wrapper.get('input[type="email"]').setValue('preview@example.com')

    const testButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('test'))

    expect(testButton).toBeTruthy()
    await testButton.trigger('click')
    await flush()

    expect(sendNewsletterPreviewMock).toHaveBeenCalledWith({
      email: 'preview@example.com',
      subject_override: 'Nebesky sprievodca: Tyzdenny newsletter',
      intro_override: 'Prehlad na tyzden 2026-02-23 az 2026-03-01.',
      tip_override: 'Use darker skies.',
    })
  })

  it('sends manually edited copy as test overrides', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    await wrapper.get('input.copyInput').setValue('Custom subject')
    const textareas = wrapper.findAll('textarea.copyTextarea')
    await textareas[0].setValue('Custom intro')
    await textareas[1].setValue('Custom tip')

    await wrapper.get('input[type="email"]').setValue('preview@example.com')

    const testButton = wrapper
      .findAll('button')
      .find((button) => button.text().toLowerCase().includes('test'))

    expect(testButton).toBeTruthy()
    await testButton.trigger('click')
    await flush()

    expect(sendNewsletterPreviewMock).toHaveBeenCalledWith({
      email: 'preview@example.com',
      subject_override: 'Custom subject',
      intro_override: 'Custom intro',
      tip_override: 'Custom tip',
    })
  })

  it('autosaves text draft and restores it after remount', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    await wrapper.get('input.copyInput').setValue('Draft subject')
    const textareas = wrapper.findAll('textarea.copyTextarea')
    await textareas[0].setValue('Draft intro')
    await textareas[1].setValue('Draft tip')
    await flush()

    const draftRaw = window.localStorage.getItem('admin.newsletter.copy_draft.v1')
    expect(draftRaw).toBeTruthy()
    const draftPayload = JSON.parse(String(draftRaw))
    expect(draftPayload.subject).toBe('Draft subject')
    expect(draftPayload.intro).toBe('Draft intro')
    expect(draftPayload.tip).toBe('Draft tip')

    wrapper.unmount()

    const wrapperAgain = mount(AdminNewsletterView)
    await flush()
    await flush()

    expect(wrapperAgain.get('input.copyInput').element.value).toBe('Draft subject')
    const restoredTextareas = wrapperAgain.findAll('textarea.copyTextarea')
    expect(restoredTextareas[0].element.value).toBe('Draft intro')
    expect(restoredTextareas[1].element.value).toBe('Draft tip')
  })
})
