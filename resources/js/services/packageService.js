import { apiGet, apiPost, apiPut, apiDelete, parseList, parseItem } from '../lib/apiHelpers'

export async function fetchPackages(params = {}) {
  const response = await apiGet('/v1/packages', { page: 1, per_page: 100, ...params })
  return parseList(response, 'packages')
}

export async function fetchPackage(id) {
  const response = await apiGet(`/v1/packages/${id}`)
  return parseItem(response, 'package')
}

export async function createPackage(payload) {
  const response = await apiPost('/v1/packages', payload)
  return parseItem(response, 'package')
}

export async function updatePackage(id, payload) {
  const response = await apiPut(`/v1/packages/${id}`, payload)
  return parseItem(response, 'package')
}

export async function deletePackage(id) {
  return apiDelete(`/v1/packages/${id}`)
}

export async function sellPackage(id, payload) {
  const response = await apiPost(`/v1/packages/${id}/sell`, payload)
  return parseItem(response, 'customer_package')
}

export async function fetchCustomerPackages(params = {}) {
  const response = await apiGet('/v1/customer-packages', { page: 1, per_page: 100, ...params })
  return parseList(response, 'customer_packages')
}

export async function redeemCustomerPackage(id, payload) {
  const response = await apiPost(`/v1/customer-packages/${id}/redeem`, payload)
  return parseItem(response, 'customer_package')
}
