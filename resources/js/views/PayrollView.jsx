import React, { useCallback, useEffect, useState } from 'react'
import { format } from 'date-fns'
import { Check, Download, Plus, RefreshCw } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { pushToast } from '../stores/toast.js'
import {
  approvePayRun,
  calculatePayRun,
  createPayRun,
  downloadPayRunCsv,
  fetchPayRuns,
  markPayRunPaid,
  payRunExportCsvUrl,
} from '../services/payrollService.js'

const STATUS_VARIANT = {
  draft: 'info',
  approved: 'warning',
  paid: 'success',
}

export default function PayrollView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.PAYROLL_MANAGE)
  const canApprove = canMutate(auth, TENANT_PERMISSIONS.PAYROLL_APPROVE)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [runs, setRuns] = useState([])
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    period_start: format(new Date(new Date().getFullYear(), new Date().getMonth(), 1), 'yyyy-MM-dd'),
    period_end: format(new Date(), 'yyyy-MM-dd'),
    notes: '',
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setRuns(await fetchPayRuns())
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load pay runs.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const handleCreate = async () => {
    setSaving(true)
    try {
      await createPayRun({
        period_start: form.period_start,
        period_end: form.period_end,
        notes: form.notes || undefined,
        calculate: true,
      })
      pushToast('Pay run created.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create pay run.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const runAction = async (action, id, successMessage) => {
    setSaving(true)
    try {
      await action(id)
      pushToast(successMessage, 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Action failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleExport = async (id) => {
    try {
      await downloadPayRunCsv(id)
    } catch (err) {
      pushToast(err?.response?.data?.message || 'CSV export failed.', 'error')
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Payroll"
        subtitle={subscriptionPageSubtitle(auth, 'Create pay runs, calculate, approve, and mark paid.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {canManage ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Create pay run</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <BaseInput label="Period start" type="date" value={form.period_start} onChange={(e) => setForm((f) => ({ ...f, period_start: e.target.value }))} />
            <BaseInput label="Period end" type="date" value={form.period_end} onChange={(e) => setForm((f) => ({ ...f, period_end: e.target.value }))} />
            <BaseInput label="Notes" value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} />
            <div className="flex items-end">
              <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleCreate()}>Create</BaseButton>
            </div>
          </div>
        </div>
      ) : null}

      <div className="space-y-4">
        {loading ? Array.from({ length: 2 }).map((_, i) => <div key={i} className="h-28 animate-pulse rounded-[18px] bg-slate-100" />) : null}
        {!loading && runs.length === 0 ? (
          <div className="rounded-[18px] border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-500">
            No pay runs yet.
          </div>
        ) : null}
        {!loading
          ? runs.map((run) => (
            <div key={run.id} className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-base font-semibold text-slate-900">
                      {run.period_start} → {run.period_end}
                    </h3>
                    <BaseBadge size="sm" variant={STATUS_VARIANT[run.status] || 'default'}>{run.status}</BaseBadge>
                  </div>
                  <p className="mt-2 text-sm text-slate-600">
                    Basic {fmt.money(run.total_basic ?? 0)} · Commission {fmt.money(run.total_commission ?? 0)} · Gross {fmt.money(run.total_gross ?? 0)}
                  </p>
                  {run.notes ? <p className="mt-1 text-xs text-slate-500">{run.notes}</p> : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  {canManage && run.status === 'draft' ? (
                    <BaseButton size="sm" variant="secondary" loading={saving} onClick={() => void runAction(calculatePayRun, run.id, 'Calculated.')}>
                      Calculate
                    </BaseButton>
                  ) : null}
                  {canApprove && run.status === 'draft' ? (
                    <BaseButton size="sm" leftIcon={Check} loading={saving} onClick={() => void runAction(approvePayRun, run.id, 'Approved.')}>
                      Approve
                    </BaseButton>
                  ) : null}
                  {canManage && run.status === 'approved' ? (
                    <BaseButton size="sm" loading={saving} onClick={() => void runAction(markPayRunPaid, run.id, 'Marked paid.')}>
                      Mark paid
                    </BaseButton>
                  ) : null}
                  <BaseButton size="sm" variant="secondary" leftIcon={Download} onClick={() => void handleExport(run.id)}>
                    Export CSV
                  </BaseButton>
                  <a
                    href={payRunExportCsvUrl(run.id)}
                    className="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-slate-500 underline"
                    target="_blank"
                    rel="noreferrer"
                  >
                    CSV URL
                  </a>
                </div>
              </div>
              {(run.lines || []).length > 0 ? (
                <div className="mt-4 overflow-x-auto border-t border-slate-100 pt-3">
                  <table className="min-w-full text-xs">
                    <thead className="text-left text-slate-500">
                      <tr>
                        <th className="px-2 py-1">Staff</th>
                        <th className="px-2 py-1 text-right">Basic</th>
                        <th className="px-2 py-1 text-right">Commission</th>
                        <th className="px-2 py-1 text-right">Gross</th>
                      </tr>
                    </thead>
                    <tbody>
                      {run.lines.map((line) => (
                        <tr key={line.id || `${run.id}-${line.user_id}`}>
                          <td className="px-2 py-1">{line.staff?.name || line.user?.name || line.user_id}</td>
                          <td className="px-2 py-1 text-right">{fmt.money(line.basic_salary ?? 0)}</td>
                          <td className="px-2 py-1 text-right">{fmt.money(line.commission_total ?? 0)}</td>
                          <td className="px-2 py-1 text-right font-medium">{fmt.money(line.gross ?? 0)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}
            </div>
          ))
          : null}
      </div>
    </div>
  )
}
