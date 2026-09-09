/** Helpers for master service ↔ product linkage and default pricing. */

export const DEFAULT_PRODUCT_NAME = 'General Supplies'

/**
 * Normalize linked products from a ServiceResource (`products` array).
 * Each entry may be `{ product_id, default_price, product }` or a bare product.
 * @param {object|null|undefined} service
 * @returns {Array<{ id: number, name: string, default_price: number|null, link: object }>}
 */
export function linkedProductsOf(service) {
  const rows = Array.isArray(service?.products) ? service.products : []
  return rows
    .map((link) => {
      const product = link?.product ?? link
      const id = Number(link?.product_id ?? product?.id)
      if (!id) return null
      return {
        id,
        name: product?.name ?? DEFAULT_PRODUCT_NAME,
        default_price: link?.default_price != null ? Number(link.default_price) : null,
        link,
      }
    })
    .filter(Boolean)
}

/**
 * @param {object|null|undefined} service
 * @param {object|null|undefined} fallbackProduct
 */
export function pickDefaultProduct(service, fallbackProduct = null) {
  const linked = linkedProductsOf(service)
  if (linked[0]) {
    return { id: linked[0].id, name: linked[0].name }
  }
  if (fallbackProduct?.id) {
    return { id: fallbackProduct.id, name: fallbackProduct.name ?? DEFAULT_PRODUCT_NAME }
  }
  return null
}

/**
 * Resolve price for a service + optional product from the master linkage.
 * No product (or unknown product) → service.default_price.
 * @param {object|null|undefined} service
 * @param {number|string|null|undefined} productId
 * @returns {number}
 */
export function resolveProductPrice(service, productId) {
  if (productId == null || productId === '') {
    return Number(service?.default_price) || 0
  }
  const linked = linkedProductsOf(service)
  const match = linked.find((p) => Number(p.id) === Number(productId))
  if (match?.default_price != null && !Number.isNaN(match.default_price)) {
    return match.default_price
  }
  return Number(service?.default_price) || 0
}

/**
 * Products available in a dropdown for a given master service.
 * Prefers linked products; falls back to all active master products.
 * @param {object|null|undefined} service
 * @param {Array<object>} masterProducts
 */
export function productsForService(service, masterProducts = []) {
  const linked = linkedProductsOf(service)
  if (linked.length > 0) {
    return linked.map((p) => ({
      id: p.id,
      name: p.name,
      is_active: true,
      default_price: p.default_price,
    }))
  }
  return (masterProducts || []).filter((p) => p.is_active !== false)
}

/**
 * Build one offering row for a master service + optional product.
 * @param {object} service
 * @param {{ id: number, name?: string }|null|undefined} product
 * @param {object} [extra]
 */
export function createOfferingFromServiceProduct(service, product = null, extra = {}) {
  const productId = product?.id ?? null
  return {
    service_id: service.id,
    service: service.name,
    name: service.name,
    category_id: service.category_id ?? service.category?.id ?? null,
    product_id: productId,
    product: productId ? (product?.name ?? '') : '',
    price: resolveProductPrice(service, productId),
    duration: Number(service.duration_minutes) || 30,
    active: true,
    is_custom: false,
    ...extra,
  }
}

/**
 * Create a service-only offering (no product). Product-specific rows are added separately.
 * @param {object} service
 * @param {Array<object>} [_masterProducts] unused — kept for call-site compatibility
 * @param {number[]|null} [productIds] when provided, create product rows for those ids instead
 */
export function createOfferingsForService(service, _masterProducts = [], productIds = null) {
  if (Array.isArray(productIds) && productIds.length > 0) {
    const linked = linkedProductsOf(service)
    const selected = linked.filter((p) => productIds.some((id) => Number(id) === Number(p.id)))
    return selected.map((p) => createOfferingFromServiceProduct(service, p))
  }

  return [createOfferingFromServiceProduct(service, null)]
}

/** @param {Array<{ service_id?: number|null, product_id?: number|null }>} rows */
export function selectedProductIdsByService(rows) {
  const map = new Map()
  for (const row of rows || []) {
    if (!row?.service_id || !row?.product_id) continue
    const serviceId = Number(row.service_id)
    const productId = Number(row.product_id)
    if (!map.has(serviceId)) map.set(serviceId, new Set())
    map.get(serviceId).add(productId)
  }
  return map
}

/** Whether a service-only (no product) offering exists for the service. */
export function hasServiceOnlyOffering(rows, serviceId) {
  return (rows || []).some(
    (row) => Number(row?.service_id) === Number(serviceId) && (row?.product_id == null || row?.product_id === ''),
  )
}

export function sameOffering(row, serviceId, productId) {
  const rowProduct = row?.product_id == null || row?.product_id === ''
    ? null
    : Number(row.product_id)
  const targetProduct = productId == null || productId === ''
    ? null
    : Number(productId)
  return Number(row?.service_id) === Number(serviceId) && rowProduct === targetProduct
}
