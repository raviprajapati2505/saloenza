import { describe, expect, it } from 'vitest'
import {
  APPOINTMENT_STATUSES,
  autoStatusHint,
  canStartService,
  mapAppointmentServicesForUpdate,
  queueBucketForAppointment,
  resolveDefaultAppointmentStatus,
  shiftAppointmentServices,
} from '../appointmentStatus.js'

describe('appointmentStatus', () => {
  it('defaults future same-day bookings to scheduled', () => {
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    const date = tomorrow.toISOString().slice(0, 10)

    expect(resolveDefaultAppointmentStatus({
      type: 'appointment',
      date,
      startTime: '10:00',
      isEditing: false,
    })).toBe(APPOINTMENT_STATUSES.SCHEDULED)
  })

  it('defaults walk-ins to completed', () => {
    expect(resolveDefaultAppointmentStatus({
      type: 'walk_in',
      date: '2026-08-22',
      startTime: '10:00',
      isEditing: false,
    })).toBe(APPOINTMENT_STATUSES.COMPLETED)
  })

  it('places future confirmed bookings in upcoming queue bucket', () => {
    const startsAt = new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString()

    expect(queueBucketForAppointment({
      status: APPOINTMENT_STATUSES.CONFIRMED,
      starts_at: startsAt,
    })).toBe('upcoming')
  })

  it('blocks starting a far-future appointment', () => {
    const startsAt = new Date(Date.now() + 4 * 60 * 60 * 1000).toISOString()
    expect(canStartService(startsAt)).toBe(false)
  })

  it('uses confirmed hint for due same-day bookings', () => {
    const hint = autoStatusHint({
      type: 'appointment',
      date: new Date().toISOString().slice(0, 10),
      startTime: '00:01',
      isProductOnly: false,
    })

    expect(hint).toContain('Confirmed')
  })

  it('preserves per-line staff and schedule in update payloads', () => {
    const raw = {
      staff_id: 1,
      services: [
        {
          service_id: 10,
          staff_id: 2,
          starts_at: '2026-08-22T10:00:00.000Z',
          ends_at: '2026-08-22T10:30:00.000Z',
        },
      ],
    }

    expect(mapAppointmentServicesForUpdate(raw)[0]).toMatchObject({
      staff_id: 2,
      starts_at: '2026-08-22T10:00:00.000Z',
      ends_at: '2026-08-22T10:30:00.000Z',
    })
  })

  it('shifts all service lines when appointment start moves', () => {
    const raw = {
      starts_at: '2026-08-22T10:00:00.000Z',
      services: [
        {
          service_id: 10,
          staff_id: 2,
          starts_at: '2026-08-22T10:00:00.000Z',
          ends_at: '2026-08-22T10:30:00.000Z',
        },
      ],
    }

    const shifted = shiftAppointmentServices(raw, '2026-08-22T11:00:00.000Z')
    expect(shifted[0].starts_at).toBe('2026-08-22T11:00:00.000Z')
    expect(shifted[0].ends_at).toBe('2026-08-22T11:30:00.000Z')
  })
})
