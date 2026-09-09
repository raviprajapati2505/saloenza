import { describe, expect, it } from 'vitest'
import {
  customerQueueItemsFromSnapshot,
  liveQueueDashboardSubtitle,
} from '../liveQueueDashboard.js'

describe('liveQueueDashboard', () => {
  it('maps live queue summary into dashboard items', () => {
    const items = customerQueueItemsFromSnapshot({
      waiting: 2,
      upcoming: 3,
      in_service: 1,
      walk_ins: 4,
    })

    expect(items).toHaveLength(4)
    expect(items[0]).toMatchObject({ key: 'waiting', value: 2 })
    expect(items[2]).toMatchObject({ key: 'in_progress', value: 1 })
  })

  it('optionally appends completed count from appointments', () => {
    const items = customerQueueItemsFromSnapshot(
      { waiting: 1, upcoming: 0, in_service: 0, walk_ins: 0 },
      { completedToday: 5 },
    )

    expect(items).toHaveLength(5)
    expect(items[4]).toMatchObject({ key: 'completed', value: 5 })
  })

  it('uses scope label from snapshot when present', () => {
    expect(liveQueueDashboardSubtitle({
      scope: { label: 'Your assigned queue' },
    })).toBe('Your assigned queue')
  })
})
