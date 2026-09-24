import { apiGet, apiPost, apiPut, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchRetentionPolicies() {
  const response = await apiGet('/v1/retention/policies')
  return parseList(response, 'policies')
}

export async function createRetentionPolicy(payload) {
  const response = await apiPost('/v1/retention/policies', payload)
  return parseItem(response, 'policy')
}

export async function updateRetentionPolicy(id, payload) {
  const response = await apiPut(`/v1/retention/policies/${id}`, payload)
  return parseItem(response, 'policy')
}

export async function fetchRetentionCustomers(params = {}) {
  const response = await apiGet('/v1/retention/customers', params)
  return {
    customers: parseList(response, 'customers'),
    status: response?.data?.data?.status ?? response?.data?.status ?? params.status,
  }
}

export async function classifyRetention() {
  const response = await apiPost('/v1/retention/classify')
  return response?.data?.data ?? response?.data ?? {}
}

export async function fetchRetentionCohorts() {
  const response = await apiGet('/v1/retention/cohorts')
  return parseList(response, 'cohorts')
}

export async function createRetentionCohort(payload) {
  const response = await apiPost('/v1/retention/cohorts', payload)
  return parseItem(response, 'cohort')
}

export async function fetchRetentionCohort(id) {
  const response = await apiGet(`/v1/retention/cohorts/${id}`)
  return parseItem(response, 'cohort')
}

export async function sendRetentionCohort(id, payload = {}) {
  const response = await apiPost(`/v1/retention/cohorts/${id}/send`, payload)
  return response?.data?.data ?? response?.data ?? {}
}
