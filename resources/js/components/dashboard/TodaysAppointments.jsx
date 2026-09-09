import React from 'react'
import BaseTable from '../ui/BaseTable.jsx'
import BaseBadge from '../ui/BaseBadge.jsx'
import { Link, useNavigate } from 'react-router-dom'
import { ChevronRight } from 'lucide-react'

export default function TodaysAppointments({ appointments = [], loading = false }) {
  const navigate = useNavigate()

  const columns = [
    { key: 'time', label: 'Time' },
    { key: 'customer', label: 'Customer', render: (row) => <span className="font-medium text-slate-900">{row.customer}</span> },
    { key: 'service', label: 'Service' },
    { key: 'staff', label: 'Staff' },
    { 
      key: 'status', 
      label: 'Status', 
      render: (row) => {
        const variants = {
          confirmed: 'success',
          'in-progress': 'info',
          completed: 'default',
          cancelled: 'danger'
        }
        return <BaseBadge variant={variants[row.status] || 'default'}>{row.status.replace('-', ' ')}</BaseBadge>
      }
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <button 
          onClick={() => navigate(`/appointments/${row.id}`)}
          className="text-slate-400 hover:text-brand-600 transition-colors flex items-center justify-end w-full"
        >
          <span className="sr-only">View</span>
          <ChevronRight className="h-4 w-4" />
        </button>
      )
    }
  ]

  return (
    <div className="rounded-2xl border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden flex flex-col h-full">
      <div className="flex items-center justify-between px-6 py-5 border-b border-slate-100">
        <h3 className="text-base font-semibold text-slate-900">Today's Appointments</h3>
        <Link to="/appointments" className="text-sm font-medium text-brand-600 hover:text-brand-700 transition-colors">
          View all
        </Link>
      </div>
      <div className="flex-1 p-0">
        <BaseTable 
          columns={columns} 
          rows={appointments.slice(0, 8)} 
          loading={loading}
          className="ring-0 shadow-none rounded-none"
          emptyState={
            <div className="text-center py-8">
              <p className="text-sm text-slate-500">No appointments scheduled for today.</p>
            </div>
          }
        />
      </div>
    </div>
  )
}
