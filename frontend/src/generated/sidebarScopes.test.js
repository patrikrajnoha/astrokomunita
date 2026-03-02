import { describe, expect, it } from 'vitest'
import {
  DEFAULT_SIDEBAR_SCOPE,
  normalizeSidebarScope,
} from '@/generated/sidebarScopes'

describe('generated sidebar scopes', () => {
  it('normalizes undefined to the default scope', () => {
    expect(normalizeSidebarScope(undefined)).toBe(DEFAULT_SIDEBAR_SCOPE)
  })

  it('keeps valid sidebar scopes unchanged', () => {
    expect(normalizeSidebarScope('search')).toBe('search')
    expect(normalizeSidebarScope('settings')).toBe('settings')
  })

  it('falls back to the default scope for invalid values', () => {
    expect(normalizeSidebarScope('neexistuje')).toBe(DEFAULT_SIDEBAR_SCOPE)
  })
})
