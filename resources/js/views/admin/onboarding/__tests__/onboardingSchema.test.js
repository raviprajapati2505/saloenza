import { describe, expect, it } from 'vitest'
import {
  branchSchema,
  onboardingSchema,
  ownerSchema,
  salonSchema,
  serviceProductSchema,
} from '../onboardingSchema.js'

// ---------------------------------------------------------------------------
// Salon Schema
// ---------------------------------------------------------------------------
describe('salonSchema', () => {
  const validSalon = {
    business_name: 'ABC Salon',
    payment_type: 'Monthly',
    amount: 1000,
    transaction_id: 'TXN123456',
    active: true,
    referral_code: '',
  }

  it('passes with valid data', () => {
    const result = salonSchema.safeParse(validSalon)
    expect(result.success).toBe(true)
  })

  it('fails when business_name is empty', () => {
    const result = salonSchema.safeParse({ ...validSalon, business_name: '' })
    expect(result.success).toBe(false)
  })

  it('fails when payment_type is invalid', () => {
    const result = salonSchema.safeParse({ ...validSalon, payment_type: 'Weekly' })
    expect(result.success).toBe(false)
  })

  it('accepts all valid payment types', () => {
    for (const type of ['Monthly', 'Quarterly', 'Yearly', 'One-time']) {
      const result = salonSchema.safeParse({ ...validSalon, payment_type: type })
      expect(result.success).toBe(true)
    }
  })

  it('fails when amount is negative', () => {
    const result = salonSchema.safeParse({ ...validSalon, amount: -5 })
    expect(result.success).toBe(false)
  })

  it('coerces string amount to number', () => {
    const result = salonSchema.safeParse({ ...validSalon, amount: '500' })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.amount).toBe(500)
    }
  })

  it('fails when transaction_id is empty', () => {
    const result = salonSchema.safeParse({ ...validSalon, transaction_id: '' })
    expect(result.success).toBe(false)
  })

  it('defaults active to true when omitted', () => {
    const { active, ...withoutActive } = validSalon
    const result = salonSchema.safeParse(withoutActive)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.active).toBe(true)
    }
  })

  it('defaults referral_code to empty string when omitted', () => {
    const { referral_code, ...withoutCode } = validSalon
    const result = salonSchema.safeParse(withoutCode)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.referral_code).toBe('')
    }
  })
})

// ---------------------------------------------------------------------------
// Branch Schema
// ---------------------------------------------------------------------------
describe('branchSchema', () => {
  const validBranch = {
    name: 'Downtown Branch',
    address_line_1: '123 Main St',
    address_line_2: '',
    city: 'Mumbai',
    state: 'Maharashtra',
    pincode: '400001',
    country: 'India',
    active: true,
  }

  it('passes with valid data', () => {
    const result = branchSchema.safeParse(validBranch)
    expect(result.success).toBe(true)
  })

  it('fails when name is empty', () => {
    const result = branchSchema.safeParse({ ...validBranch, name: '' })
    expect(result.success).toBe(false)
  })

  it('fails when address_line_1 is empty', () => {
    const result = branchSchema.safeParse({ ...validBranch, address_line_1: '' })
    expect(result.success).toBe(false)
  })

  it('accepts empty address_line_2', () => {
    const result = branchSchema.safeParse({ ...validBranch, address_line_2: '' })
    expect(result.success).toBe(true)
  })

  it('fails when city is empty', () => {
    const result = branchSchema.safeParse({ ...validBranch, city: '' })
    expect(result.success).toBe(false)
  })

  it('fails when state is empty', () => {
    const result = branchSchema.safeParse({ ...validBranch, state: '' })
    expect(result.success).toBe(false)
  })

  it('fails when pincode is less than 6 digits', () => {
    const result = branchSchema.safeParse({ ...validBranch, pincode: '12345' })
    expect(result.success).toBe(false)
  })

  it('fails when pincode is more than 6 digits', () => {
    const result = branchSchema.safeParse({ ...validBranch, pincode: '1234567' })
    expect(result.success).toBe(false)
  })

  it('fails when pincode contains non-digit characters', () => {
    const result = branchSchema.safeParse({ ...validBranch, pincode: '40ABC1' })
    expect(result.success).toBe(false)
  })

  it('accepts exactly 6-digit pincode', () => {
    const result = branchSchema.safeParse({ ...validBranch, pincode: '110001' })
    expect(result.success).toBe(true)
  })

  it('defaults country to India when omitted', () => {
    const { country, ...withoutCountry } = validBranch
    const result = branchSchema.safeParse(withoutCountry)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.country).toBe('India')
    }
  })
})

// ---------------------------------------------------------------------------
// Owner Schema
// ---------------------------------------------------------------------------
describe('ownerSchema', () => {
  const validOwner = {
    first_name: 'First Name',
    last_name: 'Last Name',
    email: 'owner@example.com',
    phone: '+919000000000',
    whatsapp: '',
    password: '',
    active: true,
  }

  it('passes with valid data', () => {
    const result = ownerSchema.safeParse(validOwner)
    expect(result.success).toBe(true)
  })

  it('fails when first_name is empty', () => {
    const result = ownerSchema.safeParse({ ...validOwner, first_name: '' })
    expect(result.success).toBe(false)
  })

  it('fails when last_name is empty', () => {
    const result = ownerSchema.safeParse({ ...validOwner, last_name: '' })
    expect(result.success).toBe(false)
  })

  it('fails when email is invalid', () => {
    const result = ownerSchema.safeParse({ ...validOwner, email: 'not-an-email' })
    expect(result.success).toBe(false)
  })

  it('fails when email is empty', () => {
    const result = ownerSchema.safeParse({ ...validOwner, email: '' })
    expect(result.success).toBe(false)
  })

  it('fails when phone is invalid', () => {
    const result = ownerSchema.safeParse({ ...validOwner, phone: '12345' })
    expect(result.success).toBe(false)
  })

  it('fails when phone contains letters', () => {
    const result = ownerSchema.safeParse({ ...validOwner, phone: '+9198765ABCDE' })
    expect(result.success).toBe(false)
  })

  it('accepts E.164 phone with country code', () => {
    const result = ownerSchema.safeParse({ ...validOwner, phone: '+919876543210' })
    expect(result.success).toBe(true)
  })

  it('accepts empty optional whatsapp', () => {
    const result = ownerSchema.safeParse({ ...validOwner, whatsapp: '' })
    expect(result.success).toBe(true)
  })

  it('accepts valid optional whatsapp', () => {
    const result = ownerSchema.safeParse({ ...validOwner, whatsapp: '+919876543211' })
    expect(result.success).toBe(true)
  })

  it('fails when whatsapp is invalid', () => {
    const result = ownerSchema.safeParse({ ...validOwner, whatsapp: '98765' })
    expect(result.success).toBe(false)
  })

  it('allows empty password (optional)', () => {
    const result = ownerSchema.safeParse({ ...validOwner, password: '' })
    expect(result.success).toBe(true)
  })

  it('allows password to be omitted', () => {
    const { password, ...withoutPassword } = validOwner
    const result = ownerSchema.safeParse(withoutPassword)
    expect(result.success).toBe(true)
  })
})

// ---------------------------------------------------------------------------
// Service Product Schema
// ---------------------------------------------------------------------------
describe('serviceProductSchema', () => {
  const validProduct = {
    service: 'Haircut',
    product: 'Scissors Set',
    price: 200,
    duration: 30,
    active: true,
  }

  it('passes with valid data', () => {
    const result = serviceProductSchema.safeParse(validProduct)
    expect(result.success).toBe(true)
  })

  it('fails when service is empty', () => {
    const result = serviceProductSchema.safeParse({ ...validProduct, service: '' })
    expect(result.success).toBe(false)
  })

  it('allows empty product for service-only offerings', () => {
    const result = serviceProductSchema.safeParse({ ...validProduct, product: '', product_id: null })
    expect(result.success).toBe(true)
  })

  it('fails when price is negative', () => {
    const result = serviceProductSchema.safeParse({ ...validProduct, price: -10 })
    expect(result.success).toBe(false)
  })

  it('coerces string price to number', () => {
    const result = serviceProductSchema.safeParse({ ...validProduct, price: '250' })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.price).toBe(250)
    }
  })

  it('fails when duration is 0', () => {
    const result = serviceProductSchema.safeParse({ ...validProduct, duration: 0 })
    expect(result.success).toBe(false)
  })

  it('coerces string duration to number', () => {
    const result = serviceProductSchema.safeParse({ ...validProduct, duration: '45' })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.duration).toBe(45)
    }
  })
})

// ---------------------------------------------------------------------------
// Composite Onboarding Schema
// ---------------------------------------------------------------------------
describe('onboardingSchema', () => {
  const validPayload = {
    subscription_plan_id: 1,
    start_trial: true,
    trial_days: 15,
    salon: {
      business_name: 'ABC Salon',
      payment_type: 'Yearly',
      amount: 5000,
      transaction_id: 'TXN999',
      active: true,
      referral_code: 'REF100',
    },
    branch: {
      name: 'Main Branch',
      address_line_1: '1 MG Road',
      address_line_2: 'Suite 5',
      city: 'Pune',
      state: 'Maharashtra',
      pincode: '411001',
      country: 'India',
      active: true,
    },
    owner: {
      first_name: 'First Name',
      last_name: 'Last Name',
      email: 'owner@example.com',
      phone: '+919876543210',
      whatsapp: '',
      password: 'Secure@123',
      active: true,
    },
    service_products: [],
  }

  it('passes with full valid payload', () => {
    const result = onboardingSchema.safeParse(validPayload)
    expect(result.success).toBe(true)
  })

  it('passes without service_products (defaults to empty array)', () => {
    const { service_products, ...withoutProducts } = validPayload
    const result = onboardingSchema.safeParse(withoutProducts)
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.service_products).toEqual([])
    }
  })

  it('fails when salon section is invalid', () => {
    const result = onboardingSchema.safeParse({
      ...validPayload,
      salon: { ...validPayload.salon, business_name: '' },
    })
    expect(result.success).toBe(false)
  })

  it('fails when branch pincode is invalid', () => {
    const result = onboardingSchema.safeParse({
      ...validPayload,
      branch: { ...validPayload.branch, pincode: 'ABC' },
    })
    expect(result.success).toBe(false)
  })

  it('fails when owner email is invalid', () => {
    const result = onboardingSchema.safeParse({
      ...validPayload,
      owner: { ...validPayload.owner, email: 'bad-email' },
    })
    expect(result.success).toBe(false)
  })

  it('validates service_products items when present', () => {
    const result = onboardingSchema.safeParse({
      ...validPayload,
      service_products: [{ service: '', product: '', price: -1, duration: 0 }],
    })
    expect(result.success).toBe(false)
  })

  it('allows service_products without a product (service-only price)', () => {
    const result = onboardingSchema.safeParse({
      ...validPayload,
      service_products: [
        { service_id: 1, service: 'Haircut', product_id: null, product: '', price: 299, duration: 30, active: true },
      ],
    })
    expect(result.success).toBe(true)
  })

  it('preserves owner, branch, and service product ids on edit payloads', () => {
    const result = onboardingSchema.safeParse({
      ...validPayload,
      subscription_plan_id: 1,
      branch: { ...validPayload.branch, id: 12 },
      owner: { ...validPayload.owner, id: 34 },
      service_products: [
        { id: 56, service: 'Haircut', product: 'Scissors', price: 200, duration: 30, active: true },
      ],
    })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.branch.id).toBe(12)
      expect(result.data.owner.id).toBe(34)
      expect(result.data.service_products[0].id).toBe(56)
    }
  })
})
