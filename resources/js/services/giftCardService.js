import { apiGet, apiPost, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchGiftCards(params = {}) {
  const response = await apiGet('/v1/gift-cards', { page: 1, per_page: 100, ...params })
  return parseList(response, 'gift_cards')
}

export async function issueGiftCard(payload) {
  const response = await apiPost('/v1/gift-cards', payload)
  return parseItem(response, 'gift_card')
}

export async function lookupGiftCard(code) {
  const response = await apiGet('/v1/gift-cards/lookup', { code })
  return parseItem(response, 'gift_card')
}

export async function fetchGiftCard(id) {
  const response = await apiGet(`/v1/gift-cards/${id}`)
  return parseItem(response, 'gift_card')
}

export async function redeemGiftCard(id, payload) {
  const response = await apiPost(`/v1/gift-cards/${id}/redeem`, payload)
  return parseItem(response, 'gift_card')
}

export async function adjustGiftCard(id, payload) {
  const response = await apiPost(`/v1/gift-cards/${id}/adjust`, payload)
  return parseItem(response, 'gift_card')
}
