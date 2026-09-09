import { api } from './api'
import { customerPhoneDisplay } from './customerContact.js'

/** Parse Laravel paginated or flat list responses. */
export function parseList(response, key) {
  const payload = response?.data?.data?.[key] ?? response?.data?.[key]
  if (!payload) return []
  if (Array.isArray(payload.data)) return payload.data
  if (Array.isArray(payload)) return payload
  return []
}

/** Read pagination meta from a list API response. */
export function parseListMeta(response) {
  return response?.data?.data?.meta ?? response?.data?.meta ?? null
}

const MASTER_LIST_PER_PAGE = 100

/** Fetch all pages of a master list endpoint (services, products, etc.). */
export async function fetchMasterList(path, collectionKey, params = {}) {
  let page = 1
  let lastPage = 1
  const items = []

  do {
    const response = await apiGet(path, {
      ...params,
      page,
      per_page: MASTER_LIST_PER_PAGE,
    })
    items.push(...parseList(response, collectionKey))
    const meta = parseListMeta(response)
    lastPage = meta?.last_page ?? page
    page += 1
  } while (page <= lastPage)

  return items
}

/** Parse single-item create/update/show responses. */
export function parseItem(response, key) {
  return response?.data?.data?.[key] ?? response?.data?.[key] ?? null
}

export async function apiGet(path, params = {}) {
  const response = await api.get(path, { params })
  return response
}

export async function apiPost(path, data) {
  const response = await api.post(path, data)
  return response
}

export async function apiPut(path, data) {
  const response = await api.put(path, data)
  return response
}

export async function apiDelete(path) {
  const response = await api.delete(path)
  return response
}

/** True when value parses to a valid calendar date/time. */
export function isValidCalendarDate(value) {
  if (value == null || value === '') return false
  const date = value instanceof Date ? value : new Date(value)
  return !Number.isNaN(date.getTime())
}

function defaultEventEnd(start, durationMinutes) {
  return new Date(new Date(start).getTime() + durationMinutes * 60000).toISOString()
}

/** Map appointment API record to FullCalendar event shape. Returns null when start is invalid. */
export function appointmentToCalendarEvent(appointment) {
  const serviceNames = appointment.services?.length
    ? appointment.services.map(s => s.service?.name).filter(Boolean)
    : (appointment.service?.name ? [appointment.service.name] : [])
  const productNames = appointment.products?.length
    ? appointment.products.map(p => p.product?.name).filter(Boolean)
    : []
  const displayNames = serviceNames.length ? serviceNames : productNames
  const serviceLabel = displayNames.length > 1
    ? `${displayNames[0]} +${displayNames.length - 1}`
    : (displayNames[0] || 'Appointment')
  const staffNames = appointment.services?.length
    ? [...new Set(appointment.services.map(s => s.staff?.name).filter(Boolean))].join(', ')
    : (appointment.staff?.name || '')

  const start = appointment.starts_at
  if (!isValidCalendarDate(start)) return null

  const durationMinutes = appointment.services?.reduce(
    (sum, s) => sum + (Number(s.duration_minutes) || 60),
    0,
  ) || 60

  let end = appointment.ends_at
  if (!isValidCalendarDate(end)) {
    end = defaultEventEnd(start, durationMinutes)
  } else if (new Date(end).getTime() <= new Date(start).getTime()) {
    end = defaultEventEnd(start, durationMinutes)
  }

  return {
    id: String(appointment.id),
    title: serviceLabel || appointment.customer?.name || 'Appointment',
    start,
    end,
    extendedProps: {
      status: appointment.status,
      type: appointment.type || 'appointment',
      service: displayNames.join(', ') || serviceLabel,
      serviceCount: displayNames.length,
      product: appointment.product?.name || '',
      customer: appointment.customer?.name || '',
      customerPhone: customerPhoneDisplay(appointment.customer) || '',
      customerHasContact: Boolean(
        appointment.customer?.has_phone
        || appointment.customer?.has_email
        || appointment.customer?.phone
        || appointment.customer?.email,
      ),
      customerId: appointment.customer_id,
      staffId: appointment.staff_id,
      staffName: staffNames,
      branchName: appointment.branch?.name || appointment.branch?.branch_name || '',
      serviceId: appointment.service_id,
      productId: appointment.product_id,
      services: appointment.services || [],
      products: appointment.products || [],
      servicesTotal: appointment.services_total != null ? Number(appointment.services_total) : null,
      productsTotal: appointment.products_total != null ? Number(appointment.products_total) : null,
      duration: appointment.starts_at && appointment.ends_at
        ? `${Math.round((new Date(appointment.ends_at) - new Date(appointment.starts_at)) / 60000)}m`
        : '',
      price: appointment.price != null ? Number(appointment.price) : null,
      discount: appointment.discount != null ? Number(appointment.discount) : 0,
      grandTotal: appointment.grand_total != null ? Number(appointment.grand_total) : null,
      paymentStatus: appointment.payment_status || 'unpaid',
      paymentMethod: appointment.payment_method || '',
      amountPaid: appointment.amount_paid != null ? Number(appointment.amount_paid) : 0,
      balanceDue: appointment.balance_due != null ? Number(appointment.balance_due) : null,
      notes: appointment.notes || '',
      raw: appointment,
    },
  }
}

/** Combine date + time string into ISO datetime. */
export function combineDateAndTime(date, timeStr) {
  if (!date || !timeStr) return null

  let hours
  let minutes

  const ampmMatch = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i)
  if (ampmMatch) {
    hours = parseInt(ampmMatch[1], 10)
    minutes = parseInt(ampmMatch[2], 10)
    const meridiem = ampmMatch[3].toUpperCase()
    if (meridiem === 'PM' && hours !== 12) hours += 12
    if (meridiem === 'AM' && hours === 12) hours = 0
  } else {
    const [h, m] = timeStr.split(':')
    hours = parseInt(h, 10)
    minutes = parseInt(m, 10)
  }

  const result = new Date(date)
  result.setHours(hours, minutes, 0, 0)
  return result.toISOString()
}
