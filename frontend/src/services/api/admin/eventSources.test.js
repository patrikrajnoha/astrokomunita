import { describe, expect, it, vi, beforeEach } from 'vitest'
import api from '@/services/api'
import { getCrawlRuns, getEventSources, runEventSourceCrawl, updateEventSource } from './eventSources'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    patch: vi.fn(),
    post: vi.fn(),
  },
}))

describe('admin event sources api client', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.patch.mockReset()
    api.post.mockReset()
  })

  it('loads event sources', async () => {
    api.get.mockResolvedValue({ data: { data: [] } })

    await getEventSources()

    expect(api.get).toHaveBeenCalledWith('/admin/event-sources')
  })

  it('updates source state', async () => {
    api.patch.mockResolvedValue({ data: { key: 'astropixels', is_enabled: false } })

    await updateEventSource(5, { is_enabled: false })

    expect(api.patch).toHaveBeenCalledWith('/admin/event-sources/5', { is_enabled: false })
  })

  it('runs selected sources and fetches crawl runs', async () => {
    api.post.mockResolvedValue({ data: { status: 'ok' } })
    api.get.mockResolvedValue({ data: { data: [] } })

    await runEventSourceCrawl({ source_keys: ['astropixels'], year: 2026 })
    await getCrawlRuns({ per_page: 10 })

    expect(api.post).toHaveBeenCalledWith('/admin/event-sources/run', { source_keys: ['astropixels'], year: 2026 })
    expect(api.get).toHaveBeenCalledWith('/admin/crawl-runs', { params: { per_page: 10 } })
  })
})
