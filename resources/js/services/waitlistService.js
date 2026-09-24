import { apiGet, apiPost, apiDelete, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchWaitlist(params = {}) {
  const response = await apiGet('/v1/waitlist', { page: 1, per_page: 100, ...params })
  return parseList(response, 'entries')
}

export async function createWaitlistEntry(payload) {
  const response = await apiPost('/v1/waitlist', payload)
  return parseItem(response, 'entry')
}

export async function deleteWaitlistEntry(id) {
  return apiDelete(`/v1/waitlist/${id}`)
}

export async function createWaitlistOffer(entryId, payload) {
  const response = await apiPost(`/v1/waitlist/${entryId}/offers`, payload)
  return parseItem(response, 'offer')
}

export async function acceptWaitlistOffer(offerId) {
  const response = await apiPost(`/v1/waitlist/offers/${offerId}/accept`)
  return parseItem(response, 'offer')
}

export async function declineWaitlistOffer(offerId, payload = {}) {
  const response = await apiPost(`/v1/waitlist/offers/${offerId}/decline`, payload)
  return parseItem(response, 'offer')
}

export async function createOffersFromCancellation(payload) {
  const response = await apiPost('/v1/waitlist/offers/from-cancellation', payload)
  return response?.data?.data?.offers ?? response?.data?.offers ?? []
}
