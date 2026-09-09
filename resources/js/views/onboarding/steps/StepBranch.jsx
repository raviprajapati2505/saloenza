import React, { forwardRef, useEffect, useImperativeHandle, useMemo, useState } from 'react'
import WeeklyHoursInput from '../../../components/inputs/WeeklyHoursInput.jsx'

const indianStates = [
  'Andhra Pradesh',
  'Arunachal Pradesh',
  'Assam',
  'Bihar',
  'Chhattisgarh',
  'Goa',
  'Gujarat',
  'Haryana',
  'Himachal Pradesh',
  'Jharkhand',
  'Karnataka',
  'Kerala',
  'Madhya Pradesh',
  'Maharashtra',
  'Manipur',
  'Meghalaya',
  'Mizoram',
  'Nagaland',
  'Odisha',
  'Punjab',
  'Rajasthan',
  'Sikkim',
  'Tamil Nadu',
  'Telangana',
  'Tripura',
  'Uttar Pradesh',
  'Uttarakhand',
  'West Bengal',
  'Andaman and Nicobar Islands',
  'Chandigarh',
  'Dadra and Nagar Haveli and Daman and Diu',
  'Delhi',
  'Jammu and Kashmir',
  'Ladakh',
  'Lakshadweep',
  'Puducherry',
]

const createDefaultHours = () => ({
  monday: { enabled: true, open: '09:00', close: '20:00' },
  tuesday: { enabled: true, open: '09:00', close: '20:00' },
  wednesday: { enabled: true, open: '09:00', close: '20:00' },
  thursday: { enabled: true, open: '09:00', close: '20:00' },
  friday: { enabled: true, open: '09:00', close: '20:00' },
  saturday: { enabled: true, open: '09:00', close: '20:00' },
  sunday: { enabled: false, open: '09:00', close: '18:00' },
})

const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400'
const areaClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400'

const StepBranch = forwardRef(function StepBranch({ modelValue = {}, onUpdateModelValue }, ref) {
  const [form, setForm] = useState({
    branchName: modelValue.branchName || '',
    address: modelValue.address || '',
    city: modelValue.city || '',
    state: modelValue.state || '',
    phone: modelValue.phone || '',
    whatsapp: modelValue.whatsapp || '',
    gstNumber: modelValue.gstNumber || '',
    hours: modelValue.hours || createDefaultHours(),
  })
  const [errors, setErrors] = useState({
    branchName: '',
    address: '',
    city: '',
    state: '',
    phone: '',
    gstNumber: '',
  })
  const [stateDropdownOpen, setStateDropdownOpen] = useState(false)
  const [stateSearch, setStateSearch] = useState(modelValue.state || '')

  const filteredStates = useMemo(() => {
    const query = stateSearch.trim().toLowerCase()
    if (!query) return indianStates
    return indianStates.filter((state) => state.toLowerCase().includes(query))
  }, [stateSearch])

  const selectState = (state) => {
    setForm((previous) => ({ ...previous, state }))
    setStateSearch(state)
    setStateDropdownOpen(false)
  }

  const gstRegex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/

  const validate = () => {
    const nextErrors = {
      branchName: form.branchName ? '' : 'Branch name is required.',
      address: form.address ? '' : 'Address is required.',
      city: form.city ? '' : 'City is required.',
      state: form.state ? '' : 'State is required.',
      phone: form.phone ? '' : 'Phone is required.',
      gstNumber: form.gstNumber && !gstRegex.test(form.gstNumber) ? 'Invalid GST format. Example: 27ABCDE1234F1Z5' : '',
    }

    setErrors(nextErrors)
    return Object.values(nextErrors).every((value) => !value)
  }

  const getData = () => ({ ...form, state: form.state || stateSearch })

  useImperativeHandle(ref, () => ({
    validate,
    getData,
  }), [form, stateSearch])

  useEffect(() => {
    onUpdateModelValue?.(getData())
  }, [form, stateSearch, onUpdateModelValue])

  return (
    <div>
      <h2 className="text-2xl font-semibold text-slate-900">Your Salon Branch</h2>
      <p className="mt-1 text-sm text-slate-500">
        Set your primary location details and weekly operating hours.
      </p>

      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label className="mb-1 block text-sm font-medium text-slate-700">Branch Name</label>
          <input
            value={form.branchName}
            type="text"
            className={fieldClass}
            placeholder="e.g. Main Branch"
            onChange={(event) => setForm((previous) => ({ ...previous, branchName: event.target.value }))}
          />
          {errors.branchName ? <p className="mt-1 text-xs text-rose-600">{errors.branchName}</p> : null}
        </div>

        <div className="sm:col-span-2">
          <label className="mb-1 block text-sm font-medium text-slate-700">Full Address</label>
          <textarea
            value={form.address}
            rows="3"
            className={areaClass}
            placeholder="Street, area, landmark"
            onChange={(event) => setForm((previous) => ({ ...previous, address: event.target.value }))}
          />
          {errors.address ? <p className="mt-1 text-xs text-rose-600">{errors.address}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">City</label>
          <input
            value={form.city}
            type="text"
            className={fieldClass}
            placeholder="Enter city"
            onChange={(event) => setForm((previous) => ({ ...previous, city: event.target.value }))}
          />
          {errors.city ? <p className="mt-1 text-xs text-rose-600">{errors.city}</p> : null}
        </div>

        <div className="relative">
          <label className="mb-1 block text-sm font-medium text-slate-700">State</label>
          <input
            value={stateSearch}
            type="text"
            className={fieldClass}
            placeholder="Search state"
            onFocus={() => setStateDropdownOpen(true)}
            onChange={(event) => setStateSearch(event.target.value)}
          />
          {stateDropdownOpen ? (
            <div className="absolute z-10 mt-1 max-h-44 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg">
              {filteredStates.map((state) => (
                <button
                  key={state}
                  type="button"
                  className="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                  onClick={() => selectState(state)}
                >
                  {state}
                </button>
              ))}
              {filteredStates.length === 0 ? (
                <p className="px-3 py-2 text-sm text-slate-500">No states found.</p>
              ) : null}
            </div>
          ) : null}
          {errors.state ? <p className="mt-1 text-xs text-rose-600">{errors.state}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Phone</label>
          <input
            value={form.phone}
            type="text"
            className={fieldClass}
            placeholder="+91 98765 43210"
            onChange={(event) => setForm((previous) => ({ ...previous, phone: event.target.value }))}
          />
          {errors.phone ? <p className="mt-1 text-xs text-rose-600">{errors.phone}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">WhatsApp Number (optional)</label>
          <input
            value={form.whatsapp}
            type="text"
            className={fieldClass}
            placeholder="+91 98765 43210"
            onChange={(event) => setForm((previous) => ({ ...previous, whatsapp: event.target.value }))}
          />
        </div>

        <div className="sm:col-span-2">
          <label className="mb-1 block text-sm font-medium text-slate-700">GST Number (optional)</label>
          <input
            value={form.gstNumber}
            type="text"
            className={`${fieldClass} uppercase`}
            placeholder="e.g. 27ABCDE1234F1Z5"
            onChange={(event) => setForm((previous) => ({ ...previous, gstNumber: event.target.value }))}
          />
          {errors.gstNumber ? <p className="mt-1 text-xs text-rose-600">{errors.gstNumber}</p> : null}
        </div>
      </div>

      <div className="mt-6">
        <h3 className="text-sm font-semibold text-slate-800">Opening Hours</h3>
        <p className="mb-3 mt-1 text-xs text-slate-500">
          Toggle each day and set opening/closing times.
        </p>
        <WeeklyHoursInput
          modelValue={form.hours}
          onUpdateModelValue={(hours) => setForm((previous) => ({ ...previous, hours }))}
        />
      </div>
    </div>
  )
})

export default StepBranch
