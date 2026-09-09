import { apiGet } from '../lib/apiHelpers'

export async function fetchStaffEarnings(params = {}) {
  const response = await apiGet('/v1/staff/earnings', params)
  return response?.data?.data?.report ?? null
}

export async function fetchStaffEarningsPresets(params = {}) {
  const response = await apiGet('/v1/staff/earnings/presets', params)
  return response?.data?.data ?? null
}
