import { apiGet, apiPost, apiPut, apiDelete, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchCommissionSchemes(params = {}) {
  const response = await apiGet('/v1/commissions/schemes', { page: 1, per_page: 100, ...params })
  return parseList(response, 'schemes')
}

export async function fetchCommissionScheme(id) {
  const response = await apiGet(`/v1/commissions/schemes/${id}`)
  return parseItem(response, 'scheme')
}

export async function createCommissionScheme(payload) {
  const response = await apiPost('/v1/commissions/schemes', payload)
  return parseItem(response, 'scheme')
}

export async function updateCommissionScheme(id, payload) {
  const response = await apiPut(`/v1/commissions/schemes/${id}`, payload)
  return parseItem(response, 'scheme')
}

export async function deleteCommissionScheme(id) {
  return apiDelete(`/v1/commissions/schemes/${id}`)
}

export async function migrateFlatRatesToScheme() {
  const response = await apiPost('/v1/commissions/schemes', { migrate_from_flat_rates: true })
  return parseItem(response, 'scheme')
}

export async function fetchCommissionRules(schemeId, params = {}) {
  const response = await apiGet(`/v1/commissions/schemes/${schemeId}/rules`, { page: 1, per_page: 100, ...params })
  return parseList(response, 'rules')
}

export async function createCommissionRule(schemeId, payload) {
  const response = await apiPost(`/v1/commissions/schemes/${schemeId}/rules`, payload)
  return parseItem(response, 'rule')
}

export async function updateCommissionRule(schemeId, ruleId, payload) {
  const response = await apiPut(`/v1/commissions/schemes/${schemeId}/rules/${ruleId}`, payload)
  return parseItem(response, 'rule')
}

export async function deleteCommissionRule(schemeId, ruleId) {
  return apiDelete(`/v1/commissions/schemes/${schemeId}/rules/${ruleId}`)
}

export async function fetchCommissionAssignments(params = {}) {
  const response = await apiGet('/v1/commissions/assignments', { page: 1, per_page: 100, ...params })
  return parseList(response, 'assignments')
}

export async function createCommissionAssignment(payload) {
  const response = await apiPost('/v1/commissions/assignments', payload)
  return parseItem(response, 'assignment')
}

export async function updateCommissionAssignment(id, payload) {
  const response = await apiPut(`/v1/commissions/assignments/${id}`, payload)
  return parseItem(response, 'assignment')
}

export async function deleteCommissionAssignment(id) {
  return apiDelete(`/v1/commissions/assignments/${id}`)
}

export async function fetchCommissionPeriods(params = {}) {
  const response = await apiGet('/v1/commissions/periods', { page: 1, per_page: 100, ...params })
  return parseList(response, 'periods')
}

export async function createCommissionPeriod(payload) {
  const response = await apiPost('/v1/commissions/periods', payload)
  return parseItem(response, 'period')
}

export async function lockCommissionPeriod(id) {
  const response = await apiPost(`/v1/commissions/periods/${id}/lock`)
  return parseItem(response, 'period')
}
