import axios from 'axios'
import { API_BASE_URL } from '../lib/api'

function authHeaders(token) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }
}

function normalizeError(error) {
  const status = error?.response?.status
  if (status === 403) {
    const err = new Error('Forbidden')
    err.forbidden = true
    throw err
  }
  if (status === 422) {
    const err = new Error('Validation failed')
    err.validationErrors = error.response.data?.errors ?? {}
    throw err
  }
  throw error
}

async function apiPost(url, body, token) {
  try {
    const response = await axios.post(url, body, { headers: authHeaders(token) })
    return response.data?.data ?? response.data
  } catch (error) {
    normalizeError(error)
  }
}

async function apiPut(url, body, token) {
  try {
    const response = await axios.put(url, body, { headers: authHeaders(token) })
    return response.data?.data ?? response.data
  } catch (error) {
    normalizeError(error)
  }
}

async function apiGet(url, params, token) {
  try {
    const response = await axios.get(url, { params, headers: authHeaders(token) })
    return response.data?.data ?? response.data
  } catch (error) {
    normalizeError(error)
  }
}

function normalizePhoneForForm(phone) {
  if (!phone) return ''
  const raw = String(phone).trim()
  if (raw.startsWith('+')) return raw
  const digits = raw.replace(/\D/g, '')
  if (!digits) return ''
  return `+${digits}`
}

export function onboardingResponseToFormValues(data) {
  const branch = data?.branch ?? {}
  const user = data?.user ?? {}

  return {
    subscription_plan_id: data?.subscription?.plan?.id ?? data?.saloon?.subscription_plan_id ?? '',
    start_trial: Boolean(data?.subscription?.status === 'trialing' || (data?.subscription?.plan?.trial_days ?? 0) > 0),
    trial_days: Number(data?.subscription?.plan?.trial_days ?? 15),
    salon: {
      business_name: data?.saloon?.business_name ?? '',
      payment_type: data?.saloon?.payment_type ?? undefined,
      amount: data?.saloon?.payment_amount ?? '',
      transaction_id: data?.saloon?.transaction_id ?? '',
      active: data?.saloon?.is_active ?? true,
      referral_code: data?.saloon?.referral_code ?? '',
      affiliate_partner_id: data?.saloon?.affiliate_partner_id ?? data?.saloon?.affiliate_partner?.id ?? null,
    },
    branch: {
      id: branch.id ?? null,
      name: branch.branch_name ?? '',
      address_line_1: branch.business_address_1 ?? '',
      address_line_2: branch.business_address_2 ?? '',
      city: branch.city ?? '',
      state: branch.state ?? '',
      pincode: branch.area_pincode ?? '',
      country: branch.country ?? 'India',
      active: branch.is_active ?? true,
    },
    owner: {
      id: user.id ?? null,
      first_name: user.firstname ?? '',
      last_name: user.lastname ?? '',
      email: user.email ?? '',
      phone: normalizePhoneForForm(user.phone),
      whatsapp: normalizePhoneForForm(user.whatsapp),
      password: '',
      active: user.is_active ?? true,
    },
    service_products: (data?.service_products ?? []).map((row) => ({
      id: row.id ?? null,
      service_id: row.service_id ?? null,
      service: row.service_name ?? '',
      product_id: row.product_id ?? null,
      product: row.product_name ?? '',
      price: Number(row.price) || 0,
      duration: Number(row.duration_minutes) || 30,
      active: row.is_active ?? true,
      is_custom: false,
    })),
  }
}

function buildOnboardingPayload(payload) {
  return {
    subscription_plan_id: payload.subscription_plan_id ? Number(payload.subscription_plan_id) : undefined,
    start_trial: Boolean(payload.start_trial),
    trial_days: payload.start_trial ? Number(payload.trial_days ?? 0) : 0,
    affiliate_partner_id: payload.salon?.affiliate_partner_id
      ? Number(payload.salon.affiliate_partner_id)
      : null,
    saloon: {
      business_name: payload.salon?.business_name,
      payment_type: payload.salon?.payment_type,
      payment_amount: Number(payload.salon?.amount),
      transaction_id: payload.salon?.transaction_id,
      is_active: payload.salon?.active ?? true,
      referral_code: payload.salon?.referral_code || null,
    },
    branch: {
      id: payload.branch?.id ?? undefined,
      branch_name: payload.branch?.name,
      business_address_1: payload.branch?.address_line_1,
      business_address_2: payload.branch?.address_line_2 || null,
      city: payload.branch?.city,
      state: payload.branch?.state,
      area_pincode: payload.branch?.pincode,
      country: payload.branch?.country || 'India',
      is_active: payload.branch?.active ?? true,
    },
    user: {
      id: payload.owner?.id ?? undefined,
      firstname: payload.owner?.first_name,
      lastname: payload.owner?.last_name,
      email: payload.owner?.email,
      phone: payload.owner?.phone,
      whatsapp: payload.owner?.whatsapp || null,
      password: payload.owner?.password || undefined,
      password_confirmation: payload.owner?.password || undefined,
      is_active: payload.owner?.active ?? true,
    },
    service_products: (payload.service_products ?? []).map((sp) => {
      const item = {
        price: Number(sp.price),
        duration_minutes: Number(sp.duration),
        is_active: sp.active ?? true,
      }
      if (sp.id) {
        item.id = Number(sp.id)
      }
      if (sp.service_id) {
        item.service_id = Number(sp.service_id)
      } else if (sp.service?.trim()) {
        item.service_name = sp.service.trim()
      }
      if (sp.product_id) {
        item.product_id = Number(sp.product_id)
      } else if (sp.is_custom && sp.product?.trim()) {
        item.product_name = sp.product.trim()
      } else {
        item.product_id = null
      }
      return item
    }),
  }
}

export function mapBackendValidationField(key) {
  if (key.startsWith('user.')) {
    const sub = key.slice(5)
    const ownerMap = {
      firstname: 'first_name',
      lastname: 'last_name',
      is_active: 'active',
    }
    return `owner.${ownerMap[sub] ?? sub}`
  }

  const branchMap = {
    'branch.branch_name': 'branch.name',
    'branch.business_address_1': 'branch.address_line_1',
    'branch.business_address_2': 'branch.address_line_2',
    'branch.area_pincode': 'branch.pincode',
    'branch.is_active': 'branch.active',
  }
  if (branchMap[key]) {
    return branchMap[key]
  }

  if (key.startsWith('saloon.')) {
    const sub = key.slice(7)
    if (sub === 'business_name') return 'salon.business_name'
    if (sub === 'payment_amount') return 'salon.amount'
    if (sub === 'is_active') return 'salon.active'
    return `salon.${sub}`
  }

  if (key === 'affiliate_partner_id') {
    return 'salon.affiliate_partner_id'
  }

  const serviceProductMatch = key.match(/^service_products\.(\d+)\.(.+)$/)
  if (serviceProductMatch) {
    const [, index, field] = serviceProductMatch
    const fieldMap = {
      service_id: 'service',
      service_name: 'service',
      product_id: 'product',
      product_name: 'product',
      duration_minutes: 'duration',
      is_active: 'active',
    }
    return `service_products.${index}.${fieldMap[field] ?? field}`
  }

  return key
}

export async function fetchOnboardingRecords(params, token) {
  return apiGet(`${API_BASE_URL}/v1/admin/onboarding`, params, token)
}

export async function fetchOnboardingRecord(saloonId, token) {
  return apiGet(`${API_BASE_URL}/v1/admin/onboarding/${saloonId}`, null, token)
}

export async function completeOwnerOnboarding(ownerId, token) {
  return apiPost(`${API_BASE_URL}/v1/admin/onboarding/owners/${ownerId}/complete`, {}, token)
}

export async function resetOwnerOnboarding(ownerId, token) {
  return apiPost(`${API_BASE_URL}/v1/admin/onboarding/owners/${ownerId}/reset`, {}, token)
}

export async function submitOnboarding(payload, token) {
  const body = buildOnboardingPayload(payload)
  return apiPost(`${API_BASE_URL}/v1/admin/onboarding`, body, token)
}

export async function updateOnboarding(saloonId, payload, token) {
  const body = buildOnboardingPayload(payload)
  return apiPut(`${API_BASE_URL}/v1/admin/onboarding/${saloonId}`, body, token)
}
