import { describe, expect, it } from 'vitest'
import {
  createOfferingsForService,
  linkedProductsOf,
  pickDefaultProduct,
  productsForService,
  resolveProductPrice,
  selectedProductIdsByService,
} from '../serviceProductHelpers.js'

describe('serviceProductHelpers', () => {
  const service = {
    id: 1,
    name: 'Haircut',
    default_price: 500,
    duration_minutes: 30,
    products: [
      { product_id: 10, default_price: 450, product: { id: 10, name: 'Basic Kit' } },
      { product_id: 11, default_price: 700, product: { id: 11, name: 'Premium Kit' } },
    ],
  }

  it('resolves linked products from service resource shape', () => {
    expect(linkedProductsOf(service)).toEqual([
      { id: 10, name: 'Basic Kit', default_price: 450, link: service.products[0] },
      { id: 11, name: 'Premium Kit', default_price: 700, link: service.products[1] },
    ])
  })

  it('picks the first linked product by default', () => {
    expect(pickDefaultProduct(service)).toEqual({ id: 10, name: 'Basic Kit' })
  })

  it('resolves price from the selected product linkage', () => {
    expect(resolveProductPrice(service, 10)).toBe(450)
    expect(resolveProductPrice(service, 11)).toBe(700)
    expect(resolveProductPrice(service, 99)).toBe(500)
  })

  it('limits product options to linked products when present', () => {
    const options = productsForService(service, [
      { id: 10, name: 'Basic Kit', is_active: true },
      { id: 99, name: 'Other', is_active: true },
    ])
    expect(options.map((p) => p.id)).toEqual([10, 11])
  })

  it('creates a service-only offering by default (products added separately)', () => {
    const rows = createOfferingsForService(service)
    expect(rows).toHaveLength(1)
    expect(rows[0]).toMatchObject({
      service_id: 1,
      product_id: null,
      price: 500,
    })
  })

  it('creates one offering per selected linked product with its own price', () => {
    const rows = createOfferingsForService(service, [], [10, 11])
    expect(rows).toHaveLength(2)
    expect(rows.map((r) => ({ product_id: r.product_id, price: r.price }))).toEqual([
      { product_id: 10, price: 450 },
      { product_id: 11, price: 700 },
    ])
  })

  it('creates offerings only for selected product ids', () => {
    const rows = createOfferingsForService(service, [], [11])
    expect(rows).toHaveLength(1)
    expect(rows[0].product_id).toBe(11)
    expect(rows[0].price).toBe(700)
  })

  it('tracks selected product ids by service', () => {
    const map = selectedProductIdsByService([
      { service_id: 1, product_id: 10 },
      { service_id: 1, product_id: 11 },
      { service_id: 2, product_id: 10 },
    ])
    expect([...map.get(1)]).toEqual([10, 11])
    expect([...map.get(2)]).toEqual([10])
  })
})
