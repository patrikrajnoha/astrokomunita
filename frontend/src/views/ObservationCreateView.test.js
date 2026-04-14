import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ObservationCreateView from './ObservationCreateView.vue'

const routerPushMock = vi.hoisted(() => vi.fn())
const csrfMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())
const createObservationMock = vi.hoisted(() => vi.fn())
const prepareImageFilesForUploadMock = vi.hoisted(() => vi.fn())
const toastSuccessMock = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: routerPushMock,
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    isAuthed: true,
    csrf: csrfMock,
  }),
}))

vi.mock('@/services/events', () => ({
  getEvents: (...args) => getEventsMock(...args),
}))

vi.mock('@/services/observations', () => ({
  createObservation: (...args) => createObservationMock(...args),
}))

vi.mock('@/utils/imageUpload', () => ({
  prepareImageFilesForUpload: (...args) => prepareImageFilesForUploadMock(...args),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: toastSuccessMock,
  }),
}))

describe('ObservationCreateView image upload', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    csrfMock.mockResolvedValue(undefined)
    getEventsMock.mockResolvedValue({ data: { data: [] } })
    createObservationMock.mockResolvedValue({ data: { id: 12, feed_post_id: 0 } })
    prepareImageFilesForUploadMock.mockImplementation(async (files) => files)
    URL.createObjectURL = vi.fn(() => 'blob:preview-image')
    URL.revokeObjectURL = vi.fn()
  })

  it('keeps file input optional on DOM level and submits with processed images', async () => {
    const wrapper = mount(ObservationCreateView, {
      props: {
        embedded: true,
      },
      global: {
        stubs: {
          InlineStatus: true,
        },
      },
    })

    await flushPromises()

    const fileInput = wrapper.get('input[type="file"]')
    expect(fileInput.attributes('required')).toBeUndefined()

    await wrapper.get('input[type="text"]').setValue('Mesiac pri Perigee')

    const imageFile = new File(['image-bytes'], 'observation.jpg', { type: 'image/jpeg' })
    Object.defineProperty(fileInput.element, 'files', {
      value: [imageFile],
      configurable: true,
    })
    await fileInput.trigger('change')
    await flushPromises()

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(createObservationMock).toHaveBeenCalledTimes(1)
    expect(createObservationMock).toHaveBeenCalledWith(expect.objectContaining({
      images: [imageFile],
    }))
  })
})
