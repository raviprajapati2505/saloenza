import React from 'react'
import { Controller, useFormContext } from 'react-hook-form'
import { Mail, User } from 'lucide-react'
import BaseInput from '../../../../components/ui/BaseInput.jsx'
import FormToggle from '../../../../components/ui/FormToggle.jsx'
import PhoneWithCountryInput from '../../../../components/inputs/PhoneWithCountryInput.jsx'

export default function OwnerInfoStep() {
  const {
    register,
    control,
    formState: { errors },
  } = useFormContext()

  const ownerErrors = errors.owner || {}

  return (
    <div>
      <div className="mb-6 flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
          <User className="h-5 w-5" />
        </div>
        <div>
          <h2 className="text-lg font-semibold text-slate-900">Owner User</h2>
          <p className="text-sm text-slate-500">Primary owner account credentials</p>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <BaseInput
            id="owner-first_name"
            label="First Name"
            required
            placeholder="Enter first name"
            prefix={User}
            error={ownerErrors.first_name?.message}
            {...register('owner.first_name')}
          />
        </div>

        <div>
          <BaseInput
            id="owner-last_name"
            label="Last Name"
            required
            placeholder="Enter last name"
            prefix={User}
            error={ownerErrors.last_name?.message}
            {...register('owner.last_name')}
          />
        </div>

        <div className="sm:col-span-2">
          <BaseInput
            id="owner-email"
            label="Email"
            required
            type="email"
            placeholder="owner@example.com"
            prefix={Mail}
            error={ownerErrors.email?.message}
            {...register('owner.email')}
          />
        </div>

        <div>
          <Controller
            name="owner.phone"
            control={control}
            render={({ field }) => (
              <PhoneWithCountryInput
                id="owner-phone"
                label="Phone"
                required
                value={field.value || ''}
                onChange={field.onChange}
                error={ownerErrors.phone?.message}
              />
            )}
          />
        </div>

        <div>
          <Controller
            name="owner.whatsapp"
            control={control}
            render={({ field }) => (
              <PhoneWithCountryInput
                id="owner-whatsapp"
                label="WhatsApp"
                optional
                value={field.value || ''}
                onChange={field.onChange}
                error={ownerErrors.whatsapp?.message}
                placeholder="WhatsApp number"
              />
            )}
          />
        </div>

        <div className="sm:col-span-2">
          <BaseInput
            id="owner-password"
            label="Password"
            type="password"
            toggleableType
            placeholder="Optional — auto-generated if blank"
            error={ownerErrors.password?.message}
            {...register('owner.password')}
          />
        </div>

        <div className="sm:col-span-2 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-700">Owner Account Active</p>
            <p className="text-xs text-slate-500">Allow this owner to log in immediately</p>
          </div>
          <Controller
            name="owner.active"
            control={control}
            render={({ field }) => (
              <FormToggle
                id="owner-active"
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
