import React, { useState, useEffect, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import {
  User, Lock, Camera, CheckCircle2, ShieldAlert, Loader2, Save, KeyRound, Mail, Phone, Contact, Building2, Settings2, Palette
} from 'lucide-react'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import BranchManagementPanel from '../components/settings/BranchManagementPanel.jsx'
import WorkspaceSettingsPanel from '../components/settings/WorkspaceSettingsPanel.jsx'
import BusinessProfilePanel from '../components/settings/BusinessProfilePanel.jsx'
import SalonConfigurationPanel from '../components/settings/SalonConfigurationPanel.jsx'
import { authHasModule, canConfigureSalonSettings, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { api } from '../lib/api'
export default function SettingsView() {
  const auth = useAuthStore()
  const [searchParams] = useSearchParams()
  const canUpdate = auth.can('settings.update')
  const canViewBranches = auth.can(TENANT_PERMISSIONS.BRANCHES_VIEW)
    && !auth.isSystemAdmin
    && auth.role?.scope !== 'platform'
    && !auth.grantsAllPermissions
  const canManageWorkspaceSettings = Boolean(auth.grantsAllPermissions || auth.isSystemAdmin)
  const canManageSalonSettings = Boolean(
    !auth.grantsAllPermissions
    && !auth.isSystemAdmin
    && auth.role?.scope !== 'platform'
    && authHasModule(auth, 'settings')
    && (auth.can('settings.view') || auth.isFranchiseOwner || auth.isFranchiseManager),
  )
  const canUpdateSalonSettings = canConfigureSalonSettings(auth)
  // Derive Bearer token from auth store
  const bearerToken = auth.token
  const [activeTab, setActiveTab] = useState('profile') // 'profile' | 'password' | 'branches' | 'workspace'

  useEffect(() => {
    const tab = searchParams.get('tab')
    if (tab === 'workspace' && canManageWorkspaceSettings) {
      setActiveTab('workspace')
    }
    if (tab === 'business' && canManageSalonSettings) {
      setActiveTab('business')
    }
    if (tab === 'salon-config' && canManageSalonSettings) {
      setActiveTab('salon-config')
    }
  }, [searchParams, canManageWorkspaceSettings, canManageSalonSettings])

  
  // Profile update states
  const [profileForm, setProfileForm] = useState({
    name: '',
    email: '',
    phone: '',
  })
  const [selectedPhoto, setSelectedPhoto] = useState(null)
  const [photoPreview, setPhotoPreview] = useState(null)
  const [photoError, setPhotoError] = useState(false)
  const [profileErrors, setProfileErrors] = useState({})
  const [profileSaving, setProfileSaving] = useState(false)
  const [profileSuccess, setProfileSuccess] = useState(false)
  const [profileMessage, setProfileMessage] = useState('')

  // Password change states
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  })
  const [passwordErrors, setPasswordErrors] = useState({})
  const [passwordSaving, setPasswordSaving] = useState(false)
  const [passwordSuccess, setPasswordSuccess] = useState(false)
  const [passwordMessage, setPasswordMessage] = useState('')

  const fileInputRef = useRef(null)

  // Populate profile form from auth store
  useEffect(() => {
    if (auth.user) {
      setProfileForm({
        name: auth.user.name || '',
        email: auth.user.email || '',
        phone: auth.user.phone || '',
      })
      setPhotoPreview(auth.user.photo || null)
      setPhotoError(false)
    }
  }, [auth.user])

  // Get Initials for Avatar Fallback
  const getInitials = (name) => {
    return name
      ? name
          .split(' ')
          .map((n) => n[0])
          .join('')
          .toUpperCase()
          .slice(0, 2)
      : 'AD'
  }

  // Handle Photo Selection
  const handlePhotoClick = () => {
    if (!canUpdate) return
    fileInputRef.current?.click()
  }

  const handlePhotoChange = (e) => {
    if (!canUpdate) return
    const file = e.target.files?.[0]
    if (!file) return

    if (file.size > 2 * 1024 * 1024) {
      setProfileErrors((prev) => ({ ...prev, photo: 'Image size must be less than 2 MB.' }))
      return
    }

    setSelectedPhoto(file)
    setPhotoPreview(URL.createObjectURL(file))
    setPhotoError(false)
    setProfileErrors((prev) => {
      const next = { ...prev }
      delete next.photo
      return next
    })
  }

  // Save Profile
  const handleSaveProfile = async (e) => {
    e.preventDefault()
    if (!canUpdate) return
    setProfileSaving(true)
    setProfileErrors({})
    setProfileSuccess(false)
    setProfileMessage('')

    try {
      let response

      // Common auth headers (NO Content-Type — let axios/browser set it)
      const authHeaders = {
        'Accept': 'application/json',
        'Authorization': `Bearer ${bearerToken}`,
      }

      if (selectedPhoto) {
        // ── Photo upload: multipart/form-data via POST + _method=PUT (Laravel requirement)
        const formData = new FormData()
        formData.append('_method', 'PUT')           // Laravel method spoofing
        formData.append('name',  profileForm.name)
        formData.append('email', profileForm.email)
        if (profileForm.phone) formData.append('phone', profileForm.phone)
        formData.append('photo', selectedPhoto)      // field name MUST be 'photo'

        // ⚠️ Do NOT set 'Content-Type' header manually — browser will set multipart/form-data
        //    with the correct boundary automatically.
        response = await api.post('/v1/profile', formData, {
          headers: authHeaders,
        })
      } else {
        response = await api.put('/v1/profile', {
          name:  profileForm.name,
          email: profileForm.email,
          phone: profileForm.phone,
        }, {
          headers: authHeaders,
        })
      }

      setProfileSuccess(true)
      setProfileMessage(response.data?.message || 'Profile updated successfully.')
      setSelectedPhoto(null)

      // Update the global user state from the API response (avoids an extra /me call)
      const updatedUser = response.data?.data?.user
      if (updatedUser) {
        // Re-fetch so auth store has the fresh photo URL etc.
        await auth.fetchMe()
      }

      setTimeout(() => setProfileSuccess(false), 4000)
    } catch (err) {
      const status = err?.response?.status
      if (status === 401) {
        auth.clearAuthState()
        window.location.href = '/login'
        return
      }
      if (status === 422) {
        setProfileErrors(err?.response?.data?.errors || {})
      } else {
        setProfileMessage(
          err?.response?.data?.message || 'Unable to update profile. Please try again.'
        )
      }
    } finally {
      setProfileSaving(false)
    }
  }


  // Save Password
  const handleSavePassword = async (e) => {
    e.preventDefault()
    if (!canUpdate) return
    setPasswordSaving(true)
    setPasswordErrors({})
    setPasswordSuccess(false)
    setPasswordMessage('')

    // Front-end confirmation match check
    if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
      setPasswordErrors({ new_password_confirmation: ['Passwords do not match.'] })
      setPasswordSaving(false)
      return
    }

    try {
      // PUT /password with Bearer token
      const response = await api.put('/v1/password', passwordForm, {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${bearerToken}`,
        },
      })

      setPasswordSuccess(true)
      setPasswordMessage(response.data?.message || 'Password changed successfully.')
      setPasswordForm({
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
      })

      setTimeout(() => setPasswordSuccess(false), 4000)
    } catch (err) {
      const status = err?.response?.status
      if (status === 401) {
        auth.clearAuthState()
        window.location.href = '/login'
        return
      }
      if (status === 422) {
        setPasswordErrors(err?.response?.data?.errors || {})
      } else {
        setPasswordMessage(
          err?.response?.data?.message || 'Unable to update password. Please try again.'
        )
      }
    } finally {
      setPasswordSaving(false)
    }
  }
  const newPassword = passwordForm.new_password || ''
  const isMinLength = newPassword.length >= 8
  const isMixedCase = /[a-z]/.test(newPassword) && /[A-Z]/.test(newPassword)
  const hasSymbol = /[\W_]/.test(newPassword)
  const isMatching = newPassword !== '' && newPassword === passwordForm.new_password_confirmation

  const getPasswordStrength = () => {
    if (!newPassword) return { percent: 0, text: 'Empty', color: 'bg-slate-200', textClass: 'text-slate-400' }
    let score = 0
    if (isMinLength) score++
    if (isMixedCase) score++
    if (hasSymbol) score++
    if (/[0-9]/.test(newPassword)) score++

    if (score <= 1) {
      return { percent: 25, text: 'Weak', color: 'bg-rose-500', textClass: 'text-rose-500' }
    } else if (score <= 3) {
      return { percent: 65, text: 'Medium', color: 'bg-amber-500', textClass: 'text-amber-500' }
    } else {
      return { percent: 100, text: 'Strong', color: 'bg-brand-500', textClass: 'text-brand-500' }
    }
  }

  const strength = getPasswordStrength()

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <PageHeader
        title="Account Settings"
        subtitle={subscriptionPageSubtitle(auth, 'Manage your personal profile, email settings, and security preferences.')}
      />

      {!canUpdate && !canManageWorkspaceSettings ? (
        <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
          Profile and password changes are disabled while your subscription is in view-only mode. Renew at Billing to edit account settings.
        </div>
      ) : null}

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* Navigation Sidebar (3 Cols) */}
        <aside className="lg:col-span-3 bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1">
          <button
            onClick={() => setActiveTab('profile')}
            className={`flex items-center gap-2.5 px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition w-full text-left cursor-pointer ${
              activeTab === 'profile'
                ? 'bg-brand-600 text-white shadow-sm shadow-brand-500/20'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
            }`}
          >
            <User className="w-4.5 h-4.5" />
            <span>Profile Details</span>
          </button>
          <button
            onClick={() => setActiveTab('password')}
            className={`flex items-center gap-2.5 px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition w-full text-left cursor-pointer ${
              activeTab === 'password'
                ? 'bg-brand-600 text-white shadow-sm shadow-brand-500/20'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
            }`}
          >
            <Lock className="w-4.5 h-4.5" />
            <span>Password & Security</span>
          </button>
          {canManageSalonSettings && (
            <>
              <button
                onClick={() => setActiveTab('business')}
                className={`flex items-center gap-2.5 px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition w-full text-left cursor-pointer ${
                  activeTab === 'business'
                    ? 'bg-brand-600 text-white shadow-sm shadow-brand-500/20'
                    : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
                }`}
              >
                <Building2 className="w-4.5 h-4.5" />
                <span>Business Profile</span>
              </button>
              <button
                onClick={() => setActiveTab('salon-config')}
                className={`flex items-center gap-2.5 px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition w-full text-left cursor-pointer ${
                  activeTab === 'salon-config'
                    ? 'bg-brand-600 text-white shadow-sm shadow-brand-500/20'
                    : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
                }`}
              >
                <Palette className="w-4.5 h-4.5" />
                <span>Salon Configuration</span>
              </button>
            </>
          )}
          {canManageWorkspaceSettings && (
            <button
              onClick={() => setActiveTab('workspace')}
              className={`flex items-center gap-2.5 px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition w-full text-left cursor-pointer ${
                activeTab === 'workspace'
                  ? 'bg-brand-600 text-white shadow-sm shadow-brand-500/20'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
              }`}
            >
              <Settings2 className="w-4.5 h-4.5" />
              <span>Workspace Settings</span>
            </button>
          )}
          {canViewBranches && (
            <button
              onClick={() => setActiveTab('branches')}
              className={`flex items-center gap-2.5 px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition w-full text-left cursor-pointer ${
                activeTab === 'branches'
                  ? 'bg-brand-600 text-white shadow-sm shadow-brand-500/20'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
              }`}
            >
              <Building2 className="w-4.5 h-4.5" />
              <span>{auth.isBranchScoped ? 'My Branch' : 'Branches'}</span>
            </button>
          )}
        </aside>

        {/* Content Box (9 Cols) */}
        <section className="lg:col-span-9 bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden min-h-[450px]">
          <AnimatePresence mode="wait">
            <motion.div
              key={activeTab}
              initial={{ opacity: 0, x: 8 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: -8 }}
              transition={{ duration: 0.15 }}
              className="p-6 md:p-8"
            >
              
              {/* PROFILE PANEL */}
              {activeTab === 'profile' && (
                <form onSubmit={handleSaveProfile} className="space-y-6">
                  <div>
                    <h3 className="text-base font-bold text-slate-900">Personal Information</h3>
                    <p className="text-xs text-slate-500 mt-1">Configure your public profile and login contact info.</p>
                  </div>

                  {profileSuccess && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800 font-semibold flex items-center gap-2 animate-fade-in">
                      <CheckCircle2 className="w-4.5 h-4.5 text-emerald-600" />
                      <span>{profileMessage}</span>
                    </div>
                  )}

                  {profileMessage && !profileSuccess && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800 font-semibold flex items-center gap-2">
                      <ShieldAlert className="w-4.5 h-4.5 text-rose-600" />
                      <span>{profileMessage}</span>
                    </div>
                  )}

                  {/* Photo Uploader */}
                  <div className="flex items-center gap-5">
                    <div className={`relative group ${canUpdate ? 'cursor-pointer' : ''}`} onClick={handlePhotoClick}>
                      <div className="w-20 h-20 rounded-2xl overflow-hidden border border-slate-200 shadow-md bg-slate-50 relative flex items-center justify-center">
                        {photoPreview && !photoError ? (
                          <img
                            src={photoPreview}
                            alt="Preview"
                            className="w-full h-full object-cover"
                            onError={() => setPhotoError(true)}
                          />
                        ) : (
                          <span className="text-2xl font-black text-slate-400">
                            {getInitials(profileForm.name)}
                          </span>
                        )}
                        <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition duration-200 rounded-2xl">
                          <Camera className="w-5 h-5 text-white" />
                        </div>
                      </div>
                      <input
                        type="file"
                        ref={fileInputRef}
                        onChange={handlePhotoChange}
                        accept="image/*"
                        disabled={!canUpdate}
                        className="hidden"
                      />
                    </div>
                    <div>
                      <h4 className="text-sm font-bold text-slate-800">Profile Photo</h4>
                      <p className="text-xs text-slate-500 mt-1">Supports JPG, PNG formats up to 2 MB.</p>
                      {canUpdate && <button
                        type="button"
                        onClick={handlePhotoClick}
                        className="text-xs font-bold text-brand-600 hover:text-brand-700 mt-2 focus:outline-none"
                      >
                        Change Photo
                      </button>
                      }
                    </div>
                  </div>
                  {profileErrors.photo && (
                    <p className="text-xs font-semibold text-rose-500 mt-1.5">{profileErrors.photo}</p>
                  )}

                  {/* Fields */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <BaseInput
                      label="Full Name"
                      type="text"
                      modelValue={profileForm.name}
                      onUpdateModelValue={(v) => setProfileForm((prev) => ({ ...prev, name: v }))}
                      error={profileErrors.name?.[0]}
                      required
                      placeholder="Enter full name"
                      prefix={Contact}
                      disabled={!canUpdate}
                    />

                    <BaseInput
                      label="Email Address"
                      type="email"
                      modelValue={profileForm.email}
                      onUpdateModelValue={(v) => setProfileForm((prev) => ({ ...prev, email: v }))}
                      error={profileErrors.email?.[0]}
                      required
                      placeholder="you@example.com"
                      prefix={Mail}
                      disabled={!canUpdate}
                    />

                    <div className="md:col-span-2">
                      <BaseInput
                        label="Phone Number"
                        type="tel"
                        modelValue={profileForm.phone}
                        onUpdateModelValue={(v) => setProfileForm((prev) => ({ ...prev, phone: v }))}
                        error={profileErrors.phone?.[0]}
                        placeholder="+91 98765 43210"
                        prefix={Phone}
                        disabled={!canUpdate}
                      />
                    </div>
                  </div>

                  {/* Save Button */}
                  {canUpdate && <div className="border-t border-slate-100 pt-5 flex justify-end">
                    <BaseButton
                      type="submit"
                      className="w-full sm:w-auto"
                      loading={profileSaving}
                      disabled={profileSaving}
                      leftIcon={profileSaving ? Loader2 : Save}
                    >
                      Save Profile
                    </BaseButton>
                  </div>
                  }
                </form>
              )}

              {/* PASSWORD PANEL */}
              {activeTab === 'password' && (
                <form onSubmit={handleSavePassword} className="space-y-6">
                  <div>
                    <h3 className="text-base font-bold text-slate-900">Change Password</h3>
                    <p className="text-xs text-slate-500 mt-1">Strengthen your account safety by setting a new secure password.</p>
                  </div>

                  {passwordSuccess && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800 font-semibold flex items-center gap-2 animate-fade-in">
                      <CheckCircle2 className="w-4.5 h-4.5 text-emerald-600" />
                      <span>{passwordMessage}</span>
                    </div>
                  )}

                  {passwordMessage && !passwordSuccess && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800 font-semibold flex items-center gap-2">
                      <ShieldAlert className="w-4.5 h-4.5 text-rose-600" />
                      <span>{passwordMessage}</span>
                    </div>
                  )}

                  <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Fields Column */}
                    <div className="lg:col-span-7 space-y-4">
                      <BaseInput
                        label="Current Password"
                        type="password"
                        modelValue={passwordForm.current_password}
                        onUpdateModelValue={(v) => setPasswordForm((prev) => ({ ...prev, current_password: v }))}
                        error={passwordErrors.current_password?.[0]}
                        required
                        toggleableType
                        prefix={KeyRound}
                        disabled={!canUpdate}
                      />

                      <BaseInput
                        label="New Password"
                        type="password"
                        modelValue={passwordForm.new_password}
                        onUpdateModelValue={(v) => setPasswordForm((prev) => ({ ...prev, new_password: v }))}
                        error={passwordErrors.new_password?.[0]}
                        required
                        toggleableType
                        prefix={KeyRound}
                        disabled={!canUpdate}
                      />

                      <BaseInput
                        label="Confirm New Password"
                        type="password"
                        modelValue={passwordForm.new_password_confirmation}
                        onUpdateModelValue={(v) => setPasswordForm((prev) => ({ ...prev, new_password_confirmation: v }))}
                        error={passwordErrors.new_password_confirmation?.[0]}
                        required
                        toggleableType
                        prefix={KeyRound}
                        disabled={!canUpdate}
                      />
                    </div>

                    {/* Requirements / Strength Meter Column */}
                    <div className="lg:col-span-5 bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4 self-start">
                      <div>
                        <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Security Assistant</h4>
                        <p className="text-xs text-slate-500 mt-1">Make sure your new password meets these requirements for security.</p>
                      </div>

                      {/* Strength Meter */}
                      <div className="space-y-2">
                        <div className="flex justify-between items-center text-xs">
                          <span className="font-medium text-slate-500">Password Strength:</span>
                          <span className={`font-bold ${strength.textClass}`}>{strength.text}</span>
                        </div>
                        <div className="h-2 w-full bg-slate-200/80 rounded-full overflow-hidden">
                          <div
                            className={`h-full transition-all duration-300 ${strength.color}`}
                            style={{ width: `${strength.percent}%` }}
                          />
                        </div>
                      </div>

                      {/* Checklist */}
                      <ul className="space-y-2.5 text-xs">
                        <li className="flex items-center gap-2.5">
                          <CheckCircle2 className={`w-4 h-4 transition-colors ${isMinLength ? 'text-brand-600' : 'text-slate-300'}`} />
                          <span className={isMinLength ? 'text-brand-900 font-medium' : 'text-slate-500'}>
                            At least 8 characters
                          </span>
                        </li>
                        <li className="flex items-center gap-2.5">
                          <CheckCircle2 className={`w-4 h-4 transition-colors ${isMixedCase ? 'text-brand-600' : 'text-slate-300'}`} />
                          <span className={isMixedCase ? 'text-brand-900 font-medium' : 'text-slate-500'}>
                            Mixed case (uppercase & lowercase)
                          </span>
                        </li>
                        <li className="flex items-center gap-2.5">
                          <CheckCircle2 className={`w-4 h-4 transition-colors ${hasSymbol ? 'text-brand-600' : 'text-slate-300'}`} />
                          <span className={hasSymbol ? 'text-brand-900 font-medium' : 'text-slate-500'}>
                            At least one special character
                          </span>
                        </li>
                        <li className="flex items-center gap-2.5 border-t border-slate-200/60 pt-3 mt-1.5">
                          <CheckCircle2 className={`w-4 h-4 transition-colors ${isMatching ? 'text-brand-600' : 'text-slate-300'}`} />
                          <span className={isMatching ? 'text-brand-900 font-medium' : 'text-slate-500'}>
                            Passwords match
                          </span>
                        </li>
                      </ul>
                    </div>
                  </div>

                  {/* Save Button */}
                  {canUpdate && <div className="border-t border-slate-100 pt-5 flex justify-end">
                    <BaseButton
                      type="submit"
                      className="w-full sm:w-auto"
                      loading={passwordSaving}
                      disabled={passwordSaving}
                      leftIcon={passwordSaving ? Loader2 : Save}
                    >
                      Change Password
                    </BaseButton>
                  </div>
                  }
                </form>
              )}

              {activeTab === 'branches' && (
                <BranchManagementPanel />
              )}

              {activeTab === 'business' && canManageSalonSettings && (
                <BusinessProfilePanel canUpdate={canUpdateSalonSettings} />
              )}

              {activeTab === 'salon-config' && canManageSalonSettings && (
                <SalonConfigurationPanel canUpdate={canUpdateSalonSettings} />
              )}

              {activeTab === 'workspace' && canManageWorkspaceSettings && (
                <WorkspaceSettingsPanel />
              )}

            </motion.div>
          </AnimatePresence>
        </section>
      </div>
    </div>
  )
}
