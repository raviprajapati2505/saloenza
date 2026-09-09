import { describe, expect, it } from 'vitest'
import {
  addRetailToCart,
  addServiceToCart,
  cartGrandTotal,
  cartLineKey,
  cartSubtotal,
  cartToCheckoutItems,
  removeCartLine,
  updateCartQuantity,
} from '../posCart.js'

describe('posCart', () => {
  it('adds and merges service lines', () => {
    const service = { service_id: 1, name: 'Haircut', price: 500, duration_minutes: 30 }
    const once = addServiceToCart([], service)
    expect(once).toHaveLength(1)
    const twice = addServiceToCart(once, service)
    expect(twice[0].quantity).toBe(2)
  })

  it('respects retail stock limits', () => {
    const product = { product_id: 9, service_id: 99, name: 'Shampoo', price: 200, stock_on_hand: 2 }
    const cart = addRetailToCart(addRetailToCart([], product), product)
    expect(cart[0].quantity).toBe(2)
    const blocked = addRetailToCart(cart, product)
    expect(blocked[0].quantity).toBe(2)
  })

  it('calculates totals and checkout payload', () => {
    const service = { service_id: 2, name: 'Facial', price: 800, duration_minutes: 60 }
    let cart = addServiceToCart([], service, { staff_id: 3 })
    cart = addRetailToCart(cart, { product_id: 4, service_id: 99, name: 'Serum', price: 150, stock_on_hand: 5 })
    cart = updateCartQuantity(cart, cartLineKey(cart[1]), 2)

    expect(cartSubtotal(cart)).toBe(1100)
    expect(cartGrandTotal(cart, 100)).toBe(1000)
    expect(cartToCheckoutItems(cart)).toEqual([
      expect.objectContaining({ kind: 'service', service_id: 2, staff_id: 3, quantity: 1 }),
      expect.objectContaining({ kind: 'retail', product_id: 4, quantity: 2 }),
    ])

    const trimmed = removeCartLine(cart, cartLineKey(cart[0]))
    expect(trimmed).toHaveLength(1)
  })
})
