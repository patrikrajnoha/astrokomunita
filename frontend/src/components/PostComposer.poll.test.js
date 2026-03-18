import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PostComposer from '@/components/PostComposer.vue'

const postMock = vi.fn()
const getMock = vi.fn()

vi.mock('@/services/api', () => ({
  default: {
    post: (...args) => postMock(...args),
    get: (...args) => getMock(...args),
    defaults: { baseURL: 'http://localhost/api' },
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: {
      name: 'Test User',
      avatar_url: null,
    },
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    warn: vi.fn(),
    error: vi.fn(),
  }),
}))

describe('PostComposer poll mode', () => {
  beforeEach(() => {
    postMock.mockReset()
    getMock.mockReset()
    getMock.mockResolvedValue({ data: [] })
    postMock.mockResolvedValue({ data: { id: 1 } })
    window.localStorage.clear()
  })

  it('disables post attachments when poll is enabled', async () => {
    const wrapper = mount(PostComposer)

    await wrapper.find('button[aria-label="Pridat anketu"]').trigger('click')

    const attachButton = wrapper.find('button[aria-label="Pridat prilohu"]')
    expect(attachButton.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Pri ankete sa obrázky pridávajú iba ku konkrétnym možnostiam.')
  })

  it('adds options up to 4', async () => {
    const wrapper = mount(PostComposer)

    await wrapper.find('button[aria-label="Pridat anketu"]').trigger('click')

    const addButton = () => wrapper.find('button[aria-label="Pridať možnosť"]')

    await addButton().trigger('click')
    await addButton().trigger('click')

    expect(wrapper.findAll('input.optionInput')).toHaveLength(4)
    expect(addButton().exists()).toBe(false)
  })

  it('submits option image in poll fields, not as post attachment', async () => {
    const wrapper = mount(PostComposer)

    await wrapper.find('button[aria-label="Pridat anketu"]').trigger('click')

    await wrapper.find('#post-composer-textarea').setValue('Otazka ankety')

    const optionInputs = wrapper.findAll('input.optionInput')
    await optionInputs[0].setValue('Moznost A')
    await optionInputs[1].setValue('Moznost B')

    const optionImageInput = wrapper.findAll('input.hiddenInput')[0]
    const imageFile = new File(['abc'], 'a.png', { type: 'image/png' })
    Object.defineProperty(optionImageInput.element, 'files', {
      value: [imageFile],
      configurable: true,
    })
    await optionImageInput.trigger('change')

    await wrapper.find('button[aria-label="Publikovat"]').trigger('click')

    expect(postMock).toHaveBeenCalledTimes(1)
    const [, formData] = postMock.mock.calls[0]
    const keys = []
    for (const [key] of formData.entries()) {
      keys.push(key)
    }

    expect(keys).toContain('poll[duration_seconds]')
    expect(keys).toContain('poll[options][0][text]')
    expect(keys).toContain('poll[options][0][image]')
    expect(keys).not.toContain('attachment')
  })
})

