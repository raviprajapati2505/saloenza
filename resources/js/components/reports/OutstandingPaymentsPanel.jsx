import React, { useState } from 'react'
import { Bell } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import { ReportKpiGrid, ReportTable } from './ReportChrome.jsx'
import { PAYMENT_STATUS_LABELS } from '../../lib/appointmentPayments.js'
import { customerPhoneDisplay } from '../../lib/customerContact.js'

export default function OutstandingPaymentsPanel({
  fmt,
  loading,
  report,
  canRemind = false,
  remindingId = null,
  onRemind,
}) {
  const [view, setView] = useState('customers')

  const kpis = report ? [
    {
      key: 'earning',
      label: 'Total earning',
      value: fmt.money(Number(report.collected || 0) + Number(report.outstanding || 0)),
      hint: 'Collected + outstanding',
    },
    { key: 'paid', label: 'Paid bills', value: report.paid_bills, hint: 'Fully settled in this range' },
    { key: 'pending', label: 'Pending bills', value: report.pending_bills, hint: 'Unpaid or partial' },
    { key: 'collected', label: 'Collected', value: fmt.money(report.collected) },
    { key: 'outstanding', label: 'Outstanding', value: fmt.money(report.outstanding), hint: `Overdue after ${report.overdue_days} day${report.overdue_days === 1 ? '' : 's'}` },
  ] : []

  return (
    <div className="space-y-5">
      <ReportKpiGrid items={kpis} loading={loading} />

      <div className="flex flex-wrap gap-2">
        {[
          { key: 'customers', label: 'By customer' },
          { key: 'bills', label: 'Open bills' },
        ].map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => setView(tab.key)}
            className={`rounded-full border px-3 py-1.5 text-xs font-semibold ${
              view === tab.key
                ? 'border-brand-300 bg-brand-50 text-brand-700'
                : 'border-slate-200 bg-white text-slate-600'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {view === 'customers' ? (
        <ReportTable
          loading={loading}
          rows={report?.customers ?? []}
          emptyMessage="No outstanding balances in this range."
          getRowKey={(row, i) => row.customer_id ?? `walkin-${i}`}
          columns={[
            { key: 'name', label: 'Customer', render: (r) => r.name },
            { key: 'phone', label: 'Phone', render: (r) => customerPhoneDisplay(r) || '—' },
            { key: 'bills', label: 'Bills', render: (r) => r.bills },
            { key: 'outstanding', label: 'Outstanding', render: (r) => fmt.money(r.outstanding) },
            { key: 'oldest', label: 'Oldest visit', render: (r) => r.oldest_visit || '—' },
          ]}
        />
      ) : (
        <ReportTable
          loading={loading}
          rows={report?.bills ?? []}
          emptyMessage="No pending bills in this range."
          getRowKey={(row) => row.appointment_id}
          columns={[
            { key: 'customer', label: 'Customer', render: (r) => r.customer_name },
            {
              key: 'phone',
              label: 'Phone',
              render: (r) => customerPhoneDisplay({
                phone: r.customer_phone,
                can_view_contact: r.can_view_contact,
                has_phone: r.has_phone,
              }) || '—',
            },
            { key: 'visit', label: 'Visit', render: (r) => r.visit_date || '—' },
            { key: 'status', label: 'Payment', render: (r) => PAYMENT_STATUS_LABELS[r.payment_status] || r.payment_status },
            { key: 'due', label: 'Balance', render: (r) => fmt.money(r.balance_due) },
            { key: 'overdue', label: 'Overdue', render: (r) => (r.is_overdue ? `${r.days_overdue}d` : '—') },
            ...(canRemind ? [{
              key: 'remind',
              label: '',
              render: (r) => (
                <BaseButton
                  size="sm"
                  variant="secondary"
                  leftIcon={Bell}
                  disabled={!r.can_remind}
                  loading={remindingId === r.appointment_id}
                  onClick={() => onRemind?.(r)}
                >
                  Remind
                </BaseButton>
              ),
            }] : []),
          ]}
        />
      )}

      <p className="text-xs text-slate-400">
        Automatic reminders run daily after the overdue window in Settings → Notifications.
        Open balances include older unpaid visits, not only the selected date range.
        Use Remind here to send one immediately.
      </p>
    </div>
  )
}
