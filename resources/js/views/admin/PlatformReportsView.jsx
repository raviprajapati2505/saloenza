import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { format, subDays } from 'date-fns'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseBadge from '../../components/ui/BaseBadge.jsx'
import ChartCard from '../../components/dashboard/ChartCard.jsx'
import AppointmentTrendChart from '../../components/dashboard/AppointmentTrendChart.jsx'
import OnboardingStatusChart from '../../components/dashboard/OnboardingStatusChart.jsx'
import {
  ReportFilters,
  ReportTabs,
  ReportKpiGrid,
  ReportTable,
  StatusChips,
} from '../../components/reports/ReportChrome.jsx'
import { useAuthStore } from '../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'
import { fetchAdminPendingCounts } from '../../services/notificationService.js'
import { fetchOnboardingRecords } from '../../services/adminOnboardingService.js'
import { fetchSubscriptionUpgradeOrders } from '../../services/adminSubscriptionService.js'
import { fetchAdminBranches } from '../../services/adminBranchService.js'
import { fetchAdminAffiliates } from '../../services/affiliatePortalService.js'
import { API_BASE_URL } from '../../lib/api'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

const ONBOARDING_STATUS = {
  completed: { label: 'Completed', variant: 'success' },
  pending: { label: 'Pending', variant: 'warning' },
  no_owner: { label: 'No owner', variant: 'default' },
}

export default function PlatformReportsView() {
  const auth = useAuthStore()
  const canOnboard = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING)
  const canAffiliates = auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES)
  const canUpgrades = auth.canPlatform(PLATFORM_PERMISSIONS.UPGRADE_REQUESTS)
  const canBranches = auth.canPlatform(PLATFORM_PERMISSIONS.BRANCHES)
  const canPlans = auth.canPlatform(PLATFORM_PERMISSIONS.SUBSCRIPTIONS)

  const availableTabs = useMemo(() => {
    const tabs = [{ key: 'overview', label: 'Overview' }]
    if (canOnboard) tabs.push({ key: 'salons', label: 'Salons' })
    if (canAffiliates) tabs.push({ key: 'affiliates', label: 'Affiliates' })
    if (canUpgrades) tabs.push({ key: 'upgrades', label: 'Upgrades' })
    if (canBranches) tabs.push({ key: 'branches', label: 'Branches' })
    if (canPlans) tabs.push({ key: 'plans', label: 'Plans' })
    return tabs
  }, [canOnboard, canAffiliates, canUpgrades, canBranches, canPlans])

  const [tab, setTab] = useState('overview')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')

  const [counts, setCounts] = useState({})
  const [onboardingSummary, setOnboardingSummary] = useState({
    total: 0, completed: 0, pending: 0, no_owner: 0,
  })
  const [salons, setSalons] = useState([])
  const [affiliates, setAffiliates] = useState([])
  const [upgrades, setUpgrades] = useState([])
  const [branches, setBranches] = useState([])
  const [plans, setPlans] = useState([])

  // Salon created-at trend (last 30 days) derived from onboarding list
  const salonTrend = useMemo(() => {
    const days = Array.from({ length: 14 }, (_, i) => {
      const d = subDays(new Date(), 13 - i)
      const key = format(d, 'yyyy-MM-dd')
      const count = salons.filter((s) => s.created_at && format(new Date(s.created_at), 'yyyy-MM-dd') === key).length
      return { label: format(d, 'dd MMM'), value: count }
    })
    return days
  }, [salons])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const tasks = [fetchAdminPendingCounts().then((c) => setCounts(c || {}))]

      if (canOnboard && auth.token) {
        tasks.push(
          fetchOnboardingRecords({}, auth.token).then((data) => {
            setOnboardingSummary(data?.summary || { total: 0, completed: 0, pending: 0, no_owner: 0 })
            setSalons(Array.isArray(data?.onboardings) ? data.onboardings : [])
          }).catch(() => {
            setSalons([])
          }),
        )
      }

      if (canAffiliates) {
        tasks.push(
          fetchAdminAffiliates({})
            .then((payload) => setAffiliates(payload.affiliate_partners || []))
            .catch(() => setAffiliates([])),
        )
      }

      if (canUpgrades && auth.token) {
        tasks.push(
          fetchSubscriptionUpgradeOrders({}, auth.token)
            .then((r) => setUpgrades(r.orders || []))
            .catch(() => setUpgrades([])),
        )
      }

      if (canBranches) {
        tasks.push(
          fetchAdminBranches({ page: 1, per_page: 100 })
            .then((r) => setBranches(r.branches || []))
            .catch(() => setBranches([])),
        )
      }

      if (canPlans && auth.token) {
        tasks.push(
          axios
            .get(`${API_BASE_URL}/v1/subscription-plans`, {
              params: { page: 1, per_page: 100 },
              headers: { Authorization: `Bearer ${auth.token}`, Accept: 'application/json' },
            })
            .then((response) => {
              const raw = response.data?.data?.subscription_plans
              const list = Array.isArray(raw) ? raw : Array.isArray(raw?.data) ? raw.data : []
              setPlans(list)
            })
            .catch(() => setPlans([])),
        )
      }

      await Promise.all(tasks)
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Unable to load platform reports.')
    } finally {
      setLoading(false)
    }
  }, [auth.token, canOnboard, canAffiliates, canUpgrades, canBranches, canPlans])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!availableTabs.find((t) => t.key === tab)) {
      setTab(availableTabs[0]?.key || 'overview')
    }
  }, [availableTabs, tab])

  useEffect(() => {
    setSearch('')
    setStatusFilter('all')
  }, [tab])

  const overviewKpis = useMemo(
    () => [
      { key: 'salons', label: 'Total salons', value: onboardingSummary.total ?? salons.length },
      { key: 'pending', label: 'Onboarding pending', value: onboardingSummary.pending ?? 0 },
      { key: 'affiliates', label: 'Affiliate applications', value: counts.affiliate_applications ?? 0 },
      { key: 'upgrades', label: 'Upgrade requests', value: counts.subscription_upgrades ?? 0 },
      { key: 'withdrawals', label: 'Withdrawal requests', value: counts.affiliate_withdrawals ?? 0 },
      { key: 'branches', label: 'Branches listed', value: branches.length },
      { key: 'plans', label: 'Subscription plans', value: plans.length },
      { key: 'completed', label: 'Completed onboardings', value: onboardingSummary.completed ?? 0 },
    ],
    [onboardingSummary, salons.length, counts, branches.length, plans.length],
  )

  const filteredSalons = useMemo(() => {
    let rows = [...salons]
    if (statusFilter !== 'all') {
      rows = rows.filter((r) => r.onboarding_status === statusFilter)
    }
    if (search.trim()) {
      const q = search.trim().toLowerCase()
      rows = rows.filter((r) =>
        String(r.business_name || '').toLowerCase().includes(q)
        || String(r.owner?.email || '').toLowerCase().includes(q)
        || String(r.city || '').toLowerCase().includes(q),
      )
    }
    return rows.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
  }, [salons, statusFilter, search])

  const filteredAffiliates = useMemo(() => {
    let rows = [...affiliates]
    if (statusFilter !== 'all') {
      rows = rows.filter((r) => String(r.status || '').toLowerCase() === statusFilter)
    }
    if (search.trim()) {
      const q = search.trim().toLowerCase()
      rows = rows.filter((r) =>
        String(r.display_name || r.name || '').toLowerCase().includes(q)
        || String(r.code || '').toLowerCase().includes(q)
        || String(r.email || '').toLowerCase().includes(q),
      )
    }
    return rows
  }, [affiliates, statusFilter, search])

  const filteredUpgrades = useMemo(() => {
    let rows = [...upgrades]
    if (statusFilter !== 'all') {
      rows = rows.filter((r) => String(r.status || '').toLowerCase() === statusFilter)
    }
    if (search.trim()) {
      const q = search.trim().toLowerCase()
      rows = rows.filter((r) =>
        String(r.saloon?.name || r.saloon?.business_name || '').toLowerCase().includes(q)
        || String(r.plan?.name || r.subscription_plan?.name || '').toLowerCase().includes(q),
      )
    }
    return rows
  }, [upgrades, statusFilter, search])

  const filteredBranches = useMemo(() => {
    let rows = [...branches]
    if (search.trim()) {
      const q = search.trim().toLowerCase()
      rows = rows.filter((r) =>
        String(r.branch_name || '').toLowerCase().includes(q)
        || String(r.saloon?.name || r.city || '').toLowerCase().includes(q),
      )
    }
    return rows
  }, [branches, search])

  const filteredPlans = useMemo(() => {
    let rows = [...plans]
    if (search.trim()) {
      const q = search.trim().toLowerCase()
      rows = rows.filter((r) => String(r.name || '').toLowerCase().includes(q))
    }
    return rows
  }, [plans, search])

  return (
    <div className="space-y-6">
      <PageHeader
        title="Platform Reports"
        subtitle="Salon onboarding, affiliates, upgrades, and footprint across the platform."
      />

      <ReportTabs tabs={availableTabs} active={tab} onChange={setTab} />

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      {tab === 'overview' ? (
        <>
          <ReportFilters onRefresh={() => void load()} loading={loading} />
          <ReportKpiGrid items={overviewKpis} loading={loading} />
          <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {canOnboard ? (
              <OnboardingStatusChart summary={onboardingSummary} loading={loading} />
            ) : (
              <ChartCard title="Onboarding" empty emptyMessage="Requires onboarding permission." />
            )}
            <AppointmentTrendChart
              title="New salons"
              subtitle="Created in the last 14 days"
              data={salonTrend}
              loading={loading}
              type="bar"
              valueLabel="Salons"
            />
          </div>
        </>
      ) : null}

      {tab === 'salons' ? (
        <>
          <div className="space-y-3">
            <StatusChips
              value={statusFilter}
              onChange={setStatusFilter}
              options={[
                { value: 'all', label: 'All' },
                { value: 'completed', label: 'Completed' },
                { value: 'pending', label: 'Pending' },
                { value: 'no_owner', label: 'No owner' },
              ]}
            />
            <ReportFilters onRefresh={() => void load()} loading={loading}>
              <div className="min-w-[200px] flex-1">
                <label className="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input
                  type="search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Salon, owner, city…"
                  className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                />
              </div>
            </ReportFilters>
          </div>
          <ReportTable
            loading={loading}
            rows={filteredSalons}
            emptyMessage="No salons match these filters."
            columns={[
              {
                key: 'business_name',
                label: 'Salon',
                render: (r) => (
                  <div>
                    <p className="font-semibold text-slate-900">{r.business_name || '—'}</p>
                    <p className="text-xs text-slate-400">{[r.city, r.state].filter(Boolean).join(', ') || '—'}</p>
                  </div>
                ),
              },
              {
                key: 'owner',
                label: 'Owner',
                render: (r) => r.owner?.name || r.owner?.email || '—',
              },
              {
                key: 'status',
                label: 'Status',
                render: (r) => {
                  const s = ONBOARDING_STATUS[r.onboarding_status] || ONBOARDING_STATUS.pending
                  return <BaseBadge variant={s.variant} size="sm">{s.label}</BaseBadge>
                },
              },
              { key: 'branches', label: 'Branches', render: (r) => r.branch_count ?? 0 },
              { key: 'created', label: 'Created', render: (r) => formatDate(r.created_at) },
              {
                key: 'action',
                label: '',
                render: (r) => (
                  <Link to={`/admin/onboarding/${r.id}/edit`} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
                    Open
                  </Link>
                ),
              },
            ]}
          />
        </>
      ) : null}

      {tab === 'affiliates' ? (
        <>
          <div className="space-y-3">
            <StatusChips
              value={statusFilter}
              onChange={setStatusFilter}
              options={[
                { value: 'all', label: 'All' },
                { value: 'pending', label: 'Pending' },
                { value: 'active', label: 'Active' },
                { value: 'rejected', label: 'Rejected' },
              ]}
            />
            <ReportFilters onRefresh={() => void load()} loading={loading}>
              <div className="min-w-[200px] flex-1">
                <label className="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input
                  type="search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Name, code, email…"
                  className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                />
              </div>
            </ReportFilters>
          </div>
          <ReportTable
            loading={loading}
            rows={filteredAffiliates}
            emptyMessage="No affiliates match these filters."
            columns={[
              {
                key: 'name',
                label: 'Partner',
                render: (r) => r.display_name || r.name || '—',
              },
              { key: 'code', label: 'Code', render: (r) => r.code || '—' },
              {
                key: 'status',
                label: 'Status',
                render: (r) => (
                  <BaseBadge
                    size="sm"
                    variant={
                      r.status === 'active' ? 'success'
                        : r.status === 'pending' ? 'warning'
                          : r.status === 'rejected' ? 'danger' : 'default'
                    }
                  >
                    {r.status || '—'}
                  </BaseBadge>
                ),
              },
              {
                key: 'referrals',
                label: 'Referrals',
                render: (r) => r.referrals_count ?? r.saloons_count ?? '—',
              },
              {
                key: 'action',
                label: '',
                render: () => (
                  <Link to="/admin/affiliates" className="text-xs font-semibold text-brand-600">
                    Manage
                  </Link>
                ),
              },
            ]}
          />
        </>
      ) : null}

      {tab === 'upgrades' ? (
        <>
          <div className="space-y-3">
            <StatusChips
              value={statusFilter}
              onChange={setStatusFilter}
              options={[
                { value: 'all', label: 'All' },
                { value: 'pending', label: 'Pending' },
                { value: 'approved', label: 'Approved' },
                { value: 'rejected', label: 'Rejected' },
                { value: 'completed', label: 'Completed' },
              ]}
            />
            <ReportFilters onRefresh={() => void load()} loading={loading}>
              <div className="min-w-[200px] flex-1">
                <label className="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input
                  type="search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Salon or plan…"
                  className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                />
              </div>
            </ReportFilters>
          </div>
          <ReportTable
            loading={loading}
            rows={filteredUpgrades}
            emptyMessage="No upgrade orders match these filters."
            columns={[
              {
                key: 'salon',
                label: 'Salon',
                render: (r) => r.saloon?.name || r.saloon?.business_name || `Salon #${r.saloon_id || '—'}`,
              },
              {
                key: 'plan',
                label: 'Plan',
                render: (r) => r.plan?.name || r.subscription_plan?.name || '—',
              },
              {
                key: 'amount',
                label: 'Amount',
                render: (r) => formatMoneyDefault(r.amount ?? r.payable_amount ?? r.total),
              },
              {
                key: 'status',
                label: 'Status',
                render: (r) => <BaseBadge size="sm">{r.status || '—'}</BaseBadge>,
              },
              { key: 'created', label: 'Created', render: (r) => formatDate(r.created_at) },
              {
                key: 'action',
                label: '',
                render: () => (
                  <Link to="/admin/subscription-upgrades" className="text-xs font-semibold text-brand-600">
                    Queue
                  </Link>
                ),
              },
            ]}
          />
        </>
      ) : null}

      {tab === 'branches' ? (
        <>
          <ReportFilters onRefresh={() => void load()} loading={loading}>
            <div className="min-w-[200px] flex-1">
              <label className="mb-1 block text-xs font-medium text-slate-600">Search</label>
              <input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Branch or salon…"
                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
              />
            </div>
          </ReportFilters>
          <ReportTable
            loading={loading}
            rows={filteredBranches}
            emptyMessage="No branches found."
            columns={[
              { key: 'name', label: 'Branch', render: (r) => r.branch_name || '—' },
              {
                key: 'salon',
                label: 'Salon',
                render: (r) => r.saloon?.name || r.saloon?.business_name || '—',
              },
              { key: 'city', label: 'City', render: (r) => r.city || '—' },
              {
                key: 'active',
                label: 'Active',
                render: (r) => (
                  <BaseBadge size="sm" variant={r.is_active ? 'success' : 'default'}>
                    {r.is_active ? 'Yes' : 'Inactive'}
                  </BaseBadge>
                ),
              },
            ]}
          />
        </>
      ) : null}

      {tab === 'plans' ? (
        <>
          <ReportFilters onRefresh={() => void load()} loading={loading}>
            <div className="min-w-[200px] flex-1">
              <label className="mb-1 block text-xs font-medium text-slate-600">Search</label>
              <input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Plan name…"
                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
              />
            </div>
          </ReportFilters>
          <ReportTable
            loading={loading}
            rows={filteredPlans}
            emptyMessage="No subscription plans found."
            columns={[
              { key: 'name', label: 'Plan', render: (r) => r.name || '—' },
              {
                key: 'price',
                label: 'Price',
                render: (r) => formatMoneyDefault(r.price),
              },
              {
                key: 'interval',
                label: 'Interval',
                render: (r) => r.billing_interval || '—',
              },
              {
                key: 'modules',
                label: 'Modules',
                render: (r) => (Array.isArray(r.modules) ? r.modules.length : '—'),
              },
              {
                key: 'active',
                label: 'Status',
                render: (r) => (
                  <BaseBadge size="sm" variant={r.is_active !== false ? 'success' : 'default'}>
                    {r.is_active !== false ? 'Active' : 'Inactive'}
                  </BaseBadge>
                ),
              },
            ]}
          />
        </>
      ) : null}
    </div>
  )
}
