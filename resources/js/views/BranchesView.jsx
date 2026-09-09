import React, { useMemo } from 'react'
import { Navigate } from 'react-router-dom'
import PageHeader from '../components/ui/PageHeader.jsx'
import BranchManagementPanel from '../components/settings/BranchManagementPanel.jsx'
import { useAuthStore } from '../stores/auth'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
export default function BranchesView() {
  const auth = useAuthStore()
  const isBranchScoped = Boolean(auth.isBranchScoped)

  const header = useMemo(() => (
    isBranchScoped
      ? {
        title: 'My Branch',
        subtitle: subscriptionPageSubtitle(auth, 'Review and update details for the branch assigned to you.'),
      }
      : {
        title: 'Branch Management',
        subtitle: subscriptionPageSubtitle(auth, 'Create and manage salon branches within your subscription plan limits.'),
      }
  ), [isBranchScoped, auth])

  if (auth.isSystemAdmin || auth.role?.scope === 'platform' || auth.grantsAllPermissions) {
    return <Navigate to="/admin/branches" replace />
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={header.title}
        subtitle={header.subtitle}
      />
      <BranchManagementPanel hideIntro />
    </div>
  )
}
