import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FormProvider, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { motion, AnimatePresence } from 'framer-motion'
import { AlertTriangle, CheckCircle2, ShieldAlert } from 'lucide-react'
import PageHeader from '../../../components/ui/PageHeader.jsx'
import BaseButton from '../../../components/ui/BaseButton.jsx'
import { useAuthStore } from '../../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../../lib/platformPermissions.js'
import {
  fetchOnboardingRecord,
  mapBackendValidationField,
  onboardingResponseToFormValues,
  updateOnboarding,
} from '../../../services/adminOnboardingService.js'
import { onboardingSchema } from './onboardingSchema.js'
import SalonInfoStep from './steps/SalonInfoStep.jsx'
import BranchInfoStep from './steps/BranchInfoStep.jsx'
import OwnerInfoStep from './steps/OwnerInfoStep.jsx'
import ServiceProductsStep from './steps/ServiceProductsStep.jsx'

const STEPS = [
  { id: 'salon', label: 'Salon Information', subtitle: 'Business details and payment info', component: SalonInfoStep },
  { id: 'branch', label: 'Branch Information', subtitle: 'Location and address', component: BranchInfoStep },
  { id: 'owner', label: 'Owner User', subtitle: 'Salon owner account', component: OwnerInfoStep },
  { id: 'service_products', label: 'Service Products', subtitle: 'Offerings for this salon', component: ServiceProductsStep, optional: true },
]

const STEP_FIELD_PATHS = {
  salon: [
    'salon.business_name',
    'salon.payment_type',
    'salon.amount',
    'salon.transaction_id',
    'salon.active',
    'salon.referral_code',
    'salon.affiliate_partner_id',
  ],
  branch: [
    'branch.name',
    'branch.address_line_1',
    'branch.address_line_2',
    'branch.city',
    'branch.state',
    'branch.pincode',
    'branch.country',
    'branch.active',
  ],
  owner: [
    'owner.first_name',
    'owner.last_name',
    'owner.email',
    'owner.phone',
    'owner.password',
    'owner.active',
  ],
}

const EMPTY_VALUES = onboardingResponseToFormValues({})

function StepIndicator({ steps, currentStepIndex }) {
  return (
    <ol className="flex items-center gap-1.5 sm:gap-2">
      {steps.map((step, index) => {
        const isComplete = index < currentStepIndex
        const isCurrent = index === currentStepIndex
        return (
          <li key={step.id} className="flex min-w-0 flex-1 items-center gap-1.5 sm:gap-2">
            <div className="flex min-w-0 flex-1 items-center gap-2">
              <span
                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors ${
                  isComplete
                    ? 'bg-brand-600 text-white'
                    : isCurrent
                      ? 'border-2 border-brand-600 bg-brand-50 text-brand-700'
                      : 'bg-slate-100 text-slate-500'
                }`}
              >
                {isComplete ? <CheckCircle2 className="h-4 w-4" /> : index + 1}
              </span>
              <div className="min-w-0 hidden sm:block">
                <p className={`truncate text-xs font-semibold ${isCurrent ? 'text-brand-700' : 'text-slate-700'}`}>
                  {step.label}
                </p>
                {step.optional && <p className="text-[10px] text-slate-400">Optional</p>}
              </div>
            </div>
            {index < steps.length - 1 && (
              <div className={`h-0.5 w-3 shrink-0 rounded-full sm:w-6 ${isComplete ? 'bg-brand-500' : 'bg-slate-200'}`} />
            )}
          </li>
        )
      })}
    </ol>
  )
}

export default function AdminSalonEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const auth = useAuthStore()
  const canUpdate = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING_UPDATE)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [globalError, setGlobalError] = useState('')
  const [currentStepIndex, setCurrentStepIndex] = useState(0)

  const methods = useForm({
    resolver: zodResolver(onboardingSchema),
    defaultValues: EMPTY_VALUES,
    mode: 'onTouched',
  })

  const {
    handleSubmit,
    setError: setFieldError,
    reset,
    getValues,
    trigger,
    formState: { errors },
  } = methods

  const currentStep = STEPS[currentStepIndex]
  const isFirstStep = currentStepIndex === 0
  const isLastStep = currentStepIndex === STEPS.length - 1
  const StepComponent = currentStep.component

  const stepHasError = useMemo(() => {
    const stepId = currentStep.id
    if (!errors[stepId]) return false
    return Object.keys(errors[stepId]).length > 0
  }, [currentStep.id, errors])

  useEffect(() => {
    let active = true

    async function load() {
      setLoading(true)
      setError('')
      try {
        const record = await fetchOnboardingRecord(id, auth.token)
        if (!active) return
        reset(onboardingResponseToFormValues(record))
      } catch (err) {
        if (!active) return
        setError(err?.response?.data?.message || 'Unable to load onboarding details.')
      } finally {
        if (active) setLoading(false)
      }
    }

    void load()
    return () => { active = false }
  }, [auth.token, id, reset])

  const applyBackendErrors = useCallback(
    (validationErrors) => {
      let firstKey = null
      for (const [key, messages] of Object.entries(validationErrors)) {
        const message = Array.isArray(messages) ? messages[0] : String(messages)
        const field = mapBackendValidationField(key)
        setFieldError(field, { type: 'server', message })
        if (!firstKey) firstKey = field
      }

      if (firstKey) {
        const sectionId = firstKey.split('.')[0]
        const stepIndex = STEPS.findIndex((step) => step.id === sectionId)
        if (stepIndex >= 0) setCurrentStepIndex(stepIndex)
      }
    },
    [setFieldError],
  )

  const validateCurrentStep = useCallback(async () => {
    const stepId = currentStep.id

    if (stepId === 'service_products') {
      const items = getValues('service_products') || []
      if (items.length === 0) return true

      const fields = items.flatMap((_, index) => [
        `service_products.${index}.service_id`,
        `service_products.${index}.service`,
        `service_products.${index}.product_id`,
        `service_products.${index}.product`,
        `service_products.${index}.price`,
        `service_products.${index}.duration`,
        `service_products.${index}.active`,
      ])

      return trigger(fields)
    }

    return trigger(STEP_FIELD_PATHS[stepId])
  }, [currentStep.id, getValues, trigger])

  const goBack = () => {
    if (submitting || isFirstStep) return
    setGlobalError('')
    setCurrentStepIndex((prev) => prev - 1)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const goNext = async () => {
    if (submitting) return
    setGlobalError('')

    const valid = await validateCurrentStep()
    if (!valid) {
      setGlobalError('Please fix the errors on this step before continuing.')
      return
    }

    setCurrentStepIndex((prev) => prev + 1)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const onSubmit = async (data) => {
    if (!canUpdate) return
    setGlobalError('')
    setSubmitting(true)

    const payload = {
      ...data,
      owner: {
        ...data.owner,
        password: data.owner.password || undefined,
      },
      service_products: (data.service_products || []).filter(
        (sp) => sp.service?.trim() || sp.service_id || sp.product?.trim() || sp.product_id,
      ),
    }

    try {
      await updateOnboarding(id, payload, auth.token)
      navigate('/admin/onboarding', { replace: true })
    } catch (err) {
      if (err.validationErrors) {
        applyBackendErrors(err.validationErrors)
        setGlobalError('Please fix the highlighted errors below.')
      } else {
        setGlobalError(err?.response?.data?.message || 'Unable to save onboarding changes.')
      }
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSubmitting(false)
    }
  }

  const handleFinalSubmit = async () => {
    if (!canUpdate) return
    const valid = await validateCurrentStep()
    if (!valid) {
      setGlobalError('Please fix the errors on this step before saving.')
      return
    }
    void handleSubmit(onSubmit)()
  }

  if (loading) {
    return <div className="py-16 text-center text-slate-400">Loading onboarding details...</div>
  }

  if (error) {
    return (
      <div className="space-y-4">
        <PageHeader
          title="Edit Salon Onboarding"
          breadcrumbs={[
            { label: 'Administration', to: '/admin/onboarding' },
            { label: 'Salon Onboarding', to: '/admin/onboarding' },
            { label: 'Edit' },
          ]}
        />
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
        <Link to="/admin/onboarding">
          <BaseButton variant="secondary">Back to list</BaseButton>
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Edit Salon Onboarding"
        subtitle="Review and update salon, branch, owner, and service product details."
        breadcrumbs={[
          { label: 'Administration', to: '/admin/onboarding' },
          { label: 'Salon Onboarding', to: '/admin/onboarding' },
          { label: 'Edit' },
        ]}
        actions={(
          <Link to="/admin/onboarding">
            <BaseButton variant="secondary" size="sm">Cancel</BaseButton>
          </Link>
        )}
      />
      {!canUpdate && (
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
          <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
          <p className="text-sm font-medium text-amber-800">This onboarding is read-only because you cannot update it.</p>
        </div>
      )}

      <div className="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div className="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
          <StepIndicator steps={STEPS} currentStepIndex={currentStepIndex} />
          <div className="mt-4 sm:hidden">
            <p className="text-sm font-semibold text-slate-900">
              Step {currentStepIndex + 1} of {STEPS.length}: {currentStep.label}
            </p>
            <p className="text-xs text-slate-500">{currentStep.subtitle}</p>
          </div>
        </div>

        <FormProvider {...methods}>
          <div className="p-6 sm:p-8">
            {globalError && (
              <motion.div
                initial={{ opacity: 0, y: -6 }}
                animate={{ opacity: 1, y: 0 }}
                className="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
              >
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <p className="text-sm text-amber-800">{globalError}</p>
              </motion.div>
            )}

            {stepHasError && !globalError && (
              <div className="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                Please fix the highlighted fields on this step.
              </div>
            )}

            <AnimatePresence mode="wait">
              <motion.div
                key={currentStep.id}
                initial={{ opacity: 0, x: 16 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -16 }}
                transition={{ duration: 0.2 }}
              >
                <fieldset disabled={!canUpdate}>
                  <StepComponent />
                </fieldset>
              </motion.div>
            </AnimatePresence>

            <div className="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-center text-xs text-slate-400 sm:text-left">
                Step {currentStepIndex + 1} of {STEPS.length}
                {currentStep.optional ? ' · This step is optional' : ''}
              </p>

              <div className="flex w-full flex-col-reverse gap-3 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                <BaseButton
                  type="button"
                  variant="secondary"
                  className="w-full sm:w-auto"
                  onClick={goBack}
                  disabled={isFirstStep || submitting}
                >
                  Back
                </BaseButton>

                {isLastStep ? (canUpdate ? (
                  <BaseButton
                    type="button"
                    variant="primary"
                    className="w-full sm:w-auto"
                    loading={submitting}
                    disabled={submitting}
                    onClick={handleFinalSubmit}
                  >
                    Save Changes
                  </BaseButton>
                ) : null) : (
                  <BaseButton
                    type="button"
                    variant="primary"
                    className="w-full sm:w-auto"
                    onClick={goNext}
                    disabled={submitting}
                  >
                    Next
                  </BaseButton>
                )}
              </div>
            </div>
          </div>
        </FormProvider>
      </div>
    </div>
  )
}
