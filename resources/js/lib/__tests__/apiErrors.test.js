import { describe, expect, it } from 'vitest'
import { formatForbiddenMessage } from '../apiErrors.js'

describe('formatForbiddenMessage', () => {
  it('includes tenant required permissions', () => {
    const message = formatForbiddenMessage({
      message: 'Forbidden. Tenant permission required.',
      required_permissions: ['customers.manage', 'customers.view'],
    })

    expect(message).toContain('customers.manage')
    expect(message).toContain('customers.view')
    expect(message).toContain('Required one of')
  })

  it('includes platform required permission', () => {
    const message = formatForbiddenMessage({
      message: 'Forbidden. Platform permission required.',
      required_permission: 'platform.onboarding.manage',
    })

    expect(message).toContain('platform.onboarding.manage')
    expect(message).toContain('Required permission')
  })

  it('falls back to base message when no permission metadata', () => {
    expect(formatForbiddenMessage({
      message: 'Forbidden. Tenant context required.',
    })).toBe('Forbidden. Tenant context required.')
  })
})
