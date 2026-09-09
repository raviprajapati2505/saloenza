import { describe, expect, it, vi } from 'vitest'
import { clearToastDedupeCache, pushToast, subscribeToasts } from '../../stores/toast.js'
import { isForbiddenError, messageFromApiError } from '../apiErrors.js'

describe('forbidden api feedback', () => {
  it('pushes a toast for tenant permission failures', () => {
    clearToastDedupeCache()
    const listener = vi.fn()
    subscribeToasts(listener)

    const error = {
      response: {
        status: 403,
        data: {
          message: 'Forbidden. Tenant permission required.',
          required_permissions: ['roles.read'],
        },
      },
    }

    expect(isForbiddenError(error)).toBe(true)
    pushToast(messageFromApiError(error), 'error')

    expect(listener).toHaveBeenCalledTimes(1)
    expect(listener.mock.calls[0][0].message).toContain('roles.read')
    expect(listener.mock.calls[0][0].type).toBe('error')
  })

  it('dedupes identical forbidden toasts within a short window', () => {
    clearToastDedupeCache()
    const listener = vi.fn()
    subscribeToasts(listener)

    const message = 'Forbidden. Tenant permission required. Required permission: roles.read.'
    pushToast(message, 'error')
    pushToast(message, 'error')

    expect(listener).toHaveBeenCalledTimes(1)
  })
})
