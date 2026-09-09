import React, { useState, useEffect, useMemo, useRef } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, User, Search, Plus, Trash2, ShoppingBag, Lock } from 'lucide-react'
import BaseModal from '../ui/BaseModal.jsx'
import BaseInput from '../ui/BaseInput.jsx'
import BaseButton from '../ui/BaseButton.jsx'
import BaseSelect from '../ui/BaseSelect.jsx'
import { useAppointmentStore } from '../../stores/appointmentStore'
import { useAuthStore } from '../../stores/auth'
import { ROLE_CODES } from '../../lib/roleDisplay'
import { canMutate } from '../../lib/subscriptionModules.js'
import { useTenantFormatter } from '../../hooks/useTenantFormatter.js'
import { apiGet, apiPost, apiPut, parseList } from '../../lib/apiHelpers'
import { PAYMENT_METHODS } from '../../lib/appointmentPayments.js'
import {
  autoStatusHint,
  resolveDefaultAppointmentStatus,
  compareDateToToday,
} from '../../lib/appointmentStatus.js'

const STATUS_OPTIONS = [
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in-progress', label: 'In Progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'no-show', label: 'No Show' },
]

function todayInput() {
  return toDateInput(new Date())
}

function nowTimeInput() {
  return toTimeInput(new Date())
}

function toDateInput(value) {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function toTimeInput(value) {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

function addMinutesToTime(timeStr, minutes) {
  if (!timeStr) return ''
  const [h, m] = timeStr.split(':').map(Number)
  const total = h * 60 + m + Number(minutes || 0)
  const wrapped = ((total % (24 * 60)) + (24 * 60)) % (24 * 60)
  const hh = String(Math.floor(wrapped / 60)).padStart(2, '0')
  const mm = String(wrapped % 60).padStart(2, '0')
  return `${hh}:${mm}`
}

function dedupeServiceProducts(products = []) {
  const seen = new Set()
  return products.filter((product) => {
    const id = String(product.product_id)
    if (seen.has(id)) return false
    seen.add(id)
    return true
  })
}

function emptyServiceLine(defaults = {}) {
  return {
    service_id: '',
    product_id: '',
    staff_id: defaults.staff_id || '',
    is_staff_locked: false,
    price: '',
    duration_minutes: '',
    start_time: defaults.start_time || nowTimeInput(),
    end_time: '',
  }
}

function emptyRetailLine(defaults = {}) {
  return {
    product_id: '',
    quantity: '1',
    unit_price: '',
    staff_id: defaults.staff_id || '',
  }
}

function ReadonlyField({ label, value }) {
  return (
    <div className="w-full">
      <label className="block text-sm font-medium text-slate-700 mb-1.5 truncate">{label}</label>
      <div className="flex items-center h-[42px] w-full rounded-xl ring-1 ring-inset ring-slate-300 bg-slate-100/90 px-3 text-sm text-slate-900 shadow-sm truncate">
        {value || '—'}
      </div>
    </div>
  )
}

export default function BookingModal() {
  const queryClient = useQueryClient()
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const open = useAppointmentStore(state => state.isBookingModalOpen)
  const selectedSlot = useAppointmentStore(state => state.selectedSlot)
  const editingAppointment = useAppointmentStore(state => state.editingAppointment)
  const bookingType = useAppointmentStore(state => state.bookingType)
  const bookingSource = useAppointmentStore(state => state.bookingSource)
  const closeBookingModal = useAppointmentStore(state => state.closeBookingModal)

  const [form, setForm] = useState({
    customer_id: '',
    customer_name: '',
    customer_phone: '',
    branch_id: '',
    type: 'appointment',
    discount: '0',
    date: '',
    status: 'scheduled',
    notes: '',
    collect_payment: false,
    payment_method: 'cash',
    amount_paid: '',
    services: [emptyServiceLine()],
    products: [],
  })
  const [errors, setErrors] = useState({})
  const [submitError, setSubmitError] = useState('')
  const [customerSearch, setCustomerSearch] = useState('')
  const [showSuggestions, setShowSuggestions] = useState(false)
  const [customerMode, setCustomerMode] = useState('new')
  /** When contact is hidden, keep customer_id linked so edits don't recreate customers. */
  const [customerContactLocked, setCustomerContactLocked] = useState(false)
  const suggestionsRef = useRef(null)
  const branchDefaultedRef = useRef(false)

  const isEditing = Boolean(editingAppointment?.id)
  const canSave = isEditing
    ? canMutate(auth, 'appointments.update')
    : canMutate(auth, 'appointments.create')
  const isWalkIn = form.type === 'walk_in' || form.type === 'product_sale'
  const filledServices = form.services.filter((line) => line.service_id)
  const filledProducts = form.products.filter((line) => line.product_id)
  const isProductOnly = filledServices.length === 0 && filledProducts.length > 0
  const isStaff = auth.role?.code === ROLE_CODES.SALON_STAFF
  const isBranchScoped = auth.role?.scope === 'branch'
  const lockBranch = isBranchScoped && Boolean(auth.user?.branch_id)
  const lockStaffRole = isStaff

  const bookingBranchId = form.branch_id || (lockBranch ? String(auth.user?.branch_id || '') : '')

  const dateRelation = compareDateToToday(form.date)
  const showStatusSelect = isEditing || dateRelation < 0
  const statusHint = !showStatusSelect
    ? autoStatusHint({
        type: form.type,
        date: form.date,
        startTime: form.services?.[0]?.start_time,
        isProductOnly,
      })
    : null

  const { data: options } = useQuery({
    queryKey: ['appointment-booking-options', bookingBranchId],
    queryFn: async () => {
      const params = bookingBranchId ? { branch_id: bookingBranchId } : {}
      const response = await apiGet('/v1/appointments/booking-options', params)
      return response?.data?.data ?? { branches: [], staff: [], services: [], defaults: {} }
    },
    enabled: open,
  })

  const branches = options?.branches ?? []
  const staff = options?.staff ?? []
  const catalogServices = options?.services ?? []
  const defaults = options?.defaults ?? {}

  const trimmedSearch = customerSearch.trim()
  const { data: customerSuggestions = [], isFetching: isSearchingCustomers } = useQuery({
    queryKey: ['appointment-customer-search', trimmedSearch],
    queryFn: async () => parseList(await apiGet('/v1/appointments/customers', { search: trimmedSearch }), 'customers'),
    enabled: open && showSuggestions && trimmedSearch.length >= 1,
  })

  const branchName = branches.find(b => String(b.id) === String(form.branch_id))?.name
    || (lockBranch ? 'Your branch' : '—')

  const retailBranchId = form.branch_id || (lockBranch ? String(auth.user?.branch_id || '') : '')
  const { data: retailProducts = [], isFetching: loadingRetail } = useQuery({
    queryKey: ['booking-retail-products', retailBranchId],
    queryFn: async () => {
      const response = await apiGet('/v1/pos/catalog', retailBranchId ? { branch_id: retailBranchId } : {})
      return response?.data?.data?.catalog?.retail_products ?? []
    },
    enabled: open && Boolean(retailBranchId),
  })

  useEffect(() => {
    if (open && !canSave) {
      closeBookingModal()
    }
  }, [open, canSave, closeBookingModal])

  useEffect(() => {
    if (!open) return

    if (isEditing) {
      const raw = editingAppointment
      const legacyLine = raw.service_id
        ? [{
            service_id: raw.service_id,
            product_id: raw.product_id,
            staff_id: raw.staff_id,
            price: raw.price,
            duration_minutes: '',
            starts_at: raw.starts_at,
            ends_at: raw.ends_at,
            is_staff_locked: false,
          }]
        : []

      const lines = (raw.services?.length ? raw.services : legacyLine).map(line => {
        const start = toTimeInput(line.starts_at) || nowTimeInput()
        const duration = line.duration_minutes || 60
        return {
          service_id: line.service_id ? String(line.service_id) : '',
          product_id: line.product_id ? String(line.product_id) : '',
          staff_id: line.staff_id ? String(line.staff_id) : '',
          is_staff_locked: Boolean(line.is_staff_locked),
          price: line.price != null ? String(line.price) : '',
          duration_minutes: duration != null ? String(duration) : '',
          start_time: start,
          end_time: toTimeInput(line.ends_at) || addMinutesToTime(start, duration),
        }
      })

      setForm({
        customer_id: raw.customer_id ? String(raw.customer_id) : '',
        customer_name: raw.customer?.name || '',
        customer_phone: raw.customer?.phone || '',
        branch_id: raw.branch_id ? String(raw.branch_id) : '',
        type: raw.type || 'appointment',
        discount: raw.discount != null ? String(raw.discount) : '0',
        date: toDateInput(raw.starts_at),
        status: raw.status || 'scheduled',
        notes: raw.notes || '',
        collect_payment: (raw.payment_status === 'paid' || raw.payment_status === 'partial'),
        payment_method: raw.payment_method || 'cash',
        amount_paid: raw.amount_paid != null ? String(raw.amount_paid) : '',
        services: lines,
        products: (raw.products ?? []).map(line => ({
          product_id: line.product_id ? String(line.product_id) : '',
          quantity: line.quantity != null ? String(line.quantity) : '1',
          unit_price: line.unit_price != null ? String(line.unit_price) : '',
          staff_id: line.staff_id ? String(line.staff_id) : '',
        })),
      })
      setCustomerMode(raw.customer_id ? 'existing' : 'new')
      setCustomerContactLocked(Boolean(
        raw.customer_id
        && raw.customer
        && raw.customer.can_view_contact === false,
      ))
    } else {
      const type = bookingType || (bookingSource === 'pos' ? 'walk_in' : 'appointment')
      const defaultStaff = lockStaffRole
        ? String(auth.user?.id || defaults.staff_id || '')
        : (selectedSlot?.resourceId ? String(selectedSlot.resourceId) : '')
      const defaultBranch = lockBranch
        ? String(auth.user?.branch_id || defaults.branch_id || '')
        : ''
      const date = type === 'walk_in'
        ? todayInput()
        : (selectedSlot?.start ? toDateInput(selectedSlot.start) : todayInput())
      const startTime = type === 'walk_in'
        ? nowTimeInput()
        : (selectedSlot?.start ? toTimeInput(selectedSlot.start) : nowTimeInput())

      setForm({
        customer_id: '',
        customer_name: '',
        customer_phone: '',
        branch_id: defaultBranch,
        type,
        discount: '0',
        date,
        status: resolveDefaultAppointmentStatus({
          type,
          date,
          startTime,
          isEditing: false,
        }),
        notes: '',
        collect_payment: false,
        payment_method: 'cash',
        amount_paid: '',
        services: [emptyServiceLine({ staff_id: defaultStaff, start_time: startTime })],
        products: [],
      })
      setCustomerMode('new')
      setCustomerContactLocked(false)
    }

    setErrors({})
    setSubmitError('')
    setShowSuggestions(false)
    setCustomerSearch('')
  }, [open, isEditing, editingAppointment, selectedSlot, bookingType, bookingSource, lockBranch, lockStaffRole, auth.user, defaults.branch_id, defaults.staff_id])

  // Owners are not tied to a branch, so the form used to open with none selected and
  // an empty retail catalog. Fall back to their default (or first) branch instead.
  // Applied once per open so clearing the branch afterwards sticks.
  useEffect(() => {
    if (!open) {
      branchDefaultedRef.current = false
      return
    }
    if (isEditing || lockBranch || branchDefaultedRef.current || form.branch_id) return

    const fallback = defaults.branch_id || branches[0]?.id
    if (!fallback) return

    branchDefaultedRef.current = true
    setForm(prev => ({ ...prev, branch_id: String(fallback) }))
  }, [open, isEditing, lockBranch, form.branch_id, defaults.branch_id, branches])

  // Keep status in sync when date/type/time changes on create
  useEffect(() => {
    if (!open || isEditing || showStatusSelect) return
    const startTime = form.services?.[0]?.start_time
    const next = resolveDefaultAppointmentStatus({
      type: form.type,
      date: form.date,
      startTime,
      isEditing: false,
    })
    if (form.status !== next) {
      setForm(prev => ({ ...prev, status: next }))
    }
  }, [open, isEditing, showStatusSelect, form.type, form.date, form.services?.[0]?.start_time, form.status])

  const servicesSubtotal = useMemo(
    () => form.services.reduce((sum, line) => sum + Number(line.price || 0), 0),
    [form.services],
  )

  const productsSubtotal = useMemo(
    () => form.products.reduce(
      (sum, line) => sum + Number(line.unit_price || 0) * Math.max(1, Number(line.quantity || 1)),
      0,
    ),
    [form.products],
  )

  const priceSubtotal = servicesSubtotal + productsSubtotal

  const grandTotal = useMemo(
    () => Math.max(priceSubtotal - Number(form.discount || 0), 0),
    [priceSubtotal, form.discount],
  )

  const update = (patch) => setForm(prev => ({ ...prev, ...patch }))

  const updateServiceLine = (index, patch) => {
    setForm(prev => ({
      ...prev,
      services: prev.services.map((line, i) => {
        if (i !== index) return line
        const next = { ...line, ...patch }
        const duration = Number(next.duration_minutes || 0)
        if (patch.start_time != null || patch.duration_minutes != null) {
          next.end_time = addMinutesToTime(next.start_time, duration || 60)
        }
        return next
      }),
    }))
  }

  const resolveLinePrice = (serviceId, productId) => {
    const service = catalogServices.find(s => String(s.service_id) === String(serviceId))
    if (!service) return { price: '', duration_minutes: '' }
    if (productId) {
      const product = (service.products || []).find(p => String(p.product_id) === String(productId))
      if (product) {
        return {
          price: product.price != null ? String(product.price) : '',
          duration_minutes: product.duration_minutes != null ? String(product.duration_minutes) : String(service.duration_minutes || ''),
        }
      }
    }
    return {
      price: service.price != null ? String(service.price) : '',
      duration_minutes: service.duration_minutes != null ? String(service.duration_minutes) : '',
    }
  }

  const handleServiceChange = (index, serviceId) => {
    const priced = resolveLinePrice(serviceId, '')
    const duration = priced.duration_minutes || '60'
    const line = form.services[index]
    updateServiceLine(index, {
      service_id: serviceId,
      product_id: '',
      price: priced.price,
      duration_minutes: duration,
      end_time: addMinutesToTime(line.start_time, duration),
    })
  }

  const handleProductChange = (index, productId) => {
    const line = form.services[index]
    const priced = resolveLinePrice(line.service_id, productId)
    const duration = priced.duration_minutes || line.duration_minutes || '60'
    updateServiceLine(index, {
      product_id: productId,
      price: priced.price !== '' && priced.price != null ? priced.price : line.price,
      duration_minutes: duration,
      end_time: addMinutesToTime(line.start_time, duration),
    })
  }

  useEffect(() => {
    if (!open || catalogServices.length === 0) return

    setForm((prev) => {
      let changed = false
      const services = prev.services.map((line) => {
        if (!line.service_id || !line.product_id) return line

        const selected = catalogServices.find(
          (service) => String(service.service_id) === String(line.service_id),
        )
        if (!selected) return line

        const allowed = dedupeServiceProducts(selected.products ?? [])
        const stillValid = allowed.some(
          (product) => String(product.product_id) === String(line.product_id),
        )

        if (stillValid) return line

        changed = true
        const priced = resolveLinePrice(line.service_id, '')

        return {
          ...line,
          product_id: '',
          price: priced.price,
          duration_minutes: priced.duration_minutes || line.duration_minutes,
        }
      })

      return changed ? { ...prev, services } : prev
    })
  }, [open, catalogServices, bookingBranchId])

  const addServiceLine = () => {
    const last = form.services[form.services.length - 1]
    const defaultStaff = lockStaffRole
      ? String(auth.user?.id || '')
      : (last?.staff_id || form.services[0]?.staff_id || '')
    const start = last?.end_time || nowTimeInput()
    update({
      services: [
        ...form.services,
        emptyServiceLine({ staff_id: defaultStaff, start_time: start }),
      ],
    })
  }

  // The last service line may only go when a product keeps the sale non-empty.
  const removeServiceLine = (index) => {
    if (form.services.length <= 1 && form.products.length === 0) return
    update({ services: form.services.filter((_, i) => i !== index) })
  }

  const updateRetailLine = (index, patch) => {
    update({ products: form.products.map((line, i) => (i === index ? { ...line, ...patch } : line)) })
  }

  const handleRetailProductChange = (index, productId) => {
    const product = retailProducts.find(p => String(p.product_id) === String(productId))
    updateRetailLine(index, {
      product_id: productId,
      unit_price: product?.price != null ? String(product.price) : '',
    })
  }

  const addRetailLine = () => {
    update({
      products: [
        ...form.products,
        emptyRetailLine({ staff_id: lockStaffRole ? String(auth.user?.id || '') : '' }),
      ],
    })
  }

  const removeRetailLine = (index) => {
    update({ products: form.products.filter((_, i) => i !== index) })
  }

  const handleSelectCustomer = (customer) => {
    update({
      customer_id: String(customer.id),
      customer_name: customer.name || '',
      customer_phone: customer.phone || '',
    })
    setCustomerMode('existing')
    setCustomerContactLocked(customer.can_view_contact === false)
    setShowSuggestions(false)
    setCustomerSearch('')
  }

  const handleUseAsNewCustomer = () => {
    update({ customer_id: '' })
    setCustomerMode('new')
    setCustomerContactLocked(false)
    setShowSuggestions(false)
  }

  const handleChangeCustomer = () => {
    update({ customer_id: '', customer_name: '', customer_phone: '' })
    setCustomerMode('new')
    setCustomerContactLocked(false)
    setCustomerSearch('')
    setShowSuggestions(false)
  }

  const handleCustomerNameChange = (value) => {
    // Hidden-contact customers must stay linked; clearing the id would recreate them without a phone.
    if (customerContactLocked && form.customer_id) {
      update({ customer_name: value })
      return
    }
    update({ customer_name: value, customer_id: '' })
    setCustomerMode('new')
    setCustomerContactLocked(false)
    setCustomerSearch(value)
    setShowSuggestions(Boolean(value.trim()))
  }

  useEffect(() => {
    const onClickOutside = (e) => {
      if (suggestionsRef.current && !suggestionsRef.current.contains(e.target)) {
        setShowSuggestions(false)
      }
    }
    document.addEventListener('mousedown', onClickOutside)
    return () => document.removeEventListener('mousedown', onClickOutside)
  }, [])

  const mutation = useMutation({
    mutationFn: async () => {
      const filledServiceLines = form.services.filter((line) => line.service_id)
      const filledProductLines = form.products.filter((line) => line.product_id)
      const isProductOnly = filledServiceLines.length === 0 && filledProductLines.length > 0
      const firstStart = filledServiceLines[0]?.start_time || form.services[0]?.start_time || nowTimeInput()
      const startsAt = new Date(`${form.date}T${firstStart}:00`)
      if (Number.isNaN(startsAt.getTime())) throw new Error('Invalid date or time')

      const payload = {
        customer_id: form.customer_id ? Number(form.customer_id) : null,
        customer_name: form.customer_name || null,
        customer_phone: form.customer_phone || null,
        branch_id: form.branch_id ? Number(form.branch_id) : null,
        type: isProductOnly ? 'product_sale' : form.type,
        starts_at: startsAt.toISOString(),
        status: (!isEditing && (isProductOnly || form.type === 'walk_in' || form.type === 'product_sale'))
          ? 'completed'
          : form.status,
        discount: form.discount !== '' ? Number(form.discount) : 0,
        notes: form.notes || null,
        ...(form.collect_payment ? {
          collect_payment: true,
          payment_method: form.payment_method,
          amount_paid: form.amount_paid !== '' ? Number(form.amount_paid) : grandTotal,
        } : {}),
        services: filledServiceLines.map(line => {
          const lineStart = new Date(`${form.date}T${line.start_time || firstStart}:00`)
          const duration = Number(line.duration_minutes || 60)
          const lineEnd = new Date(`${form.date}T${line.end_time || addMinutesToTime(line.start_time, duration)}:00`)
          return {
            service_id: Number(line.service_id),
            product_id: line.product_id ? Number(line.product_id) : null,
            staff_id: line.staff_id ? Number(line.staff_id) : null,
            is_staff_locked: Boolean(line.is_staff_locked),
            price: line.price !== '' ? Number(line.price) : null,
            duration_minutes: duration,
            starts_at: lineStart.toISOString(),
            ends_at: lineEnd.toISOString(),
          }
        }),
        products: form.products
          .filter(line => line.product_id)
          .map(line => ({
            product_id: Number(line.product_id),
            quantity: Math.max(1, Number(line.quantity || 1)),
            unit_price: line.unit_price !== '' ? Number(line.unit_price) : null,
            staff_id: line.staff_id ? Number(line.staff_id) : null,
          })),
      }

      return isEditing
        ? apiPut(`/v1/appointments/${editingAppointment.id}`, payload)
        : apiPost('/v1/appointments', payload)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['calendar-events'] })
      queryClient.invalidateQueries({ queryKey: ['appointments-list'] })
      queryClient.invalidateQueries({ queryKey: ['pos-today'] })
      queryClient.invalidateQueries({ queryKey: ['outstanding-payments'] })
      closeBookingModal()
    },
    onError: (error) => {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length) {
        const mapped = {}
        for (const key in serverErrors) mapped[key] = serverErrors[key][0]
        setErrors(mapped)
      }
      setSubmitError(error?.response?.data?.message || 'Failed to save appointment.')
    },
  })

  const validate = () => {
    const next = {}
    if (!form.customer_name.trim()) next.customer_name = 'Customer name is required'
    if (!form.date) next.date = 'Date is required'
    const filledProducts = form.products.filter(line => line.product_id)
    const filledServiceLines = form.services.filter(line => line.service_id)
    if (filledServiceLines.length === 0 && filledProducts.length === 0) {
      next.services = 'Add at least one service or product'
    }
    if (filledProducts.length > 0 && !form.branch_id && !lockBranch) {
      next.branch_id = 'Select a branch to sell products'
    }
    form.services.forEach((line, index) => {
      if (!line.service_id) {
        if (filledProducts.length === 0) next[`services.${index}.service_id`] = 'Service is required'
        return
      }
      if (!line.start_time) next[`services.${index}.start_time`] = 'Start time is required'
      if ((line.is_staff_locked || lockStaffRole) && !line.staff_id) {
        next[`services.${index}.staff_id`] = 'Staff is required'
      }
    })
    form.products.forEach((line, index) => {
      if (!line.product_id) next[`products.${index}.product_id`] = 'Product is required'
    })
    setErrors(next)
    return Object.keys(next).length === 0
  }

  const handleSubmit = () => {
    setSubmitError('')
    if (!canSave) {
      setSubmitError('Your subscription is in view-only mode. Renew to make changes.')
      return
    }
    if (!validate()) return
    mutation.mutate()
  }

  if (!open || !canSave) return null

  const title = isEditing
    ? (isWalkIn ? 'Edit Walk-in' : 'Edit Appointment')
    : (isWalkIn ? 'New Walk-in (POS)' : 'New Appointment')

  const money = fmt.money

  return (
    <BaseModal
      open={open}
      onClose={closeBookingModal}
      title={title}
      size="2xl"
      headerVariant="teal"
      headerExtra={(
        <div className="flex items-center gap-1 p-0.5 bg-black/15 rounded-xl border border-white/20 backdrop-blur-sm">
          <button
            type="button"
            onClick={() => update({
              type: 'walk_in',
              date: todayInput(),
              status: 'completed',
            })}
            className={`flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold transition-all ${isWalkIn ? 'bg-white text-brand-800 shadow-md' : 'text-brand-100 hover:text-white'}`}
          >
            <ShoppingBag className="w-3.5 h-3.5" /> Walk-in
          </button>
          <button
            type="button"
            onClick={() => update({
              type: 'appointment',
              status: resolveDefaultAppointmentStatus({
                type: 'appointment',
                date: form.date,
                startTime: form.services?.[0]?.start_time,
                isEditing: false,
              }),
            })}
            className={`flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold transition-all ${!isWalkIn ? 'bg-white text-brand-800 shadow-md' : 'text-brand-100 hover:text-white'}`}
          >
            Appointment
          </button>
        </div>
      )}
      footer={(
        <div className="flex w-full flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div className="grid w-full grid-cols-3 gap-2 sm:max-w-md sm:gap-2.5">
            <div className="flex h-[58px] flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 sm:px-3">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 leading-none">Subtotal</p>
              <p className="mt-1.5 truncate text-sm font-bold tabular-nums text-slate-800 leading-none">{money(priceSubtotal)}</p>
            </div>

            <div className="flex h-[58px] flex-col justify-center rounded-xl border border-slate-200 bg-white px-2.5 py-2 sm:px-3 focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-500/20">
              <label htmlFor="booking-discount" className="text-[10px] font-bold uppercase tracking-wider text-slate-400 leading-none">
                Discount ({fmt.symbol()})
              </label>
              <input
                id="booking-discount"
                type="number"
                min="0"
                value={form.discount}
                onChange={e => update({ discount: e.target.value })}
                placeholder="0"
                className="mt-1 w-full border-0 bg-transparent p-0 text-sm font-bold tabular-nums text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0"
              />
            </div>

            <div className="flex h-[58px] flex-col justify-center rounded-xl border border-brand-200 bg-brand-50 px-2.5 py-2 sm:px-3">
              <p className="text-[10px] font-bold uppercase tracking-wider text-brand-600 leading-none">Grand Total</p>
              <p className="mt-1.5 truncate text-sm font-black tabular-nums text-brand-700 leading-none sm:text-base">{money(grandTotal)}</p>
            </div>
          </div>

          <div className="flex w-full shrink-0 flex-col-reverse gap-2.5 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
            <BaseButton
              variant="secondary"
              size="sm"
              className="w-full sm:w-auto"
              onClick={closeBookingModal}
              disabled={mutation.isPending}
            >
              Cancel
            </BaseButton>
            <BaseButton
              variant="primary"
              size="sm"
              className="w-full sm:w-auto"
              rightIcon={Check}
              onClick={handleSubmit}
              loading={mutation.isPending}
            >
              {isEditing ? 'Save Changes' : (isWalkIn ? 'Complete Walk-in' : 'Book Appointment')}
            </BaseButton>
          </div>
        </div>
      )}
    >
      {submitError && (
        <div className="mb-2.5 p-2 text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">{submitError}</div>
      )}

      {/* Top Metadata Grid: 4 Columns on lg screens */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div className="relative" ref={suggestionsRef}>
          <div className="mb-1.5 flex items-center justify-between gap-2">
            <label className="block text-sm font-medium text-slate-700">Customer Name</label>
            {customerContactLocked && form.customer_id ? (
              <button
                type="button"
                onClick={handleChangeCustomer}
                className="text-[11px] font-semibold text-brand-600 hover:text-brand-700"
              >
                Change customer
              </button>
            ) : null}
          </div>
          <div className="relative flex items-center rounded-xl shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-inset focus-within:ring-brand-500 bg-white">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              type="text"
              value={form.customer_name}
              onChange={e => handleCustomerNameChange(e.target.value)}
              readOnly={customerContactLocked}
              onFocus={() => {
                if (customerContactLocked) return
                if (form.customer_name.trim()) {
                  setCustomerSearch(form.customer_name)
                  setShowSuggestions(true)
                }
              }}
              placeholder="Enter customer name"
              autoComplete="off"
              className={`block w-full border-0 bg-transparent h-[42px] py-0 pl-9 pr-3 text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-sm sm:leading-6 outline-none ${customerContactLocked ? 'cursor-default' : ''}`}
            />
          </div>
          {errors.customer_name && <p className="mt-1 text-xs text-rose-500">{errors.customer_name}</p>}

          {form.customer_name.trim() && !showSuggestions && (
            <p className={`mt-1 text-[11px] font-medium truncate ${customerMode === 'existing' ? 'text-brand-600' : 'text-amber-600'}`}>
              {customerMode === 'existing'
                ? (customerContactLocked
                  ? 'Linked customer (contact hidden)'
                  : 'Linked to existing customer')
                : 'New customer will be created'}
            </p>
          )}

          {showSuggestions && !customerContactLocked && trimmedSearch.length >= 1 && (
            <div className="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
              {isSearchingCustomers && (
                <div className="px-3 py-2 text-xs text-slate-400">Searching...</div>
              )}

              {!isSearchingCustomers && customerSuggestions.map(c => (
                <button
                  key={c.id}
                  type="button"
                  onClick={() => handleSelectCustomer(c)}
                  className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-slate-50 border-b border-slate-50"
                >
                  <span className="h-7 w-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold">
                    {(c.name || 'C').charAt(0)}
                  </span>
                  <span className="min-w-0">
                    <span className="block text-sm font-semibold text-slate-800 truncate">{c.name}</span>
                    {c.phone ? (
                      <span className="block text-xs text-slate-400">{c.phone}</span>
                    ) : c.has_phone ? (
                      <span className="block text-xs text-slate-400">Contact on file</span>
                    ) : null}
                  </span>
                </button>
              ))}

              {!isSearchingCustomers && customerSuggestions.length === 0 && (
                <div className="px-3 py-2 text-xs text-slate-500 border-b border-slate-50">
                  No customer found for “{trimmedSearch}”
                </div>
              )}

              <button
                type="button"
                onClick={handleUseAsNewCustomer}
                className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-amber-50 bg-amber-50/40"
              >
                <span className="h-7 w-7 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                  <User className="w-3.5 h-3.5" />
                </span>
                <span className="min-w-0">
                  <span className="block text-sm font-semibold text-amber-800 truncate">
                    Create new customer “{trimmedSearch}”
                  </span>
                </span>
              </button>
            </div>
          )}
        </div>

        <BaseInput
          label="Customer Phone"
          modelValue={form.customer_phone}
          onUpdateModelValue={v => update({ customer_phone: v, ...(customerMode === 'existing' ? {} : { customer_id: '' }) })}
          error={errors.customer_phone}
          placeholder={customerMode === 'existing' && !form.customer_phone ? 'On file / hidden' : '+91 98765 43210'}
        />

        {!lockBranch && (
          <BaseSelect
            label="Branch"
            value={form.branch_id}
            onChange={e => update({ branch_id: e.target.value })}
            options={[
              { value: '', label: 'Select branch' },
              ...branches.map(b => ({ value: String(b.id), label: b.name })),
            ]}
          />
        )}

        <BaseInput
          label="Date"
          type="date"
          modelValue={form.date}
          onUpdateModelValue={v => update({
            date: v,
            status: resolveDefaultAppointmentStatus({
              type: form.type,
              date: v,
              startTime: form.services?.[0]?.start_time,
              isEditing: false,
              current: form.status,
            }),
          })}
          error={errors.date}
        />

        {showStatusSelect && (
          <BaseSelect
            label="Status"
            value={form.status}
            onChange={e => update({ status: e.target.value })}
            options={STATUS_OPTIONS}
          />
        )}
      </div>

      {/* Services Section — Clean Table-Style Multi-Service List */}
      <div className="mt-4 space-y-2">
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-2">
              <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Services</h4>
              <span className="inline-flex items-center justify-center h-5 px-2 rounded-full bg-brand-100 text-brand-800 text-[11px] font-bold">
                {form.services.length}
              </span>
            </div>
            <p className="mt-0.5 text-[11px] text-slate-400">A product on a service is a variant, not shop stock.</p>
          </div>
          <BaseButton variant="ghost" size="sm" leftIcon={Plus} onClick={addServiceLine}>Add service</BaseButton>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-2.5 space-y-2">
          {/* Table Header Row — Visible on Desktop/Web */}
          <div className="hidden lg:grid grid-cols-12 gap-2 px-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            <div className="col-span-2">Staff</div>
            <div className="col-span-3">Service</div>
            <div className="col-span-2">Variant</div>
            <div className="col-span-2">Price ({fmt.symbol()})</div>
            <div className="col-span-1">Start</div>
            <div className="col-span-2">End / Actions</div>
          </div>

          {form.services.map((line, index) => {
            const selectedService = catalogServices.find(s => String(s.service_id) === String(line.service_id))
            const serviceProducts = dedupeServiceProducts(selectedService?.products ?? [])
            const staffForBranch = form.branch_id
              ? staff.filter(s => !s.branch_id || String(s.branch_id) === String(form.branch_id))
              : staff
            const staffLocked = lockStaffRole || (line.is_staff_locked && Boolean(line.staff_id))
            const staffName = staff.find(s => String(s.id) === String(line.staff_id))?.name
              || (lockStaffRole ? 'You' : '—')

            return (
              <div key={index} className="relative rounded-xl bg-white border border-slate-200/80 p-2.5 lg:p-2 shadow-sm transition-all hover:border-brand-300">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2 items-center">
                  {/* Staff */}
                  <div className="lg:col-span-2">
                    <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Staff</label>
                    {!staffLocked ? (
                      <BaseSelect
                        value={line.staff_id}
                        onChange={e => updateServiceLine(index, { staff_id: e.target.value })}
                        error={errors[`services.${index}.staff_id`]}
                        options={[
                          { value: '', label: 'Any staff' },
                          ...staffForBranch.map(s => ({ value: String(s.id), label: s.name })),
                        ]}
                      />
                    ) : (
                      <div className="flex items-center h-[42px] w-full rounded-xl ring-1 ring-inset ring-slate-300 bg-slate-100/90 px-3 text-sm font-medium text-slate-900 truncate">
                        {staffName}
                      </div>
                    )}
                  </div>

                  {/* Service */}
                  <div className="lg:col-span-3">
                    <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Service</label>
                    <BaseSelect
                      value={line.service_id}
                      onChange={e => handleServiceChange(index, e.target.value)}
                      error={errors[`services.${index}.service_id`]}
                      options={[
                        { value: '', label: 'Select service' },
                        ...catalogServices.map(s => ({
                          value: String(s.service_id),
                          label: `${s.name}${s.price != null ? ` — ${money(s.price)}` : ''}`,
                        })),
                      ]}
                    />
                  </div>

                  {/* Product */}
                  <div className="lg:col-span-2">
                    <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Variant</label>
                    <BaseSelect
                      key={`service-variant-${index}-${line.service_id || 'none'}`}
                      value={line.product_id}
                      onChange={e => handleProductChange(index, e.target.value)}
                      options={[
                        {
                          value: '',
                          label: selectedService?.price != null
                            ? `Service only — ${money(selectedService.price)}`
                            : (serviceProducts.length ? 'No variant' : 'No variants'),
                        },
                        ...serviceProducts.map(p => ({
                          value: String(p.product_id),
                          label: `${p.name}${p.price != null ? ` — ${money(p.price)}` : ''}`,
                        })),
                      ]}
                    />
                  </div>

                  {/* Price */}
                  <div className="lg:col-span-2">
                    <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Price ({fmt.symbol()})</label>
                    <BaseInput
                      type="number"
                      modelValue={line.price}
                      onUpdateModelValue={v => updateServiceLine(index, { price: v })}
                    />
                  </div>

                  {/* Start Time */}
                  <div className="lg:col-span-1">
                    <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Start</label>
                    <BaseInput
                      type="time"
                      modelValue={line.start_time}
                      onUpdateModelValue={v => updateServiceLine(index, { start_time: v })}
                      error={errors[`services.${index}.start_time`]}
                    />
                  </div>

                  {/* End Time & Actions */}
                  <div className="lg:col-span-2 flex items-center gap-1.5">
                    <div className="flex-1 min-w-0">
                      <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">End</label>
                      <div className="flex items-center justify-between h-[42px] w-full rounded-xl ring-1 ring-inset ring-slate-300 bg-slate-100/90 px-2.5 text-xs font-medium text-slate-900">
                        <span className="truncate">{line.end_time || '—'}</span>
                        {line.duration_minutes ? (
                          <span className="text-[10px] text-slate-400 shrink-0 ml-1">{line.duration_minutes}m</span>
                        ) : null}
                      </div>
                    </div>

                    {!lockStaffRole && (
                      <button
                        type="button"
                        onClick={() => updateServiceLine(index, { is_staff_locked: !line.is_staff_locked })}
                        className={`h-[42px] w-[36px] shrink-0 inline-flex items-center justify-center rounded-xl border transition-colors ${line.is_staff_locked ? 'border-amber-400 bg-amber-50 text-amber-600' : 'border-slate-200 bg-slate-50 text-slate-400 hover:text-slate-600'}`}
                        title={line.is_staff_locked ? 'Preferred staff locked' : 'Click to lock preferred staff'}
                      >
                        <Lock className="w-4 h-4" />
                      </button>
                    )}

                    {(form.services.length > 1 || form.products.length > 0) && (
                      <button
                        type="button"
                        onClick={() => removeServiceLine(index)}
                        className="h-[42px] w-[36px] shrink-0 inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors"
                        title="Remove service"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    )}
                  </div>
                </div>
              </div>
            )
          })}
        </div>

        {errors.services && <p className="text-xs text-rose-500">{errors.services}</p>}
      </div>

      {/* Retail products sold with this visit (or on their own) */}
      <div className="mt-4 space-y-2">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Products Sold</h4>
            {form.products.length > 0 && (
              <span className="inline-flex items-center justify-center h-5 px-2 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                {form.products.length}
              </span>
            )}
          </div>
          <BaseButton
            variant="ghost"
            size="sm"
            leftIcon={Plus}
            onClick={addRetailLine}
            disabled={!retailBranchId || retailProducts.length === 0}
          >
            Add product
          </BaseButton>
        </div>

        {!retailBranchId ? (
          <p className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-3 py-3 text-xs text-slate-500">
            Select a branch to sell products from its stock.
          </p>
        ) : !loadingRetail && retailProducts.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-3 py-3 text-xs text-slate-500">
            No products are priced for sale at {branchName}. A product needs branch stock
            with a selling price before it can be sold here — set that under Inventory → Stock.
          </p>
        ) : form.products.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-3 py-3 text-xs text-slate-500">
            No products on this bill. Add one to sell retail stock alongside the service.
          </p>
        ) : (
          <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-2.5 space-y-2">
            <div className="hidden lg:grid grid-cols-12 gap-2 px-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">
              <div className="col-span-5">Product</div>
              <div className="col-span-2">Sold by</div>
              <div className="col-span-1">Qty</div>
              <div className="col-span-2">Unit ({fmt.symbol()})</div>
              <div className="col-span-2">Total / Actions</div>
            </div>

            {form.products.map((line, index) => {
              const selected = retailProducts.find(p => String(p.product_id) === String(line.product_id))
              const staffForBranch = form.branch_id
                ? staff.filter(s => !s.branch_id || String(s.branch_id) === String(form.branch_id))
                : staff
              const quantity = Math.max(1, Number(line.quantity || 1))
              const lineTotal = Number(line.unit_price || 0) * quantity
              const overStock = selected?.stock_on_hand != null && quantity > selected.stock_on_hand

              return (
                <div key={index} className="relative rounded-xl bg-white border border-slate-200/80 p-2.5 lg:p-2 shadow-sm transition-all hover:border-emerald-300">
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2 items-center">
                    <div className="lg:col-span-5">
                      <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Product</label>
                      <BaseSelect
                        value={line.product_id}
                        onChange={e => handleRetailProductChange(index, e.target.value)}
                        error={errors[`products.${index}.product_id`]}
                        options={[
                          { value: '', label: retailProducts.length ? 'Select product' : 'No stocked products' },
                          ...retailProducts.map(p => ({
                            value: String(p.product_id),
                            label: `${p.name} — ${money(p.price)} (${p.stock_on_hand} in stock)`,
                          })),
                        ]}
                      />
                    </div>

                    <div className="lg:col-span-2">
                      <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Sold by</label>
                      {!lockStaffRole ? (
                        <BaseSelect
                          value={line.staff_id}
                          onChange={e => updateRetailLine(index, { staff_id: e.target.value })}
                          options={[
                            { value: '', label: 'Unassigned' },
                            ...staffForBranch.map(s => ({ value: String(s.id), label: s.name })),
                          ]}
                        />
                      ) : (
                        <div className="flex items-center h-[42px] w-full rounded-xl ring-1 ring-inset ring-slate-300 bg-slate-100/90 px-3 text-sm font-medium text-slate-900 truncate">
                          You
                        </div>
                      )}
                    </div>

                    <div className="lg:col-span-1">
                      <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Qty</label>
                      <BaseInput
                        type="number"
                        min="1"
                        modelValue={line.quantity}
                        onUpdateModelValue={v => updateRetailLine(index, { quantity: v })}
                      />
                    </div>

                    <div className="lg:col-span-2">
                      <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Unit ({fmt.symbol()})</label>
                      <BaseInput
                        type="number"
                        modelValue={line.unit_price}
                        onUpdateModelValue={v => updateRetailLine(index, { unit_price: v })}
                      />
                    </div>

                    <div className="lg:col-span-2 flex items-center gap-1.5">
                      <div className="flex-1 min-w-0">
                        <label className="block text-xs font-semibold text-slate-700 mb-1 lg:hidden">Total</label>
                        <div className="flex items-center h-[42px] w-full rounded-xl ring-1 ring-inset ring-slate-300 bg-slate-100/90 px-2.5 text-xs font-bold tabular-nums text-slate-900">
                          <span className="truncate">{money(lineTotal)}</span>
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() => removeRetailLine(index)}
                        className="h-[42px] w-[36px] shrink-0 inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors"
                        title="Remove product"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>

                  {overStock && (
                    <p className="mt-1.5 text-[11px] font-semibold text-amber-600">
                      Only {selected.stock_on_hand} in stock at this branch.
                    </p>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {errors.branch_id && <p className="text-xs text-rose-500">{errors.branch_id}</p>}
      </div>

      {/* Notes full-width in body */}
      <div className="mt-3">
        <label className="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
        <div className="relative flex items-center rounded-xl shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-inset focus-within:ring-brand-500 bg-white">
          <input
            type="text"
            value={form.notes}
            onChange={e => update({ notes: e.target.value })}
            placeholder="Optional notes or instructions..."
            className="block w-full border-0 bg-transparent h-[42px] py-0 px-3 text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-sm sm:leading-6 outline-none"
          />
        </div>
      </div>

      {(isWalkIn || isProductOnly || form.status === 'completed' || form.collect_payment || !isEditing) && (
        <div className="mt-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 space-y-3">
          <p className="text-sm font-semibold text-slate-700">Payment</p>
          <div className="grid grid-cols-2 gap-2">
            <button
              type="button"
              onClick={() => update({ collect_payment: false, amount_paid: '' })}
              className={`rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition ${
                !form.collect_payment
                  ? 'border-amber-300 bg-amber-50 text-amber-800'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
              }`}
            >
              Pending payment
              <span className="mt-0.5 block text-[11px] font-medium text-slate-500">Bill now, collect later</span>
            </button>
            <button
              type="button"
              onClick={() => update({
                collect_payment: true,
                amount_paid: form.amount_paid === '' ? String(grandTotal) : form.amount_paid,
              })}
              className={`rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition ${
                form.collect_payment
                  ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
              }`}
            >
              Collect now
              <span className="mt-0.5 block text-[11px] font-medium text-slate-500">Cash, card, UPI, etc.</span>
            </button>
          </div>
          {form.collect_payment && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Method</label>
                <select
                  value={form.payment_method}
                  onChange={(e) => update({ payment_method: e.target.value })}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                >
                  {PAYMENT_METHODS.map((m) => (
                    <option key={m.value} value={m.value}>{m.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Amount ({fmt.symbol()})</label>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={form.amount_paid}
                  onChange={(e) => update({ amount_paid: e.target.value })}
                  placeholder={String(grandTotal)}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                />
              </div>
            </div>
          )}
        </div>
      )}
    </BaseModal>
  )
}
