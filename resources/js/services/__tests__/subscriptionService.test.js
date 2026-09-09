import { describe, expect, it } from 'vitest'
import {
  isUpgradePlan,
  trialDaysRemaining,
  usagePercent,
} from '../../services/subscriptionService.js'

describe('subscriptionService helpers', () => {
  it('calculates trial days remaining', () => {
    const future = new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString()
    expect(trialDaysRemaining(future)).toBe(3)
    expect(trialDaysRemaining(null)).toBeNull()
  })

  it('calculates usage percent with cap', () => {
    expect(usagePercent(2, 5)).toBe(40)
    expect(usagePercent(5, 5)).toBe(100)
    expect(usagePercent(2, null)).toBe(0)
  })

  it('detects upgrade candidates by sort order or price', () => {
    const current = { id: 1, sort_order: 10, price: 999 }
    const higher = { id: 2, sort_order: 20, price: 2499 }
    const same = { id: 1, sort_order: 10, price: 999 }

    expect(isUpgradePlan(current, higher)).toBe(true)
    expect(isUpgradePlan(current, same)).toBe(false)
  })
})
