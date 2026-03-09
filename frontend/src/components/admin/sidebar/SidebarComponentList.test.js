import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SidebarComponentList from '@/components/admin/sidebar/SidebarComponentList.vue'

describe('SidebarComponentList', () => {
  it('renders empty state and emits create-item', async () => {
    const wrapper = mount(SidebarComponentList, {
      props: {
        modelValue: '',
        items: [],
        selectedId: null,
        loading: false,
        errorMessage: '',
        busy: false,
      },
    })

    expect(wrapper.text()).toContain('Zatial nemas ziadne vlastne komponenty.')

    await wrapper.get('.createBtn').trigger('click')
    expect(wrapper.emitted('create-item')).toHaveLength(1)
  })
})
