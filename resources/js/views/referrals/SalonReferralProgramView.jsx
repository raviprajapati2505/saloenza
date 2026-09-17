import React, { useEffect, useState } from 'react'
import {
  Copy,
  Gift,
  Share2,
  Users,
  CheckCircle2,
  Clock,
  CircleDollarSign,
  TrendingUp,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import { useAuthStore } from '../../stores/auth'
import { subscriptionPageSubtitle } from '../../lib/subscriptionModules.js'
import { copyTextToClipboard } from '../../lib/clipboard.js'
import { fetchSalonReferralDashboard } from '../../services/salonReferralService.js'
import { pushToast } from '../../stores/toast.js'
import { useTenantFormatter } from '../../hooks/useTenantFormatter.js'

function StatCard({ label, value, icon: Icon, accent = 'brand' }) {
  const accents = {
    brand: 'bg-brand-50 border-brand-100 text-brand-600',
    emerald: 'bg-emerald-50 border-emerald-100 text-emerald-600',
    amber: 'bg-amber-50 border-amber-100 text-amber-600',
    slate: 'bg-slate-50 border-slate-100 text-slate-600',
  }

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-slate-500">{label}</p>
          <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
        </div>
        <div className={`rounded-xl border p-2.5 ${accents[accent] || accents.brand}`}>
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  )
}

function StatusBadge({ status }) {
  if (status === 'qualified') {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
        <CheckCircle2 className="h-3.5 w-3.5" />
        Qualified
      </span>
    )
  }

  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
      <Clock className="h-3.5 w-3.5" />
      In Progress
    </span>
  )
}

function HowItWorksStep({ step, title, description }) {
  return (
    <div className="flex gap-4">
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white shadow-sm">
        {step}
      </div>
      <div>
        <h4 className="font-semibold text-slate-900">{title}</h4>
        <p className="mt-1 text-sm leading-6 text-slate-500">{description}</p>
      </div>
    </div>
  )
}

function ReferralRow({ row, formatMoney }) {
  const isCredited = row.credit_label === 'Credited'
  const amountClass = isCredited ? 'text-emerald-700' : 'text-slate-500'

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-lg font-semibold text-slate-900">{row.salon_name}</h3>
            <StatusBadge status={row.status} />
          </div>
          <p className="mt-1 text-sm text-slate-500">
            {[row.owner_name, row.plan_name].filter(Boolean).join(' | ') || '—'}
          </p>
          {row.started_at ? (
            <p className="mt-1 text-xs text-slate-400">Started: {row.started_at}</p>
          ) : null}
        </div>

        <div className="text-left sm:text-right">
          <p className={`text-xl font-bold ${amountClass}`}>
            {isCredited ? '+' : ''}{formatMoney(row.credit_amount || 0, { maximumFractionDigits: 0 })}
          </p>
          <p className="mt-0.5 text-xs font-medium uppercase tracking-wide text-slate-400">{row.credit_label}</p>
        </div>
      </div>

      <div className="mt-4">
        <div className="mb-1.5 flex items-center justify-between text-xs text-slate-500">
          <span>Progress</span>
          <span className="font-medium text-slate-700">
            {row.progress_percent}% · {row.months_completed}/{row.qualifying_months} months
          </span>
        </div>
        <div className="h-2.5 overflow-hidden rounded-full bg-slate-100">
          <div
            className={`h-full rounded-full transition-all duration-500 ${
              row.status === 'qualified'
                ? 'bg-gradient-to-r from-brand-500 to-emerald-500'
                : 'bg-brand-500'
            }`}
            style={{ width: `${Math.min(100, row.progress_percent || 0)}%` }}
          />
        </div>
      </div>
    </div>
  )
}

export default function SalonReferralProgramView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [data, setData] = useState(null)

  useEffect(() => {
    let active = true

    async function load() {
      setLoading(true)
      setError('')
      try {
        const payload = await fetchSalonReferralDashboard()
        if (!active) return
        setData(payload)
      } catch (err) {
        if (!active) return
        setError(err?.response?.data?.message || 'Unable to load referral program.')
      } finally {
        if (active) setLoading(false)
      }
    }

    void load()
    return () => { active = false }
  }, [])

  const summary = data?.summary || {}
  const commissionRate = data?.commission_rate ?? 10
  const qualifyingMonths = data?.qualifying_months ?? 6

  const copyCode = async () => {
    const code = data?.referral_code
    if (!code) {
      pushToast('Referral code is not available yet.', 'error')
      return
    }

    const copied = await copyTextToClipboard(code)
    pushToast(copied ? 'Referral code copied.' : 'Unable to copy code.', copied ? 'success' : 'error')
  }

  const copyLink = async () => {
    const link = data?.signup_url
    if (!link) {
      pushToast('Signup link is not available yet.', 'error')
      return
    }

    const copied = await copyTextToClipboard(link)
    pushToast(copied ? 'Signup link copied.' : 'Unable to copy link.', copied ? 'success' : 'error')
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Referral Program"
        subtitle={subscriptionPageSubtitle(auth, 'Earn credits by referring other salons to SalonPro')}
      />

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-emerald-50 shadow-sm">
        <div className="p-6 md:p-8">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700">
                <Gift className="h-3.5 w-3.5" />
                Your Referral Code
              </div>
              <h2 className="mt-3 text-xl font-semibold text-slate-900 md:text-2xl">
                Share this code with other salon owners
              </h2>
              <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                When they sign up and stay active for {qualifyingMonths} months, you earn {commissionRate}% of their subscription value as credits!
              </p>
              <div className="mt-4 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                <span className="rounded-full bg-white px-3 py-1 ring-1 ring-brand-200">{commissionRate}% commission per referral</span>
                <span className="rounded-full bg-white px-3 py-1 ring-1 ring-brand-200">{qualifyingMonths} months qualifying period</span>
              </div>
            </div>

            <div className="w-full max-w-sm rounded-2xl border border-white/80 bg-white/90 p-5 shadow-sm backdrop-blur">
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Your referral code</p>
              <p className="mt-2 break-all text-3xl font-bold tracking-wide text-brand-700">
                {loading ? '…' : (data?.referral_code || '—')}
              </p>
              <div className="mt-4 flex flex-wrap gap-2">
                <button
                  type="button"
                  onClick={copyCode}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500"
                >
                  <Copy className="h-4 w-4" />
                  Copy Code
                </button>
                <button
                  type="button"
                  onClick={copyLink}
                  className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                  <Share2 className="h-4 w-4" />
                  Copy Link
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total Referrals" value={summary.total_referrals ?? 0} icon={Users} accent="brand" />
        <StatCard label="Qualified" value={summary.qualified_count ?? 0} icon={CheckCircle2} accent="emerald" />
        <StatCard label="In Progress" value={summary.in_progress_count ?? 0} icon={Clock} accent="amber" />
        <StatCard
          label="Credits Earned"
          value={fmt.money(summary.credits_earned || 0, { maximumFractionDigits: 0 })}
          icon={CircleDollarSign}
          accent="emerald"
        />
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="mb-5 flex items-center gap-2">
          <TrendingUp className="h-5 w-5 text-brand-600" />
          <h3 className="text-lg font-semibold text-slate-900">How It Works</h3>
        </div>
        <div className="grid gap-6 md:grid-cols-3">
          <HowItWorksStep
            step="1"
            title="Share Your Code"
            description="Share your unique referral code with other salon owners you know."
          />
          <HowItWorksStep
            step="2"
            title="They Sign Up"
            description="When they use your code during registration, they get linked to your account."
          />
          <HowItWorksStep
            step="3"
            title="Earn Credits"
            description={`After ${qualifyingMonths} months of active subscription, you earn ${commissionRate}% of their subscription value as credits.`}
          />
        </div>
      </div>

      <div>
        <h3 className="mb-4 text-lg font-semibold text-slate-900">Your Referrals</h3>
        {loading ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
            Loading referrals…
          </div>
        ) : null}
        {!loading && (data?.referrals || []).length === 0 ? (
          <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center shadow-sm">
            <p className="text-sm font-medium text-slate-700">No referrals yet</p>
            <p className="mt-1 text-sm text-slate-500">Share your code to start earning credits when referred salons qualify.</p>
          </div>
        ) : null}
        {!loading && (data?.referrals || []).length > 0 ? (
          <div className="space-y-4">
            {(data?.referrals || []).map((row) => (
              <ReferralRow key={row.id} row={row} formatMoney={fmt.money} />
            ))}
          </div>
        ) : null}
      </div>
    </div>
  )
}
