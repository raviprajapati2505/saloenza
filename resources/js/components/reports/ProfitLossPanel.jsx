import React, { useMemo, useState } from 'react'
import { Plus, Trash2, TriangleAlert } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import BaseInput from '../ui/BaseInput.jsx'
import BaseSelect from '../ui/BaseSelect.jsx'
import { ReportKpiGrid, ReportTable } from './ReportChrome.jsx'

function StatementRow({ label, value, hint, tone = 'default', emphasis = false, indent = false }) {
  const toneClass = {
    default: 'text-slate-800',
    muted: 'text-slate-500',
    negative: 'text-rose-600',
    positive: 'text-emerald-600',
  }[tone]

  return (
    <div
      className={`flex items-baseline justify-between gap-4 px-4 py-2.5 ${
        emphasis ? 'border-t border-slate-200 bg-slate-50/70' : ''
      }`}
    >
      <div className={indent ? 'pl-4' : ''}>
        <p className={`${emphasis ? 'text-sm font-bold text-slate-900' : 'text-sm text-slate-600'}`}>{label}</p>
        {hint ? <p className="text-[11px] text-slate-400">{hint}</p> : null}
      </div>
      <p className={`shrink-0 tabular-nums ${emphasis ? 'text-base font-bold' : 'text-sm font-semibold'} ${toneClass}`}>
        {value}
      </p>
    </div>
  )
}

const BLANK_EXPENSE = {
  category: 'rent',
  title: '',
  amount: '',
  incurred_on: '',
  branch_id: '',
}

export default function ProfitLossPanel({
  fmt,
  loading,
  report,
  expenses = [],
  categories = [],
  branches = [],
  canManageExpenses = false,
  canChooseBranch = false,
  savingExpense = false,
  expenseError = '',
  onCreateExpense,
  onDeleteExpense,
  defaultDate,
}) {
  const money = fmt.money
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ ...BLANK_EXPENSE, incurred_on: defaultDate })

  const categoryOptions = useMemo(
    () => categories.map((c) => ({ value: c.value, label: c.label })),
    [categories],
  )

  const branchOptions = useMemo(
    () => [
      { value: '', label: 'Salon-wide (all branches)' },
      ...branches.map((b) => ({ value: String(b.id), label: b.branch_name || `Branch #${b.id}` })),
    ],
    [branches],
  )

  const kpis = useMemo(() => {
    if (!report) return []
    return [
      { key: 'revenue', label: 'Net revenue', value: money(report.revenue.net), hint: 'Completed visits, after discounts' },
      {
        key: 'gross',
        label: 'Gross profit',
        value: money(report.gross_profit),
        hint: report.gross_margin_percent != null ? `${report.gross_margin_percent}% of revenue` : 'Revenue less cost of goods',
      },
      { key: 'costs', label: 'Total costs', value: money(report.cost_of_goods.total + report.staff_cost.total + report.operating_expenses.total), hint: 'Goods, payroll and overheads' },
      {
        key: 'net',
        label: 'Net profit',
        value: money(report.net_profit),
        hint: report.net_margin_percent != null ? `${report.net_margin_percent}% of revenue` : 'Bottom line',
      },
    ]
  }, [report, money])

  const submitExpense = async (event) => {
    event.preventDefault()
    const created = await onCreateExpense?.({
      category: form.category,
      title: form.title.trim(),
      amount: Number(form.amount),
      incurred_on: form.incurred_on,
      branch_id: form.branch_id === '' ? null : Number(form.branch_id),
    })

    if (created) {
      setForm({ ...BLANK_EXPENSE, incurred_on: form.incurred_on })
      setShowForm(false)
    }
  }

  const formIsValid = form.title.trim() !== '' && Number(form.amount) > 0 && form.incurred_on !== ''

  if (!loading && !report) {
    return (
      <div className="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-14 text-center text-sm text-slate-500">
        No profit and loss data for this range.
      </div>
    )
  }

  return (
    <div className="space-y-5">
      <ReportKpiGrid items={kpis} loading={loading} />

      {report?.cost_of_goods?.uncosted_lines > 0 ? (
        <div className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            {report.cost_of_goods.uncosted_lines} sold line
            {report.cost_of_goods.uncosted_lines === 1 ? ' has' : 's have'} no cost price, so gross profit is
            overstated. Add cost prices under Inventory → Stock to close the gap.
          </p>
        </div>
      ) : null}

      {report ? (
        <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
          <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 bg-slate-50/80 px-4 py-3">
              <h4 className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                Profit &amp; loss statement
              </h4>
            </div>

            <StatementRow label="Service revenue" value={money(report.revenue.services)} indent />
            <StatementRow label="Product revenue" value={money(report.revenue.products)} indent />
            <StatementRow label="Discounts given" value={`− ${money(report.revenue.discounts)}`} tone="negative" indent />
            <StatementRow label="Net revenue" value={money(report.revenue.net)} emphasis />

            <StatementRow label="Retail product cost" value={`− ${money(report.cost_of_goods.retail_products)}`} tone="negative" indent />
            <StatementRow
              label="Service consumables"
              value={`− ${money(report.cost_of_goods.service_consumables)}`}
              tone="negative"
              hint="Products used up during services"
              indent
            />
            <StatementRow
              label="Gross profit"
              value={money(report.gross_profit)}
              tone={report.gross_profit >= 0 ? 'positive' : 'negative'}
              emphasis
            />

            <StatementRow label="Staff commission" value={`− ${money(report.staff_cost.commission)}`} tone="negative" indent />
            <StatementRow
              label="Salaries"
              value={`− ${money(report.staff_cost.salaries)}`}
              tone="negative"
              hint={report.staff_cost.salaries_from_expenses
                ? 'Counted from recorded salary expenses below'
                : 'Estimated from monthly salaries on staff records'}
              indent
            />
            <StatementRow label="Operating expenses" value={`− ${money(report.operating_expenses.total)}`} tone="negative" indent />
            <StatementRow
              label="Net profit"
              value={money(report.net_profit)}
              tone={report.net_profit >= 0 ? 'positive' : 'negative'}
              emphasis
            />

            {report.operating_expenses.unallocated_overheads > 0 ? (
              <div className="border-t border-slate-100 px-4 py-3 text-[11px] text-slate-400">
                {money(report.operating_expenses.unallocated_overheads)} of salon-wide overheads is not included in this
                branch view because there is no allocation rule. Clear the branch filter to see it.
              </div>
            ) : null}
          </div>

          <div className="space-y-5">
            <ReportTable
              loading={loading}
              rows={report.operating_expenses.by_category}
              emptyMessage="No operating expenses recorded in this range."
              getRowKey={(row) => row.category}
              columns={[
                { key: 'label', label: 'Expense category', render: (r) => r.label },
                { key: 'amount', label: 'Amount', render: (r) => money(r.amount) },
                {
                  key: 'share',
                  label: 'Share',
                  render: (r) => `${report.operating_expenses.total > 0
                    ? Math.round((r.amount / report.operating_expenses.total) * 100)
                    : 0}%`,
                },
              ]}
            />

            <ReportTable
              loading={loading}
              rows={report.staff_cost.by_staff}
              emptyMessage="No staff cost in this range."
              getRowKey={(row) => row.staff_id}
              columns={[
                { key: 'name', label: 'Staff', render: (r) => r.name },
                { key: 'generated', label: 'Revenue', render: (r) => money(r.revenue_generated) },
                { key: 'commission', label: 'Commission', render: (r) => money(r.commission) },
                { key: 'cost', label: 'Total cost', render: (r) => money(r.total_cost) },
              ]}
            />
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
          <h4 className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            Recorded expenses
          </h4>
          {canManageExpenses ? (
            <BaseButton
              type="button"
              size="sm"
              variant={showForm ? 'secondary' : 'primary'}
              leftIcon={showForm ? undefined : Plus}
              onClick={() => setShowForm((open) => !open)}
            >
              {showForm ? 'Cancel' : 'Add expense'}
            </BaseButton>
          ) : null}
        </div>

        {showForm && canManageExpenses ? (
          <form onSubmit={submitExpense} className="grid grid-cols-1 gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 xl:grid-cols-5">
            <BaseSelect
              label="Category"
              value={form.category}
              options={categoryOptions}
              onChange={(e) => setForm((prev) => ({ ...prev, category: e.target.value }))}
            />
            <BaseInput
              label="Description"
              value={form.title}
              placeholder="August shop rent"
              onChange={(e) => setForm((prev) => ({ ...prev, title: e.target.value }))}
            />
            <BaseInput
              label="Amount"
              type="number"
              min="0.01"
              step="0.01"
              value={form.amount}
              onChange={(e) => setForm((prev) => ({ ...prev, amount: e.target.value }))}
            />
            <BaseInput
              label="Date"
              type="date"
              value={form.incurred_on}
              onChange={(e) => setForm((prev) => ({ ...prev, incurred_on: e.target.value }))}
            />
            {canChooseBranch ? (
              <BaseSelect
                label="Branch"
                value={form.branch_id}
                options={branchOptions}
                onChange={(e) => setForm((prev) => ({ ...prev, branch_id: e.target.value }))}
              />
            ) : null}
            <div className="flex items-end sm:col-span-2 xl:col-span-5">
              <BaseButton type="submit" size="sm" disabled={!formIsValid || savingExpense}>
                {savingExpense ? 'Saving…' : 'Save expense'}
              </BaseButton>
              {expenseError ? <p className="ml-3 text-sm text-rose-600">{expenseError}</p> : null}
            </div>
          </form>
        ) : null}

        <ReportTable
          loading={loading}
          rows={expenses}
          emptyMessage="Nothing recorded yet. Add rent, utilities, and marketing spend to see true net profit."
          columns={[
            { key: 'date', label: 'Date', render: (r) => r.incurred_on || '—' },
            { key: 'title', label: 'Description', render: (r) => r.title },
            { key: 'category', label: 'Category', render: (r) => r.category_label },
            { key: 'branch', label: 'Branch', render: (r) => r.branch?.branch_name || 'Salon-wide' },
            { key: 'amount', label: 'Amount', render: (r) => money(r.amount) },
            ...(canManageExpenses
              ? [{
                key: 'actions',
                label: '',
                render: (r) => (
                  <button
                    type="button"
                    aria-label={`Delete ${r.title}`}
                    onClick={() => onDeleteExpense?.(r)}
                    className="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                ),
              }]
              : []),
          ]}
        />
      </div>
    </div>
  )
}
