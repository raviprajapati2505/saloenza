import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { format } from 'date-fns'
import {
  ArrowRight,
  Clock3,
  Footprints,
  RefreshCw,
  ShoppingBag,
  UserRound,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import StatsGrid from '../components/dashboard/StatsGrid.jsx'
import { useAuthStore } from '../stores/auth'
import { authHasModule, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { bookingSourceLabel, canStartService, mapAppointmentServicesForUpdate } from '../lib/appointmentStatus.js'
import { fetchLiveQueue } from '../services/queueService.js'
import { fetchBranches } from '../services/branchService.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { apiGet, apiPut } from '../lib/apiHelpers'
import { pushToast } from '../stores/toast.js'

const STATUS_VARIANT = {
  scheduled: 'info',
  confirmed: 'success',
  'in-progress': 'warning',
  completed: 'success',
  cancelled: 'danger',
  'no-show': 'danger',
}

const TYPE_LABELS = {
  appointment: 'Appointment',
  walk_in: 'Walk-in',
  product_sale: 'POS sale',
}

function buildStatusUpdatePayload(appt, status) {
  return {
    branch_id: appt.branch_id ?? null,
    customer_id: appt.customer_id ?? null,
    type: appt.type || 'appointment',
    starts_at: appt.starts_at,
    status,
    discount: appt.discount ?? 0,
    notes: appt.notes ?? null,
    services: mapAppointmentServicesForUpdate(appt),
  }
}

function QueueCard({ item, canUpdate, onStatusChange, updatingId, fmt }) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 transition hover:border-brand-200 hover:bg-white">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-brand-500 px-2 text-xs font-bold text-white">
              #{item.queue_position}
            </span>
            <p className="truncate text-base font-semibold text-slate-900">{item.customer?.name}</p>
            <BaseBadge variant={STATUS_VARIANT[item.status] || 'default'} size="sm">
              {(item.status || '').replace(/-/g, ' ')}
            </BaseBadge>
            {item.booking_source === 'self_booking' ? (
              <BaseBadge variant="info" size="sm">{bookingSourceLabel('self_booking')}</BaseBadge>
            ) : null}
            {item.type === 'walk_in' || item.queue_lane === 'walk_in_waiting' ? (
              <BaseBadge variant="violet" size="sm">{TYPE_LABELS.walk_in}</BaseBadge>
            ) : null}
          </div>
          <p className="mt-1 text-sm text-slate-600">{item.service_summary}</p>
          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <span>{item.staff?.name || 'Unassigned'}</span>
            <span>{item.starts_at ? format(new Date(item.starts_at), 'p') : 'Now'}</span>
            <span>{item.duration_minutes} min</span>
            {item.status === 'in-progress' ? (
              <span className="font-medium text-amber-700">Serving now</span>
            ) : item.estimated_wait_minutes > 0 ? (
              <span>Wait ~{item.estimated_wait_minutes} min</span>
            ) : null}
          </div>
        </div>
        <div className="flex flex-col items-end gap-2">
          <p className="text-sm font-semibold text-slate-900">{fmt.money(item.grand_total ?? 0)}</p>
          <div className="flex flex-wrap justify-end gap-2">
            {item.links?.customer ? (
              <Link to={item.links.customer} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
                Customer
              </Link>
            ) : null}
            <Link to={item.links?.appointment || '/appointments'} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
              Appointment
            </Link>
            <Link to={item.links?.pos || '/pos'} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
              POS
            </Link>
          </div>
          {canUpdate ? (
            <div className="flex flex-wrap gap-2">
              {['confirmed', 'scheduled'].includes(item.status) ? (
                <BaseButton
                  size="xs"
                  disabled={!canStartService(item.starts_at)}
                  onClick={() => onStatusChange(item, 'in-progress')}
                >
                  Start
                </BaseButton>
              ) : null}
              {item.status === 'in-progress' ? (
                <BaseButton size="xs" variant="secondary" onClick={() => onStatusChange(item, 'completed')}>
                  Complete
                </BaseButton>
              ) : null}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  )
}

export default function LiveQueueView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canUpdate = auth.can('appointments.update')
  const hasQueueModule = authHasModule(auth, 'queue')

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [snapshot, setSnapshot] = useState(null)
  const [branchId, setBranchId] = useState('')
  const [branches, setBranches] = useState([])
  const [updatingId, setUpdatingId] = useState(null)
  const [autoRefresh, setAutoRefresh] = useState(true)

  useEffect(() => {
    if (auth.can('queue.view_all') && auth.can('branches.view')) {
      void fetchBranches()
        .then((rows) => setBranches(rows || []))
        .catch(() => setBranches([]))
    }
  }, [auth])

  const load = useCallback(async () => {
    setError('')
    try {
      const data = await fetchLiveQueue({
        branch_id: branchId || undefined,
      })
      setSnapshot(data)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load live queue.')
      setSnapshot(null)
    } finally {
      setLoading(false)
    }
  }, [branchId])

  useEffect(() => {
    if (!hasQueueModule) {
      setLoading(false)
      return undefined
    }

    void load()
  }, [load, hasQueueModule])

  useEffect(() => {
    if (!autoRefresh || !hasQueueModule) return undefined
    const id = window.setInterval(() => {
      void load()
    }, 30000)
    return () => window.clearInterval(id)
  }, [autoRefresh, load, hasQueueModule])

  if (!auth.can('queue.view')) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
        You do not have permission to view the live queue.
      </div>
    )
  }

  if (!hasQueueModule) {
    return (
      <div className="space-y-4">
        <PageHeader
          title="Live queue"
          subtitle="This module is not included in your current subscription plan."
        />
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
          Upgrade to a plan that includes Live Queue to use the floor waiting list and walk-in board.
          {auth.can('settings.view') ? (
            <p className="mt-3">
              <Link to="/billing" className="font-medium text-brand-600 hover:text-brand-700">
                View subscription plans
              </Link>
            </p>
          ) : null}
        </div>
      </div>
    )
  }

  const summary = snapshot?.summary ?? {}

  const kpiItems = useMemo(
    () => [
      { key: 'in_service', title: 'In service', value: summary.in_service ?? 0, icon: UserRound, color: 'amber' },
      { key: 'waiting', title: 'Waiting', value: summary.waiting ?? 0, icon: Clock3, color: 'brand' },
      { key: 'walk_ins', title: 'Walk-ins', value: summary.walk_ins ?? 0, icon: Footprints, color: 'violet' },
      { key: 'pos', title: 'POS / counter', value: summary.pos ?? 0, icon: ShoppingBag, color: 'indigo' },
    ],
    [summary],
  )

  const handleStatusChange = async (item, status) => {
    if (!canUpdate || !item?.id) return
    setUpdatingId(item.id)
    try {
      const response = await apiGet(`/v1/appointments/${item.id}`)
      const appt = response?.data?.data?.appointment ?? response?.data?.appointment
      await apiPut(`/v1/appointments/${item.id}`, buildStatusUpdatePayload(appt, status))
      pushToast(`Updated to ${status.replace(/-/g, ' ')}`, 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to update appointment.', 'error')
    } finally {
      setUpdatingId(null)
    }
  }

  const sections = [
    { key: 'in_service', title: 'Currently serving', items: snapshot?.sections?.in_service ?? [] },
    { key: 'waiting', title: 'Waiting now', items: snapshot?.sections?.waiting ?? [] },
    { key: 'upcoming', title: 'Upcoming', items: snapshot?.sections?.upcoming ?? [] },
    { key: 'walk_ins', title: 'Walk-ins', items: snapshot?.sections?.walk_ins ?? [] },
  ]

  const scopeLabel = snapshot?.scope?.label
  const isStaffScope = snapshot?.scope?.mode === 'staff'

  return (
    <div className="space-y-6">
      <PageHeader
        title="Live queue"
        subtitle={subscriptionPageSubtitle(
          auth,
          snapshot?.generated_at
            ? `Updated ${format(new Date(snapshot.generated_at), 'p')} · ${scopeLabel || 'floor view for today'}`
            : (scopeLabel || 'Real-time waiting list, walk-ins, and service order.'),
        )}
        actions={(
          <div className="flex flex-wrap gap-2">
            <BaseButton
              variant={autoRefresh ? 'primary' : 'secondary'}
              size="sm"
              onClick={() => setAutoRefresh((value) => !value)}
            >
              Auto-refresh {autoRefresh ? 'on' : 'off'}
            </BaseButton>
            <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} onClick={() => void load()} loading={loading}>
              Refresh
            </BaseButton>
          </div>
        )}
      />

      <div className="grid gap-4 lg:grid-cols-4">
        {!isStaffScope && branches.length > 0 ? (
          <BaseSelect
            label="Branch"
            value={branchId}
            onChange={(event) => setBranchId(event.target.value)}
            options={[
              { value: '', label: 'All accessible branches' },
              ...branches.map((branch) => ({ value: String(branch.id), label: branch.branch_name || branch.name })),
            ]}
          />
        ) : null}
      </div>

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <StatsGrid items={kpiItems} loading={loading} />

      <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-slate-900">Serving order</h3>
            <p className="text-sm text-slate-500">Who is being served, who is waiting, and who is next</p>
          </div>
          <Link to="/appointments" className="inline-flex items-center gap-1 text-sm font-semibold text-brand-600">
            Open calendar <ArrowRight className="h-4 w-4" />
          </Link>
        </div>

        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, index) => (
              <div key={index} className="h-24 animate-pulse rounded-2xl bg-slate-50" />
            ))}
          </div>
        ) : null}

        {!loading && (snapshot?.queue?.length ?? 0) === 0 ? (
          <div className="py-12 text-center text-sm text-slate-500">Queue is clear for today.</div>
        ) : null}

        {!loading && snapshot?.queue?.length ? (
          <div className="space-y-3">
            {snapshot.queue.map((item) => (
              <QueueCard
                key={item.id}
                item={item}
                canUpdate={canUpdate}
                onStatusChange={handleStatusChange}
                updatingId={updatingId}
                fmt={fmt}
              />
            ))}
          </div>
        ) : null}
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        {sections.map((section) => (
          <div key={section.key} className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
            <h3 className="text-base font-semibold text-slate-900">{section.title}</h3>
            <p className="mt-0.5 text-sm text-slate-500">{section.items.length} active</p>
            <div className="mt-4 space-y-3">
              {section.items.length === 0 ? (
                <p className="text-sm text-slate-400">None right now.</p>
              ) : (
                section.items.slice(0, 5).map((item) => (
                  <div key={item.id} className="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2">
                    <div>
                      <p className="font-medium text-slate-900">{item.customer?.name}</p>
                      <p className="text-xs text-slate-500">{item.service_summary}</p>
                    </div>
                    <BaseBadge variant={STATUS_VARIANT[item.status] || 'default'} size="sm">
                      {TYPE_LABELS[item.type] || item.type}
                    </BaseBadge>
                  </div>
                ))
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
