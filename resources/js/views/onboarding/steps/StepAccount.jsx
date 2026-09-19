import React, { forwardRef, useEffect, useImperativeHandle, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { fetchMasterList } from '../../../lib/apiHelpers'
import { formatLimit, formatPlanPrice } from '../../../lib/subscriptionModules.js'

const StepAccount = forwardRef(function StepAccount({ modelValue }, ref) {
  const account = modelValue || {}
  const [plans, setPlans] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [selectedPlanId, setSelectedPlanId] = useState(account.subscription_plan_id ?? null)

  useEffect(() => {
    let active = true
    async function loadPlans() {
      setLoading(true)
      setError('')
      try {
        const rows = await fetchMasterList('/v1/subscription-plans', 'subscription_plans')
        if (!active) return
        setPlans(rows)
        if (!selectedPlanId && rows.length > 0) {
          const trial = rows.find((p) => p.slug === 'free' || p.slug === 'free-trial') ?? rows[0]
          setSelectedPlanId(trial.id)
        }
      } catch (err) {
        if (!active) return
        setError(err?.response?.data?.message || 'Unable to load subscription plans.')
      } finally {
        if (active) setLoading(false)
      }
    }
    void loadPlans()
    return () => { active = false }
  }, [])

  const selectedPlan = plans.find((p) => Number(p.id) === Number(selectedPlanId))

  useImperativeHandle(ref, () => ({
    validate: () => Boolean(selectedPlanId),
    getData: () => ({
      ...account,
      subscription_plan_id: selectedPlanId,
      plan_slug: selectedPlan?.slug ?? null,
      plan: selectedPlan?.name ?? null,
    }),
  }), [account, selectedPlanId, selectedPlan])

  return (
    <div>
      <h2 className="text-2xl font-semibold text-slate-900">Choose Your Plan</h2>
      <p className="mt-1 text-sm text-slate-500">
        Free plans start immediately. Paid plans with a trial start that trial right away — our team still collects offline payment to convert you to a paid subscription.
      </p>

      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        <div className="rounded-xl border border-slate-200 p-4">
          <p className="text-xs uppercase tracking-wide text-slate-500">Owner name</p>
          <p className="mt-1 text-sm font-medium text-slate-900">{account.name || '-'}</p>
        </div>
        <div className="rounded-xl border border-slate-200 p-4">
          <p className="text-xs uppercase tracking-wide text-slate-500">Email</p>
          <p className="mt-1 text-sm font-medium text-slate-900">{account.email || '-'}</p>
        </div>
      </div>

      {loading ? (
        <div className="mt-6 flex items-center gap-2 text-sm text-slate-500">
          <Loader2 className="h-4 w-4 animate-spin text-brand-600" /> Loading plans...
        </div>
      ) : null}

      {error ? (
        <div className="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      {!loading && plans.length > 0 ? (
        <div className="mt-6 grid gap-4 lg:grid-cols-2">
          {plans.map((plan) => {
            const selected = Number(plan.id) === Number(selectedPlanId)
            return (
              <button
                key={plan.id}
                type="button"
                onClick={() => setSelectedPlanId(plan.id)}
                className={`rounded-2xl border p-5 text-left transition ${
                  selected
                    ? 'border-brand-500 bg-brand-50/70 ring-2 ring-brand-500/20'
                    : 'border-slate-200 bg-white hover:border-brand-300'
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-lg font-bold text-slate-900">{plan.name}</p>
                    <p className="mt-1 text-sm text-slate-500">{plan.description || 'Salon subscription plan'}</p>
                  </div>
                  <p className="text-sm font-bold text-brand-700">{formatPlanPrice(plan)}</p>
                </div>
                <div className="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-600">
                  <span>Branches: {formatLimit(plan.max_branches)}</span>
                  <span>Staff: {formatLimit(plan.max_staff)}</span>
                  {plan.trial_days > 0 ? <span className="col-span-2">Trial: {plan.trial_days} days</span> : null}
                </div>
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {(plan.modules || []).map((module) => (
                    <span key={module} className="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 border border-slate-200">
                      {module}
                    </span>
                  ))}
                </div>
                {Number(plan.price) > 0 ? (
                  <p className="mt-3 text-xs font-medium text-amber-700">
                    Request plan · offline payment · activated by platform admin
                  </p>
                ) : (
                  <p className="mt-3 text-xs font-medium text-brand-700">
                    Start immediately on Free
                  </p>
                )}
              </button>
            )
          })}
        </div>
      ) : null}

      {selectedPlan && Number(selectedPlan.price) > 0 ? (
        <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <p className="text-sm font-semibold text-amber-900">
            {selectedPlan.name} requires offline activation
          </p>
          <p className="mt-1 text-sm text-amber-800">
            After onboarding, your salon stays under review. A platform admin will contact you, collect payment offline, then unlock your workspace. There is no online payment link.
          </p>
        </div>
      ) : null}

      {selectedPlan?.trial_days > 0 && !(Number(selectedPlan.price) > 0) ? (
        <div className="mt-6 rounded-xl border border-brand-200 bg-brand-50 p-4">
          <p className="text-sm font-semibold text-brand-800">
            {selectedPlan.name} includes a {selectedPlan.trial_days}-day trial.
          </p>
          <p className="mt-1 text-sm text-brand-700">
            After the trial, request a paid plan if you need more branches, staff, or modules.
          </p>
        </div>
      ) : null}
    </div>
  )
})

export default StepAccount
