import { apiGet, apiPost, parseList } from '../lib/apiHelpers'

export async function fetchCustomerClv(params = {}) {
  const response = await apiGet('/v1/customers/clv', params)
  return parseList(response, 'customers')
}

export async function fetchClvSummary(params = {}) {
  const response = await apiGet('/v1/clv/summary', params)
  return response?.data?.data?.summary ?? response?.data?.summary ?? null
}

export async function recomputeClv(payload = {}) {
  const response = await apiPost('/v1/clv/recompute', payload)
  return response?.data?.data ?? response?.data ?? {}
}
