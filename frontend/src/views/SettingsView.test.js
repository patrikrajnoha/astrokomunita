import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { mount } from '@vue/test-utils'

import SettingsView from './SettingsView.vue'
import SettingsActivityView from './settings/SettingsActivityView.vue'
import SettingsDataExportView from './settings/SettingsDataExportView.vue'
import SettingsEmailView from './settings/SettingsEmailView.vue'
import SettingsNavigationView from './settings/SettingsNavigationView.vue'
import SettingsNewsletterView from './settings/SettingsNewsletterView.vue'
import SettingsOnboardingView from './settings/SettingsOnboardingView.vue'
import SettingsSidebarWidgetsView from './settings/SettingsSidebarWidgetsView.vue'
import SettingsPasswordView from './settings/SettingsPasswordView.vue'
import SettingsDeactivateView from './settings/SettingsDeactivateView.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { createPinia } from 'pinia'

const authMock = vi.hoisted(() => ({
  user: {
    id: 1,
    name: 'Tester',
    email: 'tester@example.com',
    newsletter_subscribed: false,
  },
  initialized: true,
  csrf: vi.fn(async () => {}),
  fetchUser: vi.fn(async () => {}),
  logout: vi.fn(async () => {}),
  isAdmin: false,
}))

const httpMock = vi.hoisted(() => ({
  patch: vi.fn(),
  put: vi.fn(),
  delete: vi.fn(),
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/stores/onboardingTour', () => ({
  useOnboardingTourStore: () => ({
    restartTour: vi.fn(),
    isOpen: false,
    shouldAutoOpen: false,
    hydrate: vi.fn(),
    openTour: vi.fn(),
    closeTour: vi.fn(),
  }),
}))

vi.mock('@/services/api', () => ({
  default: httpMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeDataTransfer() {
  return {
    effectAllowed: 'move',
    setData: () => {},
  }
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/settings',
        component: SettingsView,
        children: [
          { path: '', name: 'settings', component: SettingsNavigationView },
          { path: 'onboarding', name: 'settings.onboarding', component: SettingsOnboardingView },
          { path: 'sidebar-widgets', name: 'settings.sidebar-widgets', component: SettingsSidebarWidgetsView },
          { path: 'email', name: 'settings.email', component: SettingsEmailView },
          { path: 'newsletter', name: 'settings.newsletter', component: SettingsNewsletterView },
          { path: 'data-export', name: 'settings.data-export', component: SettingsDataExportView },
          { path: 'password', name: 'settings.password', component: SettingsPasswordView },
          { path: 'activity', name: 'settings.activity', component: SettingsActivityView },
          { path: 'deactivate', name: 'settings.deactivate', component: SettingsDeactivateView },
        ],
      },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
    ],
  })
}

async function mountAt(path, options = {}) {
  const router = makeRouter()
  await router.push(path)
  await router.isReady()

  const wrapper = mount(
    {
      components: { ConfirmModal },
      template: '<router-view /><ConfirmModal />',
    },
    {
      attachTo: options.attachTo,
      global: {
        plugins: [createPinia(), router],
      },
    },
  )

  await flush()
  await flush()

  return { wrapper, router }
}

describe('SettingsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authMock.user = {
      id: 1,
      name: 'Tester',
      email: 'tester@example.com',
      newsletter_subscribed: false,
    }
    authMock.isAdmin = false
    authMock.initialized = true

    httpMock.patch.mockResolvedValue({
      data: {
        data: {
          newsletter_subscribed: true,
        },
      },
    })
    httpMock.put.mockResolvedValue({
      data: {
        data: {},
        meta: {},
      },
    })

    httpMock.get.mockImplementation((url) => {
      if (url === '/me/preferences') {
        return Promise.resolve({
          data: {
            data: {
              sidebar_widget_keys: ['search', 'nasa_apod'],
              sidebar_widget_overrides: {
                home: ['search', 'nasa_apod'],
              },
              has_preferences: true,
            },
            meta: {
              supported_sidebar_widgets: [
                { section_key: 'search', title: 'Hľadaj' },
                { section_key: 'nasa_apod', title: 'Astrofoto dňa' },
                { section_key: 'next_event', title: 'Najbližšia udalosť' },
                { section_key: 'latest_articles', title: 'Astro čítanie' },
              ],
              supported_sidebar_scopes: ['home', 'events', 'search'],
            },
          },
        })
      }

      if (url === '/sidebar-config') {
        return Promise.resolve({
          data: {
            data: [
              { kind: 'builtin', section_key: 'search', title: 'Hľadaj', order: 0, is_enabled: true },
              { kind: 'builtin', section_key: 'nasa_apod', title: 'Astrofoto dňa', order: 1, is_enabled: true },
              { kind: 'builtin', section_key: 'next_event', title: 'Najbližšia udalosť', order: 2, is_enabled: true },
              { kind: 'builtin', section_key: 'latest_articles', title: 'Astro čítanie', order: 3, is_enabled: true },
            ],
          },
        })
      }

      if (url === '/account/email') {
        return Promise.resolve({
          data: {
            data: {
              email: 'tester@example.com',
              verified: true,
              email_verified_at: '2026-03-01T12:00:00Z',
              requires_email_verification: true,
              seconds_to_resend: 0,
              pending_email_change: null,
            },
          },
        })
      }

      if (url === '/me/export/summary') {
        return Promise.resolve({
          data: {
            generated_at: '2026-03-10T12:00:00Z',
            schema_version: '2.0',
            estimated_bytes: 14000,
            counts: {
              posts_count: 1,
              invites_received_count: 1,
              invites_sent_count: 0,
              reminders_count: 0,
              followed_events_count: 0,
              bookmarks_count: 0,
            },
            section_counts: {
              user: 1,
              newsletter: 1,
            },
            sections: ['user', 'newsletter'],
          },
        })
      }

      if (url === '/me/export') {
        return Promise.resolve({
          data: new Blob(
            [
              JSON.stringify({
                export_version: '2.0',
                data_summary: {
                  posts_count: 1,
                  invites_count: 1,
                  invites_scope: 'received',
                  invites_received_count: 1,
                  invites_sent_count: 0,
                  reminders_count: 0,
                  followed_events_count: 0,
                  bookmarks_count: 0,
                  estimated_bytes: 14000,
                },
              }),
            ],
            { type: 'application/json' },
          ),
          headers: {
            'content-disposition':
              'attachment; filename="nebesky-sprievodca-export-tester-20260221_173000.json"',
          },
        })
      }

      if (url === '/me/export/jobs/11') {
        return Promise.resolve({
          data: {
            id: 11,
            status: 'ready',
            file_name: 'nebesky-sprievodca-export-tester-20260310_120000.zip',
            download_url: '/api/me/export/jobs/11/download?expires=2000000000&signature=test-signature',
            checksum_sha256: 'abc',
          },
        })
      }

      if (String(url).startsWith('/api/me/export/jobs/11/download')) {
        return Promise.resolve({
          data: new Blob(
            [
              JSON.stringify({
                export_version: '2.0',
                data_summary: {
                  posts_count: 1,
                  invites_count: 1,
                  invites_scope: 'received',
                  invites_received_count: 1,
                  invites_sent_count: 0,
                  reminders_count: 0,
                  followed_events_count: 0,
                  bookmarks_count: 0,
                  estimated_bytes: 14000,
                },
              }),
            ],
            { type: 'application/zip' },
          ),
          headers: {
            'content-disposition':
              'attachment; filename="nebesky-sprievodca-export-tester-20260310_120000.zip"',
          },
        })
      }

      return Promise.resolve({
        data: {},
        headers: {},
      })
    })

    httpMock.post.mockImplementation((url) => {
      if (url === '/me/export/jobs') {
        return Promise.resolve({
          data: {
            id: 11,
            status: 'pending',
            file_name: 'nebesky-sprievodca-export-tester-20260310_120000.zip',
            download_url: null,
          },
        })
      }

      if (url === '/account/email/verification/send') {
        return Promise.resolve({
          data: {
            message: 'Verification code sent.',
            data: {
              email: 'tester@example.com',
              verified: false,
              email_verified_at: null,
              requires_email_verification: true,
              seconds_to_resend: 60,
              pending_email_change: null,
            },
          },
        })
      }

      return Promise.resolve({
        data: {
          message: 'ok',
        },
      })
    })
  })

  it('renders email verification state from account email API', async () => {
    const { wrapper } = await mountAt('/settings/email')

    expect(httpMock.get).toHaveBeenCalledWith('/account/email', {
      meta: { skipErrorToast: true },
    })
    expect(wrapper.find('[data-testid="settings-email-status"]').text()).toContain('Overený')
    expect(wrapper.find('[data-testid="settings-email-status"]').text()).toContain('tester@example.com')
  })

  it('sends verification code from settings email detail', async () => {
    httpMock.get.mockImplementation((url) => {
      if (url === '/account/email') {
        return Promise.resolve({
          data: {
            data: {
              email: 'tester@example.com',
              verified: false,
              email_verified_at: null,
              requires_email_verification: true,
              seconds_to_resend: 0,
              pending_email_change: null,
            },
          },
        })
      }

      return Promise.resolve({
        data: new Blob(['{}'], { type: 'application/json' }),
        headers: {},
      })
    })

    const { wrapper } = await mountAt('/settings/email')

    await wrapper.get('#settings-email-send').trigger('click')
    await flush()

    expect(authMock.csrf).toHaveBeenCalled()
    expect(httpMock.post).toHaveBeenCalledWith('/account/email/verification/send', {})
    expect(wrapper.text()).toContain('Verification code sent.')
  })

  it('updates newsletter toggle via API', async () => {
    const { wrapper } = await mountAt('/settings/newsletter')

    const checkbox = wrapper.get('#settings-newsletter')
    await checkbox.setValue(true)
    await flush()

    expect(authMock.csrf).toHaveBeenCalledTimes(1)
    expect(httpMock.patch).toHaveBeenCalledWith('/me/newsletter', {
      newsletter_subscribed: true,
    })
    expect(authMock.user.newsletter_subscribed).toBe(true)
  })

  it('downloads profile export via API', async () => {
    if (!URL.createObjectURL) {
      URL.createObjectURL = () => 'blob:export'
    }
    if (!URL.revokeObjectURL) {
      URL.revokeObjectURL = () => {}
    }

    const createObjectUrlSpy = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:export')
    const revokeObjectUrlSpy = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
    const anchorClickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => {})

    const { wrapper } = await mountAt('/settings/data-export', { attachTo: document.body })

    await wrapper.get('#settings-export-password').setValue('export-pass-123')
    await wrapper.get('#settings-export-button').trigger('click')
    await flush()
    await flush()

    expect(httpMock.get).toHaveBeenCalledWith('/me/export/summary', {
      meta: { skipErrorToast: true },
    })
    expect(httpMock.post).toHaveBeenCalledWith(
      '/me/export/jobs',
      {
        current_password: 'export-pass-123',
      },
      {
        meta: { skipErrorToast: true },
      },
    )
    expect(httpMock.get).toHaveBeenCalledWith('/me/export/jobs/11', {
      meta: { skipErrorToast: true },
    })
    expect(httpMock.get).toHaveBeenCalledWith(
      '/api/me/export/jobs/11/download?expires=2000000000&signature=test-signature',
      {
        responseType: 'blob',
        meta: { skipErrorToast: true },
      },
    )
    expect(createObjectUrlSpy).toHaveBeenCalledTimes(1)
    expect(anchorClickSpy).toHaveBeenCalledTimes(1)

    createObjectUrlSpy.mockRestore()
    revokeObjectUrlSpy.mockRestore()
    anchorClickSpy.mockRestore()
    wrapper.unmount()
  })

  it('shows export cooldown after rate limiting', async () => {
    httpMock.post.mockImplementation((url) => {
      if (url === '/me/export/jobs') {
        return Promise.reject({
          response: {
            status: 429,
            headers: {
              'retry-after': '42',
            },
            data: {},
          },
        })
      }

      return Promise.resolve({
        data: {
          message: 'ok',
        },
      })
    })

    const { wrapper } = await mountAt('/settings/data-export')

    await wrapper.get('#settings-export-password').setValue('export-pass-123')
    await wrapper.get('#settings-export-button').trigger('click')
    await flush()
    await flush()

    expect(wrapper.text()).toContain('Príliš veľa požiadaviek na export. Skúste to znova o 42 s.')
    expect(wrapper.get('#settings-export-button').attributes('disabled')).toBeDefined()
    expect(wrapper.get('#settings-export-button').text()).toContain('Skúste znova o 42s')

    wrapper.unmount()
  })

  it('keeps user activity hidden by default and loads it on demand', async () => {
    httpMock.get.mockImplementation((url) => {
      if (url === '/account/email') {
        return Promise.resolve({
          data: {
            data: {
              email: 'tester@example.com',
              verified: true,
              email_verified_at: '2026-03-01T12:00:00Z',
              requires_email_verification: true,
              seconds_to_resend: 0,
              pending_email_change: null,
            },
          },
        })
      }

      if (url === '/me/activity') {
        return Promise.resolve({
          data: {
            last_login_at: '2026-02-23T10:00:00Z',
            posts_count: 7,
            event_participations_count: 3,
          },
        })
      }

      return Promise.resolve({
        data: new Blob(['{}'], { type: 'application/json' }),
        headers: {},
      })
    })

    const { wrapper } = await mountAt('/settings/activity')

    expect(wrapper.find('[data-testid="activity-values"]').exists()).toBe(false)

    await wrapper.get('#settings-activity-toggle').trigger('click')
    await flush()

    expect(httpMock.get).toHaveBeenCalledWith('/me/activity', {
      meta: { skipErrorToast: true },
    })
    expect(wrapper.find('[data-testid="activity-values"]').exists()).toBe(true)
  })

  it('saves selected sidebar widgets and keeps max three selected', async () => {
    httpMock.put.mockImplementation((url, payload) => {
      if (url === '/me/preferences') {
        return Promise.resolve({
          data: {
            data: {
              sidebar_widget_keys: payload.sidebar_widget_overrides?.home ?? [],
              sidebar_widget_overrides: payload.sidebar_widget_overrides ?? {},
              has_preferences: true,
            },
            meta: {
              supported_sidebar_widgets: [
                { section_key: 'search', title: 'Hľadaj' },
                { section_key: 'nasa_apod', title: 'Astrofoto dňa' },
                { section_key: 'next_event', title: 'Najbližšia udalosť' },
                { section_key: 'latest_articles', title: 'Astro čítanie' },
              ],
              supported_sidebar_scopes: ['home', 'events', 'search'],
            },
          },
        })
      }

      return Promise.resolve({
        data: {
          data: {},
          meta: {},
        },
      })
    })

    const { wrapper } = await mountAt('/settings/sidebar-widgets')

    const zones = wrapper.findAll('.widgetZone')
    const activeDropZone = zones[0].get('.widgetZone__list--active')
    let availableCards = zones[1].findAll('.widgetCard')

    await availableCards[0].trigger('dragstart', { dataTransfer: makeDataTransfer() })
    await activeDropZone.trigger('drop')
    await flush()
    await flush()

    expect(httpMock.put).toHaveBeenCalledWith(
      '/me/preferences',
      {
        sidebar_widget_overrides: {
          home: ['search', 'nasa_apod', 'next_event'],
        },
        sidebar_widget_keys: ['search', 'nasa_apod', 'next_event'],
      },
      {
        meta: { requiresAuth: true },
      },
    )

    availableCards = wrapper.findAll('.widgetZone')[1].findAll('.widgetCard')
    await availableCards[0].trigger('dragstart', { dataTransfer: makeDataTransfer() })
    await activeDropZone.trigger('drop')
    await flush()

    expect(wrapper.text()).toContain('najviac 3 widgety')
    expect(wrapper.get('.widgetZone__count').text()).toContain('3/3')
  })

  it('allows reordering enabled sidebar widgets', async () => {
    httpMock.put.mockImplementation((url, payload) => {
      if (url === '/me/preferences') {
        return Promise.resolve({
          data: {
            data: {
              sidebar_widget_keys: payload.sidebar_widget_overrides?.home ?? [],
              sidebar_widget_overrides: payload.sidebar_widget_overrides ?? {},
              has_preferences: true,
            },
            meta: {
              supported_sidebar_widgets: [
                { section_key: 'search', title: 'Hľadaj' },
                { section_key: 'nasa_apod', title: 'Astrofoto dňa' },
                { section_key: 'next_event', title: 'Najbližšia udalosť' },
                { section_key: 'latest_articles', title: 'Astro čítanie' },
              ],
              supported_sidebar_scopes: ['home', 'events', 'search'],
            },
          },
        })
      }

      return Promise.resolve({
        data: {
          data: {},
          meta: {},
        },
      })
    })

    const { wrapper } = await mountAt('/settings/sidebar-widgets')

    const activeDropZone = wrapper.get('.widgetZone__list--active')
    const activeCards = wrapper.findAll('.widgetZone__list--active .widgetCard')

    await activeCards[0].trigger('dragstart', { dataTransfer: makeDataTransfer() })
    await activeCards[1].trigger('dragenter')
    await activeDropZone.trigger('drop')
    await flush()
    await flush()

    expect(httpMock.put).toHaveBeenCalledWith(
      '/me/preferences',
      {
        sidebar_widget_overrides: {
          home: ['nasa_apod', 'search'],
        },
        sidebar_widget_keys: ['nasa_apod', 'search'],
      },
      {
        meta: { requiresAuth: true },
      },
    )

    const activeNames = wrapper.findAll('.widgetZone__list--active .widgetCard__name').map((node) => node.text())
    expect(activeNames.slice(0, 2)).toEqual(['Astrofoto dňa', 'Hľadaj'])
  })

  it('keeps admin user settings sidebar page isolated from admin default endpoints', async () => {
    authMock.isAdmin = true

    httpMock.put.mockImplementation((url, payload) => {
      if (url === '/me/preferences') {
        return Promise.resolve({
          data: {
            data: {
              sidebar_widget_keys: payload.sidebar_widget_overrides?.home ?? [],
              sidebar_widget_overrides: payload.sidebar_widget_overrides ?? {},
              has_preferences: true,
            },
            meta: {
              supported_sidebar_widgets: [
                { section_key: 'search', title: 'Hľadaj' },
                { section_key: 'nasa_apod', title: 'Astrofoto dňa' },
                { section_key: 'next_event', title: 'Najbližšia udalosť' },
                { section_key: 'latest_articles', title: 'Astro čítanie' },
              ],
              supported_sidebar_scopes: ['home', 'events', 'search'],
            },
          },
        })
      }

      return Promise.resolve({
        data: {
          data: {},
          meta: {},
        },
      })
    })

    const { wrapper } = await mountAt('/settings/sidebar-widgets')

    const zones = wrapper.findAll('.widgetZone')
    const activeDropZone = zones[0].get('.widgetZone__list--active')
    const availableCards = zones[1].findAll('.widgetCard')

    await availableCards[0].trigger('dragstart', { dataTransfer: makeDataTransfer() })
    await activeDropZone.trigger('drop')
    await flush()
    await flush()

    expect(httpMock.get).not.toHaveBeenCalledWith('/admin/sidebar-config', expect.anything())
    expect(httpMock.put).toHaveBeenCalledWith(
      '/me/preferences',
      {
        sidebar_widget_overrides: {
          home: ['search', 'nasa_apod', 'next_event'],
        },
        sidebar_widget_keys: ['search', 'nasa_apod', 'next_event'],
      },
      {
        meta: { requiresAuth: true },
      },
    )
    expect(httpMock.put).not.toHaveBeenCalledWith('/admin/sidebar-config', expect.anything(), expect.anything())
  })

  it('shows effective admin/default widgets when the user has no explicit override', async () => {
    httpMock.get.mockImplementation((url) => {
      if (url === '/me/preferences') {
        return Promise.resolve({
          data: {
            data: {
              sidebar_widget_keys: [],
              sidebar_widget_overrides: {},
              has_preferences: true,
            },
            meta: {
              supported_sidebar_widgets: [
                { section_key: 'search', title: 'H\u013Eadaj' },
                { section_key: 'nasa_apod', title: 'Astrofoto dĹa' },
                { section_key: 'next_event', title: 'NajbliĹľĹˇia udalosĹĄ' },
                { section_key: 'latest_articles', title: 'Astro \u010D\u00EDtanie' },
              ],
              supported_sidebar_scopes: ['home', 'events', 'search'],
            },
          },
        })
      }

      if (url === '/sidebar-config') {
        return Promise.resolve({
          data: {
            data: [
              { kind: 'builtin', section_key: 'search', title: 'H\u013Eadaj', order: 0, is_enabled: true },
              { kind: 'builtin', section_key: 'nasa_apod', title: 'Astrofoto dĹa', order: 1, is_enabled: true },
              { kind: 'builtin', section_key: 'next_event', title: 'NajbliĹľĹˇia udalosĹĄ', order: 2, is_enabled: true },
              { kind: 'builtin', section_key: 'latest_articles', title: 'Astro \u010D\u00EDtanie', order: 3, is_enabled: false },
            ],
          },
        })
      }

      return Promise.resolve({
        data: {},
        headers: {},
      })
    })

    const { wrapper } = await mountAt('/settings/sidebar-widgets')

    expect(wrapper.get('.widgetZone__count').text()).toContain('3/3')
    expect(wrapper.findAll('.widgetZone__list--active .widgetCard')).toHaveLength(3)
  })

  it('keeps sidebar widget settings usable when preferences request times out', async () => {
    httpMock.get.mockImplementation((url) => {
      if (url === '/me/preferences') {
        return Promise.reject({
          code: 'ECONNABORTED',
          message: 'timeout of 15000ms exceeded',
          userMessage: 'Server neodpoveda. Skus to znova neskor.',
        })
      }

      if (url === '/sidebar-config') {
        return Promise.resolve({
          data: {
            data: [
              { kind: 'builtin', section_key: 'search', title: 'Hľadaj', order: 0, is_enabled: true },
              { kind: 'builtin', section_key: 'nasa_apod', title: 'Astrofoto dňa', order: 1, is_enabled: true },
              { kind: 'builtin', section_key: 'next_event', title: 'Najbližšia udalosť', order: 2, is_enabled: true },
            ],
          },
        })
      }

      return Promise.resolve({
        data: {},
        headers: {},
      })
    })

    const { wrapper } = await mountAt('/settings/sidebar-widgets')

    expect(wrapper.text()).toContain('Nepodarilo sa nacitat preferencie.')
    expect(wrapper.findAll('.widgetZone__list--active .widgetCard')).toHaveLength(3)
    expect(wrapper.findAll('.widgetZone .widgetCard').length).toBeGreaterThan(0)
  })

  it('logs out from settings navigation session section', async () => {
    const { wrapper, router } = await mountAt('/settings')

    await wrapper.get('#settings-logout-button').trigger('click')
    await flush()
    const confirmButton = document.querySelector('.confirmModalCard .btn-danger')
    expect(confirmButton).not.toBeNull()
    confirmButton?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flush()

    expect(authMock.logout).toHaveBeenCalledTimes(1)
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('deactivates account after password confirm flow', async () => {
    httpMock.delete.mockResolvedValue({
      data: {
        message: 'Ucet bol deaktivovany.',
      },
    })

    const { wrapper, router } = await mountAt('/settings/deactivate')

    await wrapper.get('#deactivate-password').setValue('password')
    await wrapper.get('[aria-label="Deaktivovať účet"]').trigger('click')
    await flush()

    const confirmButton = document.querySelector('.confirmModalCard .btn-danger')
    expect(confirmButton).not.toBeNull()
    confirmButton?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flush()
    await flush()

    expect(authMock.csrf).toHaveBeenCalled()
    expect(httpMock.delete).toHaveBeenCalledWith('/profile', {
      data: {
        current_password: 'password',
      },
    })
    expect(authMock.logout).toHaveBeenCalledTimes(1)
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('shows deactivate password field error when password is invalid', async () => {
    httpMock.delete.mockRejectedValue({
      response: {
        status: 422,
        data: {
          errors: {
            current_password: ['Aktuálne heslo nie je spravne.'],
          },
        },
      },
    })

    const { wrapper } = await mountAt('/settings/deactivate')

    await wrapper.get('#deactivate-password').setValue('wrong-password')
    await wrapper.get('[aria-label="Deaktivovať účet"]').trigger('click')
    await flush()

    const confirmButton = document.querySelector('.confirmModalCard .btn-danger')
    expect(confirmButton).not.toBeNull()
    confirmButton?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flush()
    await flush()

    expect(wrapper.text()).toContain('Aktuálne heslo nie je spravne.')
    expect(authMock.logout).not.toHaveBeenCalled()
  })
})
