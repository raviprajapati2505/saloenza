import { apiGet, apiPost, apiPut, apiDelete, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchCustomers(params = {}) {
  const response = await apiGet('/v1/customers', params)
  return parseList(response, 'customers')
}

export async function fetchCustomer(customerId) {
  const response = await apiGet(`/v1/customers/${customerId}`)
  return parseItem(response, 'customer')
}

export async function createCustomer(payload) {
  const response = await apiPost('/v1/customers', payload)
  return parseItem(response, 'customer')
}

export async function updateCustomer(customerId, payload) {
  const response = await apiPut(`/v1/customers/${customerId}`, payload)
  return parseItem(response, 'customer')
}

export async function deleteCustomer(customerId) {
  await apiDelete(`/v1/customers/${customerId}`)
}

export async function fetchCustomerTags(params = {}) {
  const response = await apiGet('/v1/customer-tags', params)
  return parseList(response, 'tags')
}

export async function createCustomerTag(payload) {
  const response = await apiPost('/v1/customer-tags', payload)
  return parseItem(response, 'tag')
}

export async function updateCustomerTag(tagId, payload) {
  const response = await apiPut(`/v1/customer-tags/${tagId}`, payload)
  return parseItem(response, 'tag')
}

export async function deleteCustomerTag(tagId) {
  await apiDelete(`/v1/customer-tags/${tagId}`)
}
