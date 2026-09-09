import React from 'react'
import { IndianRupee, Scissors } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
  })
}

const PAYMENT_VARIANT = {
  paid: 'success',
  pending: 'warning',
  processing: 'info',
}

/**
 * Recent completed services for the logged-in staff member.
 * items: [{ id, date, customerName, serviceName, amount, commissionPercent?, commissionEarned?, paymentStatus? }]
 * summary: { total, paid, pending } — optional; only shown when provided
 */
export default function RecentServices({
  title = 'Recent services',
  subtitle = 'Your completed work',
  items = [],
  summary,
  loading = false,
  error = '',
  emptyMessage = 'No completed services yet.',
  formatMoney = formatMoneyDefault,
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
      </div>

      {summary ? (
        <div className="mb-5 grid grid-cols-3 gap-2">
          {[
            { label: 'Total value', value: formatMoney(summary.total), icon: IndianRupee },
            { label: 'Paid', value: formatMoney(summary.paid), icon: IndianRupee },
            { label: 'Pending', value: formatMoney(summary.pending), icon: IndianRupee },
          ].map((card) => (
            <div key={card.label} className="rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5">
              <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{card.label}</p>
              <p className="mt-1 text-sm font-bold text-slate-900">{card.value}</p>
            </div>
          ))}
        </div>
      ) : null}

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-14 animate-pulse rounded-xl bg-slate-50" />
          ))}
        </div>
      ) : null}

      {!loading && error ? (
        <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-6 text-center text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {!loading && !error && items.length === 0 ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center">
          <Scissors className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-2">
          {items.map((row) => (
            <li
              key={row.id}
              className="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-3 transition hover:bg-white hover:shadow-sm"
            >
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="text-sm font-semibold text-slate-900">{row.serviceName || 'Service'}</p>
                  {row.paymentStatus ? (
                    <BaseBadge variant={PAYMENT_VARIANT[row.paymentStatus] || 'default'} size="sm">
                      {row.paymentStatus}
                    </BaseBadge>
                  ) : null}
                </div>
                <p className="mt-0.5 text-xs text-slate-500">
                  {row.customerName || 'Customer'} · {formatDate(row.date)}
                </p>
              </div>
              <div className="shrink-0 text-right">
                <p className="text-sm font-bold text-slate-900">{formatMoney(row.amount)}</p>
                {row.commissionEarned != null ? (
                  <p className="text-[11px] text-brand-600">
                    Comm. {formatMoney(row.commissionEarned)}
                    {row.commissionPercent != null ? ` (${row.commissionPercent}%)` : ''}
                  </p>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}
