import { api } from '../lib/api'

export async function fetchSalonReferralDashboard() {
  const response = await api.get('/v1/referrals/dashboard')
  return response.data?.data || response.data
}
