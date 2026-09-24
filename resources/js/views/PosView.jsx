import React, { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { format } from 'date-fns'
import {
  Plus, ShoppingBag, Clock, User, Scissors, RefreshCw, CalendarDays, FileDown, CreditCard,
} from 'lucide-react'
import { Link } from 'react-router-dom'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import BookingModal from '../components/appointments/BookingModal.jsx'
import PaymentCaptureModal from '../components/appointments/PaymentCaptureModal.jsx'
import SubscriptionAccessFallback from '../components/subscription/SubscriptionAccessFallback.jsx'
import { useAppointmentStore } from '../stores/appointmentStore'
import { useAuthStore } from '../stores/auth'
import { fetchMasterList } from '../lib/apiHelpers'
import { isForbiddenError } from '../lib/apiErrors.js'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { downloadAppointmentReceipt, captureAppointmentPayment } from '../services/appointmentService.js'
import { markDepositPaid } from '../services/noShowPolicyService.js'
import { balanceDue, PAYMENT_STATUS_LABELS } from '../lib/appointmentPayments.js'
import { customerPhoneDisplay } from '../lib/customerContact.js'
import { pushToast } from '../stores/toast.js'
import { ROLE_CODES } from '../lib/roleDisplay'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'

const STATUS_BADGE = {
  scheduled: 'info',
  confirmed: 'success',
  'in-progress': 'info',
  completed: 'default',
  cancelled: 'danger',
  'no-show': 'danger',
}

export default function PosView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const openWalkInModal = useAppointmentStore((state) => state.openWalkInModal)
  const openEditModal = useAppointmentStore((state) => state.openEditModal)
  const canCreate = auth.can('appointments.create')
  const canUpdate = auth.can('appointments.update')
  const canView = auth.can('appointments.view')
  const canViewCustomers = auth.can('customers.view')
  const canChargeDeposit = auth.can(TENANT_PERMISSIONS.PAYMENTS_CHARGE)
  const isStaff = auth.role?.code === ROLE_CODES.SALON_STAFF

  const [downloadingReceiptId, setDownloadingReceiptId] = useState(null)
  const [paymentTarget, setPaymentTarget] = useState(null)
  const [paymentSaving, setPaymentSaving] = useState(false)
  const [depositBusyId, setDepositBusyId] = useState(null)

  const today = format(new Date(), 'yyyy-MM-dd')

  const listFilters = useMemo(() => ({
    from: today,
    to: today,
    type: 'walk_in,product_sale',
    ...(isStaff && auth.user?.id ? { staff_id: auth.user.id } : {}),
  }), [today, isStaff, auth.user?.id])

  const { data: walkIns = [], isLoading, isFetching, isError, error, refetch } = useQuery({
    queryKey: ['pos-today', listFilters],
    queryFn: () => fetchMasterList('/v1/appointments', 'appointments', listFilters),
    refetchInterval: 60_000,
    enabled: canView,
  })

  const paidToday = walkIns.filter((a) => a.payment_status === 'paid' && !['cancelled', 'no-show'].includes(a.status))
  const pendingPayment = walkIns.filter((a) => balanceDue(a) > 0 && !['cancelled', 'no-show'].includes(a.status) && a.payment_status !== 'refunded')

  const timeLabel = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }

  const serviceNames = (appt) => {
    const lines = [
      ...(appt.services ?? []).map((s) => {
        const qty = s.quantity > 1 ? `${s.quantity}× ` : ''
        return `${qty}${s.product?.name || s.service?.name || 'Item'}`
      }),
      ...(appt.products ?? []).map((p) => {
        const qty = p.quantity > 1 ? `${p.quantity}× ` : ''
        return `${qty}${p.product?.name || 'Product'}`
      }),
    ].filter(Boolean)

    if (lines.length) return lines.join(', ')
    return appt.service?.name || '—'
  }

  const staffNames = (appt) => {
    const names = [...new Set([
      ...(appt.services ?? []).map((s) => s.staff?.name),
      ...(appt.products ?? []).map((p) => p.staff?.name),
    ].filter(Boolean))]

    return names.join(', ') || appt.staff?.name || '—'
  }

  const handleDownloadReceipt = async (event, appointmentId) => {
    event.stopPropagation()
    setDownloadingReceiptId(appointmentId)
    try {
      await downloadAppointmentReceipt(appointmentId)
      pushToast('Receipt downloaded.', 'success')
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to download receipt.', 'error')
    } finally {
      setDownloadingReceiptId(null)
    }
  }

  const handleCapturePayment = async (payload) => {
    if (!paymentTarget?.id) return
    setPaymentSaving(true)
    try {
      await captureAppointmentPayment(paymentTarget.id, payload)
      pushToast('Payment recorded.', 'success')
      setPaymentTarget(null)
      await refetch()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to record payment.', 'error')
      throw err
    } finally {
      setPaymentSaving(false)
    }
  }

  const handleMarkDepositPaid = async (event, appointmentId) => {
    event.stopPropagation()
    setDepositBusyId(appointmentId)
    try {
      await markDepositPaid(appointmentId)
      pushToast('Deposit marked as paid.', 'success')
      await refetch()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to mark deposit paid.', 'error')
    } finally {
      setDepositBusyId(null)
    }
  }

  if (isError && isForbiddenError(error)) {
    const mode = error?.response?.data?.subscription_access_mode
    return (
      <SubscriptionAccessFallback
        mode={mode === 'locked' ? 'locked' : mode === 'read_only' ? 'read_only' : 'error'}
        message={error?.response?.data?.message}
        showRenew={auth.can('settings.view')}
      />
    )
  }

  return (
    <div className="w-full max-w-3xl mx-auto space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <PageHeader
          title="POS"
          subtitle={subscriptionPageSubtitle(auth, 'Quick walk-ins for the floor — same booking flow as appointments.')}
        />
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => refetch()}
            className="p-3 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-brand-600 hover:border-brand-200 transition-colors"
            aria-label="Refresh"
          >
            <RefreshCw className={`w-5 h-5 ${isFetching ? 'animate-spin' : ''}`} />
          </button>
          <Link
            to="/appointments"
            className="hidden sm:inline-flex items-center gap-2 px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-600 hover:border-brand-200 hover:text-brand-700"
          >
            <CalendarDays className="w-4 h-4" /> Schedule
          </Link>
        </div>
      </div>

      {canCreate && (
        <button
          type="button"
          onClick={openWalkInModal}
          className="w-full flex items-center justify-center gap-3 rounded-2xl bg-brand-600 hover:bg-brand-700 active:scale-[0.99] text-white py-5 px-6 shadow-lg shadow-brand-600/20 transition-all"
        >
          <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/15">
            <Plus className="w-7 h-7" />
          </span>
          <span className="text-left">
            <span className="block text-lg font-black tracking-tight">New Walk-in</span>
            <span className="block text-sm text-brand-100 font-medium">Services billed as completed · payment optional</span>
          </span>
        </button>
      )}

      <div className="grid grid-cols-3 gap-3">
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Today</p>
          <p className="text-3xl font-black text-slate-900 mt-1">{walkIns.length}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Paid today</p>
          <p className="text-3xl font-black text-slate-900 mt-1">{paidToday.length}</p>
        </div>
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
          <p className="text-[10px] font-bold uppercase tracking-wider text-amber-600">Pending payment</p>
          <p className="text-3xl font-black text-amber-800 mt-1">{pendingPayment.length}</p>
        </div>
      </div>

      <section className="space-y-3">
        <div className="flex items-center gap-2 text-slate-700">
          <ShoppingBag className="w-4 h-4 text-brand-600" />
          <h2 className="text-sm font-bold uppercase tracking-wider">Today&apos;s walk-ins</h2>
        </div>

        {isLoading ? (
          <div className="py-16 text-center text-slate-400">Loading tickets…</div>
        ) : walkIns.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 py-14 px-6 text-center">
            <ShoppingBag className="w-10 h-10 text-slate-300 mx-auto mb-3" />
            <p className="font-bold text-slate-600">No walk-ins yet today</p>
            <p className="text-sm text-slate-400 mt-1">
              {canCreate ? 'Tap New Walk-in when a customer arrives.' : 'Renew your subscription to add walk-ins.'}
            </p>
            {canCreate && (
              <BaseButton className="mt-5" leftIcon={Plus} onClick={openWalkInModal}>New Walk-in</BaseButton>
            )}
          </div>
        ) : (
          <div className="space-y-3">
            {walkIns.map((appt) => (
              <button
                key={appt.id}
                type="button"
                onClick={() => { if (canUpdate) openEditModal(appt) }}
                className={`w-full text-left rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all active:scale-[0.995] ${canUpdate ? 'hover:border-brand-300 hover:shadow-md cursor-pointer' : 'cursor-default'}`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <p className="font-bold text-slate-900 truncate">{appt.customer?.name || 'Walk-in'}</p>
                      <BaseBadge variant={STATUS_BADGE[appt.status] || 'default'}>
                        {appt.status?.replace('-', ' ')}
                      </BaseBadge>
                      {appt.payment_status && (
                        <BaseBadge variant={appt.payment_status === 'paid' ? 'success' : 'warning'}>
                          {PAYMENT_STATUS_LABELS[appt.payment_status] || appt.payment_status}
                        </BaseBadge>
                      )}
                    </div>
                    {(() => {
                      const phone = customerPhoneDisplay(appt.customer)
                      return phone ? <p className="text-xs text-slate-400 mt-0.5">{phone}</p> : null
                    })()}
                    {canViewCustomers && appt.customer_id && (
                      <Link
                        to={`/customers?id=${appt.customer_id}`}
                        onClick={(e) => e.stopPropagation()}
                        className="inline-block mt-1 text-xs font-semibold text-brand-600"
                      >
                        View profile
                      </Link>
                    )}
                  </div>
                  <div className="flex items-start gap-2 shrink-0">
                    {canChargeDeposit && appt.deposit_status === 'pending' && Number(appt.deposit_required_amount || 0) > 0 && (
                      <button
                        type="button"
                        title={`Mark deposit paid (${fmt.money(appt.deposit_required_amount)})`}
                        disabled={depositBusyId === appt.id}
                        onClick={(e) => void handleMarkDepositPaid(e, appt.id)}
                        className="rounded-lg border border-amber-200 bg-amber-50 px-2 py-2 text-xs font-bold text-amber-800 disabled:opacity-60"
                      >
                        Deposit
                      </button>
                    )}
                    {canUpdate && balanceDue(appt) > 0 && appt.payment_status !== 'refunded' && (
                      <button
                        type="button"
                        title="Collect payment"
                        onClick={(e) => { e.stopPropagation(); setPaymentTarget(appt) }}
                        className="rounded-lg border border-brand-200 bg-brand-50 p-2 text-brand-600"
                      >
                        <CreditCard className="w-4 h-4" />
                      </button>
                    )}
                    {canView && (
                      <button
                        type="button"
                        title="Download receipt"
                        disabled={downloadingReceiptId === appt.id}
                        onClick={(e) => handleDownloadReceipt(e, appt.id)}
                        className="rounded-lg border border-slate-200 bg-white p-2 text-slate-500 hover:border-brand-200 hover:text-brand-600 transition-colors disabled:opacity-60"
                      >
                        <FileDown className={`w-4 h-4 ${downloadingReceiptId === appt.id ? 'animate-pulse' : ''}`} />
                      </button>
                    )}
                    <p className="font-black text-brand-700">{fmt.money(appt.grand_total ?? appt.price)}</p>
                  </div>
                </div>

                <div className="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm text-slate-600">
                  <div className="flex items-center gap-1.5 min-w-0">
                    <Clock className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span>{timeLabel(appt.starts_at)}</span>
                  </div>
                  <div className="flex items-center gap-1.5 min-w-0">
                    <Scissors className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span className="truncate">{serviceNames(appt)}</span>
                  </div>
                  <div className="flex items-center gap-1.5 min-w-0">
                    <User className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span className="truncate">{staffNames(appt)}</span>
                  </div>
                </div>
              </button>
            ))}
          </div>
        )}
      </section>

      <BookingModal />

      <PaymentCaptureModal
        open={Boolean(paymentTarget)}
        appointment={paymentTarget}
        onClose={() => setPaymentTarget(null)}
        saving={paymentSaving}
        onSubmit={handleCapturePayment}
      />
    </div>
  )
}
