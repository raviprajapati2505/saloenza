import { describe, expect, it } from 'vitest'
import { appointmentToCalendarEvent, isValidCalendarDate } from '../../lib/apiHelpers.js'

describe('isValidCalendarDate', () => {
  it('accepts ISO strings and Date objects', () => {
    expect(isValidCalendarDate('2026-08-23T10:00:00.000Z')).toBe(true)
    expect(isValidCalendarDate(new Date('2026-08-23T10:00:00.000Z'))).toBe(true)
  })

  it('rejects empty and invalid values', () => {
    expect(isValidCalendarDate(null)).toBe(false)
    expect(isValidCalendarDate('')).toBe(false)
    expect(isValidCalendarDate('not-a-date')).toBe(false)
  })
})

describe('appointmentToCalendarEvent', () => {
  it('returns null when starts_at is missing', () => {
    expect(appointmentToCalendarEvent({ id: 1 })).toBeNull()
  })

  it('computes end time when ends_at is missing', () => {
    const event = appointmentToCalendarEvent({
      id: 1,
      starts_at: '2026-08-23T10:00:00.000Z',
      services: [{ duration_minutes: 30 }],
    })

    expect(event.start).toBe('2026-08-23T10:00:00.000Z')
    expect(new Date(event.end).getTime()).toBe(new Date('2026-08-23T10:30:00.000Z').getTime())
  })

  it('replaces invalid or reversed end times', () => {
    const event = appointmentToCalendarEvent({
      id: 2,
      starts_at: '2026-08-23T10:00:00.000Z',
      ends_at: '2026-08-23T09:00:00.000Z',
      services: [{ duration_minutes: 45 }],
    })

    expect(new Date(event.end).getTime()).toBe(new Date('2026-08-23T10:45:00.000Z').getTime())
  })
})
