export const TENANT_BASE_DOMAIN = import.meta.env.VITE_TENANT_BASE_DOMAIN || 'saloenza.com'

export const RESERVED_SALON_DOMAINS = new Set([
  'app',
  'www',
  'api',
  'admin',
  'mail',
  'smtp',
  'ftp',
  'cdn',
  'static',
  'assets',
  'localhost',
  'saloenza',
  'support',
  'help',
  'status',
  'blog',
  'docs',
  'staging',
  'dev',
  'test',
  'demo',
])

export function slugifySalonDomain(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '')
    .slice(0, 63)
}

export function normalizeSalonDomainInput(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9-]/g, '')
    .slice(0, 63)
}

export function salonWorkspaceHost(domain) {
  if (!domain) return ''
  return `${domain}.${TENANT_BASE_DOMAIN}`
}

export function currentSalonWorkspace() {
  if (typeof window === 'undefined') return null
  return window.__salonWorkspace || null
}
