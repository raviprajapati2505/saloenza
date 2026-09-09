import '@vitejs/plugin-react/preamble'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import App from './App.jsx'
import { AuthProvider } from './stores/auth'
import { api, setAuthToken } from './lib/api'
import ToastHost from './components/ui/ToastHost.jsx'
import PwaInstallPrompt from './components/ui/PwaInstallPrompt.jsx'
import { hydrateModuleCatalog } from './lib/moduleCatalog.js'
import { hydratePlatformBranding } from './stores/platformBranding.js'
import '../css/app.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      staleTime: 1000 * 60 * 5,
    },
  },
})

setAuthToken(localStorage.getItem('salonos_token'))

void Promise.all([
  hydratePlatformBranding(),
  hydrateModuleCatalog(),
]).finally(() => {
  createRoot(document.getElementById('app')).render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <App />
          <ToastHost />
          <PwaInstallPrompt />
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>,
  )
})

if (
  'serviceWorker' in navigator &&
  (window.location.protocol === 'https:' ||
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1' ||
    window.location.hostname.endsWith('.test'))
) {
  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/sw.js', { scope: '/', updateViaCache: 'none' })
      .catch((err) => {
        console.warn('SW registration failed:', err)
      })
  })
}

export { api, setAuthToken }

