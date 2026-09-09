import React, { useCallback, useMemo, useState } from 'react'
import { FormProvider, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { motion, AnimatePresence } from 'framer-motion'
import { AlertTriangle, CheckCircle2, ShieldAlert } from 'lucide-react'
import PageHeader from '../../../components/ui/PageHeader.jsx'
import { useAuthStore } from '../../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../../lib/platformPermissions.js'
import { mapBackendValidationField, submitOnboarding } from '../../../services/adminOnboardingService.js'
import { onboardingSchema } from './onboardingSchema.js'
import SalonInfoStep from './steps/SalonInfoStep.jsx'
import BranchInfoStep from './steps/BranchInfoStep.jsx'
import OwnerInfoStep from './steps/OwnerInfoStep.jsx'
import ServiceProductsStep from './steps/ServiceProductsStep.jsx'
import OnboardingSuccessScreen from './OnboardingSuccessScreen.jsx'
import BaseButton from '../../../components/ui/BaseButton.jsx'

const STEPS = [
  {
    id: 'salon',
    label: 'Salon Information',
    subtitle: 'Business details and payment info',
    component: SalonInfoStep,
  },
  {
    id: 'branch',
    label: 'Branch Information',
    subtitle: 'Location and address',
    component: BranchInfoStep,
  },
  {
    id: 'owner',
    label: 'Owner User',
    subtitle: 'Salon owner account',
    component: OwnerInfoStep,
  },
  {
    id: 'service_products',
    label: 'Service Products',
    subtitle: 'Optional offerings for this salon',
    component: ServiceProductsStep,
    optional: true,
  },
]

const STEP_FIELD_PATHS = {
  salon: [
    'subscription_plan_id',
    'start_trial',
    'trial_days',
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

const DEFAULT_VALUES = {
  subscription_plan_id: '',
  start_trial: true,
  trial_days: 15,
  salon: {
    business_name: '',
    payment_type: undefined,
    amount: '',
    transaction_id: '',
    active: true,
    referral_code: '',
    affiliate_partner_id: null,
  },
  branch: {
    name: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    pincode: '',
    country: 'India',
    active: true,
  },
  owner: {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    active: true,
  },
  service_products: [],
}

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
                {step.optional && (
                  <p className="text-[10px] text-slate-400">Optional</p>
                )}
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

function OnboardingReviewSummary({ values }) {
  const serviceCount = (values.service_products || []).filter(
    (row) => row.service?.trim() || row.service_id || row.product?.trim() || row.product_id,
  ).length

  return (
    <div className="rounded-2xl border border-brand-100 bg-brand-50/50 p-5 space-y-3">
      <p className="text-sm font-semibold text-brand-900">Review before submitting</p>
      <dl className="grid gap-2 sm:grid-cols-2 text-sm">
        <div>
          <dt className="text-slate-500">Salon</dt>
          <dd className="font-medium text-slate-900">{values.salon?.business_name || '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">Branch</dt>
          <dd className="font-medium text-slate-900">{values.branch?.name || '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">Owner</dt>
          <dd className="font-medium text-slate-900">
            {[values.owner?.first_name, values.owner?.last_name].filter(Boolean).join(' ') || '—'}
          </dd>
        </div>
        <div>
          <dt className="text-slate-500">Owner email</dt>
          <dd className="font-medium text-slate-900">{values.owner?.email || '—'}</dd>
        </div>
        <div>
          <dt className="text-slate-500">Affiliate</dt>
          <dd className="font-medium text-slate-900">
            {values.salon?.affiliate_partner_id ? `Partner #${values.salon.affiliate_partner_id}` : 'None'}
          </dd>
        </div>
        <div className="sm:col-span-2">
          <dt className="text-slate-500">Service products</dt>
          <dd className="font-medium text-slate-900">
            {serviceCount > 0 ? `${serviceCount} configured` : 'None (optional)'}
          </dd>
        </div>
      </dl>
    </div>
  )
}

export default function AdminOnboardingPage() {
  const auth = useAuthStore()
  const canCreate = auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING_CREATE)
  const [currentStepIndex, setCurrentStepIndex] = useState(0)
  const [submitting, setSubmitting] = useState(false)
  const [globalError, setGlobalError] = useState('')
  const [forbidden, setForbidden] = useState(false)
  const [successData, setSuccessData] = useState(null)

  const methods = useForm({
    resolver: zodResolver(onboardingSchema),
    defaultValues: DEFAULT_VALUES,
    mode: 'onTouched',
  })

  const {
    handleSubmit,
    setError,
    reset,
    getValues,
    trigger,
    watch,
    formState: { errors },
  } = methods

  const currentStep = STEPS[currentStepIndex]
  const isFirstStep = currentStepIndex === 0
  const isLastStep = currentStepIndex === STEPS.length - 1
  const StepComponent = currentStep.component
  const formValues = watch()

  const stepHasError = useMemo(() => {
    const stepId = currentStep.id
    if (!errors[stepId]) return false
    return Object.keys(errors[stepId]).length > 0
  }, [currentStep.id, errors])

  const applyBackendErrors = useCallback(
    (validationErrors) => {
      let firstKey = null
      for (const [key, messages] of Object.entries(validationErrors)) {
        const message = Array.isArray(messages) ? messages[0] : String(messages)
        const field = mapBackendValidationField(key)
        setError(field, { type: 'server', message })
        if (!firstKey) firstKey = field
      }

      if (firstKey) {
        const sectionId = firstKey.split('.')[0]
        const stepIndex = STEPS.findIndex((step) => step.id === sectionId)
        if (stepIndex >= 0) setCurrentStepIndex(stepIndex)
      }
    },
    [setError],
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
    if (!canCreate) return
    setGlobalError('')
    setForbidden(false)

    if (!auth.token) {
      setGlobalError('Authentication token is missing. Please log in again.')
      window.scrollTo({ top: 0, behavior: 'smooth' })
      return
    }

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
      const response = await submitOnboarding(payload, auth.token)
      setSuccessData(response)
    } catch (err) {
      if (err.forbidden) {
        setForbidden(true)
        window.scrollTo({ top: 0, behavior: 'smooth' })
        return
      }
      if (err.validationErrors) {
        applyBackendErrors(err.validationErrors)
        setGlobalError('Please fix the highlighted errors below.')
        window.scrollTo({ top: 0, behavior: 'smooth' })
        return
      }
      if (!err?.response) {
        setGlobalError('Unable to connect to the server. Please check your connection and try again.')
      } else {
        setGlobalError(
          err?.response?.data?.message ||
            'Something went wrong. Please try again.',
        )
      }
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSubmitting(false)
    }
  }

  const handleFinalSubmit = async () => {
    if (!canCreate) return
    const valid = await validateCurrentStep()
    if (!valid) {
      setGlobalError('Please fix the errors on this step before submitting.')
      return
    }
    void handleSubmit(onSubmit)()
  }

  const handleCreateAnother = useCallback(() => {
    if (!canCreate) return
    reset(DEFAULT_VALUES)
    setSuccessData(null)
    setGlobalError('')
    setForbidden(false)
    setCurrentStepIndex(0)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }, [canCreate, reset])

  return (
    <div className="space-y-6">
      <PageHeader
        title="New Salon Onboarding"
        subtitle="Complete each step to register a new salon, branch, and owner."
        breadcrumbs={[
          { label: 'Administration', to: '/admin/onboarding' },
          { label: 'Salon Onboarding', to: '/admin/onboarding' },
          { label: 'New' },
        ]}
      />

      {forbidden && (
        <motion.div
          initial={{ opacity: 0, y: -8 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4"
        >
          <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-rose-500" />
          <p className="text-sm font-medium text-rose-700">
            You do not have permission to perform onboarding.
          </p>
        </motion.div>
      )}
      {!canCreate && (
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
          <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
          <p className="text-sm font-medium text-amber-800">You can review this form, but you cannot create salon onboardings.</p>
        </div>
      )}

      <AnimatePresence mode="wait">
        {successData ? (
          <motion.div
            key="success"
            initial={{ opacity: 0, scale: 0.97 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0 }}
            className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10"
          >
            <OnboardingSuccessScreen
              data={successData}
              onCreateAnother={handleCreateAnother}
            />
          </motion.div>
        ) : (
          <motion.div
            key="form"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden"
          >
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
                    <fieldset disabled={!canCreate}>
                      <StepComponent />
                    </fieldset>
                  </motion.div>
                </AnimatePresence>

                {isLastStep && (
                  <div className="mt-6">
                    <OnboardingReviewSummary values={formValues} />
                  </div>
                )}

                <div className="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                  <p className="text-center text-xs text-slate-400 sm:text-left">
                    Step {currentStepIndex + 1} of {STEPS.length}
                    {currentStep.optional ? ' · This step is optional' : ''}
                    {!currentStep.optional && (
                      <> · Fields marked <span className="text-rose-400">*</span> are required</>
                    )}
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

                    {isLastStep ? (canCreate ? (
                      <BaseButton
                        type="button"
                        variant="primary"
                        className="w-full sm:w-auto"
                        loading={submitting}
                        disabled={submitting}
                        onClick={handleFinalSubmit}
                      >
                        {submitting ? 'Submitting…' : (
                          <>
                            <span className="sm:hidden">Confirm & Submit</span>
                            <span className="hidden sm:inline">Confirm & Submit Onboarding</span>
                          </>
                        )}
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
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}
