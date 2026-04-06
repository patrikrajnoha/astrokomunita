import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PostMediaImage from './PostMediaImage.vue'

function renderComponent(props = {}) {
  return mount(PostMediaImage, {
    props: {
      src: 'https://example.test/image.jpg',
      blurred: false,
      ...props,
    },
    global: {
      stubs: {
        ImageLightbox: true,
      },
    },
  })
}

describe('PostMediaImage', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('shows blurred pending overlay label while attachment moderation is pending', () => {
    const wrapper = renderComponent({
      blurred: true,
      status: 'pending',
    })

    expect(wrapper.find('.media-state-overlay').exists()).toBe(true)
    expect(wrapper.find('.media-spinner').exists()).toBe(false)
    expect(wrapper.text()).toContain('Overuje sa obsah…')
  })

  it('emits unblurred when polling detects approved attachment state', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: async () => ({
        post: {
          id: 17,
          attachment_is_blurred: false,
          attachment_moderation_status: 'ok',
        },
      }),
    })

    const wrapper = renderComponent({
      blurred: true,
      status: 'pending',
      postId: 17,
    })

    await vi.advanceTimersByTimeAsync(4000)
    await Promise.resolve()

    expect(fetch).toHaveBeenCalledWith('/api/posts/17', expect.any(Object))
    expect(wrapper.emitted('unblurred')).toEqual([
      [{ isBlurred: false, status: 'ok' }],
    ])
  })

  it('emits latest moderation status when attachment gets blocked', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: async () => ({
        post: {
          id: 33,
          attachment_is_blurred: true,
          attachment_moderation_status: 'blocked',
        },
      }),
    })

    const wrapper = renderComponent({
      blurred: true,
      status: 'pending',
      postId: 33,
    })

    await vi.advanceTimersByTimeAsync(4000)
    await Promise.resolve()

    expect(wrapper.emitted('unblurred')).toEqual([
      [{ isBlurred: true, status: 'blocked' }],
    ])
  })
})
