import React from 'react'
import BrandLogo from './BrandLogo.jsx'
import { getPlatformBranding } from '../../stores/platformBranding.js'

export default function AuthBootstrap() {
  const branding = getPlatformBranding()
  const portalName = branding?.portal_name || 'Glowsuite'

  return (
    <div className="flex min-h-screen items-center justify-center bg-black">
      <div className="flex flex-col items-center gap-4 text-center">
        <BrandLogo variant="mark" size="lg" branding={branding} />
        <div>
          <p className="text-sm font-semibold text-white">{portalName}</p>
          <p className="mt-1 text-xs text-slate-400">Loading your workspace…</p>
        </div>
        <div className="h-1 w-24 overflow-hidden rounded-full bg-slate-800">
          <div className="h-full w-1/2 animate-pulse rounded-full bg-gradient-to-r from-brand-500 to-brand-700" />
        </div>
      </div>
    </div>
  )
}
