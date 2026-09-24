import { apiGet, apiPost, apiPut, apiDelete, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchMarketingSegments(params = {}) {
  const response = await apiGet('/v1/marketing/segments', params)
  return parseList(response, 'segments')
}

export async function createMarketingSegment(payload) {
  const response = await apiPost('/v1/marketing/segments', payload)
  return parseItem(response, 'segment')
}

export async function updateMarketingSegment(id, payload) {
  const response = await apiPut(`/v1/marketing/segments/${id}`, payload)
  return parseItem(response, 'segment')
}

export async function deleteMarketingSegment(id) {
  return apiDelete(`/v1/marketing/segments/${id}`)
}

export async function previewMarketingSegment(id) {
  const response = await apiGet(`/v1/marketing/segments/${id}/preview`)
  return response?.data?.data ?? response?.data ?? {}
}

export async function fetchMarketingCampaigns(params = {}) {
  const response = await apiGet('/v1/marketing/campaigns', params)
  return parseList(response, 'campaigns')
}

export async function createMarketingCampaign(payload) {
  const response = await apiPost('/v1/marketing/campaigns', payload)
  return parseItem(response, 'campaign')
}

export async function fetchMarketingCampaign(id) {
  const response = await apiGet(`/v1/marketing/campaigns/${id}`)
  return parseItem(response, 'campaign')
}

export async function sendMarketingCampaign(id) {
  const response = await apiPost(`/v1/marketing/campaigns/${id}/send`)
  return parseItem(response, 'campaign')
}
