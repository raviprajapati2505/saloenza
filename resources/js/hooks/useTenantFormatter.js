import { useMemo } from 'react'
import { useAuthStore } from '../stores/auth'
import { createTenantFormatter } from '../lib/tenantFormatting.js'

/** Regional money/date formatting bound to the current auth tenant. */
export function useTenantFormatter() {
  const auth = useAuthStore()
  return useMemo(() => createTenantFormatter(auth), [auth])
}
