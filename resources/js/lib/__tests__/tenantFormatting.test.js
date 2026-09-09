import { describe, expect, it } from 'vitest'
import {
  createTenantFormatter,
  currencySymbol,
  formatMoney,
  formatPlanPrice,
} from '../tenantFormatting.js'

const usAuth = {
  tenant: {
    regional: {
      currency: 'USD',
      locale: 'en_US',
      timezone: 'America/New_York',
    },
  },
}

const inAuth = {
  tenant: {
    regional: {
      currency: 'INR',
      locale: 'en_IN',
      timezone: 'Asia/Kolkata',
    },
  },
}

describe('tenantFormatting', () => {
  it('formats INR amounts from tenant regional settings', () => {
    const formatted = formatMoney(1500, inAuth)
    expect(formatted).toContain('1,500')
    expect(formatted).toMatch(/₹|INR/)
  })

  it('formats USD amounts from tenant regional settings', () => {
    const formatted = formatMoney(99, usAuth)
    expect(formatted).toMatch(/\$|USD/)
    expect(formatted).toContain('99')
  })

  it('exposes currency symbol helper', () => {
    expect(currencySymbol(usAuth)).toBe('$')
    expect(currencySymbol(inAuth)).toBe('₹')
  })

  it('formats plan prices with billing interval', () => {
    expect(formatPlanPrice({ price: 0, slug: 'free' }, inAuth)).toBe('Free')
    expect(formatPlanPrice({ price: 999, billing_interval: 'month' }, inAuth)).toContain('/ month')
  })

  it('createTenantFormatter bundles helpers', () => {
    const fmt = createTenantFormatter(inAuth)
    expect(fmt.symbol()).toBe('₹')
    expect(fmt.money(500)).toContain('500')
  })
})
