import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  captureDeferredInstallPrompt,
  clearDeferredInstallPrompt,
  getDeferredInstallPrompt,
  subscribeToInstallAvailable,
} from '../deferredInstallPrompt.js'

describe('deferredInstallPrompt', () => {
  beforeEach(() => {
    clearDeferredInstallPrompt()
  })

  afterEach(() => {
    clearDeferredInstallPrompt()
  })

  it('stores and returns the deferred install prompt', () => {
    const event = {
      preventDefault: vi.fn(),
      prompt: vi.fn(),
      userChoice: Promise.resolve({ outcome: 'accepted' }),
    }

    captureDeferredInstallPrompt(event)

    expect(event.preventDefault).toHaveBeenCalled()
    expect(getDeferredInstallPrompt()).toBe(event)
  })

  it('notifies subscribers when install becomes available', () => {
    const callback = vi.fn()
    const unsubscribe = subscribeToInstallAvailable(callback)
    const event = {
      preventDefault: vi.fn(),
      prompt: vi.fn(),
      userChoice: Promise.resolve({ outcome: 'accepted' }),
    }

    captureDeferredInstallPrompt(event)

    expect(callback).toHaveBeenCalledWith(event)

    unsubscribe()
  })

  it('clears the stored prompt', () => {
    captureDeferredInstallPrompt({
      preventDefault: vi.fn(),
    })

    clearDeferredInstallPrompt()

    expect(getDeferredInstallPrompt()).toBeNull()
  })
})
