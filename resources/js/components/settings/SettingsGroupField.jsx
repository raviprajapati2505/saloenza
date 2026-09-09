import React from 'react'
import BaseInput from '../ui/BaseInput.jsx'

function BooleanField({ setting, value, onChange, disabled }) {
  const checked = value === true || value === 1 || value === '1'

  return (
    <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
      <input
        type="checkbox"
        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange(event.target.checked)}
      />
      <span>
        <span className="block text-sm font-semibold text-slate-900">{setting.label}</span>
        {setting.description ? (
          <span className="mt-1 block text-xs leading-5 text-slate-500">{setting.description}</span>
        ) : null}
      </span>
    </label>
  )
}

function SelectField({ setting, value, onChange, error, disabled }) {
  return (
    <div>
      <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">
        {setting.label}
      </label>
      <select
        value={value ?? ''}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:bg-slate-50"
      >
        {(setting.options || []).map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error ? <p className="mt-1.5 text-xs font-semibold text-rose-500">{error}</p> : null}
      {setting.description ? (
        <p className="mt-1.5 text-xs leading-5 text-slate-500">{setting.description}</p>
      ) : null}
    </div>
  )
}

function ColorField({ setting, value, onChange, error, disabled }) {
  return (
    <div>
      <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">
        {setting.label}
      </label>
      <div className="flex items-center gap-3">
        <input
          type="color"
          value={value || '#E0229A'}
          disabled={disabled}
          onChange={(event) => onChange(event.target.value)}
          className="h-11 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1 disabled:cursor-not-allowed"
        />
        <BaseInput
          modelValue={value ?? ''}
          onUpdateModelValue={onChange}
          error={error}
          disabled={disabled}
          placeholder="#E0229A"
        />
      </div>
      {setting.description ? (
        <p className="mt-1.5 text-xs leading-5 text-slate-500">{setting.description}</p>
      ) : null}
    </div>
  )
}

function TextareaField({ setting, value, onChange, error, disabled }) {
  return (
    <div>
      <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">
        {setting.label}
      </label>
      <textarea
        value={value ?? ''}
        disabled={disabled}
        rows={4}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:bg-slate-50"
      />
      {error ? <p className="mt-1.5 text-xs font-semibold text-rose-500">{error}</p> : null}
      {setting.description ? (
        <p className="mt-1.5 text-xs leading-5 text-slate-500">{setting.description}</p>
      ) : null}
    </div>
  )
}

export default function SettingsGroupField({
  setting,
  value,
  onChange,
  error,
  disabled = false,
  showSource = false,
}) {
  const type = setting.type || 'string'

  if (type === 'file') {
    return null
  }

  const sourceBadge = showSource && setting.source ? (
    <span className={`mb-2 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
      setting.is_custom
        ? 'bg-emerald-50 text-emerald-700'
        : 'bg-slate-100 text-slate-500'
    }`}>
      {setting.is_custom ? 'Custom' : 'Platform default'}
    </span>
  ) : null

  if (type === 'boolean') {
    return (
      <div>
        {sourceBadge}
        <BooleanField setting={setting} value={value} onChange={onChange} disabled={disabled} />
      </div>
    )
  }

  if (type === 'select') {
    return (
      <div>
        {sourceBadge}
        <SelectField setting={setting} value={value} onChange={onChange} error={error} disabled={disabled} />
      </div>
    )
  }

  if (type === 'color') {
    return (
      <div>
        {sourceBadge}
        <ColorField setting={setting} value={value} onChange={onChange} error={error} disabled={disabled} />
      </div>
    )
  }

  if (type === 'textarea') {
    return (
      <div>
        {sourceBadge}
        <TextareaField setting={setting} value={value} onChange={onChange} error={error} disabled={disabled} />
      </div>
    )
  }

  return (
    <div>
      {sourceBadge}
      <BaseInput
        label={setting.label}
        type={type === 'secret' ? 'password' : type === 'number' ? 'number' : 'text'}
        min={setting.min}
        max={setting.max}
        step={setting.step ?? 1}
        modelValue={value ?? ''}
        onUpdateModelValue={onChange}
        error={error}
        disabled={disabled}
      />
      {setting.description ? (
        <p className="mt-1.5 text-xs leading-5 text-slate-500">{setting.description}</p>
      ) : null}
    </div>
  )
}
