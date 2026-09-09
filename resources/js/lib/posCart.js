export function cartLineKey(line) {
  if (line.kind === 'retail') return `retail-${line.product_id}`
  const productPart = line.product_id ? `-p${line.product_id}` : ''
  const staffPart = line.staff_id ? `-s${line.staff_id}` : ''
  return `service-${line.service_id}${productPart}${staffPart}`
}

export function cartSubtotal(cart) {
  return cart.reduce((sum, line) => sum + (Number(line.price) || 0) * (Number(line.quantity) || 1), 0)
}

export function cartGrandTotal(cart, discount = 0) {
  return Math.max(cartSubtotal(cart) - (Number(discount) || 0), 0)
}

export function addServiceToCart(cart, service, options = {}) {
  const line = {
    kind: 'service',
    service_id: service.service_id,
    name: service.name,
    product_id: options.product_id ?? null,
    product_name: options.product_name ?? null,
    staff_id: options.staff_id ?? null,
    staff_name: options.staff_name ?? null,
    price: Number(options.price ?? service.price) || 0,
    duration_minutes: Number(options.duration_minutes ?? service.duration_minutes) || 60,
    quantity: 1,
  }
  const key = cartLineKey(line)
  const existing = cart.find((row) => cartLineKey(row) === key)
  if (existing) {
    return cart.map((row) => (cartLineKey(row) === key
      ? { ...row, quantity: row.quantity + 1 }
      : row))
  }
  return [...cart, line]
}

export function addRetailToCart(cart, product) {
  const line = {
    kind: 'retail',
    product_id: product.product_id,
    name: product.name,
    price: Number(product.price) || 0,
    stock_on_hand: product.stock_on_hand,
    quantity: 1,
  }
  const key = cartLineKey(line)
  const existing = cart.find((row) => cartLineKey(row) === key)
  if (existing) {
    const nextQty = existing.quantity + 1
    if (product.stock_on_hand != null && nextQty > product.stock_on_hand) return cart
    return cart.map((row) => (cartLineKey(row) === key ? { ...row, quantity: nextQty } : row))
  }
  return [...cart, line]
}

export function updateCartQuantity(cart, key, quantity) {
  const qty = Math.max(1, Number(quantity) || 1)
  return cart.map((row) => {
    if (cartLineKey(row) !== key) return row
    if (row.kind === 'retail' && row.stock_on_hand != null && qty > row.stock_on_hand) {
      return { ...row, quantity: row.stock_on_hand }
    }
    return { ...row, quantity: qty }
  })
}

export function removeCartLine(cart, key) {
  return cart.filter((row) => cartLineKey(row) !== key)
}

export function cartToCheckoutItems(cart) {
  return cart.map((line) => ({
    kind: line.kind,
    service_id: line.kind === 'retail' ? null : (line.service_id ?? null),
    product_id: line.product_id ?? null,
    staff_id: line.staff_id ?? null,
    quantity: line.quantity,
    price: line.price,
    duration_minutes: line.duration_minutes ?? null,
  }))
}

export function lineLabel(line) {
  if (line.kind === 'retail') return line.name
  const parts = [line.name]
  if (line.product_name) parts.push(line.product_name)
  return parts.join(' · ')
}
