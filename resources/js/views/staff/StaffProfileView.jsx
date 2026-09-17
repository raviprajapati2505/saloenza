import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  User, Mail, Phone, Calendar, MapPin, Clock, Loader2, Pencil, FileText, TrendingUp,
} from 'lucide-react'

import BaseBadge from '../../components/ui/BaseBadge.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import WeeklyHoursInput from '../../components/inputs/WeeklyHoursInput.jsx'
import PerformanceView from './PerformanceView.jsx'
import { useAuthStore } from '../../stores/auth'
import { canMutate } from '../../lib/subscriptionModules.js'
import { currencySymbol, formatMoney } from '../../lib/tenantFormatting.js'

const EMPTY_SCHEDULE = {
  monday: { enabled: true, open: '09:00', close: '20:00' },
  tuesday: { enabled: true, open: '09:00', close: '20:00' },
  wednesday: { enabled: true, open: '09:00', close: '20:00' },
  thursday: { enabled: true, open: '09:00', close: '20:00' },
  friday: { enabled: true, open: '09:00', close: '20:00' },
  saturday: { enabled: true, open: '09:00', close: '18:00' },
  sunday: { enabled: false, open: '09:00', close: '18:00' },
}

export default function StaffProfileView({ staffId: propStaffId, onBack, onEdit }) {
  const auth = useAuthStore()
  const canUpdateSchedule = canMutate(auth, 'staff.update')
  const staffId = propStaffId
  const [activeTab, setActiveTab] = useState('overview')
  const [loading, setLoading] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [staff, setStaff] = useState(null)
  const [savingSchedule, setSavingSchedule] = useState(false)
  const [scheduleSuccess, setScheduleSuccess] = useState(false)

  useEffect(() => {
    const fetchStaffProfile = async () => {
      if (!staffId) return
      setLoading(true)
      setLoadError('')
      try {
        const { apiGet, parseItem } = await import('../../lib/apiHelpers')
        const response = await apiGet(`/v1/staff/${staffId}`)
        const member = parseItem(response, 'staff')
        setStaff({
          id: member.id,
          name: member.name,
          firstname: member.firstname,
          lastname: member.lastname,
          role: member.role?.name || 'Staff',
          role_id: member.role_id,
          saloon_id: member.saloon_id,
          saloon: member.saloon?.name || null,
          branch_id: member.branch_id,
          joinDate: member.joined_at
            ? new Date(member.joined_at).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
            : member.created_at
              ? new Date(member.created_at).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
              : '—',
          branch: member.branch?.branch_name || '—',
          email: member.email,
          phone: member.phone,
          photo: member.photo_url || member.photo,
          notes: member.notes || '',
          is_active: Boolean(member.is_active),
          commission_rate: member.commission_rate,
          per_month_salary: member.per_month_salary,
          schedule: member.weekly_schedule || EMPTY_SCHEDULE,
        })
      } catch (error) {
        console.error('Failed to load staff profile:', error)
        setStaff(null)
        setLoadError(error?.response?.data?.message || 'Unable to load staff profile.')
      } finally {
        setLoading(false)
      }
    }

    fetchStaffProfile()
  }, [staffId])

  const handleSaveSchedule = async (newHours) => {
    if (!canUpdateSchedule) return
    setSavingSchedule(true)
    setScheduleSuccess(false)
    try {
      const { apiPut, parseItem } = await import('../../lib/apiHelpers')
      const response = await apiPut(`/v1/staff/${staffId}`, {
        firstname: staff.firstname || staff.name?.split(' ')[0] || staff.name,
        lastname: staff.lastname || staff.name?.split(' ').slice(1).join(' ') || 'Staff',
        email: staff.email,
        phone: staff.phone,
        is_active: staff.is_active,
        role_id: staff.role_id,
        saloon_id: staff.saloon_id,
        branch_id: staff.branch_id || null,
        weekly_schedule: newHours,
      })
      const member = parseItem(response, 'staff')
      setStaff((prev) => ({
        ...prev,
        schedule: member.weekly_schedule || newHours,
        firstname: member.firstname,
        lastname: member.lastname,
        role_id: member.role_id,
        saloon_id: member.saloon_id,
        branch_id: member.branch_id,
        is_active: Boolean(member.is_active),
      }))
      setScheduleSuccess(true)
      setTimeout(() => setScheduleSuccess(false), 3000)
    } catch (err) {
      console.error(err)
      alert('Failed to save schedule.')
    } finally {
      setSavingSchedule(false)
    }
  }

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] gap-3 text-slate-500">
        <Loader2 className="w-8 h-8 text-brand-600 animate-spin" />
        <p className="text-sm font-semibold">Loading staff profile...</p>
      </div>
    )
  }

  if (!staff) {
    return (
      <div className="space-y-4">
        <button
          type="button"
          onClick={onBack}
          className="text-xs font-bold text-slate-500 hover:text-slate-900 transition flex items-center gap-1 cursor-pointer bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl border border-slate-200"
        >
          &larr; Back to Staff List
        </button>
        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-6 py-10 text-center text-sm text-rose-700">
          {loadError || 'Staff profile could not be loaded.'}
        </div>
      </div>
    )
  }

  // Tabs backed by DB/API: overview, weekly_schedule, staff earnings performance.
  const tabs = [
    { id: 'overview', label: 'Overview', icon: User },
    { id: 'schedule', label: 'Schedule', icon: Clock },
    { id: 'performance', label: 'Performance', icon: TrendingUp },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <button
          type="button"
          onClick={onBack}
          className="text-xs font-bold text-slate-500 hover:text-slate-900 transition flex items-center gap-1 cursor-pointer bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl border border-slate-200"
        >
          &larr; Back to Staff List
        </button>
        {typeof onEdit === 'function' ? (
          <BaseButton size="sm" leftIcon={Pencil} onClick={onEdit}>
            Edit Staff
          </BaseButton>
        ) : null}
      </div>

      <div className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden group">
        <div className="absolute -right-24 -top-24 w-48 h-48 bg-brand-500/5 rounded-full blur-3xl group-hover:bg-brand-500/10 transition duration-500" />

        <div className="flex items-center gap-5 z-10">
          <div className="w-20 h-20 rounded-2xl overflow-hidden border-2 border-brand-500/10 shadow-md bg-slate-50 relative flex items-center justify-center">
            {staff.photo ? (
              <img src={staff.photo} alt={staff.name} className="w-full h-full object-cover" />
            ) : (
              <span className="text-2xl font-black text-slate-400">
                {staff.name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase()}
              </span>
            )}
          </div>

          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="text-xl font-black text-slate-900 tracking-tight">{staff.name}</h2>
              <BaseBadge variant="info" size="sm">{staff.role}</BaseBadge>
              <BaseBadge variant={staff.is_active ? 'success' : 'default'} size="sm">
                {staff.is_active ? 'Active' : 'Inactive'}
              </BaseBadge>
            </div>
            <div className="mt-2 flex flex-col sm:flex-row sm:items-center gap-x-4 gap-y-1 text-xs font-semibold text-slate-500">
              <div className="flex items-center gap-1">
                <MapPin className="w-3.5 h-3.5" />
                <span>{staff.branch}</span>
              </div>
              <div className="flex items-center gap-1">
                <Calendar className="w-3.5 h-3.5" />
                <span>Joined {staff.joinDate}</span>
              </div>
              {staff.saloon ? (
                <div className="flex items-center gap-1 text-brand-600">
                  <span>{staff.saloon}</span>
                </div>
              ) : null}
            </div>
          </div>
        </div>
      </div>

      <div className="border-b border-slate-200 bg-white p-2 rounded-2xl border flex flex-wrap gap-1 shadow-sm">
        {tabs.map((tab) => {
          const Icon = tab.icon
          const isActive = activeTab === tab.id
          return (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition cursor-pointer ${
                isActive
                  ? 'bg-brand-600 text-white shadow-sm shadow-brand-500/20'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{tab.label}</span>
            </button>
          )
        })}
      </div>

      <div className="min-h-[280px]">
        <AnimatePresence mode="wait">
          <motion.div
            key={activeTab}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.15 }}
          >
            {activeTab === 'overview' && (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                  <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Contact Details</h3>
                  <div className="space-y-4">
                    <div className="flex items-center gap-3">
                      <Mail className="w-4 h-4 text-slate-400" />
                      <div>
                        <p className="text-[10px] text-slate-400 font-bold uppercase">Email</p>
                        <p className="text-xs font-semibold text-slate-700 mt-0.5">{staff.email || '—'}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <Phone className="w-4 h-4 text-slate-400" />
                      <div>
                        <p className="text-[10px] text-slate-400 font-bold uppercase">Phone</p>
                        <p className="text-xs font-semibold text-slate-700 mt-0.5">{staff.phone || '—'}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <MapPin className="w-4 h-4 text-slate-400" />
                      <div>
                        <p className="text-[10px] text-slate-400 font-bold uppercase">Branch</p>
                        <p className="text-xs font-semibold text-slate-700 mt-0.5">{staff.branch}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                  <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2 flex items-center gap-2">
                    <FileText className="w-4 h-4" />
                    Compensation
                  </h3>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p className="text-[10px] font-bold uppercase text-slate-400">Commission rate</p>
                      <p className="mt-1 font-semibold text-slate-800">
                        {staff.commission_rate != null ? `${staff.commission_rate}%` : 'Not set'}
                      </p>
                    </div>
                    <div>
                      <p className="text-[10px] font-bold uppercase text-slate-400">Monthly salary</p>
                      <p className="mt-1 font-semibold text-slate-800">
                        {staff.per_month_salary != null ? formatMoney(staff.per_month_salary, auth) : 'Not set'}
                      </p>
                    </div>
                  </div>
                </div>

                <div className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm lg:col-span-2">
                  <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2 flex items-center gap-2">
                    <FileText className="w-4 h-4" />
                    Notes
                  </h3>
                  <p className="text-sm text-slate-600 whitespace-pre-wrap">
                    {staff.notes || 'No notes added yet.'}
                  </p>
                </div>
              </div>
            )}

            {activeTab === 'performance' && (
              <PerformanceView staffId={staff.id} staffName={staff.name} />
            )}

            {activeTab === 'schedule' && (
              <div className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Weekly Work Hours</h3>
                    <p className="text-xs text-slate-500 mt-1">Schedule for {staff.name}.</p>
                  </div>
                  {scheduleSuccess && (
                    <span className="text-xs font-bold text-emerald-600 pr-2">Schedule saved!</span>
                  )}
                  {savingSchedule && (
                    <span className="text-xs font-bold text-slate-400 pr-2 flex items-center gap-1">
                      <Loader2 className="w-3.5 h-3.5 animate-spin" /> Saving...
                    </span>
                  )}
                </div>
                <div className="max-w-2xl">
                  <WeeklyHoursInput
                    modelValue={staff.schedule}
                    onUpdateModelValue={canUpdateSchedule ? handleSaveSchedule : undefined}
                    readOnly={!canUpdateSchedule}
                  />
                  {!canUpdateSchedule ? (
                    <p className="mt-3 text-xs text-slate-500">Schedule is view-only while your subscription is expired.</p>
                  ) : null}
                </div>
              </div>
            )}
          </motion.div>
        </AnimatePresence>
      </div>
    </div>
  )
}
