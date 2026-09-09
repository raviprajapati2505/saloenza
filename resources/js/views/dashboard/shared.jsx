import React from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'

export function DashboardStatCard({ label, value, hint, loading = false }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-slate-900">
        {loading ? '…' : value}
      </p>
      {hint ? <p className="mt-1 text-xs text-slate-400">{hint}</p> : null}
    </div>
  )
}

export function DashboardQuickLink({ to, icon: Icon, title, description }) {
  return (
    <Link
      to={to}
      className="group flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:bg-brand-50/40"
    >
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 group-hover:bg-brand-100 group-hover:text-brand-700">
        <Icon className="h-5 w-5" />
      </div>
      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-1 text-sm font-semibold text-slate-900">
          {title}
          <ArrowRight className="h-3.5 w-3.5 opacity-0 transition group-hover:opacity-100" />
        </p>
        {description ? <p className="mt-0.5 text-xs text-slate-500">{description}</p> : null}
      </div>
    </Link>
  )
}

export function DashboardSection({ title, children, action }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {action}
      </div>
      {children}
    </div>
  )
}
