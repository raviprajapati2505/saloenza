import { apiGet } from '../lib/apiHelpers'

export async function fetchSalonInsights(params = {}) {
  const response = await apiGet('/v1/analytics/insights', params)
  return response?.data?.data?.insights ?? response?.data?.insights ?? null
}
