import { apiGet } from '../lib/apiHelpers'

export async function fetchLiveQueue(params = {}) {
  const response = await apiGet('/v1/queue/live', params)
  return response?.data?.data ?? null
}
