import React, { useCallback, useEffect, useState } from 'react'
import { AlertTriangle, RefreshCw, Sparkles, TrendingDown } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'
import BaseButton from '../ui/BaseButton.jsx'
import {
  fetchInventoryAlerts,
  fetchInventoryClassifications,
  fetchInventoryStockoutRisks,
  recomputeInventoryIntelligence,
} from '../../services/inventoryService.js'

const VELOCITY_VARIANT = {
  fast: 'success',
  medium: 'info',
  slow: 'warning',
  dormant: 'default',
}

const SEVERITY_VARIANT = {
  critical: 'danger',
  high: 'danger',
  medium: 'warning',
  low: 'info',
}

export default function InventoryIntelligencePanel({ branchId, canManage, fmt }) {
  const [loading, setLoading] = useState(true)
  const [recomputing, setRecomputing] = useState(false)
  const [error, setError] = useState('')
  const [classifications, setClassifications] = useState([])
  const [risks, setRisks] = useState([])
  const [alerts, setAlerts] = useState([])

  const load = useCallback(async () => {
    if (!branchId) {
      setLoading(false)
      return
    }
    setLoading(true)
    setError('')
    try {
      const params = { branch_id: Number(branchId) }
      const [classData, riskData, alertData] = await Promise.all([
        fetchInventoryClassifications(params),
        fetchInventoryStockoutRisks(params),
        fetchInventoryAlerts(params),
      ])
      setClassifications(Array.isArray(classData) ? classData : [])
      setRisks(Array.isArray(riskData) ? riskData : [])
      setAlerts(Array.isArray(alertData) ? alertData : [])
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load inventory intelligence.')
    } finally {
      setLoading(false)
    }
  }, [branchId])

  useEffect(() => {
    void load()
  }, [load])

  const handleRecompute = async () => {
    setRecomputing(true)
    setError('')
    try {
      await recomputeInventoryIntelligence({})
      await load()
    } catch (err) {
      setError(err?.response?.data?.message || 'Recompute failed.')
    } finally {
      setRecomputing(false)
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
            <Sparkles className="w-4 h-4 text-brand-600" />
            Smart inventory
          </h3>
          <p className="text-xs text-slate-500 mt-1">
            Velocity classes, stockout risk, and reorder suggestions based on completed product sales.
          </p>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => load()}
            className="p-3 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-brand-600"
          >
            <RefreshCw className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} />
          </button>
          {canManage && (
            <BaseButton size="sm" onClick={handleRecompute} loading={recomputing}>
              Recompute
            </BaseButton>
          )}
        </div>
      </div>

      {error && (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Classifications</p>
          <p className="text-2xl font-black text-slate-800 mt-1">{classifications.length}</p>
        </div>
        <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Stockout risks</p>
          <p className="text-2xl font-black text-amber-600 mt-1">{risks.length}</p>
        </div>
        <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Open alerts</p>
          <p className="text-2xl font-black text-rose-600 mt-1">{alerts.length}</p>
        </div>
      </div>

      {loading ? (
        <div className="rounded-2xl border border-dashed border-slate-200 py-12 text-center text-slate-400">
          Loading intelligence…
        </div>
      ) : (
        <>
          <section className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
              <TrendingDown className="w-4 h-4 text-brand-600" />
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Velocity classifications</h4>
            </div>
            {classifications.length === 0 ? (
              <p className="py-10 text-center text-sm text-slate-400">No metrics yet. Run recompute to populate.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead>
                    <tr className="border-b border-slate-100">
                      {['Product', 'Class', 'Avg / day (30d)', 'Cover', 'Suggest'].map((col) => (
                        <th key={col} className="px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">{col}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {classifications.map((row) => (
                      <tr key={row.id} className="border-b border-slate-50 last:border-0">
                        <td className="px-4 py-3 text-sm font-semibold text-slate-800">{row.product?.name || '—'}</td>
                        <td className="px-4 py-3">
                          <BaseBadge variant={VELOCITY_VARIANT[row.velocity_class] || 'default'}>
                            {row.velocity_class || '—'}
                          </BaseBadge>
                        </td>
                        <td className="px-4 py-3 text-sm tabular-nums">{row.avg_daily_sales_30d ?? '—'}</td>
                        <td className="px-4 py-3 text-sm tabular-nums">{row.days_of_cover != null ? `${row.days_of_cover}d` : '—'}</td>
                        <td className="px-4 py-3 text-sm font-bold tabular-nums">{row.suggested_reorder_qty ?? 0}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          <section className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-600" />
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Stockout risks</h4>
            </div>
            {risks.length === 0 ? (
              <p className="py-10 text-center text-sm text-slate-400">No stockout risks for this branch.</p>
            ) : (
              <ul className="divide-y divide-slate-50">
                {risks.map((row) => (
                  <li key={row.id} className="px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-slate-800">{row.product?.name}</p>
                      <p className="text-xs text-slate-500">
                        On hand {row.quantity_on_hand}
                        {row.days_to_stockout != null ? ` · ~${row.days_to_stockout}d to stockout` : ''}
                      </p>
                    </div>
                    <BaseBadge variant={row.quantity_on_hand <= 0 ? 'danger' : 'warning'}>
                      {row.quantity_on_hand <= 0 ? 'Out' : 'At risk'}
                    </BaseBadge>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-100 bg-slate-50/50">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Open alerts</h4>
            </div>
            {alerts.length === 0 ? (
              <p className="py-10 text-center text-sm text-slate-400">No open alerts.</p>
            ) : (
              <ul className="divide-y divide-slate-50">
                {alerts.map((alert) => (
                  <li key={alert.id} className="px-4 py-3 flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-slate-800">{alert.message}</p>
                      <p className="text-xs text-slate-500 mt-0.5">{alert.type} · {alert.product?.name || '—'}</p>
                    </div>
                    <BaseBadge variant={SEVERITY_VARIANT[alert.severity] || 'default'}>{alert.severity}</BaseBadge>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}
    </div>
  )
}
