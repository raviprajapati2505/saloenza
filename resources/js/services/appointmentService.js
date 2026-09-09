import { api } from '../lib/api'
import { apiPost, parseItem } from '../lib/apiHelpers'

export async function downloadAppointmentReceipt(appointmentId) {
  const response = await api.get(`/v1/appointments/${appointmentId}/receipt`, {
    responseType: 'blob',
  })

  const blob = new Blob([response.data], { type: 'application/pdf' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `receipt-${appointmentId}.pdf`
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

export async function captureAppointmentPayment(appointmentId, payload) {
  const response = await apiPost(`/v1/appointments/${appointmentId}/capture-payment`, payload)
  return parseItem(response, 'appointment')
}

export async function refundAppointmentPayment(appointmentId, payload = {}) {
  const response = await apiPost(`/v1/appointments/${appointmentId}/refund-payment`, payload)
  return parseItem(response, 'appointment')
}

export async function remindAppointmentPayment(appointmentId) {
  const response = await apiPost(`/v1/appointments/${appointmentId}/remind-payment`)
  return parseItem(response, 'appointment')
}
