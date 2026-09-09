import { apiGet, apiPost, apiPut, apiDelete, parseList } from '../lib/apiHelpers'

export async function fetchExpenses(params = {}) {
  const response = await apiGet('/v1/expenses', { page: 1, per_page: 100, ...params })
  return parseList(response, 'expenses')
}

export async function fetchExpenseCategories() {
  const response = await apiGet('/v1/expenses/categories')
  return response?.data?.categories ?? response?.categories ?? []
}

export async function createExpense(payload) {
  const response = await apiPost('/v1/expenses', payload)
  return response?.data?.expense ?? response?.expense ?? null
}

export async function updateExpense(id, payload) {
  const response = await apiPut(`/v1/expenses/${id}`, payload)
  return response?.data?.expense ?? response?.expense ?? null
}

export async function deleteExpense(id) {
  return apiDelete(`/v1/expenses/${id}`)
}

export async function fetchProfitLossReport(params = {}) {
  const response = await apiGet('/v1/reports/profit-loss', params)
  return response?.data?.summary ?? response?.summary ?? null
}
