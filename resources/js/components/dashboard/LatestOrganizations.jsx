import React from 'react'
import { Link } from 'react-router-dom'
import { Building2, ChevronRight, MapPin } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

const STATUS_MAP = {
  completed: { label: 'Completed', variant: 'success' },
  pending: { label: 'Pending', variant: 'warning' },
  no_owner: { label: 'No owner', variant: 'default' },
  active: { label: 'Active', variant: 'success' },
  inactive: { label: 'Inactive', variant: 'danger' },
}

/**
 * Latest salons / organizations list.
 * Items shaped from admin onboarding API:
 * { id, business_name, owner, branch_count, onboarding_status, city, state, created_at, is_active }
 */
export default function LatestOrganizations({
  title = 'Latest salons',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No salons onboarded yet.',
  viewAllTo = '/admin/onboarding',
  getActionTo = (row) => `/admin/onboarding/${row.id}/edit`,
}) {
  return (
    <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            View all
          </Link>
        ) : null}
      </div>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="flex animate-pulse items-center gap-3 rounded-xl border border-slate-100 p-3">
              <div className="h-10 w-10 rounded-xl bg-slate-100" />
              <div className="flex-1 space-y-2">
                <div className="h-3 w-1/3 rounded bg-slate-100" />
                <div className="h-3 w-1/2 rounded bg-slate-50" />
              </div>
            </div>
          ))}
        </div>
      ) : null}

      {!loading && error ? (
        <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-6 text-center text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {!loading && !error && items.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center">
          <Building2 className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <>
          {/* Desktop table */}
          <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[640px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-100 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  <th className="pb-3 pr-4 font-semibold">Salon</th>
                  <th className="pb-3 pr-4 font-semibold">Owner</th>
                  <th className="pb-3 pr-4 font-semibold">Branches</th>
                  <th className="pb-3 pr-4 font-semibold">Status</th>
                  <th className="pb-3 pr-4 font-semibold">Created</th>
                  <th className="pb-3 font-semibold text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50">
                {items.map((row) => {
                  const statusKey = row.onboarding_status || (row.is_active ? 'active' : 'inactive')
                  const status = STATUS_MAP[statusKey] || STATUS_MAP.pending
                  const location = [row.city, row.state].filter(Boolean).join(', ')
                  return (
                    <tr key={row.id} className="group transition hover:bg-slate-50/80">
                      <td className="py-3.5 pr-4">
                        <div className="flex items-center gap-3">
                          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <Building2 className="h-4 w-4" />
                          </div>
                          <div className="min-w-0">
                            <p className="truncate font-semibold text-slate-900">
                              {row.business_name || 'Untitled salon'}
                            </p>
                            {location ? (
                              <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-400">
                                <MapPin className="h-3 w-3 shrink-0" />
                                {location}
                              </p>
                            ) : null}
                          </div>
                        </div>
                      </td>
                      <td className="py-3.5 pr-4 text-slate-600">
                        {row.owner?.name || row.owner?.email || '—'}
                      </td>
                      <td className="py-3.5 pr-4 text-slate-600">{row.branch_count ?? 0}</td>
                      <td className="py-3.5 pr-4">
                        <BaseBadge variant={status.variant} size="sm">
                          {status.label}
                        </BaseBadge>
                      </td>
                      <td className="py-3.5 pr-4 text-slate-500">{formatDate(row.created_at)}</td>
                      <td className="py-3.5 text-right">
                        <Link
                          to={getActionTo(row)}
                          className="inline-flex items-center gap-0.5 text-xs font-semibold text-brand-600 opacity-0 transition group-hover:opacity-100 hover:text-brand-700"
                        >
                          Manage <ChevronRight className="h-3.5 w-3.5" />
                        </Link>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {/* Mobile cards */}
          <div className="space-y-3 md:hidden">
            {items.map((row) => {
              const statusKey = row.onboarding_status || (row.is_active ? 'active' : 'inactive')
              const status = STATUS_MAP[statusKey] || STATUS_MAP.pending
              return (
                <Link
                  key={row.id}
                  to={getActionTo(row)}
                  className="flex items-start gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-brand-200 hover:bg-brand-50/30"
                >
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <Building2 className="h-4 w-4" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                      <p className="truncate font-semibold text-slate-900">
                        {row.business_name || 'Untitled salon'}
                      </p>
                      <BaseBadge variant={status.variant} size="sm">
                        {status.label}
                      </BaseBadge>
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                      {row.owner?.name || 'No owner'} · {row.branch_count ?? 0} branches
                    </p>
                    <p className="mt-1 text-[11px] text-slate-400">{formatDate(row.created_at)}</p>
                  </div>
                </Link>
              )
            })}
          </div>
        </>
      ) : null}
    </div>
  )
}
