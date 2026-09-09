import { api } from '../lib/api'

const listParams = (params = {}) => ({
  page: 1,
  per_page: 50,
  ...params,
})

export async function fetchAffiliateDashboard() {
  const response = await api.get('/v1/affiliate/dashboard')
  return response.data?.data || response.data
}

export async function fetchAffiliateReferrals(params = {}) {
  const response = await api.get('/v1/affiliate/referrals', { params: listParams(params) })
  return response.data?.data || response.data
}

export async function fetchAffiliateCommissions(params = {}) {
  const response = await api.get('/v1/affiliate/commissions', { params: listParams(params) })
  return response.data?.data || response.data
}

export async function fetchAffiliateWithdrawals(params = {}) {
  const response = await api.get('/v1/affiliate/withdrawals', { params: listParams(params) })
  return response.data?.data || response.data
}

export async function fetchAffiliateReports(params = {}) {
  const response = await api.get('/v1/affiliate/reports', { params })
  return response.data?.data || response.data
}

export async function createAffiliateWithdrawal(payload) {
  const response = await api.post('/v1/affiliate/withdrawals', payload)
  return response.data?.data || response.data
}

export async function applyAffiliatePartner(payload) {
  const response = await api.post('/v1/public/affiliate/apply', payload)
  return response.data?.data || response.data
}

export async function approveAdminAffiliate(id) {
  const response = await api.post(`/v1/admin/affiliates/${id}/approve`)
  return response.data?.data || response.data
}

export async function rejectAdminAffiliate(id, payload = {}) {
  const response = await api.post(`/v1/admin/affiliates/${id}/reject`, payload)
  return response.data?.data || response.data
}

export async function fetchAdminAffiliates(params = {}) {
  const response = await api.get('/v1/admin/affiliates', { params: listParams(params) })
  return response.data?.data || response.data
}

export async function createAdminAffiliate(payload) {
  const response = await api.post('/v1/admin/affiliates', payload)
  return response.data?.data || response.data
}

export async function updateAdminAffiliate(id, payload) {
  const response = await api.put(`/v1/admin/affiliates/${id}`, payload)
  return response.data?.data || response.data
}

export async function fetchAdminAffiliateWithdrawals(params = {}) {
  const response = await api.get('/v1/admin/affiliate-withdrawals', { params: listParams(params) })
  return response.data?.data || response.data
}

export async function approveAdminAffiliateWithdrawal(id, payload = {}) {
  const response = await api.post(`/v1/admin/affiliate-withdrawals/${id}/approve`, payload)
  return response.data?.data || response.data
}

export async function rejectAdminAffiliateWithdrawal(id, payload = {}) {
  const response = await api.post(`/v1/admin/affiliate-withdrawals/${id}/reject`, payload)
  return response.data?.data || response.data
}

export async function markAdminAffiliateWithdrawalPaid(id, payload = {}) {
  const response = await api.post(`/v1/admin/affiliate-withdrawals/${id}/mark-paid`, payload)
  return response.data?.data || response.data
}
