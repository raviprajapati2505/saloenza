import { api, setAuthToken } from '../lib/api'
import { PLATFORM_PERMISSIONS } from '../lib/platformPermissions'
import { getRoleDisplay } from '../lib/roleDisplay'
import { isMutationPermission, isSubscriptionLocked as detectSubscriptionLocked, isSubscriptionReadOnly as detectSubscriptionReadOnly } from '../lib/subscriptionModules'
import { registerSubscriptionAccessSync } from '../lib/subscriptionAccessSync'
import { setModuleCatalog } from '../lib/moduleCatalog.js'
import { setPlatformBranding } from './platformBranding.js'
import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { flushSync } from 'react-dom'

function normalizePermissionCodes(permissions) {
  if (!permissions) return []
  if (Array.isArray(permissions)) {
    return permissions
      .map((entry) => (typeof entry === 'string' ? entry : entry?.code))
      .filter(Boolean)
  }
  if (typeof permissions === 'object' && Array.isArray(permissions.data)) {
    return normalizePermissionCodes(permissions.data)
  }
  return []
}

const TOKEN_KEY = 'salonos_token'
const USE_MOCK_AUTH = import.meta.env.VITE_USE_MOCK_AUTH === 'true'
const MOCK_TOKEN = 'mock-token-salonos'

const sleep = (ms = 350) => new Promise((resolve) => setTimeout(resolve, ms))

const ROLE_CODES = {
  PLATFORM_SUPER_ADMIN: 'platform.super_admin',
  AFFILIATE_PARTNER: 'affiliate.partner',
  SALON_FRANCHISE_OWNER: 'salon.franchise_owner',
  SALON_FRANCHISE_MANAGER: 'salon.franchise_manager',
  SALON_BRANCH_MANAGER: 'salon.branch_manager',
  SALON_STAFF: 'salon.staff',
}

const buildMockSession = (identifier) => {
  const isSysAdmin = identifier === 'admin@salonos.com'
  return {
    token: MOCK_TOKEN,
    is_system_admin: isSysAdmin,
    user: {
      id: 1,
      name: isSysAdmin ? 'System Admin' : 'Salon Owner',
      email: String(identifier || '').includes('@') ? identifier : 'owner@salonos.test',
      phone: String(identifier || '').includes('@') ? null : (identifier || null),
      is_system_admin: isSysAdmin,
      role_id: isSysAdmin ? null : 1,
      saloon_id: isSysAdmin ? null : 1,
    },
    tenant: isSysAdmin ? null : {
      id: 1,
      name: 'Glowsuite',
      plan: { id: 1, name: 'Free', slug: 'free', price: 0, billing_interval: 'monthly' },
      modules: ['appointments', 'customers', 'staff', 'catalog', 'settings'],
      limits: { max_branches: 1, max_staff: 3, branches_used: 0, staff_used: 0 },
      trial_ends_at: new Date(Date.now() + 10 * 24 * 60 * 60 * 1000).toISOString(),
    },
    role: isSysAdmin ? {
      id: 1,
      name: 'Super Admin',
      code: ROLE_CODES.PLATFORM_SUPER_ADMIN,
      is_active: true,
      is_system: true,
      scope: 'platform',
      saloon_id: null,
    } : {
      id: 1,
      name: 'Salon Franchise Owner',
      code: ROLE_CODES.SALON_FRANCHISE_OWNER,
      is_active: true,
      is_system: true,
      scope: 'salon',
      saloon_id: null,
      permissions: [{ code: 'appointments.view' }]
    },
    permissions: isSysAdmin ? [
      PLATFORM_PERMISSIONS.ONBOARDING,
      PLATFORM_PERMISSIONS.ONBOARDING_CREATE,
      PLATFORM_PERMISSIONS.ONBOARDING_UPDATE,
      PLATFORM_PERMISSIONS.ONBOARDING_DELETE,
      PLATFORM_PERMISSIONS.SUBSCRIPTIONS,
      PLATFORM_PERMISSIONS.SUBSCRIPTION_PLANS_CREATE,
      PLATFORM_PERMISSIONS.SUBSCRIPTION_PLANS_UPDATE,
      PLATFORM_PERMISSIONS.SUBSCRIPTION_PLANS_DELETE,
      PLATFORM_PERMISSIONS.BRANCHES,
      PLATFORM_PERMISSIONS.BRANCHES_CREATE,
      PLATFORM_PERMISSIONS.BRANCHES_UPDATE,
      PLATFORM_PERMISSIONS.UPGRADE_REQUESTS,
      PLATFORM_PERMISSIONS.UPGRADE_REQUESTS_UPDATE,
      'categories.view',
      'categories.create',
      'categories.update',
      'categories.delete',
      'services.view',
      'services.create',
      'services.update',
      'services.delete',
      'products.view',
      'products.create',
      'products.update',
      'products.delete',
      'settings.view',
      'settings.update',
      'branches.view',
      'branches.create',
      'branches.update',
      'branches.delete',
      'roles.view',
      'roles.create',
      'roles.update',
      'roles.delete',
      'assign_permissions.view',
      'assign_permissions.update',
    ] : [
      'categories.view',
      'categories.create',
      'categories.update',
      'categories.delete',
      'services.view',
      'services.create',
      'services.update',
      'services.delete',
      'products.view',
      'products.create',
      'products.update',
      'products.delete',
      'settings.view',
      'settings.update',
      'branches.view',
      'branches.create',
      'branches.update',
      'branches.delete',
      'roles.view',
      'roles.create',
      'roles.update',
      'roles.delete',
      'assign_permissions.view',
      'assign_permissions.update',
    ],
  }
}

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [token, setToken] = useState(null)
  const [tenant, setTenant] = useState(null)
  const [affiliatePartner, setAffiliatePartner] = useState(null)
  const [shouldOnboard, setShouldOnboard] = useState(false)
  const [permissions, setPermissions] = useState([])
  const [isAuthenticated, setIsAuthenticated] = useState(false)
  const [loading, setLoading] = useState(false)
  const [initialized, setInitialized] = useState(false)
  const [role, setRole] = useState(null)
  const [isSystemAdmin, setIsSystemAdmin] = useState(false)
  const [subscriptionModules, setSubscriptionModules] = useState([])
  const [subscriptionLimits, setSubscriptionLimits] = useState(null)
  const [workspace, setWorkspace] = useState('tenant')

  const planName = useMemo(() => (
    tenant?.plan?.name || tenant?.plan_name || tenant?.subscription_plan || null
  ), [tenant])

  const isFree = useMemo(() => {
    const slug = String(tenant?.plan?.slug || tenant?.plan?.name || tenant?.plan_name || tenant?.subscription_plan || '').toLowerCase()
    return slug === 'free-trial' || slug === 'free'
  }, [tenant])

  const isSubscriptionReadOnly = useMemo(
    () => detectSubscriptionReadOnly({ tenant, grantsAllPermissions: isSystemAdmin }),
    [tenant, isSystemAdmin],
  )

  const isSubscriptionLocked = useMemo(
    () => detectSubscriptionLocked({ tenant, grantsAllPermissions: isSystemAdmin }),
    [tenant, isSystemAdmin],
  )

  const syncSubscriptionAccessMode = useCallback((accessMode) => {
    if (!accessMode || isSystemAdmin) return
    setTenant((current) => {
      if (!current) return current
      return {
        ...current,
        subscription_access_mode: accessMode,
        subscription_expired: accessMode === 'read_only' || accessMode === 'locked',
      }
    })
  }, [isSystemAdmin])

  const patchTenant = useCallback((partial) => {
    if (!partial) return
    setTenant((current) => (current ? { ...current, ...partial } : current))
  }, [])

  const authHeaders = useCallback(() => (token ? { Authorization: `Bearer ${token}` } : {}), [token])

  const clearAuthState = useCallback(() => {
    setUser(null)
    setToken(null)
    setAuthToken(null)
    setTenant(null)
    setAffiliatePartner(null)
    setRole(null)
    setIsSystemAdmin(false)
    setSubscriptionModules([])
    setSubscriptionLimits(null)
    setShouldOnboard(false)
    setWorkspace('tenant')
    setPermissions([])
    setIsAuthenticated(false)
  }, [])

  const markOnboardingCompleted = useCallback(() => {
    setShouldOnboard(false)
  }, [])

  const applyAuthPayload = useCallback((payload, { sync = false } = {}) => {
    const nextWorkspace = payload.workspace
      || (payload.affiliate_partner ? 'affiliate' : null)
      || (payload.role?.scope === 'affiliate' ? 'affiliate' : null)
      || (payload.role?.scope === 'platform' || payload.is_system_admin ? 'platform' : 'tenant')

    const apply = () => {
      setUser(payload.user || null)
      setTenant(payload.tenant || null)
      setAffiliatePartner(payload.affiliate_partner || null)
      setRole(payload.role || null)
      setIsSystemAdmin(Boolean(payload.is_system_admin))
      setShouldOnboard(Boolean(payload.should_onboard))
      setPermissions(normalizePermissionCodes(payload.permissions))
      setSubscriptionModules(payload.subscription_modules || payload.tenant?.modules || [])
      setSubscriptionLimits(payload.subscription_limits || payload.tenant?.limits || null)
      setModuleCatalog(payload.subscription_module_catalog || null)
      if (payload.platform_branding) {
        setPlatformBranding(payload.platform_branding)
      }
      setWorkspace(nextWorkspace)
      setIsAuthenticated(Boolean(payload.user))
    }

    // Login navigates immediately after this; flush so route guards see the session.
    if (sync) {
      flushSync(apply)
      return
    }

    apply()
  }, [])

  const fetchMe = useCallback(async (overrideToken = null) => {
    setLoading(true)
    try {
      if (USE_MOCK_AUTH) {
        await sleep(200)
        const mock = buildMockSession(user?.email || user?.phone || 'owner@salonos.test')
        setUser(mock.user)
        setTenant(mock.tenant)
        setAffiliatePartner(null)
        setRole(mock.role)
        setIsSystemAdmin(mock.is_system_admin)
        setPermissions(mock.permissions)
        setSubscriptionModules(mock.tenant?.modules || [])
        setSubscriptionLimits(mock.tenant?.limits || null)
        setWorkspace(mock.role?.scope === 'platform' ? 'platform' : 'tenant')
        setIsAuthenticated(true)
        return mock
      }

      const activeToken = overrideToken || token
      if (activeToken) {
        setAuthToken(activeToken)
      }

      const response = await api.get('/v1/me')

      const data = response.data || {}
      const payload = data.data || data
      applyAuthPayload(payload)
      return payload
    } catch (error) {
      const status = error?.response?.status
      if (status === 401 || status === 419) {
        // Token rejected by server — clear everything, force re-login
        setToken(null)
        setAuthToken(null)
        localStorage.removeItem(TOKEN_KEY)
        setUser(null)
        setTenant(null)
        setAffiliatePartner(null)
        setRole(null)
        setIsSystemAdmin(false)
        setSubscriptionModules([])
        setSubscriptionLimits(null)
        setWorkspace('tenant')
        setPermissions([])
        setIsAuthenticated(false)
        return null
      }

      // Network error or 500: preserve authentication if we had a token
      // Do NOT replace with a mock session — that would wipe the real token
      if (overrideToken || token) {
        setIsAuthenticated(true)
        return null
      }

      setUser(null)
      setTenant(null)
      setAffiliatePartner(null)
      setRole(null)
      setIsSystemAdmin(false)
      setWorkspace('tenant')
      setPermissions([])
      setIsAuthenticated(false)
      throw error
    } finally {
      setLoading(false)
    }
  }, [authHeaders, token, user?.email, user?.phone, applyAuthPayload])

  const login = useCallback(async (login, password, deviceName = 'web') => {
    setLoading(true)
    try {
      if (USE_MOCK_AUTH) {
        await sleep()
        const mock = buildMockSession(login)
        setToken(mock.token)
        setUser(mock.user)
        setTenant(mock.tenant)
        setAffiliatePartner(null)
        setRole(mock.role)
        setIsSystemAdmin(mock.is_system_admin)
        setPermissions(mock.permissions)
        setSubscriptionModules(mock.tenant?.modules || [])
        setSubscriptionLimits(mock.tenant?.limits || null)
        setWorkspace(mock.role?.scope === 'platform' ? 'platform' : 'tenant')
        setIsAuthenticated(true)
        localStorage.setItem(TOKEN_KEY, mock.token)
        return {
          ...mock,
          mock: true,
          next_step: password === '222222' ? '2fa' : null,
        }
      }

      const response = await api.post('/v1/public/login', {
        login,
        password,
        device_name: deviceName,
      })

      const data = response.data || {}
      const nextToken = data.token || data.access_token || data?.data?.token || null
      setToken(nextToken)
      setAuthToken(nextToken)
      if (nextToken) localStorage.setItem(TOKEN_KEY, nextToken)
      else localStorage.removeItem(TOKEN_KEY)

      const payload = data.data || data
      applyAuthPayload(payload, { sync: true })

      if (!(payload.user || data.user)) {
        await fetchMe(nextToken)
      }

      return response.data
    } catch (error) {
      if (USE_MOCK_AUTH) {
        await sleep()
        const mock = buildMockSession(login)
        setToken(mock.token)
        setUser(mock.user)
        setTenant(mock.tenant)
        setAffiliatePartner(null)
        setRole(mock.role)
        setIsSystemAdmin(mock.is_system_admin)
        setPermissions(mock.permissions)
        setSubscriptionModules(mock.tenant?.modules || [])
        setSubscriptionLimits(mock.tenant?.limits || null)
        setWorkspace(mock.role?.scope === 'platform' ? 'platform' : 'tenant')
        setIsAuthenticated(true)
        localStorage.setItem(TOKEN_KEY, mock.token)
        return { ...mock, mock: true }
      }
      throw error
    } finally {
      setLoading(false)
    }
  }, [fetchMe, applyAuthPayload])

  const logout = useCallback(async () => {
    setLoading(true)
    try {
      if (!USE_MOCK_AUTH) {
        await api.post('/v1/logout')
      } else {
        await sleep(150)
      }
    } catch {
      // ignore
    } finally {
      clearAuthState()
      localStorage.removeItem(TOKEN_KEY)
      setLoading(false)
    }
  }, [authHeaders, clearAuthState])

  const grantsAllPermissions = useMemo(
    () => isSystemAdmin || role?.code === ROLE_CODES.PLATFORM_SUPER_ADMIN,
    [isSystemAdmin, role?.code],
  )

  const can = useCallback((code) => {
    if (!code) return true
    if (grantsAllPermissions) return true
    if (isSubscriptionReadOnly && isMutationPermission(code)) return false
    return permissions.includes(code)
  }, [grantsAllPermissions, permissions, isSubscriptionReadOnly])

  const canAny = useCallback((codes) => {
    if (!codes?.length) return true
    if (grantsAllPermissions) return true
    return codes.some((code) => {
      if (isSubscriptionReadOnly && isMutationPermission(code)) return false
      return permissions.includes(code)
    })
  }, [grantsAllPermissions, permissions, isSubscriptionReadOnly])

  const canPlatform = useCallback((code) => {
    if (!code) return true
    if (grantsAllPermissions) return true
    return permissions.includes(code)
  }, [grantsAllPermissions, permissions])

  const canAffiliate = useCallback((code) => {
    if (!code) return true
    if (grantsAllPermissions) return true
    return permissions.includes(code)
  }, [grantsAllPermissions, permissions])

  const hasRoleCode = useCallback((code) => {
    if (!code) return false
    return role?.code === code
  }, [role])

  const isFranchiseOwner = useMemo(
    () => hasRoleCode(ROLE_CODES.SALON_FRANCHISE_OWNER),
    [hasRoleCode],
  )

  const isFranchiseManager = useMemo(
    () => hasRoleCode(ROLE_CODES.SALON_FRANCHISE_MANAGER),
    [hasRoleCode],
  )

  const isBranchManager = useMemo(
    () => hasRoleCode(ROLE_CODES.SALON_BRANCH_MANAGER),
    [hasRoleCode],
  )

  const isStaffMember = useMemo(
    () => hasRoleCode(ROLE_CODES.SALON_STAFF),
    [hasRoleCode],
  )

  const isBranchScoped = useMemo(
    () => Boolean(role?.scope === 'branch' || isBranchManager || isStaffMember),
    [role?.scope, isBranchManager, isStaffMember],
  )

  const isAffiliatePartner = useMemo(
    () => hasRoleCode(ROLE_CODES.AFFILIATE_PARTNER) || workspace === 'affiliate',
    [hasRoleCode, workspace],
  )

  const canModule = useCallback((module) => {
    if (!module) return true
    if (grantsAllPermissions) return true
    return subscriptionModules.includes(module)
  }, [grantsAllPermissions, subscriptionModules])

  const roleDisplay = useMemo(
    () => getRoleDisplay({ isSystemAdmin, role }),
    [isSystemAdmin, role],
  )

  const initialize = useCallback(async () => {
    if (initialized) return
    const savedToken = localStorage.getItem(TOKEN_KEY)
    if (savedToken) {
      setToken(savedToken)
      setAuthToken(savedToken)
      try {
        await fetchMe(savedToken)
      } catch {
        // handled
      }
    } else {
      setIsAuthenticated(false)
    }
    setInitialized(true)
  }, [fetchMe, initialized])

  useEffect(() => {
    registerSubscriptionAccessSync(syncSubscriptionAccessMode)
    return () => registerSubscriptionAccessSync(null)
  }, [syncSubscriptionAccessMode])

  const bootstrapStarted = useRef(false)

  useEffect(() => {
    if (bootstrapStarted.current) return
    bootstrapStarted.current = true
    void initialize()
  }, [initialize])

  const value = useMemo(() => ({
    user,
    token,
    tenant,
    affiliatePartner,
    role,
    roleDisplay,
    subscriptionModules,
    subscriptionLimits,
    workspace,
    isSystemAdmin,
    grantsAllPermissions,
    shouldOnboard,
    permissions,
    isAuthenticated,
    loading,
    initialized,
    isFree,
    planName,
    isSubscriptionReadOnly,
    isSubscriptionLocked,
    syncSubscriptionAccessMode,
    patchTenant,
    authHeaders,
    clearAuthState,
    markOnboardingCompleted,
    login,
    logout,
    fetchMe,
    can,
    canAny,
    canPlatform,
    canAffiliate,
    canModule,
    hasRoleCode,
    isFranchiseOwner,
    isFranchiseManager,
    isBranchManager,
    isStaffMember,
    isBranchScoped,
    isAffiliatePartner,
    initialize,
  }), [
    user, token, tenant, affiliatePartner, role, roleDisplay, subscriptionModules, subscriptionLimits, workspace, isSystemAdmin, grantsAllPermissions, shouldOnboard, permissions, isAuthenticated, loading, initialized,
    isFree, planName, isSubscriptionReadOnly, isSubscriptionLocked, syncSubscriptionAccessMode, patchTenant,
    authHeaders, clearAuthState, markOnboardingCompleted, login, logout, fetchMe, can, canAny, canPlatform, canAffiliate, canModule, hasRoleCode,
    isFranchiseOwner, isFranchiseManager, isBranchManager, isStaffMember, isBranchScoped, isAffiliatePartner, initialize,
  ])

  return React.createElement(AuthContext.Provider, { value }, children)
}

export function useAuthStore() {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuthStore must be used within AuthProvider')
  }
  return ctx
}
