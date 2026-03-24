import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import UsersView from './UsersView.vue'

const apiGetMock = vi.fn()

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    patch: vi.fn(),
    post: vi.fn(),
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { id: 1 },
    isAdmin: true,
  }),
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
    prompt: vi.fn(async () => 'reason'),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/community/users',
        name: 'admin.users',
        component: UsersView,
      },
      {
        path: '/admin/users/:id',
        name: 'admin.users.detail',
        alias: ['/admin/users/:id/detail'],
        component: UsersView,
      },
      {
        path: '/admin/users/:id/full',
        name: 'admin.users.detail.page',
        component: { template: '<div>detail-page</div>' },
      },
      {
        path: '/u/:username',
        name: 'user-profile',
        component: { template: '<div>profile</div>' },
      },
    ],
  })
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function normalizeText(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
}

describe('UsersView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    apiGetMock.mockImplementation(async (url) => {
      if (url === '/admin/users') {
        return {
          data: {
            current_page: 1,
            data: [
              {
                id: 9,
                name: 'Regular',
                username: 'regular',
                role: 'user',
                email: 'regular@example.test',
                is_bot: false,
                is_active: true,
                is_banned: false,
              },
            ],
            total: 1,
            per_page: 20,
            last_page: 1,
          },
        }
      }

      if (url === '/admin/users/9') {
        return {
          data: {
            id: 9,
            name: 'Regular',
            username: 'regular',
            role: 'user',
            email: 'regular@example.test',
            is_bot: false,
            is_active: true,
            is_banned: false,
          },
        }
      }

      throw new Error(`Unexpected GET ${url}`)
    })
  })

  it('requests users list with include_bots=false and keeps role filter community-only', async () => {
    const router = makeRouter()
    await router.push('/admin/community/users')
    await router.isReady()

    const wrapper = mount(UsersView, {
      global: {
        plugins: [router],
      },
      attachTo: document.body,
    })

    await flush()
    await flush()

    const regularRow = wrapper.get('[data-row-id="9"]')
    expect(regularRow.find('.col-email .truncateText').text()).toBe('regular@example.test')

    const roleSelect = wrapper.findAll('select.filterSelect')[0]
    const roleOptions = roleSelect
      ? roleSelect.findAll('option').map((option) => option.attributes('value'))
      : []
    expect(roleOptions).not.toContain('bot')

    expect(apiGetMock).toHaveBeenCalledWith('/admin/users', {
      params: expect.objectContaining({
        include_bots: false,
      }),
    })
  })

  it('shows moderation and editor role actions for eligible non-bot accounts', async () => {
    const router = makeRouter()
    await router.push('/admin/community/users')
    await router.isReady()

    const wrapper = mount(UsersView, {
      global: {
        plugins: [router],
      },
      attachTo: document.body,
    })

    await flush()
    await flush()

    const regularRow = wrapper.get('[data-row-id="9"]')
    await regularRow.find('.dropdownTrigger').trigger('click')
    await flush()

    const regularMenu = document.body.querySelector('[role="menu"]')
    expect(normalizeText(regularMenu?.textContent || '')).toContain('zobrazit profil')
    expect(normalizeText(regularMenu?.textContent || '')).toContain('sprava uctu')
    expect(normalizeText(regularMenu?.textContent || '')).toContain('pridat rolu editor')
    expect(normalizeText(regularMenu?.textContent || '')).toContain('zablokovat ucet')
    expect(normalizeText(regularMenu?.textContent || '')).toContain('deaktivovat ucet')
    expect(normalizeText(regularMenu?.textContent || '')).toContain('resetovat profil')
  })

  it('opens compact manage modal from row click and stays on users route', async () => {
    const router = makeRouter()
    await router.push('/admin/community/users')
    await router.isReady()

    const wrapper = mount(UsersView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    const regularRow = wrapper.get('[data-row-id="9"]')
    await regularRow.trigger('click')
    await flush()
    await flush()

    expect(router.currentRoute.value.name).toBe('admin.users')
    let modal = document.body.querySelector('[data-testid="manage-account-modal"]')
    expect(modal).toBeTruthy()
    expect(apiGetMock).toHaveBeenCalledWith('/admin/users/9')

    await router.push('/admin/community/users')
    await flush()
    await flush()

    expect(router.currentRoute.value.name).toBe('admin.users')
    modal = document.body.querySelector('[data-testid="manage-account-modal"]')
    expect(modal).toBeTruthy()
  })

  it('opens manage account as popup from row actions', async () => {
    const router = makeRouter()
    await router.push('/admin/community/users')
    await router.isReady()

    const wrapper = mount(UsersView, {
      global: {
        plugins: [router],
      },
      attachTo: document.body,
    })

    await flush()
    await flush()

    const regularRow = wrapper.get('[data-row-id="9"]')
    await regularRow.find('.dropdownTrigger').trigger('click')
    await flush()

    const manageItem = [...document.body.querySelectorAll('[role="menuitem"]')]
      .find((node) => normalizeText(node.textContent || '').includes('sprava uctu'))

    expect(manageItem).toBeTruthy()
    manageItem?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flush()
    await flush()

    const modal = document.body.querySelector('[data-testid="manage-account-modal"]')
    expect(modal).toBeTruthy()
    expect(normalizeText(modal?.textContent || '')).toContain('sprava')
    expect(modal?.textContent || '').toContain('Regular')
    expect(apiGetMock).toHaveBeenCalledWith('/admin/users/9')

    wrapper.unmount()
  })

  it('opens manage account popup on /admin/users/:id/detail route', async () => {
    const router = makeRouter()
    await router.push('/admin/users/9/detail')
    await router.isReady()

    const wrapper = mount(UsersView, {
      global: {
        plugins: [router],
      },
      attachTo: document.body,
    })

    await flush()
    await flush()

    const modal = document.body.querySelector('[data-testid="manage-account-modal"]')
    expect(modal).toBeTruthy()
    expect(normalizeText(modal?.textContent || '')).toContain('sprava')
    expect(modal?.textContent || '').toContain('Regular')
    expect(apiGetMock).toHaveBeenCalledWith('/admin/users/9')
    expect(router.currentRoute.value.name).toBe('admin.users')

    wrapper.unmount()
  })

  it('switches from compact summary to embedded detail in the same modal', async () => {
    const router = makeRouter()
    await router.push('/admin/community/users')
    await router.isReady()

    const wrapper = mount(UsersView, {
      global: {
        plugins: [router],
      },
      attachTo: document.body,
    })

    await flush()
    await flush()

    const regularRow = wrapper.get('[data-row-id="9"]')
    await regularRow.find('.dropdownTrigger').trigger('click')
    await flush()

    const manageItem = [...document.body.querySelectorAll('[role="menuitem"]')]
      .find((node) => normalizeText(node.textContent || '').includes('sprava uctu'))
    expect(manageItem).toBeTruthy()
    manageItem?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flush()
    await flush()

    const modal = document.body.querySelector('[data-testid="manage-account-modal"]')
    expect(modal).toBeTruthy()
    const openDetailButton = modal?.querySelector('.manageActionBtn--primary')
    expect(openDetailButton).toBeTruthy()
    openDetailButton?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flush()
    await flush()

    expect(router.currentRoute.value.name).toBe('admin.users')
    const backToSummaryButton = modal?.querySelector('.manageEmbeddedDetail__top .manageActionBtn')
    expect(backToSummaryButton).toBeTruthy()
    expect(normalizeText(modal?.textContent || '')).toContain('informacie o ucte')

    const resolved = router.resolve({ name: 'admin.users.detail.page', params: { id: '9' } })
    expect(resolved.path).toBe('/admin/users/9/full')

    wrapper.unmount()
  })
})

