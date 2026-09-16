import React, { useEffect, useMemo, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useAuthStore } from '../../stores/auth'
import BrandLogo from '../ui/BrandLogo.jsx'
import { motion, AnimatePresence } from 'framer-motion'
import { HelpCircle, Zap, ChevronLeft, ChevronRight, Settings, X } from 'lucide-react'
import { filterAffiliateNavGroups, filterNavGroups, filterTenantNavGroups } from '../../lib/navigation.js'
import { SHOW_NAV_PENDING_COUNTS } from '../../lib/featureFlags.js'
import { fetchAdminPendingCounts } from '../../services/notificationService.js'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'

import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

const HIDDEN_SIDEBAR_PATHS = new Set([
  '/analytics',
])

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function AppSidebar({ collapsed = false, onToggleSidebar, mobileOpen = false, onCloseMobile }) {
  const location = useLocation()
  const auth = useAuthStore()

  const planLabel = useMemo(() => auth.planName || 'Free', [auth.planName])
  const isFreePlan = useMemo(() => auth.isFree || String(planLabel).toLowerCase() === 'free', [auth.isFree, planLabel])
  const isBranchScoped = useMemo(
    () => Boolean(auth.isBranchScoped),
    [auth.isBranchScoped],
  )

  const mobileGroups = useMemo(() => {
    if (auth.workspace === 'affiliate') {
      return filterAffiliateNavGroups(auth)
    }

    const isPlatformWorkspace = Boolean(
      auth.grantsAllPermissions
      || auth.isSystemAdmin
      || auth.role?.scope === 'platform'
      || auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES)
      || auth.canPlatform(PLATFORM_PERMISSIONS.UPGRADE_REQUESTS)
      || auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING)
      || auth.canPlatform(PLATFORM_PERMISSIONS.SUBSCRIPTIONS)
      || auth.canPlatform(PLATFORM_PERMISSIONS.BRANCHES),
    )

    return isPlatformWorkspace
      ? filterNavGroups(auth)
      : filterTenantNavGroups(auth)
  }, [auth])

  const visibleGroups = useMemo(() => {
    if (auth.workspace === 'affiliate') {
      return filterAffiliateNavGroups(auth)
    }

    const isPlatformWorkspace = Boolean(
      auth.grantsAllPermissions
      || auth.isSystemAdmin
      || auth.role?.scope === 'platform'
      || auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES)
      || auth.canPlatform(PLATFORM_PERMISSIONS.UPGRADE_REQUESTS)
      || auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING)
      || auth.canPlatform(PLATFORM_PERMISSIONS.SUBSCRIPTIONS)
      || auth.canPlatform(PLATFORM_PERMISSIONS.BRANCHES),
    )

    const groups = isPlatformWorkspace
      ? filterNavGroups(auth)
      : filterTenantNavGroups(auth)

    return groups
      .map((group) => ({
        ...group,
        items: group.items.filter((item) => !HIDDEN_SIDEBAR_PATHS.has(item.to)),
      }))
      .filter((group) => group.items.length > 0)
  }, [auth])

  const [pendingCounts, setPendingCounts] = useState({})

  const canLoadPendingCounts = Boolean(
    SHOW_NAV_PENDING_COUNTS
    && auth.isAuthenticated
    && (
      auth.grantsAllPermissions
      || auth.isSystemAdmin
      || auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES)
      || auth.canPlatform(PLATFORM_PERMISSIONS.UPGRADE_REQUESTS)
      || auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING)
    ),
  )

  useEffect(() => {
    if (!canLoadPendingCounts) {
      setPendingCounts({})
      return undefined
    }

    let cancelled = false

    const loadCounts = async () => {
      try {
        const counts = await fetchAdminPendingCounts()
        if (!cancelled) setPendingCounts(counts || {})
      } catch {
        if (!cancelled) setPendingCounts({})
      }
    }

    void loadCounts()
    const timer = window.setInterval(() => {
      void loadCounts()
    }, 60000)

    return () => {
      cancelled = true
      window.clearInterval(timer)
    }
  }, [canLoadPendingCounts, location.pathname])

  const resolvePendingCount = (item) => {
    if (!SHOW_NAV_PENDING_COUNTS || !item?.pendingCountKeys?.length) return 0
    return item.pendingCountKeys.reduce((sum, key) => sum + Number(pendingCounts[key] || 0), 0)
  }

  const isActive = (targetPath) => location.pathname === targetPath || location.pathname.startsWith(targetPath + '/')

  return (
    <>
      <motion.aside 
        initial={false}
      animate={{ width: collapsed ? 72 : 260 }}
      transition={{ type: "spring", stiffness: 300, damping: 30 }}
      className="fixed left-0 top-0 z-30 hidden h-screen bg-[#0A0F1C] border-r border-slate-800/60 text-slate-300 md:flex md:flex-col shadow-2xl"
    >
      {/* Brand */}
      <div className="relative flex-shrink-0 border-b border-slate-800/60 py-4 px-3 min-h-[88px] flex items-center">
        <div className={cn("flex items-center w-full min-w-0", collapsed ? "justify-center" : "pr-3")}>
          {collapsed ? (
            <BrandLogo variant="mark" size="md" className="rounded-xl" />
          ) : (
            <BrandLogo size="h-[58px]" className="w-full max-w-[220px]" />
          )}
        </div>
        
        {/* Toggle Button */}
        <button
          onClick={onToggleSidebar}
          className="absolute top-1/2 -translate-y-1/2 -right-3 flex h-6 w-6 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-slate-400 hover:text-white hover:border-slate-500 transition-colors shadow-lg z-50 hidden md:flex"
        >
          {collapsed ? <ChevronRight className="h-3.5 w-3.5" /> : <ChevronLeft className="h-3.5 w-3.5" />}
        </button>
      </div>

      {/* Navigation */}
      <div className="flex-1 overflow-y-auto scrollbar-hide py-4 px-3 flex flex-col gap-6">
        {visibleGroups.map((group, idx) => (
          <div key={idx} className="flex flex-col gap-1">
            <AnimatePresence>
              {!collapsed && (
                <motion.div 
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  className="px-2 pb-1 text-[10px] font-bold tracking-wider text-slate-500 uppercase whitespace-nowrap overflow-hidden"
                >
                  {group.title}
                </motion.div>
              )}
            </AnimatePresence>
            
            {group.items.map((item) => {
              const active = isActive(item.to)
              const Icon = item.icon
              const itemLabel = isBranchScoped && item.scopedLabel ? item.scopedLabel : item.label
              const pendingCount = resolvePendingCount(item)
              
              return (
                <Link 
                  key={item.to} 
                  to={item.to} 
                  className={cn(
                    "group relative flex items-center rounded-xl px-2.5 py-2 text-[13px] font-medium transition-all duration-200",
                    active 
                      ? "text-brand-400 bg-brand-500/10 shadow-[inset_0_1px_0_rgba(204,15,103,0.1)]" 
                      : "text-slate-400 hover:bg-slate-800/50 hover:text-slate-200"
                  )}
                >
                  {/* Active Indicator Glow */}
                  {active && (
                    <motion.div
                      layoutId="sidebarActiveIndicator"
                      className="absolute left-0 top-1/2 -mt-2.5 h-5 w-1 rounded-r-full bg-brand-500 shadow-[0_0_10px_rgba(204,15,103,0.8)]"
                      transition={{ type: "spring", stiffness: 300, damping: 30 }}
                    />
                  )}
                  
                  <Icon className={cn("h-4 w-4 shrink-0 transition-colors", active ? "text-brand-400" : "text-slate-500 group-hover:text-slate-300")} strokeWidth={active ? 2.5 : 2} />
                  
                  <AnimatePresence>
                    {!collapsed && (
                      <motion.span 
                        initial={{ opacity: 0, x: -10 }}
                        animate={{ opacity: 1, x: 0 }}
                        exit={{ opacity: 0, width: 0 }}
                        className="ml-3 flex min-w-0 flex-1 items-center justify-between gap-2 overflow-hidden whitespace-nowrap"
                      >
                        <span className="truncate">{itemLabel}</span>
                        {pendingCount > 0 ? (
                          <span className="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-rose-500/90 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">
                            {pendingCount > 99 ? '99+' : pendingCount}
                          </span>
                        ) : null}
                      </motion.span>
                    )}
                  </AnimatePresence>

                  {collapsed && pendingCount > 0 ? (
                    <span className="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white">
                      {pendingCount > 9 ? '9+' : pendingCount}
                    </span>
                  ) : null}

                  {/* Tooltip for collapsed mode */}
                  {collapsed && (
                    <div className="absolute left-14 z-50 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100 pointer-events-none whitespace-nowrap border border-slate-700/50">
                      {itemLabel}{pendingCount > 0 ? ` (${pendingCount})` : ''}
                    </div>
                  )}
                </Link>
              )
            })}
          </div>
        ))}
      </div>

      <div className="flex-shrink-0 p-3 flex flex-col gap-1 border-t border-slate-800/60 bg-slate-900/20">
        {auth.workspace !== 'affiliate' && auth.can('settings.view') && (
          <Link 
            to="/settings" 
            className={cn(
              "group relative flex items-center rounded-xl px-2.5 py-2 text-[13px] font-medium transition-all duration-200",
              isActive('/settings') ? "text-brand-400 bg-brand-500/10" : "text-slate-400 hover:bg-slate-800/50 hover:text-slate-200"
            )}
          >
            <Settings className="h-4 w-4 shrink-0 text-slate-500 group-hover:text-slate-300" strokeWidth={2} />
            <AnimatePresence>
              {!collapsed && (
                <motion.span 
                  initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0, width: 0 }}
                  className="ml-3 whitespace-nowrap overflow-hidden"
                >
                  Settings
                </motion.span>
              )}
            </AnimatePresence>
            {collapsed && (
              <div className="absolute left-14 z-50 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100 pointer-events-none whitespace-nowrap">Settings</div>
            )}
          </Link>
        )}
        
        <button type="button" className="group relative flex items-center rounded-xl px-2.5 py-2 text-left text-[13px] font-medium text-slate-400 transition-all duration-200 hover:bg-slate-800/50 hover:text-slate-200 w-full">
          <HelpCircle className="h-4 w-4 shrink-0 text-slate-500 group-hover:text-slate-300" strokeWidth={2} />
          <AnimatePresence>
            {!collapsed && (
              <motion.span 
                initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0, width: 0 }}
                className="ml-3 whitespace-nowrap overflow-hidden"
              >
                Support
              </motion.span>
            )}
          </AnimatePresence>
          {collapsed && (
            <div className="absolute left-14 z-50 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white opacity-0 shadow-xl transition-opacity duration-200 group-hover:opacity-100 pointer-events-none whitespace-nowrap">Support</div>
          )}
        </button>

        {auth.workspace !== 'affiliate' && (auth.isSubscriptionLocked || isFreePlan) && auth.can('settings.view') && (
          <div className="mt-2">
            <Link to="/billing" className={cn(
              "relative flex w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-r from-brand-500 to-brand-700 py-2.5 text-sm font-semibold text-white transition-all hover:from-brand-400 hover:to-brand-600 shadow-[0_4px_15px_rgba(143,10,72,0.3)] hover:shadow-[0_6px_20px_rgba(143,10,72,0.4)]",
              collapsed ? "px-0" : "px-3"
            )}>
              {collapsed ? (
                <Zap className="h-4 w-4" fill="currentColor" />
              ) : (
                <span className="flex items-center gap-2">
                  <Zap className="h-4 w-4" fill="currentColor" />
                  {auth.isSubscriptionLocked ? 'Billing & Plans' : 'Upgrade Pro'}
                </span>
              )}
            </Link>
          </div>
        )}
      </div>
    </motion.aside>

    {/* Mobile Slide-Over Drawer */}
    <AnimatePresence>
      {mobileOpen && (
        <>
          {/* Backdrop overlay */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={onCloseMobile}
            className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm md:hidden"
          />

          {/* Drawer panel */}
          <motion.aside
            initial={{ x: '-100%' }}
            animate={{ x: 0 }}
            exit={{ x: '-100%' }}
            transition={{ type: 'spring', stiffness: 300, damping: 30 }}
            className="fixed left-0 top-0 bottom-0 z-50 w-72 max-w-[85vw] bg-[#0A0F1C] border-r border-slate-800 text-slate-300 flex flex-col shadow-2xl md:hidden"
          >
            {/* Mobile Header */}
            <div className="flex-shrink-0 border-b border-slate-800/60 px-3 py-3 min-h-[80px] flex items-center justify-between gap-3">
              <BrandLogo size="h-[52px]" className="max-w-[200px] min-w-0" />

              <button
                type="button"
                aria-label="Close navigation menu"
                onClick={onCloseMobile}
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Mobile Navigation List */}
            <div className="flex-1 overflow-y-auto scrollbar-hide py-4 px-3 flex flex-col gap-6">
              {mobileGroups.map((group, idx) => (
                <div key={idx} className="flex flex-col gap-1">
                  <div className="px-2 pb-1 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                    {group.title}
                  </div>
                  {group.items.map((item) => {
                    const active = isActive(item.to)
                    const Icon = item.icon
                    const itemLabel = isBranchScoped && item.scopedLabel ? item.scopedLabel : item.label
                    const pendingCount = resolvePendingCount(item)

                    return (
                      <Link
                        key={item.to}
                        to={item.to}
                        onClick={onCloseMobile}
                        className={cn(
                          "group relative flex items-center rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200",
                          active
                            ? "text-brand-400 bg-brand-500/10 shadow-[inset_0_1px_0_rgba(204,15,103,0.1)] font-semibold"
                            : "text-slate-300 hover:bg-slate-800/50 hover:text-white"
                        )}
                      >
                        {active && (
                          <div className="absolute left-0 top-1/2 -mt-2.5 h-5 w-1 rounded-r-full bg-brand-500 shadow-[0_0_10px_rgba(204,15,103,0.8)]" />
                        )}
                        <Icon className={cn("h-4 w-4 shrink-0 mr-3 transition-colors", active ? "text-brand-400" : "text-slate-400 group-hover:text-slate-200")} strokeWidth={active ? 2.5 : 2} />
                        <span className="truncate flex-1">{itemLabel}</span>
                        {pendingCount > 0 && (
                          <span className="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-rose-500/90 px-1.5 py-0.5 text-[10px] font-bold text-white">
                            {pendingCount > 99 ? '99+' : pendingCount}
                          </span>
                        )}
                      </Link>
                    )
                  })}
                </div>
              ))}
            </div>

            {/* Mobile Footer Actions */}
            <div className="flex-shrink-0 p-3 flex flex-col gap-1 border-t border-slate-800/60 bg-slate-900/40 pb-safe">
              {auth.workspace !== 'affiliate' && auth.can('settings.view') && (
                <Link
                  to="/settings"
                  onClick={onCloseMobile}
                  className={cn(
                    "flex items-center rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200",
                    isActive('/settings') ? "text-brand-400 bg-brand-500/10 font-semibold" : "text-slate-300 hover:bg-slate-800/50 hover:text-white"
                  )}
                >
                  <Settings className="h-4 w-4 shrink-0 mr-3 text-slate-400" strokeWidth={2} />
                  <span>Settings</span>
                </Link>
              )}

              {auth.workspace !== 'affiliate' && (auth.isSubscriptionLocked || isFreePlan) && auth.can('settings.view') && (
                <div className="mt-2">
                  <Link
                    to="/billing"
                    onClick={onCloseMobile}
                    className="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-brand-500 to-brand-700 py-2.5 text-sm font-semibold text-white shadow-[0_4px_15px_rgba(143,10,72,0.3)]"
                  >
                    <Zap className="h-4 w-4 mr-2" fill="currentColor" />
                    {auth.isSubscriptionLocked ? 'Billing & Plans' : 'Upgrade Pro'}
                  </Link>
                </div>
              )}
            </div>
          </motion.aside>
        </>
      )}
    </AnimatePresence>
    </>
  )
}
