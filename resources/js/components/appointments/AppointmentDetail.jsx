import React from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { X, Calendar, Clock, User, CheckCircle, Play, Ban, Phone, Package, Edit2, Trash2, MapPin, FileDown, Bell } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'
import BaseButton from '../ui/BaseButton.jsx'
import { useAppointmentStore } from '../../stores/appointmentStore'
import { appointmentToCalendarEvent, apiPut, apiDelete } from '../../lib/apiHelpers.js'
import { useAuthStore } from '../../stores/auth'
import { useTenantFormatter } from '../../hooks/useTenantFormatter.js'
import { downloadAppointmentReceipt, captureAppointmentPayment, refundAppointmentPayment, remindAppointmentPayment } from '../../services/appointmentService.js'
import {
  markDepositPaid,
  waiveDeposit,
  refundDeposit,
  applyNoShowFee,
} from '../../services/noShowPolicyService.js'
import PaymentCaptureModal from './PaymentCaptureModal.jsx'
import { PAYMENT_STATUS_LABELS, PAYMENT_STATUS_VARIANT, paymentMethodLabel, balanceDue } from '../../lib/appointmentPayments.js'
import { customerHasContactOnFile } from '../../lib/customerContact.js'
import { bookingSourceLabel, canStartService, mapAppointmentServicesForUpdate } from '../../lib/appointmentStatus.js'
import { pushToast } from '../../stores/toast.js'
import { TENANT_PERMISSIONS } from '../../lib/tenantPermissions.js'

const DEPOSIT_LABELS = {
  not_required: 'Not required',
  pending: 'Deposit due',
  paid: 'Deposit paid',
  waived: 'Deposit waived',
  refunded: 'Deposit refunded',
}

const FEE_LABELS = {
  'n/a': 'No fee',
  pending: 'Fee pending',
  charged: 'Fee charged',
  waived: 'Fee waived',
  failed: 'Fee failed',
}

export default function AppointmentDetail() {
  const queryClient = useQueryClient()
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canUpdate = auth.can('appointments.update')
  const canDelete = auth.can('appointments.delete')
  const canViewCustomers = auth.can('customers.view')
  const canChargeDeposit = auth.can(TENANT_PERMISSIONS.PAYMENTS_CHARGE)
  const canWaiveDeposit = auth.can(TENANT_PERMISSIONS.PAYMENTS_WAIVE)
  const canRefundDeposit = auth.can(TENANT_PERMISSIONS.PAYMENTS_REFUND)

  const event = useAppointmentStore(state => state.selectedEvent)
  const setSelectedEvent = useAppointmentStore(state => state.setSelectedEvent)
  const openEditModal = useAppointmentStore(state => state.openEditModal)

  const [localEvent, setLocalEvent] = React.useState(event)
  const [confirmDelete, setConfirmDelete] = React.useState(false)
  const [downloadingReceipt, setDownloadingReceipt] = React.useState(false)
  const [paymentModalOpen, setPaymentModalOpen] = React.useState(false)
  const [paymentSaving, setPaymentSaving] = React.useState(false)
  const [confirmRefund, setConfirmRefund] = React.useState(false)
  const [remindingPayment, setRemindingPayment] = React.useState(false)
  const [depositBusy, setDepositBusy] = React.useState(false)

  React.useEffect(() => {
    if (event) setLocalEvent(event)
    setConfirmDelete(false)
  }, [event])

  const displayMoney = (value) => {
    if (value == null || value === '') return '—'
    if (typeof value === 'number' || (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(String(value).trim()))) {
      return fmt.money(value)
    }
    return String(value)
  }

  const activeEvent = event || localEvent

  const invalidateVisitQueries = () => {
    queryClient.invalidateQueries({ queryKey: ['calendar-events'] })
    queryClient.invalidateQueries({ queryKey: ['appointments-list'] })
    queryClient.invalidateQueries({ queryKey: ['pos-today'] })
    queryClient.invalidateQueries({ queryKey: ['outstanding-payments'] })
  }

  const updateStatusMutation = useMutation({
    mutationFn: async ({ id, status }) => {
      const raw = activeEvent?.extendedProps?.raw || {}
      const services = mapAppointmentServicesForUpdate(raw)

      const products = (raw.products ?? []).map(line => ({
        product_id: line.product_id,
        quantity: line.quantity ?? 1,
        unit_price: line.unit_price ?? null,
        staff_id: line.staff_id ?? null,
      }))

      const response = await apiPut(`/v1/appointments/${id}`, {
        branch_id: raw.branch_id ?? null,
        customer_id: raw.customer_id ?? null,
        type: raw.type || 'appointment',
        starts_at: raw.starts_at || activeEvent.startStr || activeEvent.start?.toISOString(),
        status,
        discount: raw.discount ?? 0,
        notes: raw.notes ?? null,
        services,
        products,
      })
      return { id, status, appointment: response?.data?.data?.appointment ?? response?.data?.appointment ?? null }
    },
    onSuccess: ({ status, appointment }) => {
      invalidateVisitQueries()
      if (status === 'no-show' && appointment) {
        const mapped = appointmentToCalendarEvent(appointment)
        setSelectedEvent(mapped)
        setLocalEvent(mapped)
        const fee = Number(appointment.no_show_fee_amount || 0)
        const feeStatus = appointment.no_show_fee_status || 'n/a'
        if (fee > 0 && feeStatus === 'pending') {
          pushToast(`Marked no-show. Fee pending: ${fmt.money(fee)}.`, 'warning')
          return
        }
        pushToast('Marked as no-show.', 'success')
        return
      }
      setSelectedEvent(null)
    },
    onError: (error) => {
      pushToast(error?.response?.data?.message || 'Unable to update appointment.', 'error')
    },
  })

  const applyAppointmentUpdate = (appointment) => {
    if (!appointment) return
    const mapped = appointmentToCalendarEvent(appointment)
    setSelectedEvent(mapped)
    setLocalEvent(mapped)
    invalidateVisitQueries()
  }

  const runDepositAction = async (action) => {
    if (!activeEvent?.id) return
    setDepositBusy(true)
    try {
      let appointment = null
      if (action === 'paid') appointment = await markDepositPaid(activeEvent.id)
      if (action === 'waive') appointment = await waiveDeposit(activeEvent.id)
      if (action === 'refund') appointment = await refundDeposit(activeEvent.id)
      applyAppointmentUpdate(appointment)
      pushToast(
        action === 'paid' ? 'Deposit marked as paid.' : action === 'waive' ? 'Deposit waived.' : 'Deposit refunded.',
        'success',
      )
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to update deposit.', 'error')
    } finally {
      setDepositBusy(false)
    }
  }

  const runNoShowFeeAction = async (action) => {
    if (!activeEvent?.id) return
    setDepositBusy(true)
    try {
      const appointment = await applyNoShowFee(activeEvent.id, { action })
      applyAppointmentUpdate(appointment)
      pushToast(action === 'charged' ? 'No-show fee charged.' : 'No-show fee waived.', 'success')
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to update no-show fee.', 'error')
    } finally {
      setDepositBusy(false)
    }
  }

  const deleteMutation = useMutation({
    mutationFn: async (id) => apiDelete(`/v1/appointments/${id}`),
    onSuccess: () => {
      invalidateVisitQueries()
      setSelectedEvent(null)
    },
  })

  const handleAction = (status) => {
    if (!activeEvent) return
    updateStatusMutation.mutate({ id: activeEvent.id, status })
  }

  if (!activeEvent) return null

  const props = activeEvent.extendedProps || {}
  const raw = props.raw || {}
  const customerName = typeof props.customer === 'string'
    ? props.customer
    : (props.customer?.name || raw.customer?.name || '')
  const customerId = props.customerId ?? raw.customer_id ?? raw.customer?.id ?? null
  const paymentStatus = props.paymentStatus || raw.payment_status || 'unpaid'
  const paymentMethod = props.paymentMethod || raw.payment_method || ''
  const startsAt = raw.starts_at || activeEvent.startStr || activeEvent.start?.toISOString?.()
  const mayStartService = canStartService(startsAt)
  const sourceLabel = bookingSourceLabel(raw.booking_source || props.bookingSource)
  const amountPaid = props.amountPaid ?? raw.amount_paid ?? 0
  const dueBalance = props.balanceDue ?? balanceDue(raw)
  const depositStatus = raw.deposit_status || 'not_required'
  const depositAmount = Number(raw.deposit_required_amount || 0)
  const feeStatus = raw.no_show_fee_status || 'n/a'
  const feeAmount = Number(raw.no_show_fee_amount || 0)
  const showDepositCard = depositStatus !== 'not_required' && depositAmount > 0
  const showFeeCard = props.status === 'no-show' && feeStatus !== 'n/a' && feeAmount > 0

  const statusColors = {
    scheduled: 'info',
    confirmed: 'success',
    'in-progress': 'info',
    completed: 'default',
    cancelled: 'danger',
    'no-show': 'danger',
  }

  const handleDownloadReceipt = async () => {
    if (!activeEvent?.id) return
    setDownloadingReceipt(true)
    try {
      await downloadAppointmentReceipt(activeEvent.id)
      pushToast('Receipt downloaded.', 'success')
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to download receipt.', 'error')
    } finally {
      setDownloadingReceipt(false)
    }
  }

  const handleCapturePayment = async (payload) => {
    setPaymentSaving(true)
    try {
      await captureAppointmentPayment(activeEvent.id, payload)
      invalidateVisitQueries()
      setSelectedEvent(null)
      pushToast('Payment recorded.', 'success')
    } finally {
      setPaymentSaving(false)
    }
  }

  const handleRemindPayment = async () => {
    if (!activeEvent?.id) return
    setRemindingPayment(true)
    try {
      await remindAppointmentPayment(activeEvent.id)
      invalidateVisitQueries()
      pushToast('Payment reminder sent.', 'success')
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to send a payment reminder.', 'error')
    } finally {
      setRemindingPayment(false)
    }
  }

  const handleRefundPayment = async () => {
    setPaymentSaving(true)
    try {
      await refundAppointmentPayment(activeEvent.id)
      invalidateVisitQueries()
      setConfirmRefund(false)
      setSelectedEvent(null)
      pushToast('Payment refunded.', 'success')
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to refund payment.', 'error')
    } finally {
      setPaymentSaving(false)
    }
  }

  const isPending = updateStatusMutation.isPending || deleteMutation.isPending || depositBusy

  const renderDetail = () => (
    <>
      <div className="relative flex items-center justify-between p-4 border-b border-slate-100 bg-slate-50/50 shrink-0">
        <div className="absolute left-1/2 top-2 h-1 w-10 -translate-x-1/2 rounded-full bg-slate-200 xl:hidden" aria-hidden />
        <h3 className="font-bold text-slate-900">Appointment Details</h3>
        <button
          type="button"
          onClick={() => setSelectedEvent(null)}
          className="p-2 rounded-lg text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors touch-manipulation"
        >
          <X className="w-4 h-4" />
        </button>
      </div>

      <div
        className="flex-1 overflow-y-auto overscroll-contain scroll-touch p-4 sm:p-6 space-y-5 relative min-h-0 touch-pan-y"
        onWheel={(e) => e.stopPropagation()}
      >
        {isPending && (
          <div className="absolute inset-0 bg-white/60 backdrop-blur-[2px] z-10 flex items-center justify-center">
            <div className="flex flex-col items-center gap-3">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-600"></div>
              <span className="text-sm font-medium text-teal-700">Updating...</span>
            </div>
          </div>
        )}

        <div>
          <div className="flex items-center justify-between mb-2 gap-2">
            <BaseBadge variant={statusColors[props.status] || 'default'}>
              {props.status?.replace('-', ' ') || 'Unknown'}
            </BaseBadge>
            <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider bg-slate-100 px-2 py-1 rounded-md">#{activeEvent.id}</span>
          </div>
          <div className="flex items-center gap-2 flex-wrap mb-1">
            {props.type === 'walk_in' && <BaseBadge variant="info">Walk-in</BaseBadge>}
            {props.type === 'product_sale' && <BaseBadge variant="success">Product Sale</BaseBadge>}
            {sourceLabel ? <BaseBadge variant="info">{sourceLabel}</BaseBadge> : null}
          </div>
          <h2 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
            {(Array.isArray(props.services) && props.services.length > 1)
              ? `${props.services.length} services`
              : (props.service || activeEvent.title)}
          </h2>
          {props.product && <p className="text-sm text-slate-500 mt-1 flex items-center gap-1.5"><Package className="w-3.5 h-3.5" /> {props.product}</p>}
        </div>

        {Array.isArray(props.services) && props.services.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider">Services</h4>
            {props.services.map((line, idx) => (
              <div key={line.id || idx} className="flex items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                <div className="min-w-0">
                  <p className="font-semibold text-slate-800 truncate">{line.service?.name || 'Service'}</p>
                  <p className="text-xs text-slate-500 truncate">
                    {line.staff?.name || 'Any staff'}
                    {line.is_staff_locked ? ' · preferred' : ''}
                    {line.product?.name ? ` · ${line.product.name}` : ''}
                  </p>
                </div>
                <span className="font-semibold text-slate-700 shrink-0">{fmt.money(line.price || 0)}</span>
              </div>
            ))}
          </div>
        )}

        {Array.isArray(props.products) && props.products.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider">Products Sold</h4>
            {props.products.map((line, idx) => (
              <div key={line.id || idx} className="flex items-center justify-between gap-2 rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-sm">
                <div className="min-w-0">
                  <p className="font-semibold text-slate-800 truncate flex items-center gap-1.5">
                    <Package className="w-3.5 h-3.5 text-emerald-600" />
                    {line.product?.name || 'Product'}
                  </p>
                  <p className="text-xs text-slate-500 truncate">
                    {line.quantity} × {fmt.money(line.unit_price || 0)}
                    {line.staff?.name ? ` · sold by ${line.staff.name}` : ''}
                  </p>
                </div>
                <span className="font-semibold text-slate-700 shrink-0">{fmt.money(line.line_total || 0)}</span>
              </div>
            ))}
          </div>
        )}

        <div className="p-4 rounded-xl border border-slate-100 bg-slate-50 space-y-3">
          <div className="flex items-center gap-3">
            <div className="h-12 w-12 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 text-white flex items-center justify-center font-bold text-lg ring-2 ring-white">
              {(customerName || 'C').charAt(0)}
            </div>
            <div className="min-w-0">
              <p className="text-sm font-bold text-slate-900 truncate">{customerName || 'Walk-in'}</p>
              {props.customerPhone && (
                <p className="text-xs text-slate-500 font-medium flex items-center gap-1"><Phone className="w-3 h-3" /> {props.customerPhone}</p>
              )}
              {canViewCustomers && customerId && (
                <Link
                  to={`/customers?id=${customerId}`}
                  className="inline-flex items-center gap-1 mt-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700"
                  onClick={() => setSelectedEvent(null)}
                >
                  View customer profile
                </Link>
              )}
            </div>
          </div>
        </div>

        <div className="space-y-4 bg-white p-4 rounded-xl border border-slate-100">
          <div className="flex items-start gap-4">
            <div className="p-2 bg-slate-50 rounded-lg text-slate-400"><Calendar className="w-5 h-5" /></div>
            <div>
              <p className="text-sm font-bold text-slate-900">{activeEvent.start ? activeEvent.start.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }) : 'N/A'}</p>
              <p className="text-xs font-medium text-slate-500 mt-0.5">Date</p>
            </div>
          </div>
          <div className="flex items-start gap-4">
            <div className="p-2 bg-slate-50 rounded-lg text-slate-400"><Clock className="w-5 h-5" /></div>
            <div>
              <p className="text-sm font-bold text-slate-900">
                {activeEvent.start ? activeEvent.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''} - {activeEvent.end ? activeEvent.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
              </p>
              <p className="text-xs font-medium text-slate-500 mt-0.5">Duration: {props.duration || 'N/A'}</p>
            </div>
          </div>
          <div className="flex items-start gap-4">
            <div className="p-2 bg-slate-50 rounded-lg text-slate-400"><User className="w-5 h-5" /></div>
            <div>
              <p className="text-sm font-bold text-slate-900">{props.staffName || 'Any Staff'}</p>
              <p className="text-xs font-medium text-slate-500 mt-0.5">Assigned Professional</p>
            </div>
          </div>
          {props.branchName && (
            <div className="flex items-start gap-4">
              <div className="p-2 bg-slate-50 rounded-lg text-slate-400"><MapPin className="w-5 h-5" /></div>
              <div>
                <p className="text-sm font-bold text-slate-900">{props.branchName}</p>
                <p className="text-xs font-medium text-slate-500 mt-0.5">Branch</p>
              </div>
            </div>
          )}
        </div>

        <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-4 text-sm">
          <div className="flex items-center justify-between gap-3">
            <span className="text-slate-500">Price</span>
            <span className="font-semibold tabular-nums text-slate-800">{displayMoney(props.price)}</span>
          </div>
          <div className="mt-2.5 flex items-center justify-between gap-3">
            <span className="text-slate-500">Discount</span>
            <span className="font-semibold tabular-nums text-slate-800">{fmt.money(props.discount || 0)}</span>
          </div>
          <div className="mt-3 flex items-center justify-between gap-3 border-t border-slate-200 pt-3">
            <span className="font-bold text-slate-800">Grand Total</span>
            <span className="text-base font-black tabular-nums text-brand-700">{displayMoney(props.grandTotal ?? props.price)}</span>
          </div>
          <div className="mt-2.5 flex items-center justify-between gap-3">
            <span className="text-slate-500">Collected</span>
            <span className="font-semibold tabular-nums text-emerald-700">{fmt.money(amountPaid)}</span>
          </div>
          {dueBalance > 0 && (
            <div className="mt-2 flex items-center justify-between gap-3">
              <span className="text-slate-500">Balance due</span>
              <span className="font-semibold tabular-nums text-amber-700">{fmt.money(dueBalance)}</span>
            </div>
          )}
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <BaseBadge variant={PAYMENT_STATUS_VARIANT[paymentStatus] || 'default'}>
              {PAYMENT_STATUS_LABELS[paymentStatus] || paymentStatus}
            </BaseBadge>
            {paymentMethod && (
              <span className="text-xs font-semibold text-slate-500">{paymentMethodLabel(paymentMethod)}</span>
            )}
          </div>
        </div>

        {showDepositCard ? (
          <div className="rounded-xl border border-amber-100 bg-amber-50/70 p-4 text-sm">
            <div className="flex items-center justify-between gap-3">
              <span className="font-semibold text-amber-900">Deposit</span>
              <BaseBadge variant={depositStatus === 'paid' ? 'success' : depositStatus === 'pending' ? 'warning' : 'default'}>
                {DEPOSIT_LABELS[depositStatus] || depositStatus}
              </BaseBadge>
            </div>
            <div className="mt-2 flex items-center justify-between gap-3">
              <span className="text-amber-800/80">Amount</span>
              <span className="font-bold tabular-nums text-amber-950">{fmt.money(depositAmount)}</span>
            </div>
            {(canChargeDeposit || canWaiveDeposit || canRefundDeposit) ? (
              <div className="mt-3 grid grid-cols-1 gap-2">
                {canChargeDeposit && depositStatus === 'pending' ? (
                  <BaseButton size="sm" variant="primary" loading={depositBusy} disabled={isPending} onClick={() => void runDepositAction('paid')}>
                    Mark deposit paid
                  </BaseButton>
                ) : null}
                {canWaiveDeposit && (depositStatus === 'pending' || depositStatus === 'paid') ? (
                  <BaseButton size="sm" variant="secondary" loading={depositBusy} disabled={isPending} onClick={() => void runDepositAction('waive')}>
                    Waive deposit
                  </BaseButton>
                ) : null}
                {canRefundDeposit && depositStatus === 'paid' ? (
                  <BaseButton size="sm" variant="ghost" className="text-rose-700 bg-rose-50 hover:bg-rose-100" loading={depositBusy} disabled={isPending} onClick={() => void runDepositAction('refund')}>
                    Refund deposit
                  </BaseButton>
                ) : null}
              </div>
            ) : null}
          </div>
        ) : null}

        {showFeeCard ? (
          <div className="rounded-xl border border-rose-100 bg-rose-50/70 p-4 text-sm">
            <div className="flex items-center justify-between gap-3">
              <span className="font-semibold text-rose-900">No-show fee</span>
              <BaseBadge variant={feeStatus === 'charged' ? 'success' : feeStatus === 'pending' ? 'warning' : 'default'}>
                {FEE_LABELS[feeStatus] || feeStatus}
              </BaseBadge>
            </div>
            <div className="mt-2 flex items-center justify-between gap-3">
              <span className="text-rose-800/80">Amount</span>
              <span className="font-bold tabular-nums text-rose-950">{fmt.money(feeAmount)}</span>
            </div>
            {feeStatus === 'pending' && (canChargeDeposit || canWaiveDeposit) ? (
              <div className="mt-3 grid grid-cols-1 gap-2">
                {canChargeDeposit ? (
                  <BaseButton size="sm" variant="primary" loading={depositBusy} disabled={isPending} onClick={() => void runNoShowFeeAction('charged')}>
                    Charge no-show fee
                  </BaseButton>
                ) : null}
                {canWaiveDeposit ? (
                  <BaseButton size="sm" variant="secondary" loading={depositBusy} disabled={isPending} onClick={() => void runNoShowFeeAction('waived')}>
                    Waive fee
                  </BaseButton>
                ) : null}
              </div>
            ) : null}
          </div>
        ) : null}

        {props.notes && (
          <div>
            <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Notes</h4>
            <div className="p-3 bg-amber-50/80 border border-amber-100 rounded-xl text-sm font-medium text-amber-800">
              {props.notes}
            </div>
          </div>
        )}
      </div>

      <div className="p-4 border-t border-slate-100 bg-slate-50/50 shrink-0 space-y-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
        {!canUpdate && !canDelete ? (
          <p className="text-center text-xs text-slate-500">
            View-only mode — renew your subscription to update appointments.
          </p>
        ) : null}
        {canUpdate && (
          <div className="grid grid-cols-2 gap-3">
            {props.status === 'confirmed' && (
              <>
                {mayStartService ? (
                  <BaseButton variant="primary" leftIcon={Play} className="col-span-2" onClick={() => handleAction('in-progress')} loading={isPending}>Start Service</BaseButton>
                ) : (
                  <p className="col-span-2 rounded-xl bg-slate-100 px-3 py-2 text-center text-xs text-slate-600">
                    Service can be started closer to the appointment time.
                  </p>
                )}
                <BaseButton variant="ghost" leftIcon={Ban} className="bg-slate-200/50 hover:bg-slate-200 text-slate-700" onClick={() => handleAction('no-show')} disabled={isPending}>No-Show</BaseButton>
                <BaseButton variant="ghost" leftIcon={X} className="text-rose-600 bg-rose-50 hover:bg-rose-100" onClick={() => handleAction('cancelled')} disabled={isPending}>Cancel</BaseButton>
              </>
            )}
            {props.status === 'scheduled' && (
              <>
                <BaseButton variant="primary" leftIcon={CheckCircle} className="col-span-2" onClick={() => handleAction('confirmed')} loading={isPending}>Confirm</BaseButton>
                {mayStartService ? (
                  <BaseButton variant="secondary" leftIcon={Play} className="col-span-2" onClick={() => handleAction('in-progress')} disabled={isPending}>Start Now</BaseButton>
                ) : null}
              </>
            )}
            {props.status === 'in-progress' && (
              <BaseButton variant="primary" leftIcon={CheckCircle} className="col-span-2 bg-emerald-500 hover:bg-emerald-600 ring-emerald-500" onClick={() => handleAction('completed')} loading={isPending}>Complete Service</BaseButton>
            )}
          </div>
        )}

        <div className="grid grid-cols-2 gap-3">
          <BaseButton
            variant="secondary"
            leftIcon={FileDown}
            className="col-span-2"
            onClick={handleDownloadReceipt}
            loading={downloadingReceipt}
            disabled={isPending}
          >
            Download Receipt
          </BaseButton>
          {canUpdate && dueBalance > 0 && paymentStatus !== 'refunded' && (
            <BaseButton
              variant="primary"
              className="col-span-2"
              onClick={() => setPaymentModalOpen(true)}
              disabled={isPending}
            >
              Collect pending payment
            </BaseButton>
          )}
          {canUpdate && dueBalance > 0 && paymentStatus !== 'refunded' && (customerHasContactOnFile(raw.customer) || props.customerHasContact || props.customerPhone) && (
            <BaseButton
              variant="secondary"
              leftIcon={Bell}
              className="col-span-2"
              onClick={handleRemindPayment}
              loading={remindingPayment}
              disabled={isPending}
            >
              Send payment reminder
            </BaseButton>
          )}
          {canUpdate && amountPaid > 0 && paymentStatus !== 'refunded' && (
            confirmRefund ? (
              <BaseButton
                variant="danger"
                className="col-span-2"
                onClick={handleRefundPayment}
                loading={paymentSaving}
                disabled={isPending}
              >
                Confirm refund?
              </BaseButton>
            ) : (
              <BaseButton
                variant="ghost"
                className="col-span-2 text-rose-600 bg-rose-50 hover:bg-rose-100"
                onClick={() => setConfirmRefund(true)}
                disabled={isPending || paymentSaving}
              >
                Refund payment
              </BaseButton>
            )
          )}
          {canUpdate && (
            <BaseButton variant="secondary" leftIcon={Edit2} onClick={() => openEditModal(raw)} disabled={isPending}>Edit</BaseButton>
          )}
          {canDelete && (
            confirmDelete ? (
              <BaseButton variant="danger" leftIcon={Trash2} onClick={() => deleteMutation.mutate(activeEvent.id)} loading={deleteMutation.isPending}>Confirm?</BaseButton>
            ) : (
              <BaseButton variant="ghost" leftIcon={Trash2} className="text-rose-600 bg-rose-50 hover:bg-rose-100" onClick={() => setConfirmDelete(true)} disabled={isPending}>Delete</BaseButton>
            )
          )}
        </div>
      </div>
    </>
  )

  return (
    <>
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        className="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[2px]"
        onClick={() => setSelectedEvent(null)}
      />
      <motion.div
        initial={{ y: '100%' }}
        animate={{ y: 0 }}
        exit={{ y: '100%' }}
        transition={{ type: 'spring', stiffness: 320, damping: 32 }}
        className="fixed inset-x-0 bottom-0 z-50 flex max-h-[88dvh] flex-col overflow-hidden rounded-t-3xl border border-slate-200 bg-white shadow-2xl xl:hidden"
      >
        {renderDetail()}
      </motion.div>

      <motion.div
        initial={{ opacity: 0, x: 24 }}
        animate={{ opacity: 1, x: 0 }}
        exit={{ opacity: 0, x: 24 }}
        transition={{ duration: 0.22, ease: 'easeOut' }}
        className="fixed right-0 top-0 z-50 hidden h-dvh w-full max-w-md flex-col overflow-hidden border-l border-slate-200 bg-white shadow-2xl xl:flex"
      >
        {renderDetail()}
      </motion.div>

      <PaymentCaptureModal
        open={paymentModalOpen}
        appointment={raw}
        onClose={() => setPaymentModalOpen(false)}
        saving={paymentSaving}
        onSubmit={handleCapturePayment}
      />
    </>
  )
}
