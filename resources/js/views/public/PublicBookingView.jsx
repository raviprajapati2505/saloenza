import React, { useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { format } from 'date-fns'
import { CalendarDays, CheckCircle2, Clock, Loader2, Scissors, UserRound } from 'lucide-react'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BaseSelect from '../../components/ui/BaseSelect.jsx'
import {
  fetchPublicBooking,
  fetchPublicBookingSlots,
  submitPublicBooking,
} from '../../services/bookingLinkService.js'
import { formatMoney } from '../../lib/tenantFormatting.js'
import { pushToast } from '../../stores/toast.js'

const STEPS = ['Services', 'Date & time', 'Your details', 'Confirm']

export default function PublicBookingView() {
  const { token } = useParams()
  const [step, setStep] = useState(0)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [bootstrap, setBootstrap] = useState(null)
  const [selectedServices, setSelectedServices] = useState([])
  const [branchId, setBranchId] = useState('')
  const [staffId, setStaffId] = useState('')
  const [date, setDate] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [slots, setSlots] = useState([])
  const [slotsLoading, setSlotsLoading] = useState(false)
  const [selectedSlot, setSelectedSlot] = useState(null)
  const [customer, setCustomer] = useState({ name: '', phone: '', email: '', notes: '' })
  const [confirmed, setConfirmed] = useState(null)

  const moneyAuth = useMemo(
    () => ({ tenant: { regional: bootstrap?.regional } }),
    [bootstrap?.regional],
  )
  const money = (value) => formatMoney(value, moneyAuth)

  useEffect(() => {
    setLoading(true)
    void fetchPublicBooking(token)
      .then((data) => {
        setBootstrap(data)
        if (data?.link?.branch_id) {
          setBranchId(String(data.link.branch_id))
        } else if (data?.branches?.length === 1) {
          setBranchId(String(data.branches[0].id))
        }
      })
      .catch((err) => {
        setError(err?.response?.data?.message || 'This booking link is unavailable.')
      })
      .finally(() => setLoading(false))
  }, [token])

  const services = bootstrap?.services ?? []
  const branchStaff = useMemo(() => {
    const allStaff = bootstrap?.staff ?? []
    if (!branchId) return allStaff
    return allStaff.filter((member) => String(member.branch_id) === String(branchId))
  }, [bootstrap?.staff, branchId])

  const selectedStaff = useMemo(
    () => branchStaff.find((member) => String(member.id) === String(staffId)) || null,
    [branchStaff, staffId],
  )

  const selectedDetails = useMemo(
    () => services.filter((service) => selectedServices.includes(service.id)),
    [services, selectedServices],
  )
  const totalPrice = selectedDetails.reduce((sum, service) => sum + (Number(service.price) || 0), 0)
  const totalDuration = selectedDetails.reduce((sum, service) => sum + (Number(service.duration_minutes) || 0), 0)

  useEffect(() => {
    if (staffId && !branchStaff.some((member) => String(member.id) === String(staffId))) {
      setStaffId('')
      setSelectedSlot(null)
      setSlots([])
    }
  }, [branchStaff, staffId])

  const toggleService = (serviceId) => {
    setSelectedServices((current) => (
      current.includes(serviceId)
        ? current.filter((id) => id !== serviceId)
        : [...current, serviceId]
    ))
  }

  const loadSlots = async () => {
    if (!selectedServices.length || !staffId) return
    setSlotsLoading(true)
    setError('')
    try {
      const data = await fetchPublicBookingSlots(token, {
        date,
        branch_id: branchId || undefined,
        staff_id: Number(staffId),
        service_ids: selectedServices,
      })
      setSlots(data?.slots ?? [])
      setSelectedSlot(null)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load available times.')
      setSlots([])
    } finally {
      setSlotsLoading(false)
    }
  }

  useEffect(() => {
    if (step === 1 && selectedServices.length && staffId) {
      void loadSlots()
    }
  }, [step, date, branchId, staffId, selectedServices.join(',')])

  const handleSlotClick = (slot) => {
    if (!slot.available) {
      pushToast(slot.unavailable_reason || 'Selected staff is not available at this time.', 'error')
      return
    }
    setSelectedSlot(slot)
  }

  const handleSubmit = async () => {
    setSubmitting(true)
    setError('')
    try {
      const appointment = await submitPublicBooking(token, {
        branch_id: branchId ? Number(branchId) : undefined,
        staff_id: Number(staffId),
        service_ids: selectedServices,
        starts_at: selectedSlot.starts_at,
        customer_name: customer.name.trim(),
        customer_phone: customer.phone.trim(),
        customer_email: customer.email.trim() || undefined,
        notes: customer.notes.trim() || undefined,
      })
      setConfirmed(appointment)
      setStep(4)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to confirm your booking.')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
      </div>
    )
  }

  if (!bootstrap) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <div className="max-w-md rounded-[18px] border border-rose-200 bg-white p-8 text-center shadow-sm">
          <p className="text-lg font-semibold text-slate-900">Booking unavailable</p>
          <p className="mt-2 text-sm text-slate-600">{error || 'Please contact the salon directly.'}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-brand-50 via-white to-violet-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-8 text-center">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-brand-500">Book online</p>
          <h1 className="mt-2 text-3xl font-bold text-slate-900">{bootstrap.salon?.name}</h1>
          <p className="mt-2 text-sm text-slate-600">Choose services, pick a staff member and time, then confirm.</p>
        </div>

        <div className="mb-6 flex flex-wrap justify-center gap-2">
          {STEPS.map((label, index) => (
            <span
              key={label}
              className={`rounded-full px-3 py-1 text-xs font-semibold ${
                step === index ? 'bg-brand-500 text-white' : step > index ? 'bg-emerald-100 text-emerald-700' : 'bg-white text-slate-500'
              }`}
            >
              {index + 1}. {label}
            </span>
          ))}
        </div>

        <div className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgb(0,0,0,0.06)] sm:p-8">
          {error ? (
            <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
          ) : null}

          {step === 0 ? (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-slate-900">Select services</h2>
              <div className="grid gap-3">
                {services.map((service) => {
                  const checked = selectedServices.includes(service.id)
                  return (
                    <label
                      key={service.id}
                      className={`flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition ${
                        checked ? 'border-brand-300 bg-brand-50/50' : 'border-slate-100 hover:border-brand-200'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => toggleService(service.id)}
                        className="mt-1 h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-400"
                      />
                      <div className="flex-1">
                        <div className="flex items-center justify-between gap-3">
                          <p className="font-semibold text-slate-900">{service.name}</p>
                          <p className="text-sm font-semibold text-slate-700">{money(service.price)}</p>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                          {service.category ? `${service.category} · ` : ''}{service.duration_minutes} min
                        </p>
                      </div>
                    </label>
                  )
                })}
              </div>
              <BaseButton
                className="w-full"
                disabled={!selectedServices.length}
                onClick={() => setStep(1)}
              >
                Continue
              </BaseButton>
            </div>
          ) : null}

          {step === 1 ? (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-slate-900">Choose staff, date & time</h2>
              {bootstrap.branches?.length > 1 && !bootstrap.link?.branch_id ? (
                <BaseSelect
                  label="Branch"
                  value={branchId}
                  onChange={(event) => {
                    setBranchId(event.target.value)
                    setStaffId('')
                    setSelectedSlot(null)
                  }}
                  options={bootstrap.branches.map((branch) => ({
                    value: String(branch.id),
                    label: branch.name,
                  }))}
                />
              ) : null}
              <BaseSelect
                label="Staff"
                value={staffId}
                onChange={(event) => {
                  setStaffId(event.target.value)
                  setSelectedSlot(null)
                }}
                placeholder="Select a staff member"
                options={branchStaff.map((member) => ({
                  value: String(member.id),
                  label: member.name,
                }))}
              />
              {!branchStaff.length ? (
                <p className="text-sm text-amber-700">No staff members are available for this branch.</p>
              ) : null}
              <BaseInput label="Date" type="date" value={date} min={format(new Date(), 'yyyy-MM-dd')} onChange={(event) => setDate(event.target.value)} />
              <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600">
                <div className="flex items-center gap-2"><Scissors className="h-4 w-4" /> {selectedDetails.length} services selected</div>
                <div className="mt-1 flex items-center gap-2"><Clock className="h-4 w-4" /> {totalDuration} minutes · {money(totalPrice)}</div>
                {selectedStaff ? (
                  <div className="mt-1 flex items-center gap-2"><UserRound className="h-4 w-4" /> {selectedStaff.name}</div>
                ) : null}
              </div>
              {!staffId ? (
                <p className="text-sm text-slate-500">Select a staff member to see available times.</p>
              ) : slotsLoading ? (
                <div className="flex justify-center py-8"><Loader2 className="h-6 w-6 animate-spin text-brand-500" /></div>
              ) : (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                  {slots.map((slot) => (
                    <button
                      key={slot.starts_at}
                      type="button"
                      onClick={() => handleSlotClick(slot)}
                      aria-disabled={!slot.available}
                      className={`rounded-xl border px-3 py-2 text-sm font-semibold transition ${
                        !slot.available
                          ? 'cursor-not-allowed border-slate-100 bg-slate-100 text-slate-400 line-through'
                          : selectedSlot?.starts_at === slot.starts_at
                            ? 'border-brand-400 bg-brand-500 text-white'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-brand-300'
                      }`}
                    >
                      {slot.label}
                    </button>
                  ))}
                </div>
              )}
              {staffId && !slotsLoading && slots.length === 0 ? (
                <p className="text-sm text-slate-500">No open slots for this date. Try another day.</p>
              ) : null}
              {staffId && !slotsLoading && slots.length > 0 && !slots.some((slot) => slot.available) ? (
                <p className="text-sm text-slate-500">Selected staff has no open times on this date. Try another day or staff member.</p>
              ) : null}
              <div className="flex gap-3">
                <BaseButton variant="secondary" onClick={() => setStep(0)}>Back</BaseButton>
                <BaseButton className="flex-1" disabled={!selectedSlot} onClick={() => setStep(2)}>Continue</BaseButton>
              </div>
            </div>
          ) : null}

          {step === 2 ? (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-slate-900">Your details</h2>
              <BaseInput label="Full name" value={customer.name} onChange={(event) => setCustomer((current) => ({ ...current, name: event.target.value }))} />
              <BaseInput label="Mobile number" value={customer.phone} onChange={(event) => setCustomer((current) => ({ ...current, phone: event.target.value }))} />
              <BaseInput label="Email (optional)" type="email" value={customer.email} onChange={(event) => setCustomer((current) => ({ ...current, email: event.target.value }))} />
              <BaseInput label="Notes (optional)" value={customer.notes} onChange={(event) => setCustomer((current) => ({ ...current, notes: event.target.value }))} />
              <div className="flex gap-3">
                <BaseButton variant="secondary" onClick={() => setStep(1)}>Back</BaseButton>
                <BaseButton className="flex-1" disabled={!customer.name.trim() || !customer.phone.trim()} onClick={() => setStep(3)}>Review booking</BaseButton>
              </div>
            </div>
          ) : null}

          {step === 3 ? (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-slate-900">Review & confirm</h2>
              <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                <p><strong>Services:</strong> {selectedDetails.map((service) => service.name).join(', ')}</p>
                <p className="mt-2"><strong>Staff:</strong> {selectedStaff?.name || '—'}</p>
                <p className="mt-2"><strong>When:</strong> {selectedSlot ? format(new Date(selectedSlot.starts_at), 'EEE, d MMM yyyy · p') : '—'}</p>
                <p className="mt-2"><strong>Duration:</strong> {totalDuration} minutes</p>
                <p className="mt-2"><strong>Estimated total:</strong> {money(totalPrice)}</p>
                <p className="mt-2"><strong>Name:</strong> {customer.name}</p>
                <p className="mt-2"><strong>Phone:</strong> {customer.phone}</p>
              </div>
              <div className="flex gap-3">
                <BaseButton variant="secondary" onClick={() => setStep(2)}>Back</BaseButton>
                <BaseButton className="flex-1" loading={submitting} onClick={() => void handleSubmit()}>Confirm appointment</BaseButton>
              </div>
            </div>
          ) : null}

          {step === 4 && confirmed ? (
            <div className="py-6 text-center">
              <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <CheckCircle2 className="h-8 w-8" />
              </div>
              <h2 className="mt-4 text-2xl font-bold text-slate-900">Booking confirmed</h2>
              <p className="mt-2 text-sm text-slate-600">
                Your appointment at {bootstrap.salon?.name} is booked for{' '}
                {confirmed.starts_at ? format(new Date(confirmed.starts_at), 'EEE, d MMM yyyy · p') : 'the selected time'}.
              </p>
              <div className="mt-6 inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700">
                <CalendarDays className="h-4 w-4" />
                Reference #{confirmed.id}
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  )
}
