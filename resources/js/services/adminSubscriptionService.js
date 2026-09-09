import axios from 'axios'
import { API_BASE_URL } from '../lib/api'

function authHeaders(token) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }
}

export async function fetchSubscriptionUpgradeOrders(params, token) {
  const response = await axios.get(`${API_BASE_URL}/v1/admin/subscription-upgrades`, {
    params,
    headers: authHeaders(token),
  })
  const raw = response.data?.data?.subscription_upgrade_orders
  return {
    orders: Array.isArray(raw) ? raw : Array.isArray(raw?.data) ? raw.data : [],
    meta: response.data?.data?.meta ?? raw?.meta ?? null,
  }
}

export async function approveSubscriptionUpgrade(orderId, payload, token) {
  const response = await axios.post(
    `${API_BASE_URL}/v1/admin/subscription-upgrades/${orderId}/approve`,
    payload,
    { headers: authHeaders(token) },
  )
  return response.data?.data?.subscription_upgrade_order ?? response.data?.data ?? response.data
}

export async function rejectSubscriptionUpgrade(orderId, reason, token) {
  const response = await axios.post(
    `${API_BASE_URL}/v1/admin/subscription-upgrades/${orderId}/reject`,
    { reason },
    { headers: authHeaders(token) },
  )
  return response.data?.data?.subscription_upgrade_order ?? response.data?.data ?? response.data
}

export async function assignSalonSubscription(saloonId, payload, token) {
  const response = await axios.post(
    `${API_BASE_URL}/v1/admin/saloons/${saloonId}/subscription/assign`,
    payload,
    { headers: authHeaders(token) },
  )
  return response.data?.data?.subscription_upgrade_order ?? response.data?.data ?? response.data
}

export async function fetchSubscriptionHistory(params, token) {
  const response = await axios.get(`${API_BASE_URL}/v1/admin/subscription-history`, {
    params,
    headers: authHeaders(token),
  })
  const raw = response.data?.data?.subscription_histories
  return {
    histories: Array.isArray(raw) ? raw : Array.isArray(raw?.data) ? raw.data : [],
    meta: response.data?.data?.meta ?? raw?.meta ?? null,
  }
}

export async function fetchUpcomingSubscriptionRenewals(params = {}, token) {
  const response = await axios.get(`${API_BASE_URL}/v1/admin/subscription-renewals`, {
    params: { days: 30, limit: 12, include_expired: true, ...params },
    headers: authHeaders(token),
  })
  return response.data?.data || response.data
}
