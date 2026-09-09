import { describe, expect, it } from 'vitest'
import { hasTenantContext } from '../guards.jsx'

describe('tenant guards', () => {
  it('detects tenant context for salon users', () => {
    expect(hasTenantContext({
      isSystemAdmin: false,
      tenant: { id: 1 },
      user: { saloon_id: 1 },
    })).toBe(true)
  })

  it('treats platform owners as having tenant context', () => {
    expect(hasTenantContext({
      grantsAllPermissions: true,
      tenant: null,
      user: { saloon_id: null },
    })).toBe(true)
  })
})
