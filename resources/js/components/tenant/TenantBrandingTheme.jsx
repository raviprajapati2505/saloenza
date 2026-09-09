import { useEffect } from 'react'
import { useAuthStore } from '../../stores/auth'
import { applyTenantBrandingTheme, resetTenantBrandingTheme } from '../../lib/tenantBranding.js'

export default function TenantBrandingTheme() {
  const auth = useAuthStore()
  const branding = auth.tenant?.branding

  useEffect(() => {
    if (auth.grantsAllPermissions || auth.isSystemAdmin || !branding) {
      resetTenantBrandingTheme()
      return
    }

    applyTenantBrandingTheme(branding)
    return () => resetTenantBrandingTheme()
  }, [auth.grantsAllPermissions, auth.isSystemAdmin, branding])

  return null
}
