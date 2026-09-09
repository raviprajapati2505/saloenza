import { describe, expect, it } from 'vitest'
import {
  canConfigureSalonSettings,
  canMutate,
  canRenewCurrentPlan,
  canRenewSubscription,
  hasPermissionCode,
  isMutationPermission,
  isSubscriptionLocked,
  isSubscriptionReadOnly,
  subscriptionPageSubtitle,
} from '../subscriptionModules.js'

describe('subscriptionModules', () => {
  const readOnlyAuth = {
    tenant: { subscription_access_mode: 'read_only', plan: { slug: 'basic' } },
    can: (code) => ['appointments.view', 'appointments.create', 'settings.view'].includes(code),
  }

  const lockedAuth = {
    tenant: { subscription_access_mode: 'locked', plan: { slug: 'free' } },
    can: () => true,
  }

  const expiredPaidFallbackAuth = {
    tenant: { subscription_expired: true, plan: { slug: 'basic' } },
    can: (code) => code.endsWith('.view') || code.endsWith('.create'),
  }

  it('detects read-only and locked modes', () => {
    expect(isSubscriptionReadOnly(readOnlyAuth)).toBe(true)
    expect(isSubscriptionLocked(readOnlyAuth)).toBe(false)
    expect(isSubscriptionLocked(lockedAuth)).toBe(true)
    expect(isSubscriptionReadOnly(expiredPaidFallbackAuth)).toBe(true)
    expect(isSubscriptionLocked({ tenant: { subscription_expired: true, plan: { slug: 'free' } } })).toBe(true)
  })

  it('blocks mutation permissions while allowing view permissions in read-only mode', () => {
    expect(isMutationPermission('appointments.create')).toBe(true)
    expect(isMutationPermission('appointments.view')).toBe(false)
    expect(isMutationPermission('customers.manage')).toBe(true)

    expect(canMutate(readOnlyAuth, 'appointments.view')).toBe(true)
    expect(canMutate(readOnlyAuth, 'appointments.create')).toBe(false)
    expect(canMutate(readOnlyAuth, 'customers.manage')).toBe(false)
    expect(canMutate(lockedAuth, 'appointments.view')).toBe(false)
  })

  it('allows salon owners to configure portal settings during read-only mode', () => {
    const ownerAuth = {
      ...readOnlyAuth,
      isFranchiseOwner: true,
      permissions: ['settings.view', 'settings.update'],
      subscriptionModules: ['settings', 'appointments'],
      can: (code) => ['settings.view', 'settings.update'].includes(code),
    }

    expect(hasPermissionCode(ownerAuth, 'settings.update')).toBe(true)
    expect(canConfigureSalonSettings(ownerAuth)).toBe(true)
    expect(canConfigureSalonSettings({
      ...ownerAuth,
      isFranchiseOwner: false,
      permissions: ['settings.view'],
    })).toBe(false)
  })

  it('keeps billing renewal available in read-only mode', () => {
    expect(canRenewSubscription(readOnlyAuth)).toBe(true)
    expect(canRenewSubscription({ ...readOnlyAuth, can: () => false })).toBe(false)
    expect(canRenewCurrentPlan(readOnlyAuth, {
      accessMode: 'read_only',
      plan: { slug: 'basic', name: 'Basic', price: 299 },
      status: 'expired',
    })).toBe(true)
    expect(canRenewCurrentPlan(lockedAuth, {
      accessMode: 'locked',
      plan: { slug: 'free', price: 0 },
    })).toBe(false)
  })

  it('uses view-only subtitles when subscription is expired paid', () => {
    expect(subscriptionPageSubtitle(readOnlyAuth, 'Manage items.')).toMatch(/view-only/i)
    expect(subscriptionPageSubtitle({ tenant: { subscription_access_mode: 'full' } }, 'Manage items.')).toBe('Manage items.')
  })
})
