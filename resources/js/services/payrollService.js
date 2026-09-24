import { api, API_BASE_URL } from '../lib/api'
import { apiGet, apiPost, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchPayRuns(params = {}) {
  const response = await apiGet('/v1/payroll/pay-runs', { page: 1, per_page: 100, ...params })
  return parseList(response, 'pay_runs')
}

export async function fetchPayRun(id) {
  const response = await apiGet(`/v1/payroll/pay-runs/${id}`)
  return parseItem(response, 'pay_run')
}

export async function createPayRun(payload) {
  const response = await apiPost('/v1/payroll/pay-runs', payload)
  return parseItem(response, 'pay_run')
}

export async function calculatePayRun(id) {
  const response = await apiPost(`/v1/payroll/pay-runs/${id}/calculate`)
  return parseItem(response, 'pay_run')
}

export async function approvePayRun(id) {
  const response = await apiPost(`/v1/payroll/pay-runs/${id}/approve`)
  return parseItem(response, 'pay_run')
}

export async function markPayRunPaid(id) {
  const response = await apiPost(`/v1/payroll/pay-runs/${id}/mark-paid`)
  return parseItem(response, 'pay_run')
}

export function payRunExportCsvUrl(id) {
  return `${API_BASE_URL}/v1/payroll/pay-runs/${id}/export.csv`
}

export async function downloadPayRunCsv(id) {
  const response = await api.get(`/v1/payroll/pay-runs/${id}/export.csv`, {
    responseType: 'blob',
  })
  const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `pay-run-${id}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
