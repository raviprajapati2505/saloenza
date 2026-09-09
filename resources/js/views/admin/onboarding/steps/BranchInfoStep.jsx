import React, { useMemo, useRef, useState } from 'react'
import { Controller, useFormContext } from 'react-hook-form'
import { MapPin } from 'lucide-react'
import BaseInput from '../../../../components/ui/BaseInput.jsx'
import FormToggle from '../../../../components/ui/FormToggle.jsx'

const INDIAN_STATES = [
  'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar',
  'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa',
  'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka',
  'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
  'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
  'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
]

const fieldClass =
  'block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition'

export default function BranchInfoStep() {
  const {
    register,
    control,
    formState: { errors },
  } = useFormContext()

  const branchErrors = errors.branch || {}

  const [stateSearch, setStateSearch] = useState('')
  const [dropdownOpen, setDropdownOpen] = useState(false)
  const dropdownRef = useRef(null)

  const filteredStates = useMemo(() => {
    const q = stateSearch.trim().toLowerCase()
    return q ? INDIAN_STATES.filter((s) => s.toLowerCase().includes(q)) : INDIAN_STATES
  }, [stateSearch])

  return (
    <div>
      <div className="mb-6 flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
          <MapPin className="h-5 w-5" />
        </div>
        <div>
          <h2 className="text-lg font-semibold text-slate-900">Branch Information</h2>
          <p className="text-sm text-slate-500">Primary branch location and address</p>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        {/* Branch Name */}
        <div className="sm:col-span-2">
          <BaseInput
            id="branch-name"
            label="Branch Name"
            required
            placeholder="e.g. Main Branch"
            error={branchErrors.name?.message}
            {...register('branch.name')}
          />
        </div>

        {/* Address Line 1 */}
        <div className="sm:col-span-2">
          <BaseInput
            id="branch-address_line_1"
            label="Address Line 1"
            required
            placeholder="Street, area, landmark"
            error={branchErrors.address_line_1?.message}
            {...register('branch.address_line_1')}
          />
        </div>

        {/* Address Line 2 */}
        <div className="sm:col-span-2">
          <BaseInput
            id="branch-address_line_2"
            label="Address Line 2"
            placeholder="Apartment, suite, floor (optional)"
            error={branchErrors.address_line_2?.message}
            {...register('branch.address_line_2')}
          />
        </div>

        {/* City */}
        <div>
          <BaseInput
            id="branch-city"
            label="City"
            required
            placeholder="Enter city"
            error={branchErrors.city?.message}
            {...register('branch.city')}
          />
        </div>

        {/* State — searchable dropdown */}
        <div className="relative">
          <label className="block text-sm font-medium text-slate-700 mb-1.5">
            State <span className="text-rose-500 ml-0.5">*</span>
          </label>
          <Controller
            name="branch.state"
            control={control}
            render={({ field }) => (
              <>
                <input
                  id="branch-state"
                  type="text"
                  autoComplete="off"
                  className={fieldClass}
                  placeholder="Search state…"
                  value={dropdownOpen ? stateSearch : (field.value || '')}
                  onFocus={() => {
                    setStateSearch(field.value || '')
                    setDropdownOpen(true)
                  }}
                  onBlur={() => setTimeout(() => setDropdownOpen(false), 150)}
                  onChange={(e) => setStateSearch(e.target.value)}
                />
                {dropdownOpen && (
                  <div
                    ref={dropdownRef}
                    className="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg"
                  >
                    {filteredStates.length > 0 ? (
                      filteredStates.map((state) => (
                        <button
                          key={state}
                          type="button"
                          className="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-brand-50 hover:text-brand-700"
                          onMouseDown={() => {
                            field.onChange(state)
                            setStateSearch(state)
                            setDropdownOpen(false)
                          }}
                        >
                          {state}
                        </button>
                      ))
                    ) : (
                      <p className="px-3 py-2 text-sm text-slate-500">No states found.</p>
                    )}
                  </div>
                )}
              </>
            )}
          />
          {branchErrors.state?.message && (
            <p className="mt-1.5 text-sm text-rose-500 font-medium">{branchErrors.state.message}</p>
          )}
        </div>

        {/* Pincode */}
        <div>
          <BaseInput
            id="branch-pincode"
            label="Pincode"
            required
            placeholder="6-digit pincode"
            maxLength={6}
            error={branchErrors.pincode?.message}
            {...register('branch.pincode')}
          />
        </div>

        {/* Country */}
        <div>
          <BaseInput
            id="branch-country"
            label="Country"
            required
            placeholder="Enter country"
            error={branchErrors.country?.message}
            {...register('branch.country')}
          />
        </div>

        {/* Active Toggle */}
        <div className="sm:col-span-2 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-700">Branch Active</p>
            <p className="text-xs text-slate-500">Enable or disable this branch</p>
          </div>
          <Controller
            name="branch.active"
            control={control}
            render={({ field }) => (
              <FormToggle
                id="branch-active"
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
