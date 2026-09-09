import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Building2,
  CheckCircle2,
  Clock,
  HandCoins,
  Link2,
  MapPinned,
  Plus,
  Settings2,
  UserX,
  Bell,
  Wallet,
} from 'lucide-react'
import BaseButton from '../../components/ui/BaseButton.jsx'
import HeroBanner from '../../components/dashboard/HeroBanner.jsx'
import StatsGrid from '../../components/dashboard/StatsGrid.jsx'
import OnboardingStatusChart from '../../components/dashboard/OnboardingStatusChart.jsx'
import RecentActivity from '../../components/dashboard/RecentActivity.jsx'
import LatestOrganizations from '../../components/dashboard/LatestOrganizations.jsx'
import SubscriptionOverview from '../../components/dashboard/SubscriptionOverview.jsx'
import UpcomingSubscriptionRenewals from '../../components/dashboard/UpcomingSubscriptionRenewals.jsx'
import QuickActions from '../../components/dashboard/QuickActions.jsx'
import NotificationPanel from '../../components/dashboard/NotificationPanel.jsx'
import { useAuthStore } from '../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'
import {
  fetchAdminNotifications,
  fetchAdminPendingCounts,
  markAdminNotificationRead,
  markAllAdminNotificationsRead,
} from '../../services/notificationService.js'
import { fetchOnboardingRecords } from '../../services/adminOnboardingService.js'
import { fetchUpcomingSubscriptionRenewals } from '../../services/adminSubscriptionService.js'

function resolveActivityIcon(notification) {
  const event = String(notification.event || notification.title || '').toLowerCase()
  if (event.includes('affiliate') && event.includes('withdraw')) return Wallet
  if (event.includes('affiliate')) return Link2
  if (event.includes('upgrade') || event.includes('subscription')) return HandCoins
  if (event.includes('onboard') || event.includes('salon')) return Building2
  return Clock
}

function resolveActivityStatus(notification) {
  if (!notification.read_at) {
    return { status: 'info', statusLabel: 'New' }
  }
  return { status: 'default', statusLabel: 'Read' }
}

export default function PlatformDashboardView() {
  const auth = useAuthStore()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [counts, setCounts] = useState({})
  const [summary, setSummary] = useState({ total: 0, completed: 0, pending: 0, no_owner: 0 })
  const [salons, setSalons] = useState([])
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [notificationsError, setNotificationsError] = useState('')
  const [markingAll, setMarkingAll] = useState(false)
  const [renewals, setRenewals] = useState([])
  const [renewalsSummary, setRenewalsSummary] = useState({})
  const [renewalsError, setRenewalsError] = useState('')

  const canOnboard = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING)
  const canCreateSalon = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING_CREATE)
  const canAffiliates = auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES)
  const canUpgrades = auth.canPlatform(PLATFORM_PERMISSIONS.UPGRADE_REQUESTS)
  const canBranches = auth.canPlatform(PLATFORM_PERMISSIONS.BRANCHES)
  const canPlans = auth.canPlatform(PLATFORM_PERMISSIONS.SUBSCRIPTIONS)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    setNotificationsError('')
    setRenewalsError('')
    try {
      const [pending, notificationPayload, renewalPayload, onboarding] = await Promise.all([
        fetchAdminPendingCounts(),
        fetchAdminNotifications({ per_page: 8 }).catch((err) => {
          setNotificationsError(err?.response?.data?.message || 'Unable to load notifications.')
          return { notifications: [], unread_count: 0 }
        }),
        canPlans && auth.token
          ? fetchUpcomingSubscriptionRenewals({}, auth.token).catch((err) => {
              setRenewalsError(err?.response?.data?.message || 'Unable to load subscription renewals.')
              return { renewals: [], summary: {} }
            })
          : Promise.resolve(null),
        canOnboard && auth.token ? fetchOnboardingRecords({}, auth.token) : Promise.resolve(null),
      ])

      setCounts(pending || {})
      setNotifications(notificationPayload?.notifications || [])
      setUnreadCount(Number(notificationPayload?.unread_count || 0))
      setRenewals(renewalPayload?.renewals || [])
      setRenewalsSummary(renewalPayload?.summary || {})

      if (onboarding?.summary) {
        setSummary(onboarding.summary)
      }
      if (Array.isArray(onboarding?.onboardings)) {
        const sorted = [...onboarding.onboardings].sort(
          (a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0),
        )
        setSalons(sorted.slice(0, 6))
      } else if (!canOnboard) {
        setSalons([])
        setSummary({ total: 0, completed: 0, pending: 0, no_owner: 0 })
      }
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Unable to load platform dashboard.')
    } finally {
      setLoading(false)
    }
  }, [auth.token, canOnboard, canPlans])

  useEffect(() => {
    void load()
  }, [load])

  const greetingName = auth.user?.name || auth.user?.firstname || 'Platform Owner'

  const heroSummary = useMemo(
    () => [
      { key: 'salons', label: 'Total salons', value: summary.total ?? 0 },
      { key: 'pending', label: 'Onboarding pending', value: summary.pending ?? 0 },
      { key: 'affiliates', label: 'Affiliate apps', value: counts.affiliate_applications ?? 0 },
      { key: 'upgrades', label: 'Upgrade requests', value: counts.subscription_upgrades ?? 0 },
    ],
    [summary, counts],
  )

  const kpiItems = useMemo(
    () => [
      {
        key: 'total',
        title: 'Total salons',
        value: summary.total ?? 0,
        icon: Building2,
        color: 'teal',
      },
      {
        key: 'completed',
        title: 'Completed onboardings',
        value: summary.completed ?? 0,
        icon: CheckCircle2,
        color: 'blue',
      },
      {
        key: 'pending',
        title: 'Onboarding pending',
        value: summary.pending ?? 0,
        icon: Clock,
        color: 'amber',
      },
      {
        key: 'no_owner',
        title: 'Salons without owner',
        value: summary.no_owner ?? 0,
        icon: UserX,
        color: 'rose',
      },
      {
        key: 'affiliate_apps',
        title: 'Affiliate applications',
        value: counts.affiliate_applications ?? 0,
        icon: Link2,
        color: 'indigo',
      },
      {
        key: 'withdrawals',
        title: 'Affiliate withdrawals',
        value: counts.affiliate_withdrawals ?? 0,
        icon: Wallet,
        color: 'amber',
      },
      {
        key: 'upgrades',
        title: 'Upgrade requests',
        value: counts.subscription_upgrades ?? 0,
        icon: HandCoins,
        color: 'teal',
      },
      {
        key: 'unread',
        title: 'Unread notifications',
        value: unreadCount,
        icon: Bell,
        color: 'blue',
      },
    ],
    [summary, counts, unreadCount],
  )

  const queueItems = useMemo(
    () => [
      {
        key: 'pending_onboarding',
        label: 'Onboarding pending',
        value: summary.pending ?? 0,
        hint: 'Owners still finishing setup',
        icon: Clock,
        color: 'amber',
        href: canOnboard ? '/admin/onboarding' : undefined,
      },
      {
        key: 'affiliate_apps',
        label: 'Affiliate applications',
        value: counts.affiliate_applications ?? 0,
        hint: 'Awaiting approval',
        icon: Link2,
        color: 'indigo',
        href: canAffiliates ? '/admin/affiliates' : undefined,
      },
      {
        key: 'withdrawals',
        label: 'Withdrawal requests',
        value: counts.affiliate_withdrawals ?? 0,
        hint: 'Payout queue',
        icon: Wallet,
        color: 'rose',
        href: canAffiliates ? '/admin/affiliates' : undefined,
      },
      {
        key: 'upgrades',
        label: 'Plan upgrades',
        value: counts.subscription_upgrades ?? 0,
        hint: 'Manual payment / activation',
        icon: HandCoins,
        color: 'teal',
        href: canUpgrades ? '/admin/subscription-upgrades' : undefined,
      },
    ],
    [summary, counts, canOnboard, canAffiliates, canUpgrades],
  )

  const quickActions = useMemo(
    () => [
      canCreateSalon && {
        key: 'create-salon',
        label: 'Onboard salon',
        description: 'Create a new salon',
        icon: Plus,
        to: '/admin/onboarding/new',
        color: 'teal',
      },
      canOnboard && {
        key: 'salons',
        label: 'Manage salons',
        description: 'Onboarding hub',
        icon: Building2,
        to: '/admin/onboarding',
        color: 'indigo',
      },
      canAffiliates && {
        key: 'affiliates',
        label: 'Affiliates',
        description: 'Partners & payouts',
        icon: Link2,
        to: '/admin/affiliates',
        color: 'amber',
      },
      canUpgrades && {
        key: 'upgrades',
        label: 'Upgrades',
        description: 'Approve plan changes',
        icon: HandCoins,
        to: '/admin/subscription-upgrades',
        color: 'rose',
      },
      canBranches && {
        key: 'branches',
        label: 'Branches',
        description: 'All locations',
        icon: MapPinned,
        to: '/admin/branches',
        color: 'emerald',
      },
      canPlans && {
        key: 'plans',
        label: 'Plans',
        description: 'Subscription catalog',
        icon: Settings2,
        to: '/admin/subscription-plans',
        color: 'slate',
      },
    ].filter(Boolean),
    [canCreateSalon, canOnboard, canAffiliates, canUpgrades, canBranches, canPlans],
  )

  const activityItems = useMemo(
    () =>
      notifications.map((n) => {
        const { status, statusLabel } = resolveActivityStatus(n)
        return {
          id: n.id,
          title: n.title || 'Notification',
          description: n.body || '',
          actor: n.meta?.saloon_name || n.meta?.partner_name || n.meta?.display_name || undefined,
          timestamp: n.created_at,
          status,
          statusLabel,
          icon: resolveActivityIcon(n),
          href: n.action_url || undefined,
        }
      }),
    [notifications],
  )

  const handleMarkRead = async (id) => {
    try {
      const payload = await markAdminNotificationRead(id)
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: payload?.notification?.read_at || new Date().toISOString() } : n)),
      )
      setUnreadCount(Number(payload?.unread_count ?? Math.max(0, unreadCount - 1)))
    } catch {
      // Non-blocking
    }
  }

  const handleMarkAllRead = async () => {
    setMarkingAll(true)
    try {
      await markAllAdminNotificationsRead()
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() })),
      )
      setUnreadCount(0)
    } catch {
      // Non-blocking
    } finally {
      setMarkingAll(false)
    }
  }

  return (
    <div className="space-y-6">
      <HeroBanner
        title="Platform Dashboard"
        tone="brand"
        greeting={`Welcome back, ${greetingName}`}
        subtitle="Monitor salon onboarding, affiliate queues, and subscription upgrades across the platform."
        summaryCards={heroSummary}
        loading={loading}
        onRefresh={() => void load()}
        refreshing={loading}
        actions={
          canCreateSalon ? (
            <Link to="/admin/onboarding/new">
              <BaseButton size="sm" leftIcon={Plus}>
                Onboard salon
              </BaseButton>
            </Link>
          ) : null
        }
      />

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <StatsGrid items={kpiItems} loading={loading} />

      <SubscriptionOverview
        title="Work queues"
        subtitle="Items waiting for platform action"
        items={queueItems}
        loading={loading}
      />

      {canPlans ? (
        <UpcomingSubscriptionRenewals
          items={renewals}
          summary={renewalsSummary}
          loading={loading}
          error={renewalsError}
          viewAllTo="/admin/subscription-renewals"
        />
      ) : null}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div className="xl:col-span-1">
          <OnboardingStatusChart summary={summary} loading={loading} />
        </div>
        <div className="xl:col-span-2">
          <LatestOrganizations
            items={salons}
            loading={loading}
            emptyMessage={
              canOnboard
                ? 'No salons onboarded yet.'
                : 'Salon list requires onboarding permission.'
            }
            viewAllTo={canOnboard ? '/admin/onboarding' : undefined}
          />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <RecentActivity
          items={activityItems}
          loading={loading}
          error={notificationsError}
          emptyMessage="No recent platform activity."
        />
        <NotificationPanel
          items={notifications}
          loading={loading}
          error={notificationsError}
          unreadCount={unreadCount}
          onMarkRead={handleMarkRead}
          onMarkAllRead={handleMarkAllRead}
          markingAll={markingAll}
        />
        <QuickActions actions={quickActions} loading={loading} />
      </div>
    </div>
  )
}
