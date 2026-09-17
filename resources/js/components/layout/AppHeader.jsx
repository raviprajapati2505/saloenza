import React, { useEffect, useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuthStore } from '../../stores/auth'
import { motion, AnimatePresence } from 'framer-motion'
import { Menu, Transition } from '@headlessui/react'
import { Search, Menu as MenuIcon, Settings, LogOut, Command } from 'lucide-react'
import RoleBadge from '../ui/RoleBadge.jsx'
import BrandLogo from '../ui/BrandLogo.jsx'
import NotificationBell from './NotificationBell.jsx'
import { getPlatformBranding } from '../../stores/platformBranding.js'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function AppHeader({ onToggleSidebar, onToggleMobile }) {
  const location = useLocation()
  const router = useNavigate()
  const auth = useAuthStore()

  const [searchOpen, setSearchOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [scrolled, setScrolled] = useState(false)

  const breadcrumb = useMemo(() => {
    const platformName = getPlatformBranding()?.portal_name || 'Saloenza'
    const map = {
      dashboard: 'Overview',
      appointments: 'Schedule',
      staff: 'Team',
      inventory: 'Inventory',
      customers: 'Customers',
      billing: 'Billing',
      analytics: 'Reports',
      reports: 'Reports',
      admin: 'Administration',
      settings: 'Settings',
      affiliate: 'Affiliate Portal',
    }
    const key = location.pathname.split('/')[1]
    return map[key] || platformName
  }, [location.pathname])

  const avatarInitials = useMemo(() => {
    const name = auth.user?.name || 'Salon User'
    return name.split(' ').map((part) => part.charAt(0)).join('').slice(0, 2).toUpperCase()
  }, [auth.user?.name])

  const trialDaysLeft = useMemo(() => {
    const endsAt = auth.tenant?.trial_ends_at || auth.tenant?.subscription?.trial_ends_at
    if (!endsAt) return null
    const diffMs = new Date(endsAt).getTime() - Date.now()
    const days = Math.ceil(diffMs / (1000 * 60 * 60 * 24))
    return days > 0 ? days : 0
  }, [auth.tenant])

  const showTrialBanner = auth.isFree && trialDaysLeft !== null && trialDaysLeft <= 14
  const isLocked = auth.tenant?.subscription_access_mode === 'locked'

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 10)
    }
    window.addEventListener('scroll', handleScroll)
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  useEffect(() => {
    const handleKeydown = (event) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        setSearchOpen(true)
      }
      if (event.key === 'Escape') {
        setSearchOpen(false)
      }
    }
    window.addEventListener('keydown', handleKeydown)
    return () => window.removeEventListener('keydown', handleKeydown)
  }, [])

  const handleLogout = async () => {
    await auth.logout()
    router('/login')
  }

  return (
    <>
      <header className={cn(
        "sticky top-0 z-20 transition-all duration-300",
        // Distinct dark navy header bar on mobile for fast view and sharp separation; Frosted white navbar on desktop
        "bg-[#0A0F1C] border-b border-slate-800 text-white shadow-md md:shadow-none md:text-slate-800 md:border-transparent",
        scrolled
          ? "md:bg-white/90 md:backdrop-blur-xl md:border-slate-200/60 md:shadow-sm"
          : "md:bg-transparent"
      )}>
        {showTrialBanner && auth.can('settings.view') ? (
          <div className="bg-gradient-to-r from-amber-500/10 via-amber-400/10 to-amber-500/10 px-4 py-2 border-b border-amber-200/50 flex items-center justify-center">
            <p className="text-xs font-semibold text-amber-300 md:text-amber-800 tracking-wide">
              Trial ending in {trialDaysLeft} days. <Link to="/billing" className="ml-1 underline decoration-amber-400 underline-offset-2 hover:text-white md:hover:text-amber-900 transition-colors">Upgrade to Pro</Link>
            </p>
          </div>
        ) : null}
        {isLocked && auth.can('settings.view') ? (
          <div className="relative overflow-hidden border-b border-white/10 bg-gradient-to-r from-[#1a1810] via-slate-900 to-[#151a25] px-4 py-2.5">
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-amber-500/10 via-brand-600/8 to-transparent" aria-hidden />
            <p className="relative text-center text-xs font-medium text-slate-200 sm:text-sm">
              Free trial ended — workspace locked.
              <Link to="/billing" className="ml-2 font-semibold text-white underline decoration-brand-400/50 underline-offset-4 hover:decoration-brand-300">
                Upgrade on Billing →
              </Link>
            </p>
          </div>
        ) : null}

        <div className="flex items-center justify-between gap-3 px-4 py-3 sm:px-6 h-16">
          {/* Left: Mobile Toggle & Brand Context / Desktop Breadcrumb */}
          <div className="flex min-w-0 items-center gap-3">
            <button 
              type="button" 
              aria-label="Open mobile menu"
              className="md:hidden flex items-center justify-center p-2 -ml-1 rounded-xl text-brand-400 bg-brand-500/10 hover:bg-brand-500/20 active:scale-95 transition-all border border-brand-500/20 shadow-sm"
              onClick={onToggleMobile || onToggleSidebar}
            >
              <MenuIcon className="w-5 h-5" />
            </button>

            {/* Mobile Title with Logo */}
            <div className="flex items-center gap-2.5 md:hidden min-w-0">
              <BrandLogo variant="mark" size="sm" tone="onDark" className="rounded-lg shrink-0" />
              <div className="flex flex-col min-w-0">
                <span className="text-xs font-bold text-brand-400 tracking-wide leading-tight truncate">
                  {auth.tenant?.name || getPlatformBranding()?.portal_name || 'Saloenza'}
                </span>
                <span className="text-[11px] font-semibold text-slate-300 leading-tight truncate">
                  {breadcrumb}
                </span>
              </div>
            </div>

            {/* Desktop Breadcrumb */}
            <h1 className="text-[15px] font-semibold tracking-tight text-slate-800 hidden md:block">
              {breadcrumb}
            </h1>
          </div>

          {/* Center: Global Search (Cmd+K) */}
          <div className="flex-1 max-w-xl mx-auto flex justify-center hidden md:flex">
            <button 
              type="button" 
              className="group flex w-full max-w-md items-center gap-2 rounded-full border border-slate-200 bg-white/50 px-4 py-2 text-sm text-slate-500 shadow-sm transition-all hover:bg-white hover:border-brand-500/30 hover:shadow-[0_0_15px_rgba(143,10,72,0.1)] focus:outline-none focus:ring-2 focus:ring-brand-500/20"
              onClick={() => setSearchOpen(true)}
            >
              <Search className="h-4 w-4 text-slate-400 group-hover:text-brand-500 transition-colors" />
              <span className="flex-1 text-left tracking-wide">Search workspace...</span>
              <div className="flex items-center gap-1 opacity-70">
                <Command className="h-3 w-3" />
                <span className="text-[11px] font-medium tracking-wider">K</span>
              </div>
            </button>
          </div>

          {/* Right: Actions & Avatar */}
          <div className="flex items-center gap-2.5">
            {/* Mobile Quick Search Icon */}
            <button
              type="button"
              aria-label="Search workspace"
              className="md:hidden flex h-8 w-8 items-center justify-center rounded-xl text-slate-300 hover:text-white hover:bg-slate-800/80 active:scale-95 transition-all"
              onClick={() => setSearchOpen(true)}
            >
              <Search className="h-4 w-4" />
            </button>

            <NotificationBell />

            <div className="h-4 w-px bg-slate-800 md:bg-slate-200 mx-0.5" />

            {/* Avatar Dropdown */}
            <Menu as="div" className="relative">
              <Menu.Button className="flex items-center gap-2 rounded-full p-0.5 hover:bg-slate-800/60 md:hover:bg-slate-100/80 transition-colors focus:outline-none">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-tr from-brand-500 to-brand-700 md:from-slate-800 md:to-slate-700 text-[11px] font-bold text-white shadow-sm border border-brand-400/30 md:border-slate-600/20">
                  {avatarInitials}
                </div>
              </Menu.Button>

              <AnimatePresence>
                <Menu.Items
                  as={motion.div}
                  initial={{ opacity: 0, y: 10, scale: 0.95 }}
                  animate={{ opacity: 1, y: 0, scale: 1 }}
                  exit={{ opacity: 0, y: 5, scale: 0.95 }}
                  transition={{ type: "spring", stiffness: 400, damping: 30 }}
                  className="absolute right-0 mt-2 w-56 origin-top-right rounded-2xl bg-white p-1.5 shadow-xl border border-slate-100 focus:outline-none z-50 text-slate-800"
                >
                  <div className="px-3 py-2.5 mb-1 border-b border-slate-100">
                    <p className="text-sm font-medium text-slate-900 truncate">{auth.user?.name}</p>
                    <p className="text-xs text-slate-500 truncate">{auth.user?.email || auth.user?.phone || '-'}</p>
                    <div className="mt-2">
                      <RoleBadge auth={auth} size="xs" />
                    </div>
                  </div>
                  
                  <div className="px-1 py-1">
                    {auth.workspace !== 'affiliate' && auth.can('settings.view') && (
                      <Menu.Item>
                        {({ active }) => (
                          <Link
                            to={(auth.grantsAllPermissions || auth.isSystemAdmin) ? '/settings?tab=workspace' : '/settings'}
                            className={cn(
                            "flex w-full items-center gap-2 rounded-xl px-2 py-2 text-sm transition-colors",
                            active ? "bg-slate-50 text-brand-600" : "text-slate-700"
                          )}>
                            <Settings className="h-4 w-4" /> Workspace Settings
                          </Link>
                        )}
                      </Menu.Item>
                    )}
                  </div>
                  <div className="px-1 py-1 border-t border-slate-100">
                    <Menu.Item>
                      {({ active }) => (
                        <button onClick={handleLogout} className={cn(
                          "flex w-full items-center gap-2 rounded-xl px-2 py-2 text-sm font-medium transition-colors",
                          active ? "bg-rose-50 text-rose-600" : "text-rose-600/90"
                        )}>
                          <LogOut className="h-4 w-4" /> Sign out
                        </button>
                      )}
                    </Menu.Item>
                  </div>
                </Menu.Items>
              </AnimatePresence>
            </Menu>
          </div>
        </div>
      </header>

      {/* Global Search Command Palette (Modal) */}
      <AnimatePresence>
        {searchOpen && (
          <motion.div 
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/20 backdrop-blur-sm px-4 pt-[10vh]" 
            onClick={(e) => { if (e.target === e.currentTarget) setSearchOpen(false) }}
          >
            <motion.div 
              initial={{ opacity: 0, y: -20, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: -10, scale: 0.98 }}
              transition={{ type: "spring", stiffness: 400, damping: 30 }}
              className="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-200/50"
            >
              <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-4">
                <Search className="h-5 w-5 text-brand-500" />
                <input 
                  autoFocus
                  value={searchQuery} 
                  onChange={(e) => setSearchQuery(e.target.value)} 
                  type="text" 
                  className="w-full border-0 p-0 text-base outline-none placeholder:text-slate-400 focus:ring-0 text-slate-800 bg-transparent" 
                  placeholder="Search customers, appointments, services..." 
                />
                <kbd className="hidden sm:inline-block rounded-md bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500 border border-slate-200">
                  ESC
                </kbd>
              </div>
              <div className="px-4 py-12 text-center text-sm text-slate-500 bg-slate-50/50">
                <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 mb-3 text-slate-400">
                  <Command className="h-5 w-5" />
                </div>
                <p>Start typing to search your workspace...</p>
                <p className="text-xs text-slate-400 mt-1">Search is UI-only for now.</p>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  )
}
