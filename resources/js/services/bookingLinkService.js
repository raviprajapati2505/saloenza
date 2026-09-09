import { api } from '../lib/api'
import { apiGet, apiPost } from '../lib/apiHelpers'

export async function fetchBookingLinks() {
  const response = await apiGet('/v1/booking-links')
  return response?.data?.data?.links ?? []
}

export async function createBookingLink(payload = {}) {
  const response = await apiPost('/v1/booking-links', payload)
  return response?.data?.data?.link ?? null
}

export async function updateBookingLink(id, payload = {}) {
  const response = await api.patch(`/v1/booking-links/${id}`, payload)
  return response?.data?.data?.link ?? null
}

export async function fetchPublicBooking(token) {
  const response = await apiGet(`/v1/public/book/${token}`)
  return response?.data?.data ?? null
}

export async function fetchPublicBookingSlots(token, params = {}) {
  const response = await apiGet(`/v1/public/book/${token}/slots`, params)
  return response?.data?.data ?? null
}

export async function submitPublicBooking(token, payload) {
  const response = await apiPost(`/v1/public/book/${token}`, payload)
  return response?.data?.data?.appointment ?? null
}
