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
        component: { template: '<div>detail</div>' },
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
                id: 4,
                name: 'Kozmo',
                username: 'kozmobot',
                role: 'bot',
                email: 'legacy-bot@example.test',
                is_bot: true,
                is_active: true,
                is_banned: false,
              },
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
            total: 2,
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

  it('renders bot role badge and hides bot email behind bot-account hint', async () => {
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

    const botRow = wrapper.get('[data-row-id="4"]')
    expect(botRow.find('.col-role .roleBadge').text()).toBe('bot')
    expect(botRow.find('.col-email .truncateText').exists()).toBe(false)
    expect(botRow.find('.default-avatar').exists()).toBe(true)

    const hint = botRow.get('.botEmailBadge')
    expect(hint.text()).toBe('bot účet')
    expect(hint.attributes('title')).toContain('Automatizovaný účet')

    const regularRow = wrapper.get('[data-row-id="9"]')
    expect(regularRow.find('.col-email .truncateText').text()).toBe('regular@example.test')
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

    const botRow = wrapper.get('[data-row-id="4"]')
    await botRow.find('.dropdownTrigger').trigger('click')
    await flush()

    const botMenu = document.body.querySelector('[role="menu"]')
    expect(botMenu?.textContent || '').toContain('Správa účtu')
    expect(botMenu?.textContent || '').toContain('Zablokovať účet')
    expect(botMenu?.textContent || '').toContain('Deaktivovať účet')
    expect(botMenu?.textContent || '').toContain('Resetovať profil')
    expect(botMenu?.textContent || '').not.toContain('Pridať rolu editor')

    await botRow.find('.dropdownTrigger').trigger('click')
    await flush()

    const regularRow = wrapper.get('[data-row-id="9"]')
    await regularRow.find('.dropdownTrigger').trigger('click')
    await flush()

    const regularMenu = document.body.querySelector('[role="menu"]')
    expect(regularMenu?.textContent || '').toContain('Zobraziť profil')
    expect(regularMenu?.textContent || '').toContain('Správa účtu')
    expect(regularMenu?.textContent || '').toContain('Pridať rolu editor')
    expect(regularMenu?.textContent || '').toContain('Zablokovať účet')
    expect(regularMenu?.textContent || '').toContain('Deaktivovať účet')
    expect(regularMenu?.textContent || '').toContain('Resetovať profil')
  })

  it('allows row-click navigation to user detail for both bot and non-bot accounts', async () => {
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

    expect(router.currentRoute.value.name).toBe('admin.users.detail')
    expect(router.currentRoute.value.params.id).toBe('9')

    await router.push('/admin/community/users')
    await flush()
    await flush()

    const botRow = wrapper.get('[data-row-id="4"]')
    await botRow.trigger('click')
    await flush()

    expect(router.currentRoute.value.name).toBe('admin.users.detail')
    expect(router.currentRoute.value.params.id).toBe('4')
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
      .find((node) => (node.textContent || '').includes('Správa účtu'))

    expect(manageItem).toBeTruthy()
    manageItem?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flush()
    await flush()

    const modal = document.body.querySelector('[data-testid="manage-account-modal"]')
    expect(modal).toBeTruthy()
    expect(modal?.textContent || '').toContain('Správa účtu')
    expect(modal?.textContent || '').toContain('Regular')
    expect(apiGetMock).toHaveBeenCalledWith('/admin/users/9')

    wrapper.unmount()
  })
})
