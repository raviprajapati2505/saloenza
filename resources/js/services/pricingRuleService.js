import { apiGet, apiPost, apiPut, apiDelete, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchPricingRules(params = {}) {
  const response = await apiGet('/v1/pricing-rules', { page: 1, per_page: 100, ...params })
  return parseList(response, 'pricing_rules')
}

export async function createPricingRule(payload) {
  const response = await apiPost('/v1/pricing-rules', payload)
  return parseItem(response, 'pricing_rule')
}

export async function updatePricingRule(id, payload) {
  const response = await apiPut(`/v1/pricing-rules/${id}`, payload)
  return parseItem(response, 'pricing_rule')
}

export async function deletePricingRule(id) {
  return apiDelete(`/v1/pricing-rules/${id}`)
}

export async function resolvePrice(params) {
  const response = await apiGet('/v1/pricing/resolve', params)
  return response?.data?.data ?? response?.data ?? null
}
