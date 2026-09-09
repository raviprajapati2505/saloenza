import React, { useState } from 'react'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import BaseModal from '../components/ui/BaseModal.jsx'
import BaseTable from '../components/ui/BaseTable.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import { Plus, Search, Mail, Trash2 } from 'lucide-react'

export default function ComponentsTestView() {
  const [modalOpen, setModalOpen] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [loading, setLoading] = useState(false)

  const handleSimulateLoad = () => {
    setLoading(true)
    setTimeout(() => setLoading(false), 2000)
  }

  const tableColumns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'status', label: 'Status', render: (row) => <BaseBadge variant={row.status === 'Active' ? 'success' : 'default'}>{row.status}</BaseBadge> },
  ]

  const tableData = [
    { name: 'Alice Smith', email: 'alice@example.com', status: 'Active' },
    { name: 'Bob Jones', email: 'bob@example.com', status: 'Inactive' },
    { name: 'Charlie Brown', email: 'charlie@example.com', status: 'Active' },
  ]

  return (
    <div className="space-y-12 pb-24">
      <PageHeader 
        title="UI Component Library" 
        subtitle="Testing all the new core components in one place."
        breadcrumbs={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Components' }]}
        actions={
          <BaseButton leftIcon={Plus} onClick={() => setModalOpen(true)}>
            Open Modal
          </BaseButton>
        }
      />

      <section className="space-y-4">
        <h3 className="text-lg font-semibold border-b pb-2">1. BaseButton Variations</h3>
        <div className="flex flex-wrap gap-4 items-center">
          <BaseButton variant="primary" leftIcon={Mail}>Primary</BaseButton>
          <BaseButton variant="secondary" rightIcon={Search}>Secondary</BaseButton>
          <BaseButton variant="danger" leftIcon={Trash2}>Danger</BaseButton>
          <BaseButton variant="ghost">Ghost Button</BaseButton>
          <BaseButton loading={true}>Loading State</BaseButton>
          <BaseButton disabled>Disabled</BaseButton>
          <BaseButton size="sm">Small Size</BaseButton>
          <BaseButton size="lg" variant="primary">Large Size</BaseButton>
        </div>
      </section>

      <section className="space-y-4 max-w-2xl">
        <h3 className="text-lg font-semibold border-b pb-2">2. Form Inputs (BaseInput & BaseSelect)</h3>
        <div className="space-y-6">
          <BaseInput 
            label="Email Address" 
            placeholder="you@example.com" 
            prefix={Mail} 
            required 
            hint="We'll never share your email."
          />
          <BaseInput 
            label="Username with Error" 
            placeholder="Enter username" 
            error="This username is already taken."
          />
          <BaseSelect 
            label="Select Role" 
            options={[{value: 'admin', label: 'Admin'}, {value: 'user', label: 'User'}]} 
            placeholder="Choose a role..."
          />
        </div>
      </section>

      <section className="space-y-4">
        <h3 className="text-lg font-semibold border-b pb-2">3. Badges</h3>
        <div className="flex gap-4">
          <BaseBadge variant="success">Success</BaseBadge>
          <BaseBadge variant="warning">Warning</BaseBadge>
          <BaseBadge variant="danger">Danger</BaseBadge>
          <BaseBadge variant="info">Info</BaseBadge>
          <BaseBadge variant="default">Default</BaseBadge>
        </div>
      </section>

      <section className="space-y-4">
        <h3 className="text-lg font-semibold border-b pb-2 flex justify-between items-center">
          4. BaseTable
          <BaseButton size="sm" variant="secondary" onClick={handleSimulateLoad}>
            Toggle Loading State
          </BaseButton>
        </h3>
        <BaseTable 
          columns={tableColumns} 
          rows={tableData} 
          loading={loading}
        />
      </section>

      <section className="space-y-4">
        <h3 className="text-lg font-semibold border-b pb-2">5. Modals & Dialogs</h3>
        <div className="flex gap-4">
          <BaseButton onClick={() => setModalOpen(true)}>Open Base Modal</BaseButton>
          <BaseButton variant="danger" onClick={() => setConfirmOpen(true)}>Open Confirm Dialog</BaseButton>
        </div>
      </section>

      {/* Modals placed at bottom */}
      <BaseModal 
        open={modalOpen} 
        onClose={() => setModalOpen(false)} 
        title="Create New Project"
        footer={
          <>
            <BaseButton variant="secondary" onClick={() => setModalOpen(false)}>Cancel</BaseButton>
            <BaseButton variant="primary" onClick={() => setModalOpen(false)}>Save Changes</BaseButton>
          </>
        }
      >
        <div className="space-y-4">
          <p className="text-slate-600 text-sm">Fill out the details below to create your new project workspace.</p>
          <BaseInput label="Project Name" placeholder="e.g., Marketing Q4" />
        </div>
      </BaseModal>

      <ConfirmDialog 
        open={confirmOpen}
        onCancel={() => setConfirmOpen(false)}
        onConfirm={() => {
          setLoading(true)
          setTimeout(() => { setLoading(false); setConfirmOpen(false) }, 1000)
        }}
        title="Delete Customer Account"
        message="Are you sure you want to delete this customer? This action cannot be undone and all data will be permanently removed."
        confirmLabel="Yes, delete account"
        loading={loading}
      />
    </div>
  )
}
