import { describe, expect, it } from 'vitest'
import {
  customerContactSubtitle,
  customerEmailDisplay,
  customerHasContactOnFile,
  customerPhoneDisplay,
} from '../customerContact.js'

describe('customerContact helpers', () => {
  it('hides phone and email when the viewer cannot see contacts', () => {
    const customer = {
      phone: '+919999999999',
      email: 'hidden@example.com',
      can_view_contact: false,
      has_phone: true,
      has_email: true,
    }

    expect(customerPhoneDisplay(customer)).toBe('Hidden')
    expect(customerEmailDisplay(customer)).toBe('Hidden')
    expect(customerContactSubtitle(customer)).toBe('Contact hidden')
    expect(customerHasContactOnFile(customer)).toBe(true)
  })

  it('shows real contacts when allowed', () => {
    const customer = {
      phone: '+919888888888',
      email: 'open@example.com',
      can_view_contact: true,
      has_phone: true,
      has_email: true,
    }

    expect(customerPhoneDisplay(customer)).toBe('+919888888888')
    expect(customerEmailDisplay(customer)).toBe('open@example.com')
    expect(customerContactSubtitle(customer)).toBe('open@example.com')
  })

  it('handles customers with no contact on file', () => {
    const customer = {
      can_view_contact: false,
      has_phone: false,
      has_email: false,
    }

    expect(customerPhoneDisplay(customer)).toBeNull()
    expect(customerContactSubtitle(customer)).toBe('No contact info')
    expect(customerHasContactOnFile(customer)).toBe(false)
  })
})
