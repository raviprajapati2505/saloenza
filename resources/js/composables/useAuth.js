import { useMemo } from 'react'
import { useAuthStore } from '../stores/auth'

export function useAuth() {
  const auth = useAuthStore()

  return useMemo(
    () => ({
      user: auth.user,
      tenant: auth.tenant,
      role: auth.role,
      isAuthenticated: auth.isAuthenticated,
      can: auth.can,
      canPlatform: auth.canPlatform,
      hasRoleCode: auth.hasRoleCode,
      isFranchiseOwner: auth.isFranchiseOwner,
      isFranchiseManager: auth.isFranchiseManager,
      isBranchManager: auth.isBranchManager,
      isStaffMember: auth.isStaffMember,
      isOwner: auth.isFranchiseOwner,
      isManager: auth.isFranchiseManager || auth.isBranchManager,
    }),
    [auth],
  )
}
