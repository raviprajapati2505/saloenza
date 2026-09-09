import React from 'react'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '../../stores/auth'
import PlatformDashboardView from './PlatformDashboardView.jsx'
import SalonOwnerDashboardView from './SalonOwnerDashboardView.jsx'
import BranchManagerDashboardView from './BranchManagerDashboardView.jsx'
import StaffDashboardView from './StaffDashboardView.jsx'

/**
 * Picks the correct home dashboard for the signed-in role/workspace.
 * Affiliate partners use /affiliate/dashboard (separate portal).
 */
export default function DashboardRouter() {
  const auth = useAuthStore()

  if (auth.workspace === 'affiliate' || auth.isAffiliatePartner) {
    return <Navigate to="/affiliate/dashboard" replace />
  }

  if (
    auth.workspace === 'platform'
    || auth.isSystemAdmin
    || auth.grantsAllPermissions
    || auth.role?.scope === 'platform'
  ) {
    return <PlatformDashboardView />
  }

  if (auth.isBranchManager) {
    return <BranchManagerDashboardView />
  }

  if (auth.isStaffMember || (auth.isBranchScoped && !auth.isBranchManager)) {
    return <StaffDashboardView />
  }

  // Salon franchise owner / franchise manager (and other salon-scoped roles)
  return <SalonOwnerDashboardView />
}
