import { api } from '../lib/api.js'

export async function fetchAdminNotifications(params = {}) {
  const response = await api.get('/v1/admin/notifications', { params })
  return response.data?.data || {}
}

export async function fetchAdminNotificationUnreadCount() {
  const response = await api.get('/v1/admin/notifications/unread-count')
  return response.data?.data || { unread_count: 0 }
}

export async function fetchAdminPendingCounts() {
  const response = await api.get('/v1/admin/notifications/pending-counts')
  return response.data?.data?.counts || {}
}

export async function markAdminNotificationRead(id) {
  const response = await api.post(`/v1/admin/notifications/${id}/read`)
  return response.data?.data || {}
}

export async function markAllAdminNotificationsRead() {
  const response = await api.post('/v1/admin/notifications/read-all')
  return response.data?.data || {}
}
