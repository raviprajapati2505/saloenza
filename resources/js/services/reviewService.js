import { apiGet, apiPost, apiPut, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchReviewRequests(params = {}) {
  const response = await apiGet('/v1/reviews/requests', { page: 1, per_page: 100, ...params })
  return parseList(response, 'review_requests')
}

export async function fetchServiceRatings(params = {}) {
  const response = await apiGet('/v1/reviews/ratings', { page: 1, per_page: 100, ...params })
  return parseList(response, 'service_ratings')
}

export async function fetchReviewSchedule(params = {}) {
  const response = await apiGet('/v1/reviews/schedule', params)
  return response?.data?.data?.candidates ?? response?.data?.candidates ?? []
}

export async function fetchReviewSettings() {
  const response = await apiGet('/v1/reviews/settings')
  return response?.data?.data?.settings ?? response?.data?.settings ?? null
}

export async function updateReviewSettings(payload) {
  const response = await apiPut('/v1/reviews/settings', payload)
  return response?.data?.data?.settings ?? response?.data?.settings ?? null
}

export async function sendReviewRequest(payload) {
  const response = await apiPost('/v1/reviews/send', payload)
  return response?.data?.data ?? response?.data ?? {}
}

export async function rateReviewRequest(id, payload) {
  const response = await apiPost(`/v1/reviews/requests/${id}/rate`, payload)
  return response?.data?.data ?? response?.data ?? {}
}
