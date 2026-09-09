import { describe, expect, it } from 'vitest'
import { inclusiveRangeEnd } from '../useCalendar.jsx'

describe('inclusiveRangeEnd', () => {
  it('converts FullCalendar exclusive end to inclusive API date', () => {
    const exclusiveEnd = new Date(2026, 7, 1) // 1 Sep 2026 (month is 0-indexed)
    expect(inclusiveRangeEnd(exclusiveEnd)).toBe('2026-08-31')
  })

  it('handles single-day ranges', () => {
    const exclusiveEnd = new Date(2026, 7, 24)
    expect(inclusiveRangeEnd(exclusiveEnd)).toBe('2026-08-23')
  })
})
