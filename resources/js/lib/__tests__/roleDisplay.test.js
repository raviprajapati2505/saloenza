import { describe, expect, it } from 'vitest'
import {
  getLoginWelcomeMessage,
  getRoleDisplay,
  ROLE_CODES,
} from '../roleDisplay.js'

describe('roleDisplay', () => {
  it('labels system admin as platform super admin', () => {
    expect(getRoleDisplay({ isSystemAdmin: true }).label).toBe('Platform Super Admin')
    expect(getRoleDisplay({ isSystemAdmin: true }).scopeLabel).toBe('Platform')
  })

  it('uses role name and scope for salon franchise owner', () => {
    const display = getRoleDisplay({
      isSystemAdmin: false,
      role: {
        name: 'Salon Franchise Owner',
        code: ROLE_CODES.SALON_FRANCHISE_OWNER,
        scope: 'salon',
      },
    })
    expect(display.label).toBe('Salon Franchise Owner')
    expect(display.scopeLabel).toBe('Salon')
    expect(display.tone).toBe('salon')
  })

  it('falls back to code labels when role name is missing', () => {
    expect(getRoleDisplay({
      role: { code: ROLE_CODES.SALON_BRANCH_MANAGER, scope: 'branch' },
    }).label).toBe('Branch Manager')
  })

  it('builds login welcome message with user and role', () => {
    const message = getLoginWelcomeMessage({
      user: { name: 'Alex Owner' },
      role: {
        name: 'Salon Franchise Owner',
        code: ROLE_CODES.SALON_FRANCHISE_OWNER,
        scope: 'salon',
      },
    })
    expect(message).toBe('Signed in as Alex Owner · Salon Franchise Owner (Salon)')
  })
})
