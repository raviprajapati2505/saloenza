import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { RefreshCw, Send, Users } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { pushToast } from '../stores/toast.js'
import {
  classifyRetention,
  createRetentionCohort,
  fetchRetentionCohorts,
  fetchRetentionCustomers,
  sendRetentionCohort,
} from '../services/retentionService.js'

const STATUS_TABS = [
  { key: 'at_risk', label: 'At risk' },
  { key: 'lapsed', label: 'Lapsed' },
  { key: 'lost', label: 'Lost' },
]

export default function RetentionView() {
  const auth = useAuthStore()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.CRM_RETENTION_MANAGE)

  const [status, setStatus] = useState('at_risk')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [customers, setCustomers] = useState([])
  const [cohorts, setCohorts] = useState([])
  const [selected, setSelected] = useState([])
  const [cohortName, setCohortName] = useState('')
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [{ customers: rows }, cohortRows] = await Promise.all([
        fetchRetentionCustomers({ status, limit: 200 }),
        fetchRetentionCohorts(),
      ])
      setCustomers(rows)
      setCohorts(cohortRows)
      setSelected([])
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load retention data.')
      setCustomers([])
    } finally {
      setLoading(false)
    }
  }, [status])

  useEffect(() => {
    void load()
  }, [load])

  const allSelected = useMemo(
    () => customers.length > 0 && selected.length === customers.length,
    [customers, selected],
  )

  const toggleAll = () => {
    setSelected(allSelected ? [] : customers.map((c) => c.id || c.customer_id).filter(Boolean))
  }

  const toggleOne = (id) => {
    setSelected((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id]))
  }

  const handleClassify = async () => {
    setSaving(true)
    try {
      const result = await classifyRetention()
      pushToast(`Classified. At risk ${result.at_risk ?? '—'}, lapsed ${result.lapsed ?? '—'}, lost ${result.lost ?? '—'}.`, 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Classify failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleCreateCohort = async () => {
    if (!cohortName.trim() || selected.length === 0) {
      pushToast('Select customers and enter a cohort name.', 'info')
      return
    }
    setSaving(true)
    try {
      await createRetentionCohort({
        name: cohortName.trim(),
        lapse_status: status,
        customer_ids: selected,
      })
      pushToast('Cohort created.', 'success')
      setCohortName('')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create cohort.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleSend = async (cohortId) => {
    setSaving(true)
    try {
      await sendRetentionCohort(cohortId, { deliver: true })
      pushToast('Cohort marked sent.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to send cohort.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Retention"
        subtitle={subscriptionPageSubtitle(auth, 'Find at-risk customers, build cohorts, and mark outreach sent.')}
        actions={(
          <div className="flex flex-wrap gap-2">
            {canManage ? (
              <BaseButton size="sm" leftIcon={Users} loading={saving} onClick={() => void handleClassify()}>
                Classify
              </BaseButton>
            ) : null}
            <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
              Refresh
            </BaseButton>
          </div>
        )}
      />

      <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        {STATUS_TABS.map((item) => (
          <button
            key={item.key}
            type="button"
            onClick={() => setStatus(item.key)}
            className={`rounded-lg px-3 py-2 text-sm font-medium ${status === item.key ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {canManage ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Create cohort from selection</h3>
          <div className="mt-3 flex flex-wrap items-end gap-3">
            <div className="min-w-[220px] flex-1">
              <BaseInput label="Cohort name" value={cohortName} onChange={(e) => setCohortName(e.target.value)} placeholder={`${status.replace('_', ' ')} outreach`} />
            </div>
            <BaseButton leftIcon={Users} loading={saving} onClick={() => void handleCreateCohort()}>
              Create ({selected.length})
            </BaseButton>
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900 capitalize">{status.replace('_', ' ')} customers</h3>
        </div>
        {loading ? <div className="space-y-3 p-5">{Array.from({ length: 5 }).map((_, i) => <div key={i} className="h-12 animate-pulse rounded-xl bg-slate-50" />)}</div> : null}
        {!loading && customers.length === 0 ? <p className="px-5 py-10 text-center text-sm text-slate-500">No customers in this status.</p> : null}
        {!loading && customers.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr>
                  <th className="px-5 py-3">
                    <input type="checkbox" checked={allSelected} onChange={toggleAll} />
                  </th>
                  <th className="px-5 py-3">Customer</th>
                  <th className="px-5 py-3">Last visit</th>
                  <th className="px-5 py-3">Visits</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {customers.map((row) => {
                  const id = row.id || row.customer_id
                  return (
                    <tr key={id}>
                      <td className="px-5 py-3">
                        <input type="checkbox" checked={selected.includes(id)} onChange={() => toggleOne(id)} />
                      </td>
                      <td className="px-5 py-3 font-medium text-slate-900">{row.name || row.customer?.name || `#${id}`}</td>
                      <td className="px-5 py-3">{row.last_visit_at || row.last_visit || '—'}</td>
                      <td className="px-5 py-3">{row.visit_count ?? row.visits ?? '—'}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Cohorts</h3>
        </div>
        {cohorts.length === 0 ? <p className="px-5 py-10 text-center text-sm text-slate-500">No cohorts yet.</p> : null}
        {cohorts.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {cohorts.map((cohort) => (
              <li key={cohort.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                  <p className="font-medium text-slate-900">{cohort.name}</p>
                  <p className="text-xs text-slate-500">
                    {cohort.lapse_status} · {cohort.member_count ?? cohort.customers_count ?? 0} members
                    {cohort.sent_at ? ` · sent ${cohort.sent_at}` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <BaseBadge size="sm" variant={cohort.sent_at ? 'success' : 'info'}>
                    {cohort.sent_at ? 'Sent' : 'Draft'}
                  </BaseBadge>
                  {canManage && !cohort.sent_at ? (
                    <BaseButton size="sm" variant="secondary" leftIcon={Send} loading={saving} onClick={() => void handleSend(cohort.id)}>
                      Mark send
                    </BaseButton>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </div>
  )
}
