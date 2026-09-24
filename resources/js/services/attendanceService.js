import { apiGet, apiPost, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchAttendancePunches(params = {}) {
  const response = await apiGet('/v1/attendance/punches', { page: 1, per_page: 100, ...params })
  return parseList(response, 'punches')
}

export async function punchAttendance(payload) {
  const response = await apiPost('/v1/attendance/punch', payload)
  return parseItem(response, 'punch')
}

export async function fetchLeaveRequests(params = {}) {
  const response = await apiGet('/v1/attendance/leave-requests', { page: 1, per_page: 100, ...params })
  return parseList(response, 'leave_requests')
}

export async function requestLeave(payload) {
  const response = await apiPost('/v1/attendance/leave-requests', payload)
  return parseItem(response, 'leave_request')
}

export async function decideLeave(id, payload) {
  const response = await apiPost(`/v1/attendance/leave-requests/${id}/decide`, payload)
  return parseItem(response, 'leave_request')
}

export async function generateShifts(payload) {
  const response = await apiPost('/v1/attendance/shifts/generate', payload)
  return response?.data?.data ?? response?.data ?? {}
}
