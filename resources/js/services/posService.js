import { apiGet, apiPost } from '../lib/apiHelpers'

export async function fetchPosCatalog(params = {}) {
  const response = await apiGet('/v1/pos/catalog', params)
  return response?.data?.catalog ?? response?.catalog ?? {}
}

export async function posCheckout(payload) {
  const response = await apiPost('/v1/pos/checkout', payload)
  return response?.data?.appointment ?? response?.appointment ?? null
}
