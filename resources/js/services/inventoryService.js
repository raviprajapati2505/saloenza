import { apiGet, apiPost, apiPut, apiDelete, parseList } from '../lib/apiHelpers'

const LIST_DEFAULTS = { page: 1, per_page: 100 }

export async function fetchInventorySummary(params = {}) {
  const response = await apiGet('/v1/inventory/summary', params)
  return response?.data?.summary ?? response?.summary ?? {}
}

export async function fetchInventoryStock(params = {}) {
  const response = await apiGet('/v1/inventory/stock', { ...LIST_DEFAULTS, ...params })
  return {
    stock: parseList(response, 'stock'),
    meta: response?.data?.meta ?? response?.meta ?? {},
  }
}

export async function saveInventoryStock(payload) {
  const response = await apiPost('/v1/inventory/stock', payload)
  return response?.data?.stock ?? response?.stock ?? null
}

export async function adjustInventoryStock(stockId, payload) {
  const response = await apiPost(`/v1/inventory/stock/${stockId}/adjust`, payload)
  return response?.data?.stock ?? response?.stock ?? null
}

export async function fetchSuppliers(params = {}) {
  const response = await apiGet('/v1/suppliers', { page: 1, per_page: 50, ...params })
  return parseList(response, 'suppliers')
}

export async function createSupplier(payload) {
  const response = await apiPost('/v1/suppliers', payload)
  return response?.data?.supplier ?? response?.supplier ?? null
}

export async function fetchPurchaseOrders(params = {}) {
  const response = await apiGet('/v1/purchase-orders', { page: 1, per_page: 20, ...params })
  return parseList(response, 'purchase_orders')
}

export async function createPurchaseOrder(payload) {
  const response = await apiPost('/v1/purchase-orders', payload)
  return response?.data?.purchase_order ?? response?.purchase_order ?? null
}

export async function receivePurchaseOrder(id) {
  const response = await apiPost(`/v1/purchase-orders/${id}/receive`)
  return response?.data?.purchase_order ?? response?.purchase_order ?? null
}

export async function fetchProductSalesReport(params = {}) {
  const response = await apiGet('/v1/inventory/product-sales', params)
  return response?.data?.summary ?? response?.summary ?? null
}

export async function fetchInventoryClassifications(params = {}) {
  const response = await apiGet('/v1/inventory/intelligence/classifications', params)
  return response?.data?.classifications ?? []
}

export async function fetchInventoryStockoutRisks(params = {}) {
  const response = await apiGet('/v1/inventory/intelligence/stockout-risks', params)
  return response?.data?.risks ?? []
}

export async function fetchInventoryAlerts(params = {}) {
  const response = await apiGet('/v1/inventory/intelligence/alerts', params)
  return response?.data?.alerts ?? []
}

export async function recomputeInventoryIntelligence(params = {}) {
  const response = await apiPost('/v1/inventory/intelligence/recompute', params)
  return response?.data ?? {}
}

export async function fetchProducts(params = {}) {
  const response = await apiGet('/v1/products', { page: 1, per_page: 100, ...params })
  return parseList(response, 'products')
}
