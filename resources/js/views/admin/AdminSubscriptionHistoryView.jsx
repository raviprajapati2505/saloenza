import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { History, Loader2, RefreshCw, Search } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseSelect from '../../components/ui/BaseSelect.jsx'
import { useAuthStore } from '../../stores/auth'
import { pushToast } from '../../stores/toast.js'
import { fetchSubscriptionHistory } from '../../services/adminSubscriptionService.js'

const ACTION_OPTIONS = [
  { value: 'all', label: 'All actions' },
  { value: 'assigned', label: 'Assigned' },
  { value: 'trial_started', label: 'Trial started' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'renewed', label: 'Renewed' },
  { value: 'expired', label: 'Expired' },
]

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function actionLabel(action) {
  return ACTION_OPTIONS.find((row) => row.value === action)?.label || action
}

export default function AdminSubscriptionHistoryView() {
  const auth = useAuthStore()
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [actionFilter, setActionFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const loadData = useCallback(async () => {
    if (!auth.token) return
    setLoading(true)
    try {
      const params = { page, per_page: 25 }
      if (actionFilter !== 'all') params.action = actionFilter

      const result = await fetchSubscriptionHistory(params, auth.token)
      setRows(result.histories)
      setMeta(result.meta)
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Failed to load subscription history.', 'error')
      setRows([])
      setMeta(null)
    } finally {
      setLoading(false)
    }
  }, [auth.token, actionFilter, page])

  useEffect(() => {
    void loadData()
  }, [loadData])

  const filteredRows = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return rows
    return rows.filter((row) =>
      (row.saloon_name || '').toLowerCase().includes(q)
      || (row.plan?.name || '').toLowerCase().includes(q)
      || (row.from_plan?.name || '').toLowerCase().includes(q)
      || (row.notes || '').toLowerCase().includes(q)
      || (row.action || '').toLowerCase().includes(q),
    )
  }, [rows, search])

  const totalPages = useMemo(() => Number(meta?.last_page || 1), [meta])

  return (
    <div className="space-y-6">
      <PageHeader
        title="Subscription History"
        description="Audit trail of salon plan assignments, trials, and cancellations."
        icon={History}
        actions={(
          <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" leftIcon={RefreshCw} onClick={() => void loadData()}>
            Refresh
          </BaseButton>
        )}
      />

      <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:flex-row md:items-center md:justify-between">
        <div className="relative max-w-md flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search salon, plan, or notes..."
            className="w-full rounded-xl border border-slate-200 py-2.5 pl-10 pr-4 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
        <div className="w-full md:w-56">
          <BaseSelect
            label="Action"
            value={actionFilter}
            onChange={(e) => {
              setPage(1)
              setActionFilter(e.target.value)
            }}
            options={ACTION_OPTIONS}
          />
        </div>
      </div>

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-semibold">When</th>
                <th className="px-4 py-3 font-semibold">Salon</th>
                <th className="px-4 py-3 font-semibold">Action</th>
                <th className="px-4 py-3 font-semibold">Plan</th>
                <th className="px-4 py-3 font-semibold">From</th>
                <th className="px-4 py-3 font-semibold">Period</th>
                <th className="px-4 py-3 font-semibold">Changed by</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={7} className="px-4 py-12 text-center text-slate-500">
                    <Loader2 className="mx-auto mb-2 h-5 w-5 animate-spin" />
                    Loading history…
                  </td>
                </tr>
              ) : filteredRows.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-12 text-center text-slate-500">
                    <History className="mx-auto mb-2 h-6 w-6 text-slate-300" />
                    No subscription history found.
                  </td>
                </tr>
              ) : filteredRows.map((row) => (
                <tr key={row.id} className="hover:bg-slate-50/80">
                  <td className="whitespace-nowrap px-4 py-3 text-slate-600">{formatDate(row.created_at)}</td>
                  <td className="px-4 py-3 font-medium text-slate-900">{row.saloon_name || `Salon #${row.saloon_id}`}</td>
                  <td className="px-4 py-3">
                    <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                      {actionLabel(row.action)}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-700">{row.plan?.name || '—'}</td>
                  <td className="px-4 py-3 text-slate-500">{row.from_plan?.name || '—'}</td>
                  <td className="px-4 py-3 text-slate-500">
                    <div>{formatDate(row.starts_at)}</div>
                    <div className="text-xs">Ends {formatDate(row.ends_at || row.trial_ends_at)}</div>
                  </td>
                  <td className="px-4 py-3 text-slate-600">
                    {row.changed_by?.name || 'System'}
                    {row.notes ? <div className="mt-0.5 max-w-xs truncate text-xs text-slate-400">{row.notes}</div> : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {totalPages > 1 ? (
          <div className="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
            <span className="text-center sm:text-left">
              Page {meta?.current_page || page} of {totalPages}
              {meta?.total != null ? ` · ${meta.total} records` : ''}
            </span>
            <div className="flex w-full gap-2 sm:w-auto">
              <BaseButton
                type="button"
                variant="secondary"
                className="flex-1 sm:flex-none"
                disabled={page <= 1 || loading}
                onClick={() => setPage((prev) => Math.max(1, prev - 1))}
              >
                Previous
              </BaseButton>
              <BaseButton
                type="button"
                variant="secondary"
                className="flex-1 sm:flex-none"
                disabled={page >= totalPages || loading}
                onClick={() => setPage((prev) => prev + 1)}
              >
                Next
              </BaseButton>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  )
}
