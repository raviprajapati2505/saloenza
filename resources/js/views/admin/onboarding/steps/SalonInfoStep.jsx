import React, { useEffect, useState } from 'react'
import { Controller, useFormContext } from 'react-hook-form'
import { Building2, CreditCard, Hash, Layers, Loader2, Link2, Tag } from 'lucide-react'
import BaseInput from '../../../../components/ui/BaseInput.jsx'
import BaseSelect from '../../../../components/ui/BaseSelect.jsx'
import FormToggle from '../../../../components/ui/FormToggle.jsx'
import { fetchMasterList } from '../../../../lib/apiHelpers.js'
import { formatLimit, formatPlanPrice, TRIAL_DURATION_OPTIONS } from '../../../../lib/subscriptionModules.js'
import { fetchAdminAffiliates } from '../../../../services/affiliatePortalService.js'

const PAYMENT_TYPES = [
  { value: 'Monthly', label: 'Monthly' },
  { value: 'Quarterly', label: 'Quarterly' },
  { value: 'Yearly', label: 'Yearly' },
  { value: 'One-time', label: 'One-time' },
]

export default function SalonInfoStep() {
  const {
    register,
    control,
    watch,
    setValue,
    formState: { errors },
  } = useFormContext()

  const salonErrors = errors.salon || {}
  const [plans, setPlans] = useState([])
  const [affiliates, setAffiliates] = useState([])
  const [loadingPlans, setLoadingPlans] = useState(true)
  const [loadingAffiliates, setLoadingAffiliates] = useState(true)
  const selectedPlanId = watch('subscription_plan_id')
  const startTrial = watch('start_trial')
  const selectedPlan = plans.find((plan) => Number(plan.id) === Number(selectedPlanId))

  useEffect(() => {
    let active = true
    async function loadPlans() {
      setLoadingPlans(true)
      try {
        const rows = await fetchMasterList('/v1/subscription-plans', 'subscription_plans')
        if (!active) return
        setPlans(rows)
        if (!selectedPlanId && rows.length > 0) {
          const freePlan = rows.find((plan) => plan.slug === 'free' || plan.slug === 'free-trial') ?? rows[0]
          setValue('subscription_plan_id', freePlan.id, { shouldValidate: true })
          const defaultTrialDays = Number(freePlan.trial_days || 0)
          setValue('start_trial', defaultTrialDays > 0, { shouldValidate: true })
          setValue('trial_days', defaultTrialDays > 0 ? defaultTrialDays : 15, { shouldValidate: true })
        }
      } catch (error) {
        console.error('Failed to load subscription plans:', error)
      } finally {
        if (active) setLoadingPlans(false)
      }
    }
    void loadPlans()
    return () => { active = false }
  }, [setValue])

  useEffect(() => {
    let active = true
    async function loadAffiliates() {
      setLoadingAffiliates(true)
      try {
        const payload = await fetchAdminAffiliates({ status: 'active', per_page: 100 })
        if (!active) return
        setAffiliates(payload.affiliate_partners || [])
      } catch (error) {
        console.error('Failed to load affiliates:', error)
      } finally {
        if (active) setLoadingAffiliates(false)
      }
    }
    void loadAffiliates()
    return () => { active = false }
  }, [])

  useEffect(() => {
    if (!selectedPlan) return
    const planTrialDays = Number(selectedPlan.trial_days || 0)
    if (planTrialDays > 0) {
      setValue('start_trial', true, { shouldValidate: true })
      setValue('trial_days', planTrialDays, { shouldValidate: true })
    } else {
      setValue('start_trial', false, { shouldValidate: true })
      setValue('trial_days', 0, { shouldValidate: true })
    }
  }, [selectedPlanId, selectedPlan, setValue])

  return (
    <div>
      <div className="mb-6 flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
          <Building2 className="h-5 w-5" />
        </div>
        <div>
          <h2 className="text-lg font-semibold text-slate-900">Salon Information</h2>
          <p className="text-sm text-slate-500">Basic salon details, subscription plan, affiliate, and payment info</p>
        </div>
      </div>

      <div className="mb-6 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
          <Layers className="h-4 w-4 text-brand-600" />
          Subscription Plan
        </div>

        {loadingPlans ? (
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
            Loading plans...
          </div>
        ) : (
          <Controller
            name="subscription_plan_id"
            control={control}
            render={({ field }) => (
              <BaseSelect
                id="subscription_plan_id"
                label="Plan"
                required
                placeholder="Select a subscription plan"
                options={plans.map((plan) => ({
                  value: String(plan.id),
                  label: `${plan.name} · ${formatPlanPrice(plan)} · ${formatLimit(plan.max_branches)} branches`,
                }))}
                value={field.value ? String(field.value) : ''}
                onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : '')}
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
                error={errors.subscription_plan_id?.message}
              />
            )}
          />
        )}

        {selectedPlan ? (
          <div className="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
            <span>Branches: {formatLimit(selectedPlan.max_branches)}</span>
            <span>Staff: {formatLimit(selectedPlan.max_staff)}</span>
            <span>Plan trial default: {Number(selectedPlan.trial_days || 0) > 0 ? `${selectedPlan.trial_days} days` : 'Off'}</span>
          </div>
        ) : null}

        <div className="mt-4 flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-700">Enable free trial for this salon</p>
            <p className="text-xs text-slate-500">
              Override the plan default trial for this onboarding (15 days / 1 month / 2 months).
            </p>
          </div>
          <Controller
            name="start_trial"
            control={control}
            render={({ field }) => (
              <FormToggle
                id="start_trial"
                checked={Boolean(field.value)}
                onChange={(checked) => {
                  field.onChange(checked)
                  if (checked && Number(watch('trial_days') || 0) <= 0) {
                    const fallback = Number(selectedPlan?.trial_days || 0) > 0
                      ? Number(selectedPlan.trial_days)
                      : 15
                    setValue('trial_days', fallback, { shouldValidate: true })
                  }
                  if (!checked) {
                    setValue('trial_days', 0, { shouldValidate: true })
                  }
                }}
                label={field.value ? 'Trial on' : 'Trial off'}
              />
            )}
          />
        </div>

        {startTrial ? (
          <div className="mt-3">
            <Controller
              name="trial_days"
              control={control}
              render={({ field }) => (
                <BaseSelect
                  id="trial_days"
                  label="Trial duration"
                  required
                  options={TRIAL_DURATION_OPTIONS.filter((option) => option.value > 0)}
                  value={field.value !== undefined && field.value !== null ? String(field.value) : ''}
                  onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : 15)}
                  onBlur={field.onBlur}
                  name={field.name}
                  ref={field.ref}
                  error={errors.trial_days?.message}
                />
              )}
            />
          </div>
        ) : null}
      </div>

      <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
          <Link2 className="h-4 w-4 text-brand-600" />
          Affiliate Partner
        </div>
        {loadingAffiliates ? (
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
            Loading affiliates...
          </div>
        ) : (
          <Controller
            name="salon.affiliate_partner_id"
            control={control}
            render={({ field }) => (
              <BaseSelect
                id="salon-affiliate_partner_id"
                label="Attributed affiliate (optional)"
                placeholder="No affiliate"
                options={[
                  { value: '', label: 'No affiliate' },
                  ...affiliates.map((partner) => ({
                    value: String(partner.id),
                    label: `${partner.display_name || partner.user?.name || 'Partner'} · ${partner.code}`,
                  })),
                ]}
                value={field.value ? String(field.value) : ''}
                onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : null)}
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
                error={salonErrors.affiliate_partner_id?.message}
              />
            )}
          />
        )}
        <p className="mt-2 text-xs text-slate-500">
          When set, this salon is tracked under the affiliate and onboarding commission uses the payment amount.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <BaseInput
            id="salon-business-name"
            label="Business Name"
            required
            placeholder="e.g. Salon business name"
            prefix={Building2}
            error={salonErrors.business_name?.message}
            {...register('salon.business_name')}
          />
        </div>

        <div>
          <Controller
            name="salon.payment_type"
            control={control}
            render={({ field }) => (
              <BaseSelect
                id="salon-payment_type"
                label="Payment Type"
                required
                placeholder="Select payment type"
                options={PAYMENT_TYPES}
                error={salonErrors.payment_type?.message}
                value={field.value ?? ''}
                onChange={(e) => field.onChange(e.target.value || undefined)}
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            )}
          />
        </div>

        <div>
          <BaseInput
            id="salon-amount"
            label="Amount"
            required
            type="number"
            min="0"
            placeholder="1000"
            prefix={CreditCard}
            error={salonErrors.amount?.message}
            {...register('salon.amount')}
          />
        </div>

        <div>
          <BaseInput
            id="salon-transaction_id"
            label="Transaction ID"
            required
            placeholder="e.g. TXN-000000"
            prefix={Hash}
            error={salonErrors.transaction_id?.message}
            {...register('salon.transaction_id')}
          />
        </div>

        <div>
          <BaseInput
            id="salon-referral_code"
            label="Referral Code"
            placeholder="Optional referral code"
            prefix={Tag}
            error={salonErrors.referral_code?.message}
            {...register('salon.referral_code')}
          />
        </div>

        <div className="sm:col-span-2 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-700">Active Status</p>
            <p className="text-xs text-slate-500">Enable or disable this salon immediately upon creation</p>
          </div>
          <Controller
            name="salon.active"
            control={control}
            render={({ field }) => (
              <FormToggle
                id="salon-active"
                checked={field.value}
                onChange={field.onChange}
                label={field.value ? 'Active' : 'Inactive'}
              />
            )}
          />
        </div>
      </div>
    </div>
  )
}
