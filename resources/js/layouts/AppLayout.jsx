import React, { useState, useEffect, Suspense } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import AppBottomNav from '../components/layout/AppBottomNav.jsx'
import AppHeader from '../components/layout/AppHeader.jsx'
import AppSidebar from '../components/layout/AppSidebar.jsx'
import LoadingBar from '../components/ui/LoadingBar.jsx'
import PageSpinner from '../components/ui/PageSpinner.jsx'
import SubscriptionReadOnlyNotice from '../components/subscription/SubscriptionReadOnlyNotice.jsx'
import TenantBrandingTheme from '../components/tenant/TenantBrandingTheme.jsx'

const STORAGE_KEY = 'salonos_sidebar_collapsed'

export default function AppLayout({ children, drawer }) {
  const location = useLocation()

  // Initialize from localStorage or default to false
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
    return localStorage.getItem(STORAGE_KEY) === 'true'
  })
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  // Auto-close mobile menu on route change
  useEffect(() => {
    setMobileMenuOpen(false)
  }, [location.pathname])

  // Check window size on mount to auto-collapse on smaller desktop screens
  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth < 1024 && window.innerWidth >= 768) {
        setSidebarCollapsed(true)
      }
      if (window.innerWidth >= 768) {
        setMobileMenuOpen(false)
      }
    }
    handleResize()
    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])

  const toggleSidebar = () => {
    const nextValue = !sidebarCollapsed
    setSidebarCollapsed(nextValue)
    localStorage.setItem(STORAGE_KEY, String(nextValue))
  }

  const toggleMobileMenu = () => {
    setMobileMenuOpen((prev) => !prev)
  }

  const isAppointmentsWorkspace = location.pathname.startsWith('/appointments')

  return (
    <div className="min-h-screen bg-[#F8FAFC] text-slate-900 selection:bg-brand-500/30 font-sans relative antialiased">
      <TenantBrandingTheme />
      {/* Top progress loader bar for smooth screen redirection feedback */}
      <LoadingBar />

      <AppSidebar
        collapsed={sidebarCollapsed}
        onToggleSidebar={toggleSidebar}
        mobileOpen={mobileMenuOpen}
        onCloseMobile={() => setMobileMenuOpen(false)}
      />

      <div
        style={{
          transition: 'margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
        }}
        className={`flex flex-col min-h-screen transition-all duration-300 ${sidebarCollapsed ? 'md:ml-[72px]' : 'md:ml-[260px]'
          }`}
      >
        <AppHeader
          onToggleSidebar={toggleSidebar}
          onToggleMobile={toggleMobileMenu}
        />

        <main
          className={
            isAppointmentsWorkspace
              ? 'relative w-full max-w-none flex-1 px-3 py-3 sm:px-5 lg:px-6 pb-24 md:pb-6'
              : 'flex-1 relative w-full max-w-[1600px] mx-auto px-4 py-6 sm:px-8 lg:px-10 pb-28 md:pb-10 min-h-[calc(100vh-4rem)]'
          }
        >
          <div className={isAppointmentsWorkspace ? 'shrink-0' : 'mb-5'}>
            <SubscriptionReadOnlyNotice />
          </div>
          <Suspense fallback={<PageSpinner fullScreen label="Loading screen..." />}>
            {children || (
              <AnimatePresence mode="wait">
                <motion.div
                  key={location.pathname}
                  initial={{ opacity: 0, y: 6 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -6 }}
                  transition={{ duration: 0.2, ease: "easeOut" }}
                  className="w-full"
                >
                  <Outlet />
                </motion.div>
              </AnimatePresence>
            )}
          </Suspense>
        </main>
      </div>

      {/* Floating detail drawer overlay */}
      <AnimatePresence>
        {drawer && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="fixed inset-0 z-40 bg-slate-900/20 backdrop-blur-sm"
            />
            <motion.div
              initial={{ x: "100%", boxShadow: "-10px 0 30px rgba(0,0,0,0)" }}
              animate={{ x: 0, boxShadow: "-10px 0 30px rgba(0,0,0,0.1)" }}
              exit={{ x: "100%", boxShadow: "-10px 0 30px rgba(0,0,0,0)" }}
              transition={{ type: "spring", stiffness: 300, damping: 30 }}
              className="fixed right-0 top-0 z-50 h-screen w-full max-w-md border-l border-slate-200/60 bg-white"
            >
              {drawer}
            </motion.div>
          </>
        )}
      </AnimatePresence>

      <AppBottomNav />
    </div>
  )
}

