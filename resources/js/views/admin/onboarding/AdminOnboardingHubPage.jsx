import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import {
  Building2, Plus, Search, RefreshCw, CheckCircle2, Clock, UserX, RotateCcw, ShieldAlert, Edit2, Trash2,
} from 'lucide-react'
import PageHeader from '../../../components/ui/PageHeader.jsx'
import BaseButton from '../../../components/ui/BaseButton.jsx'
import { useAuthStore } from '../../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../../lib/platformPermissions.js'
import { deleteSaloon } from '../../../services/saloonService.js'
import {
  completeOwnerOnboarding,
  fetchOnboardingRecords,
  resetOwnerOnboarding,
} from '../../../services/adminOnboardingService.js'

const STATUS_CONFIG = {
  completed: { label: 'Completed', className: 'bg-emerald-50 text-emerald-700 border-emerald-200', icon: CheckCircle2 },
  pending: { label: 'Pending', className: 'bg-amber-50 text-amber-700 border-amber-200', icon: Clock },
  no_owner: { label: 'No Owner', className: 'bg-slate-100 text-slate-600 border-slate-200', icon: UserX },
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

export default function AdminOnboardingHubPage() {
  const auth = useAuthStore()
  const canCreate = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING_CREATE)
  const canUpdate = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING_UPDATE)
  const canDelete = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING_DELETE)
  const [records, setRecords] = useState([])
  const [summary, setSummary] = useState({ total: 0, completed: 0, pending: 0, no_owner: 0 })
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [forbidden, setForbidden] = useState(false)
  const [actionId, setActionId] = useState(null)
  const [successMessage, setSuccessMessage] = useState('')

  const loadRecords = useCallback(async () => {
    if (!auth.token) return
    setLoading(true)
    setError('')
    setForbidden(false)
    try {
      const params = {}
      if (search.trim()) params.search = search.trim()
      if (statusFilter !== 'all') params.onboarding_status = statusFilter
      const data = await fetchOnboardingRecords(params, auth.token)
      setRecords(Array.isArray(data.onboardings) ? data.onboardings : [])
      setSummary(data.summary || { total: 0, completed: 0, pending: 0, no_owner: 0 })
    } catch (err) {
      if (err.forbidden) setForbidden(true)
      else setError(err?.response?.data?.message || 'Failed to load onboarding records.')
      setRecords([])
    } finally {
      setLoading(false)
    }
  }, [auth.token, search, statusFilter])

  useEffect(() => {
    const timer = setTimeout(() => { void loadRecords() }, search ? 300 : 0)
    return () => clearTimeout(timer)
  }, [loadRecords, search])

  const filteredCount = useMemo(() => records.length, [records])

  const handleComplete = async (ownerId) => {
    if (!canUpdate) return
    setActionId(`complete-${ownerId}`)
    setSuccessMessage('')
    try {
      await completeOwnerOnboarding(ownerId, auth.token)
      setSuccessMessage('Owner onboarding marked complete.')
      await loadRecords()
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to complete onboarding.')
    } finally {
      setActionId(null)
    }
  }

  const handleReset = async (ownerId) => {
    if (!canUpdate) return
    setActionId(`reset-${ownerId}`)
    setSuccessMessage('')
    try {
      await resetOwnerOnboarding(ownerId, auth.token)
      setSuccessMessage('Owner onboarding reset — they will be prompted again on login.')
      await loadRecords()
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to reset onboarding.')
    } finally {
      setActionId(null)
    }
  }

  const handleDelete = async (record) => {
    if (!canDelete) return
    const label = record.business_name || 'this salon'
    if (!window.confirm(`Delete ${label}? This cannot be undone.`)) return

    setActionId(`delete-${record.id}`)
    setSuccessMessage('')
    try {
      await deleteSaloon(record.id)
      setSuccessMessage(`${label} deleted successfully.`)
      await loadRecords()
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to delete salon.')
    } finally {
      setActionId(null)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Salon Onboarding"
        subtitle="Manage tenant onboarding status and provision new salons."
        breadcrumbs={[
          { label: 'Administration' },
          { label: 'Salon Onboarding' },
        ]}
        actions={(
          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <BaseButton variant="secondary" size="sm" className="w-full sm:w-auto" leftIcon={RefreshCw} onClick={loadRecords} loading={loading}>
              Refresh
            </BaseButton>
            {canCreate && (
              <Link to="/admin/onboarding/new" className="w-full sm:w-auto">
                <BaseButton size="sm" className="w-full" leftIcon={Plus}>Onboard New Salon</BaseButton>
              </Link>
            )}
          </div>
        )}
      />

      {forbidden && (
        <div className="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4">
          <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-rose-500" />
          <p className="text-sm font-medium text-rose-700">You do not have permission to manage onboarding.</p>
        </div>
      )}

      {successMessage && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {successMessage}
        </div>
      )}

      {error && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{error}</div>
      )}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { label: 'Total Salons', value: summary.total, icon: Building2 },
          { label: 'Completed', value: summary.completed, icon: CheckCircle2 },
          { label: 'Pending', value: summary.pending, icon: Clock },
          { label: 'No Owner', value: summary.no_owner, icon: UserX },
        ].map(stat => {
          const Icon = stat.icon
          return (
            <div key={stat.label} className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{stat.label}</p>
                <Icon className="w-4 h-4 text-brand-600" />
              </div>
              <p className="text-2xl font-black text-slate-800 mt-2">{stat.value}</p>
            </div>
          )
        })}
      </div>

      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Search salon, owner, email..."
            className="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
        <div className="flex gap-2 flex-wrap">
          {['all', 'completed', 'pending', 'no_owner'].map(status => (
            <button
              key={status}
              type="button"
              onClick={() => setStatusFilter(status)}
              className={`px-3 py-2 text-xs font-bold uppercase rounded-xl border transition cursor-pointer ${
                statusFilter === status ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-500 border-slate-200'
              }`}
            >
              {status === 'all' ? 'All' : status.replace('_', ' ')}
            </button>
          ))}
        </div>
      </div>

      <div className="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="py-16 text-center text-slate-400">Loading onboarding records...</div>
        ) : records.length === 0 ? (
          <div className="py-16 text-center text-slate-400">
            <Building2 className="w-10 h-10 mx-auto mb-3 text-slate-300" />
            <p className="font-semibold text-slate-600">No salons found</p>
            {canCreate && <Link to="/admin/onboarding/new" className="inline-block mt-4">
              <BaseButton size="sm" leftIcon={Plus}>Onboard New Salon</BaseButton>
            </Link>}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50/50">
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Salon</th>
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Owner</th>
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Affiliate</th>
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Status</th>
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Created</th>
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {records.map((record, i) => {
                  const status = STATUS_CONFIG[record.onboarding_status] || STATUS_CONFIG.no_owner
                  const StatusIcon = status.icon
                  const owner = record.owner
                  const isDeleting = actionId === `delete-${record.id}`
                  return (
                    <motion.tr
                      key={record.id}
                      initial={{ opacity: 0, y: 8 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: i * 0.03 }}
                      className="border-b border-slate-100"
                    >
                      <td className="py-4 px-6">
                        <p className="text-sm font-semibold text-slate-900">{record.business_name}</p>
                        <p className="text-xs text-slate-500 mt-0.5">
                          {[record.city, record.state].filter(Boolean).join(', ') || `${record.branch_count} branch(es)`}
                        </p>
                      </td>
                      <td className="py-4 px-6">
                        {owner ? (
                          <>
                            <p className="text-sm font-medium text-slate-800">{owner.name}</p>
                            <p className="text-xs text-slate-500">{owner.email}</p>
                          </>
                        ) : (
                          <span className="text-xs text-slate-400">No owner assigned</span>
                        )}
                      </td>
                      <td className="py-4 px-6">
                        {record.affiliate_partner ? (
                          <>
                            <p className="text-sm font-medium text-slate-800">{record.affiliate_partner.display_name || record.affiliate_partner.code}</p>
                            <p className="text-xs text-slate-500">{record.affiliate_partner.code}</p>
                          </>
                        ) : (
                          <span className="text-xs text-slate-400">—</span>
                        )}
                      </td>
                      <td className="py-4 px-6">
                        <div className="flex flex-col gap-1.5">
                          <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${status.className}`}>
                            <StatusIcon className="w-3.5 h-3.5" />
                            {status.label}
                          </span>
                          {record.activation_pending ? (
                            <span className="inline-flex w-fit items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800">
                              Awaiting payment / activation
                            </span>
                          ) : null}
                        </div>
                      </td>
                      <td className="py-4 px-6 text-sm text-slate-600">{formatDate(record.created_at)}</td>
                      <td className="py-4 px-6">
                        <div className="flex items-center justify-end gap-2 flex-wrap">
                          {canUpdate && <Link
                            to={`/admin/onboarding/${record.id}/edit`}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100"
                          >
                            <Edit2 className="w-3.5 h-3.5" />
                            Edit
                          </Link>}
                          {canDelete && (
                          <button
                            type="button"
                            onClick={() => handleDelete(record)}
                            disabled={isDeleting}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 disabled:opacity-50"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                            Delete
                          </button>
                          )}
                          {canUpdate && owner && record.onboarding_status === 'pending' && (
                            <button
                              type="button"
                              onClick={() => handleComplete(owner.id)}
                              disabled={actionId === `complete-${owner.id}`}
                              className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 disabled:opacity-50"
                            >
                              <CheckCircle2 className="w-3.5 h-3.5" />
                              Complete
                            </button>
                          )}
                          {canUpdate && owner && record.onboarding_status === 'completed' && (
                            <button
                              type="button"
                              onClick={() => handleReset(owner.id)}
                              disabled={actionId === `reset-${owner.id}`}
                              className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100 disabled:opacity-50"
                            >
                              <RotateCcw className="w-3.5 h-3.5" />
                              Reset
                            </button>
                          )}
                        </div>
                      </td>
                    </motion.tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <p className="text-xs text-slate-400">
        Showing {filteredCount} record(s). Pending owners are redirected to the tenant onboarding wizard on login.
      </p>
    </div>
  )
}
