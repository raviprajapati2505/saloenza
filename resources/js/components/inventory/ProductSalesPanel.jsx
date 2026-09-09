import React from 'react'
import { Boxes, Coins, Percent, Receipt } from 'lucide-react'

function StatCard({ label, value, hint, icon: Icon }) {
  return (
    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm">
      <div className="flex items-center justify-between">
        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{label}</p>
        <Icon className="w-4 h-4 text-brand-600" />
      </div>
      <h3 className="text-2xl font-black text-slate-800 mt-2">{value}</h3>
      {hint && <p className="text-[11px] text-slate-400 mt-1">{hint}</p>}
    </div>
  )
}

function BreakdownTable({ title, columns, rows, empty }) {
  return (
    <div className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
      <div className="px-4 py-3 border-b border-slate-100 bg-slate-50/50">
        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">{title}</h4>
      </div>
      {rows.length === 0 ? (
        <p className="py-10 text-center text-sm text-slate-400">{empty}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="border-b border-slate-100">
                {columns.map((col) => (
                  <th
                    key={col.key}
                    className={`px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 ${col.align === 'right' ? 'text-right' : ''}`}
                  >
                    {col.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr key={index} className="border-b border-slate-50 last:border-0">
                  {columns.map((col) => (
                    <td
                      key={col.key}
                      className={`px-4 py-3 text-sm text-slate-700 ${col.align === 'right' ? 'text-right font-bold tabular-nums' : ''}`}
                    >
                      {col.render(row)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

export default function ProductSalesPanel({ fmt, loading, range, onRangeChange, report }) {
  const money = fmt.money

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-end gap-3">
        <div>
          <label className="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">From</label>
          <input
            type="date"
            value={range.from}
            max={range.to}
            onChange={(e) => onRangeChange({ ...range, from: e.target.value })}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">To</label>
          <input
            type="date"
            value={range.to}
            min={range.from}
            onChange={(e) => onRangeChange({ ...range, to: e.target.value })}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
          />
        </div>
      </div>

      {loading ? (
        <div className="rounded-2xl border border-dashed border-slate-200 py-16 text-center text-slate-400">
          Loading product sales…
        </div>
      ) : !report ? (
        <div className="rounded-2xl border border-dashed border-slate-200 py-16 text-center text-slate-400">
          No product sales data for this range.
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard
              label="Units Sold"
              value={report.units_sold}
              hint={`${report.transactions} bill${report.transactions === 1 ? '' : 's'}`}
              icon={Boxes}
            />
            <StatCard
              label="Retail Revenue"
              value={money(report.gross_revenue)}
              hint={`Avg line ${money(report.avg_line_value)}`}
              icon={Coins}
            />
            <StatCard
              label="Gross Margin"
              value={money(report.gross_margin)}
              hint={report.margin_percent != null ? `${report.margin_percent}% margin` : 'Add cost prices for margin'}
              icon={Percent}
            />
            <StatCard
              label="Cost of Goods"
              value={money(report.cost_of_goods)}
              icon={Receipt}
            />
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Where retail comes from</h4>
              <div className="mt-4 space-y-3">
                {[
                  { label: 'Counter sales (products only)', value: report.counter_sale_revenue },
                  { label: 'Sold alongside a service', value: report.attached_to_service_revenue },
                ].map((row) => {
                  const total = report.gross_revenue || 1
                  const pct = Math.round((row.value / total) * 100)
                  return (
                    <div key={row.label}>
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-slate-600">{row.label}</span>
                        <span className="font-bold tabular-nums text-slate-800">{money(row.value)}</span>
                      </div>
                      <div className="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div className="h-full rounded-full bg-brand-500" style={{ width: `${pct}%` }} />
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>

            <BreakdownTable
              title="Top sellers by staff"
              empty="Nobody has sold a product yet."
              rows={report.by_staff ?? []}
              columns={[
                { key: 'name', label: 'Staff', render: (row) => row.name },
                { key: 'units', label: 'Units', align: 'right', render: (row) => row.units },
                { key: 'revenue', label: 'Revenue', align: 'right', render: (row) => money(row.revenue) },
              ]}
            />
          </div>

          <BreakdownTable
            title="Top products"
            empty="No products sold in this range."
            rows={report.top_products ?? []}
            columns={[
              {
                key: 'name',
                label: 'Product',
                render: (row) => (
                  <>
                    <span className="block font-bold text-slate-900">{row.name}</span>
                    <span className="block text-[10px] text-slate-400">{row.sku || '—'} · {row.category || 'Uncategorised'}</span>
                  </>
                ),
              },
              { key: 'units', label: 'Units', align: 'right', render: (row) => row.units },
              { key: 'revenue', label: 'Revenue', align: 'right', render: (row) => money(row.revenue) },
              { key: 'margin', label: 'Margin', align: 'right', render: (row) => money(row.margin) },
            ]}
          />
        </>
      )}
    </div>
  )
}
