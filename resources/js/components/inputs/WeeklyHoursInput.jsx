import React, { useEffect, useState } from 'react'

const days = [
  { key: 'monday', label: 'Mon' },
  { key: 'tuesday', label: 'Tue' },
  { key: 'wednesday', label: 'Wed' },
  { key: 'thursday', label: 'Thu' },
  { key: 'friday', label: 'Fri' },
  { key: 'saturday', label: 'Sat' },
  { key: 'sunday', label: 'Sun' },
]

export default function WeeklyHoursInput({ modelValue, onUpdateModelValue, readOnly = false }) {
  const [localValue, setLocalValue] = useState(() => structuredClone(modelValue))

  useEffect(() => {
    setLocalValue(structuredClone(modelValue))
  }, [modelValue])

  const emitValue = (nextValue) => {
    setLocalValue(nextValue)
    if (!readOnly) {
      onUpdateModelValue?.(structuredClone(nextValue))
    }
  }

  const toggleDay = (dayKey) => {
    if (readOnly) return
    const nextValue = structuredClone(localValue)
    nextValue[dayKey].enabled = !nextValue[dayKey].enabled
    emitValue(nextValue)
  }

  const updateTime = (dayKey, field, value) => {
    if (readOnly) return
    const nextValue = structuredClone(localValue)
    nextValue[dayKey][field] = value
    emitValue(nextValue)
  }

  return (
    <div className="rounded-xl border border-slate-200">
      <div className="grid grid-cols-12 gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <div className="col-span-3">Day</div>
        <div className="col-span-3 text-center">Open</div>
        <div className="col-span-3 text-center">From</div>
        <div className="col-span-3 text-center">To</div>
      </div>

      {days.map((day) => (
        <div
          key={day.key}
          className="grid grid-cols-12 items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0"
        >
          <div className="col-span-3 text-sm font-medium text-slate-700">{day.label}</div>

          <div className="col-span-3 flex justify-center">
            <button
              type="button"
              disabled={readOnly}
              className={`relative inline-flex h-6 w-11 items-center rounded-full transition ${localValue[day.key].enabled ? 'bg-brand-500' : 'bg-slate-300'} ${readOnly ? 'cursor-not-allowed opacity-60' : ''}`}
              onClick={() => toggleDay(day.key)}
            >
              <span
                className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${localValue[day.key].enabled ? 'translate-x-5' : 'translate-x-0.5'}`}
              />
            </button>
          </div>

          <div className="col-span-3">
            <input
              value={localValue[day.key].open}
              type="time"
              className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-700 disabled:bg-slate-100"
              disabled={readOnly || !localValue[day.key].enabled}
              onChange={(event) => updateTime(day.key, 'open', event.target.value)}
            />
          </div>

          <div className="col-span-3">
            <input
              value={localValue[day.key].close}
              type="time"
              className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-700 disabled:bg-slate-100"
              disabled={readOnly || !localValue[day.key].enabled}
              onChange={(event) => updateTime(day.key, 'close', event.target.value)}
            />
          </div>
        </div>
      ))}
    </div>
  )
}
