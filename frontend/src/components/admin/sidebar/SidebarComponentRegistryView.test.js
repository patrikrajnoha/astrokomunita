import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SidebarComponentRegistryView from '@/components/admin/sidebar/SidebarComponentRegistryView.vue'

describe('SidebarComponentRegistryView', () => {
  it('shows empty state when search has no matches', async () => {
    const wrapper = mount(SidebarComponentRegistryView, {
      global: {
        stubs: {
          ComponentPlaygroundCard: {
            template: '<article class="card-stub">{{ entry.label }}</article>',
            props: ['entry'],
          },
        },
      },
    })

    await wrapper.get('.searchInput').setValue('___not_found___')
    expect(wrapper.text()).toContain('Ziadny widget nevyhovuje filtru.')
  })
})
