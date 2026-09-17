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

const qaAuth = {
  tenant: {
    regional: {
      currency: 'QAR',
      locale: 'en_QA',
      timezone: 'Asia/Qatar',
    },
  },
}

describe('tenantFormatting', () => {
  it('formats QAR amounts from tenant regional settings', () => {
    const formatted = formatMoney(1500, qaAuth)
    expect(formatted).toContain('1,500')
    expect(formatted).toMatch(/QAR|ر\.ق/)
  })

  it('formats USD amounts from tenant regional settings', () => {
    const formatted = formatMoney(99, usAuth)
    expect(formatted).toMatch(/\$|USD/)
    expect(formatted).toContain('99')
  })

  it('exposes currency symbol helper', () => {
    expect(currencySymbol(usAuth)).toBe('$')
    expect(currencySymbol(qaAuth)).toBe('ر.ق')
  })

  it('falls back to QAR for unsupported currencies', () => {
    const legacyAuth = {
      tenant: { regional: { currency: 'INR', locale: 'en_IN', timezone: 'Asia/Kolkata' } },
    }
    expect(currencySymbol(legacyAuth)).toBe('ر.ق')
  })

  it('formats plan prices with billing interval', () => {
    expect(formatPlanPrice({ price: 0, slug: 'free' }, qaAuth)).toBe('Free')
    expect(formatPlanPrice({ price: 999, billing_interval: 'month' }, qaAuth)).toContain('/ month')
  })

  it('createTenantFormatter bundles helpers', () => {
    const fmt = createTenantFormatter(qaAuth)
    expect(fmt.symbol()).toBe('ر.ق')
    expect(fmt.money(500)).toContain('500')
  })
})
