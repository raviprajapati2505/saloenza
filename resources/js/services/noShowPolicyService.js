import { apiGet, apiPost, apiPut, parseItem } from '../lib/apiHelpers'

export async function fetchNoShowPolicy() {
  const response = await apiGet('/v1/no-show-policy')
  return parseItem(response, 'policy')
}

export async function updateNoShowPolicy(payload) {
  const response = await apiPut('/v1/no-show-policy', payload)
  return parseItem(response, 'policy')
}

export async function evaluateNoShowPolicy(payload) {
  const response = await apiPost('/v1/no-show-policy/evaluate', payload)
  return response?.data?.data ?? response?.data ?? {}
}

export async function markDepositPaid(appointmentId) {
  const response = await apiPost(`/v1/appointments/${appointmentId}/deposit/paid`)
  return parseItem(response, 'appointment')
}

export async function waiveDeposit(appointmentId) {
  const response = await apiPost(`/v1/appointments/${appointmentId}/deposit/waive`)
  return parseItem(response, 'appointment')
}

export async function refundDeposit(appointmentId) {
  const response = await apiPost(`/v1/appointments/${appointmentId}/deposit/refund`)
  return parseItem(response, 'appointment')
}

export async function applyNoShowFee(appointmentId, payload = {}) {
  const response = await apiPost(`/v1/appointments/${appointmentId}/no-show-fee`, payload)
  return parseItem(response, 'appointment')
}
