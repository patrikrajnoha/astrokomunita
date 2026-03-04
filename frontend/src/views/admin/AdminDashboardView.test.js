import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import AdminDashboardView from '@/views/admin/AdminDashboardView.vue'

const getStatsMock = vi.fn()
const downloadStatsCsvMock = vi.fn()
const getAuthSettingsMock = vi.fn()
const updateAuthSettingsMock = vi.fn()
const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()

vi.mock('@/services/api/admin/stats', () => ({
  getStats: (...args) => getStatsMock(...args),
  downloadStatsCsv: (...args) => downloadStatsCsvMock(...args),
}))

vi.mock('@/services/api/admin/authSettings', () => ({
  getAuthSettings: (...args) => getAuthSettingsMock(...args),
  updateAuthSettings: (...args) => updateAuthSettingsMock(...args),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: (...args) => toastSuccessMock(...args),
    error: (...args) => toastErrorMock(...args),
  }),
}))

const statsPayload = {
  kpi: {
    users_total: 123,
    users_active_30d: 45,
    posts_total: 999,
    events_total: 321,
    posts_moderated_total: 77,
  },
  demographics: {
    by_role: { user: 120, admin: 2, bot: 1 },
    by_region: { unknown: 80, sk: 30, cz: 10, other: 3 },
  },
  trend: {
    range_days: 30,
    points: Array.from({ length: 30 }).map((_, idx) => ({
      date: `2026-02-${String(idx + 1).padStart(2, '0')}`,
      new_users: idx % 3,
      new_posts: idx + 2,
      new_events: idx % 2,
    })),
  },
  generated_at: '2026-02-17T10:00:00Z',
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/dashboard', name: 'admin.dashboard', component: AdminDashboardView },
      { path: '/admin/community/users', name: 'admin.users', component: { template: '<div>users</div>' } },
      { path: '/admin/community/moderation', name: 'admin.moderation', component: { template: '<div>moderation</div>' } },
      { path: '/admin/events/published', name: 'admin.events', component: { template: '<div>events</div>' } },
      { path: '/admin/events/crawling', name: 'admin.event-sources', component: { template: '<div>sources</div>' } },
      { path: '/admin/events/candidates', name: 'admin.event-candidates', component: { template: '<div>candidates</div>' } },
    ],
  })
}

describe('AdminDashboardView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getStatsMock.mockResolvedValue(statsPayload)
    getAuthSettingsMock.mockResolvedValue({
      data: {
        data: {
          require_email_verification_for_new_users: true,
        },
      },
    })
    updateAuthSettingsMock.mockResolvedValue({
      data: {
        data: {
          require_email_verification_for_new_users: false,
        },
      },
    })
    downloadStatsCsvMock.mockResolvedValue({
      blob: new Blob(['section,metric,value']),
      filename: 'admin_stats.csv',
    })
  })

  it('renders KPI cards from mocked API', async () => {
    const router = makeRouter()
    await router.push('/admin/dashboard')
    await router.isReady()

    const wrapper = mount(AdminDashboardView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Používatelia')
    expect(wrapper.text()).toContain('123')
    expect(wrapper.text()).toContain('Aktívni (30 dní)')
    expect(wrapper.text()).toContain('45')
  })

  it('export button triggers download method once', async () => {
    if (!URL.createObjectURL) {
      URL.createObjectURL = () => 'blob:test'
    }
    if (!URL.revokeObjectURL) {
      URL.revokeObjectURL = () => {}
    }

    const createObjectUrlSpy = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:test')
    const revokeObjectUrlSpy = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
    const anchorClickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => {})

    const router = makeRouter()
    await router.push('/admin/dashboard')
    await router.isReady()

    const wrapper = mount(AdminDashboardView, {
      global: { plugins: [router] },
      attachTo: document.body,
    })

    await flush()
    await flush()

    const button = wrapper.findAll('button').find((node) => node.text().includes('Export CSV'))
    expect(button).toBeTruthy()

    await button.trigger('click')
    await flush()

    expect(downloadStatsCsvMock).toHaveBeenCalledTimes(1)

    createObjectUrlSpy.mockRestore()
    revokeObjectUrlSpy.mockRestore()
    anchorClickSpy.mockRestore()
    wrapper.unmount()
  })

  it('graph renders in trend section', async () => {
    const router = makeRouter()
    await router.push('/admin/dashboard')
    await router.isReady()

    const wrapper = mount(AdminDashboardView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    expect(wrapper.find('[aria-label="Graf trendu"]').exists()).toBe(true)
  })

  it('toggles email verification setting from dashboard', async () => {
    const router = makeRouter()
    await router.push('/admin/dashboard')
    await router.isReady()

    const wrapper = mount(AdminDashboardView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    const checkbox = wrapper.find('section[aria-label="Overenie e-mailu"] input[type="checkbox"]')
    expect(checkbox.exists()).toBe(true)
    expect(checkbox.element.checked).toBe(true)

    await checkbox.setValue(false)
    await flush()

    expect(updateAuthSettingsMock).toHaveBeenCalledWith({
      require_email_verification_for_new_users: false,
    })
  })
})
