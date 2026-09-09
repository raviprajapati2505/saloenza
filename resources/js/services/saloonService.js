import { apiGet, apiPut, apiDelete, parseItem } from '../lib/apiHelpers'

export async function fetchSaloon(id) {
  const response = await apiGet(`/v1/saloons/${id}`)
  return parseItem(response, 'saloon')
}

export async function updateSaloon(id, payload) {
  const response = await apiPut(`/v1/saloons/${id}`, payload)
  return parseItem(response, 'saloon')
}

export async function deleteSaloon(id) {
  return apiDelete(`/v1/saloons/${id}`)
}

export function saloonToFormValues(saloon) {
  if (!saloon) {
    return {
      business_name: '',
      payment_type: undefined,
      amount: '',
      transaction_id: '',
      active: true,
      referral_code: '',
    }
  }

  return {
    business_name: saloon.business_name || '',
    payment_type: saloon.payment_type || undefined,
    amount: saloon.payment_amount ?? '',
    transaction_id: saloon.transaction_id || '',
    active: saloon.is_active ?? true,
    referral_code: saloon.referral_code || '',
  }
}

export function formValuesToSaloonPayload(form) {
  return {
    business_name: form.business_name,
    payment_type: form.payment_type,
    payment_amount: Number(form.amount),
    transaction_id: form.transaction_id,
    is_active: Boolean(form.active),
    referral_code: form.referral_code?.trim() || null,
  }
}
