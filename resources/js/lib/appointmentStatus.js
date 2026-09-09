export const APPOINTMENT_STATUSES = {
  SCHEDULED: 'scheduled',
  CONFIRMED: 'confirmed',
  IN_PROGRESS: 'in-progress',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
  NO_SHOW: 'no-show',
}

export const EARLY_START_GRACE_MINUTES = 15
export const WAITING_WINDOW_MINUTES = 15

export function compareDateToToday(dateStr) {
  if (!dateStr) return 0
  const selected = new Date(`${dateStr}T12:00:00`)
  const today = new Date()
  today.setHours(12, 0, 0, 0)
  selected.setHours(12, 0, 0, 0)
  if (selected.getTime() === today.getTime()) return 0
  return selected.getTime() > today.getTime() ? 1 : -1
}

export function parseStartDateTime(dateStr, startTime) {
  if (!dateStr || !startTime) return null
  const normalized = String(startTime).slice(0, 5)
  const start = new Date(`${dateStr}T${normalized}:00`)
  return Number.isNaN(start.getTime()) ? null : start
}

export function isStartInFuture(dateStr, startTime, now = new Date()) {
  const start = parseStartDateTime(dateStr, startTime)
  return Boolean(start && start.getTime() > now.getTime())
}

export function canStartService(startsAtIso, now = new Date()) {
  if (!startsAtIso) return true
  const startsAt = new Date(startsAtIso)
  if (Number.isNaN(startsAt.getTime())) return true
  const earliest = now.getTime() + EARLY_START_GRACE_MINUTES * 60 * 1000
  return startsAt.getTime() <= earliest
}

/**
 * Default status when creating a booking (not editing).
 */
export function resolveDefaultAppointmentStatus({
  type,
  date,
  startTime,
  isEditing,
  current,
}) {
  if (isEditing && current) return current
  if (type === 'walk_in' || type === 'product_sale') return APPOINTMENT_STATUSES.COMPLETED

  const cmp = compareDateToToday(date)
  if (cmp < 0) return APPOINTMENT_STATUSES.COMPLETED
  if (cmp > 0) return APPOINTMENT_STATUSES.SCHEDULED
  if (isStartInFuture(date, startTime)) return APPOINTMENT_STATUSES.SCHEDULED

  return APPOINTMENT_STATUSES.CONFIRMED
}

export function autoStatusHint({ type, date, startTime, isProductOnly }) {
  if (type === 'walk_in' || type === 'product_sale' || isProductOnly) {
    return 'Completed (auto)'
  }

  const cmp = compareDateToToday(date)
  if (cmp < 0) return 'Completed (auto)'
  if (cmp > 0) return 'Scheduled (auto)'
  if (isStartInFuture(date, startTime)) return 'Scheduled (auto)'

  return 'Confirmed — waiting to start (auto)'
}

/**
 * Queue bucket for live floor views.
 * @returns {'in_service'|'waiting'|'upcoming'|null}
 */
export function queueBucketForAppointment(appt, now = new Date()) {
  const status = appt?.status
  const startsAt = appt?.starts_at ? new Date(appt.starts_at) : null

  if (status === APPOINTMENT_STATUSES.IN_PROGRESS) return 'in_service'
  if (![APPOINTMENT_STATUSES.SCHEDULED, APPOINTMENT_STATUSES.CONFIRMED].includes(status)) {
    return null
  }

  if (!startsAt || Number.isNaN(startsAt.getTime())) return 'waiting'

  const waitingCutoff = now.getTime() + WAITING_WINDOW_MINUTES * 60 * 1000
  if (startsAt.getTime() <= waitingCutoff) return 'waiting'

  return 'upcoming'
}

export function bookingSourceLabel(source) {
  if (!source) return null
  const labels = {
    internal: 'Staff booked',
    self_booking: 'Self-booked',
    pos: 'POS',
    walk_in: 'Walk-in',
  }
  return labels[source] || source
}

/**
 * Normalize appointment service lines for PUT payloads (status changes, calendar moves).
 * Preserves per-line staff and schedule when present on the raw appointment.
 */
export function mapAppointmentServicesForUpdate(raw) {
  const legacyLine = raw?.service_id
    ? [{
        service_id: raw.service_id,
        product_id: raw.product_id,
        staff_id: raw.staff_id,
        price: raw.price,
        duration_minutes: null,
        starts_at: raw.starts_at ?? null,
        ends_at: raw.ends_at ?? null,
      }]
    : []

  return (raw?.services?.length ? raw.services : legacyLine).map((line) => ({
    service_id: line.service_id,
    product_id: line.product_id ?? null,
    staff_id: line.staff_id ?? raw.staff_id ?? null,
    price: line.price ?? null,
    duration_minutes: line.duration_minutes ?? null,
    starts_at: line.starts_at ?? null,
    ends_at: line.ends_at ?? null,
  }))
}

/**
 * Shift all service line times when the appointment header start moves (calendar drag/drop).
 */
export function shiftAppointmentServices(raw, newStartsAtIso) {
  const services = mapAppointmentServicesForUpdate(raw)
  const oldStartMs = raw?.starts_at ? new Date(raw.starts_at).getTime() : null
  const newStartMs = newStartsAtIso ? new Date(newStartsAtIso).getTime() : null

  if (oldStartMs == null || newStartMs == null || oldStartMs === newStartMs) {
    return services
  }

  const deltaMs = newStartMs - oldStartMs

  return services.map((line) => {
    if (!line.starts_at || !line.ends_at) return line
    return {
      ...line,
      starts_at: new Date(new Date(line.starts_at).getTime() + deltaMs).toISOString(),
      ends_at: new Date(new Date(line.ends_at).getTime() + deltaMs).toISOString(),
    }
  })
}
