import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import SidebarConfigView from '@/views/admin/SidebarConfigView.vue'

const confirmMock = vi.fn(async () => true)
const showToastMock = vi.fn()

const getMock = vi.fn()
const updateMock = vi.fn()
const customListMock = vi.fn()

const defaultItems = [
  { kind: 'builtin', section_key: 'search', title: 'Hladat', order: 0, is_enabled: true },
  { kind: 'builtin', section_key: 'latest_articles', title: 'Najnovsie clanky', order: 1, is_enabled: true },
]

const store = {
  byScope: {},
  getDefaultForScope: () => defaultItems,
}

vi.mock('vue-router', async () => {
  const actual = await vi.importActual('vue-router')
  return {
    ...actual,
    onBeforeRouteLeave: vi.fn(),
  }
})

vi.mock('vuedraggable', () => ({
  default: {
    name: 'DraggableStub',
    props: ['modelValue'],
    emits: ['update:modelValue', 'end'],
    template: `
      <div>
        <slot
          v-for="element in modelValue"
          name="item"
          :element="element"
          :key="element.client_key || element.section_key"
        />
      </div>
    `,
  },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    showToast: (...args) => showToastMock(...args),
  }),
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: (...args) => confirmMock(...args),
  }),
}))

vi.mock('@/services/api/admin/sidebarConfig', () => ({
  sidebarConfigAdminApi: {
    get: (...args) => getMock(...args),
    update: (...args) => updateMock(...args),
  },
  sidebarCustomComponentsAdminApi: {
    list: (...args) => customListMock(...args),
  },
}))

vi.mock('@/stores/sidebarConfig', () => ({
  useSidebarConfigStore: () => store,
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

function createPayload({ items = defaultItems, customComponents = [] } = {}) {
  return {
    data: items,
    available_custom_components: customComponents,
  }
}

function mountView(payload = createPayload()) {
  getMock.mockResolvedValue(payload)
  updateMock.mockImplementation(async (_scope, items) => ({
    data: items.map((item) => ({
      ...item,
      title: item.section_key === 'search' ? 'Hladat' : 'Najnovsie clanky',
    })),
  }))
  customListMock.mockResolvedValue({ data: [] })

  return mount(SidebarConfigView, {
    global: {
      stubs: {
        SidebarCustomComponentsView: { template: '<div data-testid="custom-mode">custom mode</div>' },
        SidebarComponentRegistryView: { template: '<div data-testid="widgets-mode">widgets mode</div>' },
      },
    },
  })
}

describe('SidebarConfigView', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  beforeEach(() => {
    vi.clearAllMocks()
    store.byScope = {}
    confirmMock.mockResolvedValue(true)
  })

  it('switches between primary tabs', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.get('button.modeTab:nth-child(2)').trigger('click')
    expect(wrapper.find('[data-testid="custom-mode"]').exists()).toBe(true)

    await wrapper.get('button.modeTab:nth-child(3)').trigger('click')
    expect(wrapper.find('[data-testid="widgets-mode"]').exists()).toBe(true)
  })

  it('switches scope and reloads data', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const notificationsTab = wrapper
      .findAll('button.scopeTab')
      .find((button) => button.text().includes('Notifikacie'))

    await notificationsTab.trigger('click')
    await flush()

    expect(getMock).toHaveBeenCalledTimes(2)
    expect(getMock).toHaveBeenLastCalledWith('notifications')
  })

  it('filters layout rows by search query', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.get('.toolbar .searchInput').setValue('najnovsie')
    await flush()

    const titles = wrapper.findAll('.rowTitle').map((node) => node.text())
    expect(titles).toEqual(['Najnovsie clanky'])
  })

  it('toggles visibility and updates summary', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const firstToggle = wrapper.findAll('.rowsList input[type="checkbox"]')[0]
    await firstToggle.setValue(false)
    await flush()

    expect(wrapper.find('.summaryLine').text()).toContain('1 aktivne')
  })

  it('reorders rows from detail panel controls', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const rowsBefore = wrapper.findAll('.layoutRow')
    expect(rowsBefore[0].text()).toContain('Hladat')
    expect(rowsBefore[1].text()).toContain('Najnovsie clanky')

    await rowsBefore[1].trigger('click')
    await flush()
    await wrapper.get('.panelActions .btn').trigger('click')
    await flush()

    const titles = wrapper.findAll('.rowTitle').map((node) => node.text())
    expect(titles[0]).toBe('Najnovsie clanky')
  })

  it('auto-saves layout after change', async () => {
    vi.useFakeTimers()
    const wrapper = mountView()
    await flush()
    await flush()

    const firstToggle = wrapper.findAll('.rowsList input[type="checkbox"]')[0]
    await firstToggle.setValue(false)
    await flush()

    expect(wrapper.find('.saveState').text()).toContain('Caka na ulozenie')
    expect(updateMock).toHaveBeenCalledTimes(0)

    await vi.advanceTimersByTimeAsync(750)
    await flush()
    await flush()

    expect(updateMock.mock.calls.length).toBeGreaterThanOrEqual(1)
    expect(wrapper.find('.saveState').text()).toContain('Ulozene automaticky')
    vi.useRealTimers()
  })

  it('does not render right panel when no details and no custom components', async () => {
    const wrapper = mountView(createPayload({ customComponents: [] }))
    await flush()
    await flush()

    expect(wrapper.find('.sidePanel').exists()).toBe(false)
  })
})
