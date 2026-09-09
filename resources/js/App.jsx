import React from 'react'
import { useLocation } from 'react-router-dom'
import { useAuthStore } from './stores/auth'
import { AppRoutes, isAuthLayoutPath } from './router/index.jsx'
import AppLayout from './layouts/AppLayout.jsx'
import AuthBootstrap from './components/ui/AuthBootstrap.jsx'
import { InitAuth } from './router/guards.jsx'

export default function App() {
  const location = useLocation()
  const auth = useAuthStore()
  const isAuthLayout = isAuthLayoutPath(location.pathname)

  if (!auth.initialized) {
    return (
      <>
        <InitAuth />
        <AuthBootstrap />
      </>
    )
  }

  if (isAuthLayout || !auth.isAuthenticated) {
    return (
      <div className="app-shell min-h-screen">
        <main>
          <AppRoutes />
        </main>
      </div>
    )
  }

  return (
    <AppLayout>
      <AppRoutes />
    </AppLayout>
  )
}
