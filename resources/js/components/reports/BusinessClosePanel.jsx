import React from 'react'
import { ReportKpiGrid, ReportTable } from './ReportChrome.jsx'
import { paymentMethodLabel } from '../../lib/appointmentPayments.js'

export default function BusinessClosePanel({ fmt, loading, report }) {
  const summary = report?.summary ?? {}
  const money = fmt.money

  const kpis = report ? [
    {
      key: 'earning',
      label: 'Total earning',
      value: money(Number(summary.collected || 0) + Number(summary.outstanding || 0)),
      hint: 'Collected + outstanding',
    },
    { key: 'collected', label: 'Collected', value: money(summary.collected), hint: 'Cash + digital received' },
    { key: 'outstanding', label: 'Outstanding', value: money(summary.outstanding), hint: 'Balance still due' },
    { key: 'expenses', label: 'Expenses', value: money(summary.expenses) },
    { key: 'commission', label: 'Staff commission', value: money(summary.staff_commission), hint: 'Payable from generated revenue' },
    { key: 'net', label: 'Net collected', value: money(summary.net_collected), hint: 'Collected − expenses' },
    { key: 'completed', label: 'Completed visits', value: summary.completed ?? 0 },
  ] : []

  return (
    <div className="space-y-5">
      <ReportKpiGrid items={kpis} loading={loading} />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ReportTable
          loading={loading}
          rows={report?.payment_methods ?? []}
          emptyMessage="No collections in this range."
          columns={[
            { key: 'method', label: 'Payment method', render: (r) => paymentMethodLabel(r.method) },
            { key: 'amount', label: 'Collected', render: (r) => money(r.amount) },
          ]}
        />
        <ReportTable
          loading={loading}
          rows={report?.expenses?.by_category ?? []}
          emptyMessage="No expenses in this range."
          columns={[
            { key: 'category', label: 'Expense category', render: (r) => r.category },
            { key: 'amount', label: 'Amount', render: (r) => money(r.amount) },
          ]}
        />
      </div>

      <ReportTable
        loading={loading}
        rows={report?.staff ?? []}
        emptyMessage="No staff work recorded in this range."
        columns={[
          { key: 'name', label: 'Staff', render: (r) => r.name },
          { key: 'role', label: 'Role', render: (r) => r.role || '—' },
          { key: 'services', label: 'Jobs', render: (r) => r.services },
          { key: 'hours', label: 'Hours', render: (r) => r.hours_worked ?? 0 },
          { key: 'revenue', label: 'Generated', render: (r) => money(r.revenue_generated) },
          { key: 'collected', label: 'Collected share', render: (r) => money(r.collected) },
          { key: 'commission', label: 'Commission', render: (r) => money(r.commission) },
        ]}
      />

      <ReportTable
        loading={loading}
        rows={report?.daily ?? []}
        emptyMessage="No daily close rows for this range."
        columns={[
          { key: 'date', label: 'Date', render: (r) => r.date },
          { key: 'visits', label: 'Visits', render: (r) => r.visits },
          { key: 'completed', label: 'Completed', render: (r) => r.completed },
          { key: 'collected', label: 'Collected', render: (r) => money(r.collected) },
          { key: 'outstanding', label: 'Outstanding', render: (r) => money(r.outstanding) },
          { key: 'booked', label: 'Booked', render: (r) => money(r.booked) },
        ]}
      />
    </div>
  )
}
