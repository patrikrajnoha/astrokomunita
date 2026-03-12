import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  clearHomeFeedPrefetch,
  consumeHomeFeedPrefetch,
  prefetchHomeFeed,
} from './feedPrefetch'

describe('feedPrefetch', () => {
  beforeEach(() => {
    clearHomeFeedPrefetch()
  })

  it('prefetches and consumes payload once', async () => {
    const payload = {
      data: [{ id: 1 }, { id: 2 }],
      next_page_url: '/feed?page=2',
    }
    const api = {
      get: vi.fn().mockResolvedValue({ data: payload }),
    }

    const prefetched = await prefetchHomeFeed(api)
    expect(prefetched).toEqual(payload)
    expect(api.get).toHaveBeenCalledTimes(1)

    const consumed = consumeHomeFeedPrefetch()
    expect(consumed).toEqual(payload)
    expect(consumeHomeFeedPrefetch()).toBeNull()
  })

  it('deduplicates concurrent prefetch requests', async () => {
    const payload = { data: [{ id: 1 }], next_page_url: null }
    const api = {
      get: vi.fn().mockResolvedValue({ data: payload }),
    }

    const [first, second] = await Promise.all([
      prefetchHomeFeed(api),
      prefetchHomeFeed(api),
    ])

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(first).toEqual(payload)
    expect(second).toEqual(payload)
  })

  it('ignores stale in-flight payload after prefetch state is cleared', async () => {
    const firstPayload = { data: [{ id: 1 }], next_page_url: '/feed?page=2' }
    const secondPayload = { data: [{ id: 2 }], next_page_url: null }

    let resolveFirstRequest
    const firstRequest = new Promise((resolve) => {
      resolveFirstRequest = resolve
    })

    const api = {
      get: vi
        .fn()
        .mockImplementationOnce(() => firstRequest)
        .mockResolvedValueOnce({ data: secondPayload }),
    }

    const stalePrefetchPromise = prefetchHomeFeed(api)
    clearHomeFeedPrefetch()
    const freshPrefetchPromise = prefetchHomeFeed(api)

    resolveFirstRequest({ data: firstPayload })
    await stalePrefetchPromise

    const freshPayload = await freshPrefetchPromise
    expect(api.get).toHaveBeenCalledTimes(2)
    expect(freshPayload).toEqual(secondPayload)
    expect(consumeHomeFeedPrefetch()).toEqual(secondPayload)
  })
})
