import { describe, expect, it, vi } from 'vitest'
import { useAdminTable } from './useAdminTable'

describe('useAdminTable', () => {
  it('resolves pagination from Laravel resource meta payload', async () => {
    const fetchFunction = vi.fn(async () => ({
      data: {
        data: [{ id: 1 }],
        meta: {
          current_page: 1,
          last_page: 3,
          per_page: 20,
          total: 42,
          from: 1,
          to: 20,
        },
      },
    }))

    const table = useAdminTable(fetchFunction, { autoFetch: false })
    await table.fetch()

    expect(table.pagination.value).toEqual({
      currentPage: 1,
      lastPage: 3,
      perPage: 20,
      total: 42,
      from: 1,
      to: 20,
    })
    expect(table.hasNextPage.value).toBe(true)
    expect(table.hasPrevPage.value).toBe(false)
  })

  it('supports legacy top-level paginator payload', async () => {
    const fetchFunction = vi.fn(async () => ({
      data: {
        data: [{ id: 1 }],
        current_page: 2,
        last_page: 4,
        per_page: 10,
        total: 33,
        from: 11,
        to: 20,
      },
    }))

    const table = useAdminTable(fetchFunction, { autoFetch: false })
    await table.fetch()

    expect(table.pagination.value).toEqual({
      currentPage: 2,
      lastPage: 4,
      perPage: 10,
      total: 33,
      from: 11,
      to: 20,
    })
    expect(table.hasNextPage.value).toBe(true)
    expect(table.hasPrevPage.value).toBe(true)
  })

  it('auto fetch reacts to setFilter updates including zero values', async () => {
    const fetchFunction = vi.fn(async () => ({
      data: {
        data: [],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 20,
          total: 0,
        },
      },
    }))

    const table = useAdminTable(fetchFunction, { autoFetch: true })
    expect(fetchFunction).toHaveBeenCalledTimes(1)

    table.setFilter('visibility', 0)

    await vi.waitFor(() => {
      expect(fetchFunction).toHaveBeenCalledTimes(2)
    })
    expect(fetchFunction.mock.calls[1][0]).toMatchObject({
      visibility: 0,
    })
  })
})
