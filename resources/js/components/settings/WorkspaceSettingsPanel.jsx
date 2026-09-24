import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { ImageIcon, Loader2, RotateCcw, Save, Settings2 } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import SettingsGroupField from './SettingsGroupField.jsx'
import {
  deletePlatformLogo,
  fetchPlatformSettings,
  updatePlatformSettingsGroup,
  uploadPlatformLogo,
} from '../../services/adminPlatformSettingsService.js'
import { pushToast } from '../../stores/toast.js'
import { setPlatformBranding } from '../../stores/platformBranding.js'

function PlatformLogoSection({ group, onBrandingUpdated }) {
  const fileInputRef = useRef(null)
  const [uploading, setUploading] = useState(false)
  const logoSetting = group.settings?.logo_path
  const logoUrl = logoSetting?.value || null
  const hasCustomLogo = Boolean(logoUrl && !String(logoUrl).includes('/images/glowsuite-logo.png'))

  const handleUpload = async (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    if (file.size > 2 * 1024 * 1024) {
      pushToast('Logo must be under 2 MB.', 'error')
      return
    }

    setUploading(true)
    try {
      const payload = await uploadPlatformLogo(file)
      if (payload?.branding) {
        setPlatformBranding(payload.branding)
      }
      onBrandingUpdated(payload)
      pushToast('Platform logo updated successfully.', 'success')
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to upload logo.', 'error')
    } finally {
      setUploading(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }

  const handleReset = async () => {
    setUploading(true)
    try {
      const payload = await deletePlatformLogo()
      if (payload?.branding) {
        setPlatformBranding(payload.branding)
      }
      onBrandingUpdated(payload)
      pushToast('Platform logo reset to default.', 'success')
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to reset logo.', 'error')
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 md:col-span-2">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-4">
          <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
            {logoUrl ? (
              <img src={logoUrl} alt="Platform logo" className="h-full w-full object-contain" />
            ) : (
              <ImageIcon className="h-7 w-7 text-slate-300" />
            )}
          </div>
          <div>
            <p className="text-sm font-bold text-slate-900">Platform Logo</p>
            <p className="mt-1 text-xs text-slate-500">
              {hasCustomLogo ? 'Using your uploaded platform logo.' : 'Using bundled default logo.'}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <BaseButton
            type="button"
            variant="secondary"
            onClick={() => fileInputRef.current?.click()}
            disabled={uploading}
            leftIcon={uploading ? Loader2 : ImageIcon}
          >
            Upload Logo
          </BaseButton>
          {hasCustomLogo ? (
            <BaseButton
              type="button"
              variant="ghost"
              onClick={handleReset}
              disabled={uploading}
              leftIcon={RotateCcw}
            >
              Use Default
            </BaseButton>
          ) : null}
          <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleUpload} />
        </div>
      </div>
    </div>
  )
}

function SettingsGroupSection({ group, onSaved, onBrandingUpdated }) {
  const [values, setValues] = useState({})
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    const next = {}
    Object.values(group.settings || {}).forEach((setting) => {
      next[setting.key] = setting.value ?? ''
    })
    setValues(next)
    setErrors({})
  }, [group])

  const updateValue = (key, value) => {
    setValues((previous) => ({ ...previous, [key]: value }))
    setErrors((previous) => ({ ...previous, [key]: '' }))
  }

  const save = async () => {
    setSaving(true)
    setErrors({})
    try {
      const payload = await updatePlatformSettingsGroup(group.group, values)
      onSaved(payload)
      pushToast(`${group.label} updated successfully.`, 'success')
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      const nextErrors = {}
      Object.entries(serverErrors).forEach(([field, messages]) => {
        const key = field.replace(/^settings\./, '')
        nextErrors[key] = Array.isArray(messages) ? messages[0] : messages
      })
      setErrors(nextErrors)
      pushToast(error?.response?.data?.message || 'Unable to save settings.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const settings = useMemo(
    () => Object.values(group.settings || {}).filter((setting) => setting.type !== 'file'),
    [group.settings],
  )

  return (
    <div className="rounded-2xl border border-slate-200/80 bg-slate-50/40 p-5 md:p-6">
      <div className="mb-5 flex items-start gap-3">
        <div className="rounded-xl border border-brand-100 bg-brand-50 p-2.5 text-brand-600">
          <Settings2 className="h-5 w-5" />
        </div>
        <div>
          <h4 className="text-sm font-bold text-slate-900">{group.label}</h4>
          {group.description ? (
            <p className="mt-1 text-xs text-slate-500">{group.description}</p>
          ) : null}
        </div>
      </div>

      <div className="grid gap-5 md:grid-cols-2">
        {group.group === 'branding' ? (
          <PlatformLogoSection group={group} onBrandingUpdated={onBrandingUpdated} />
        ) : null}
        {settings.map((setting) => (
          <SettingsGroupField
            key={setting.key}
            setting={setting}
            value={values[setting.key]}
            onChange={(value) => updateValue(setting.key, value)}
            error={errors[setting.key]}
          />
        ))}
      </div>

      <div className="mt-6 flex justify-end border-t border-slate-200/80 pt-5">
        <BaseButton className="w-full sm:w-auto" onClick={save} disabled={saving} leftIcon={saving ? Loader2 : Save}>
          Save {group.label}
        </BaseButton>
      </div>
    </div>
  )
}

export default function WorkspaceSettingsPanel() {
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [groups, setGroups] = useState([])

  const loadData = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const payload = await fetchPlatformSettings()
      setGroups(payload.groups || [])
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load workspace settings.')
      setGroups([])
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadData()
  }, [loadData])

  const handleGroupSaved = (updatedGroup) => {
    setGroups((previous) => previous.map((group) => (
      group.group === updatedGroup.group ? updatedGroup : group
    )))
  }

  const handleBrandingUpdated = (payload) => {
    if (payload?.group) {
      handleGroupSaved(payload.group)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-base font-bold text-slate-900">Workspace Settings</h3>
        <p className="mt-1 text-xs text-slate-500">
          Configure platform-wide defaults for salon portals, referral credits, and branding.
          Salons inherit these values until they set their own configuration.
        </p>
      </div>

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">{error}</div>
      ) : null}

      {loading ? (
        <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-8 text-sm text-slate-500">
          <Loader2 className="h-4 w-4 animate-spin" />
          Loading workspace settings…
        </div>
      ) : null}

      {!loading && groups.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
          No settings groups configured yet.
        </div>
      ) : null}

      {!loading && groups.map((group) => (
        <SettingsGroupSection
          key={group.group}
          group={group}
          onSaved={handleGroupSaved}
          onBrandingUpdated={handleBrandingUpdated}
        />
      ))}
    </div>
  )
}
