import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiPostMock = vi.hoisted(() => vi.fn())

vi.mock('./api', () => ({
  default: {
    get: vi.fn(),
    post: apiPostMock,
    delete: vi.fn(),
  },
}))

describe('observations service image payload', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    apiPostMock.mockResolvedValue({ data: { id: 1 } })
  })

  it('sends observation image files under the images key', async () => {
    const { createObservation } = await import('./observations')

    const firstImage = new File(['first'], 'first.jpg', { type: 'image/jpeg' })
    const secondImage = new File(['second'], 'second.png', { type: 'image/png' })

    await createObservation({
      title: 'Test',
      observed_at: '2026-04-14T22:13:00Z',
      images: [firstImage, secondImage],
    })

    expect(apiPostMock).toHaveBeenCalledTimes(1)
    const [requestUrl, formData] = apiPostMock.mock.calls[0]
    expect(requestUrl).toBe('/observations')
    expect(formData).toBeInstanceOf(FormData)

    const formEntries = Array.from(formData.entries())
    const imageEntries = formEntries.filter(([key]) => key === 'images')

    expect(imageEntries).toHaveLength(2)
    expect(formEntries.some(([key]) => key === 'images[]')).toBe(false)
    expect(imageEntries.map(([, value]) => value.name)).toEqual(['first.jpg', 'second.png'])
  })
})
