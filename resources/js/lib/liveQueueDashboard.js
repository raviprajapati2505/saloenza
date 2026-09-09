/**
 * Maps live queue API snapshot summary into CustomerQueue dashboard items.
 *
 * @param {Record<string, number>|null|undefined} summary
 * @param {{ completedToday?: number|null }} [options]
 * @returns {Array<{ key: string, label: string, value: number, iconKey: string, hint: string }>}
 */
export function customerQueueItemsFromSnapshot(summary, options = {}) {
  const data = summary ?? {}

  const items = [
    {
      key: 'waiting',
      label: 'Waiting now',
      value: data.waiting ?? 0,
      iconKey: 'waiting',
      hint: 'Due within 15 min',
    },
    {
      key: 'upcoming',
      label: 'Upcoming today',
      value: data.upcoming ?? 0,
      iconKey: 'avg_wait',
      hint: 'Later today',
    },
    {
      key: 'in_progress',
      label: 'In service',
      value: data.in_service ?? 0,
      iconKey: 'checked_in',
      hint: 'Currently being served',
    },
    {
      key: 'walk_in',
      label: 'Walk-ins',
      value: data.walk_ins ?? 0,
      iconKey: 'walk_in',
      hint: 'Active today',
    },
  ]

  if (options.completedToday != null) {
    items.push({
      key: 'completed',
      label: 'Completed',
      value: options.completedToday,
      iconKey: 'avg_wait',
      hint: 'Finished today',
    })
  }

  return items
}

/**
 * @param {{ scope?: { label?: string } }|null|undefined} snapshot
 * @param {string} [fallback]
 */
export function liveQueueDashboardSubtitle(snapshot, fallback = 'Floor status right now') {
  return snapshot?.scope?.label || fallback
}
