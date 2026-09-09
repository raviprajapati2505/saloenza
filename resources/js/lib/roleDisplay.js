/** @typedef {{ label: string, scopeLabel: string | null, tone: 'platform' | 'salon' | 'branch' | 'neutral' }} RoleDisplay */

export const ROLE_CODES = {
  PLATFORM_SUPER_ADMIN: 'platform.super_admin',
  SALON_FRANCHISE_OWNER: 'salon.franchise_owner',
  SALON_FRANCHISE_MANAGER: 'salon.franchise_manager',
  SALON_BRANCH_MANAGER: 'salon.branch_manager',
  SALON_STAFF: 'salon.staff',
}

const CODE_LABELS = {
  [ROLE_CODES.PLATFORM_SUPER_ADMIN]: 'Super Admin',
  [ROLE_CODES.SALON_FRANCHISE_OWNER]: 'Salon Franchise Owner',
  [ROLE_CODES.SALON_FRANCHISE_MANAGER]: 'Franchise Manager',
  [ROLE_CODES.SALON_BRANCH_MANAGER]: 'Branch Manager',
  [ROLE_CODES.SALON_STAFF]: 'Staff',
}

const SCOPE_LABELS = {
  platform: 'Platform',
  salon: 'Salon',
  branch: 'Branch',
}

/**
 * Resolve a human-readable role label from auth session state.
 * @param {{ isSystemAdmin?: boolean, role?: { name?: string, code?: string, scope?: string } | null } | null | undefined} auth
 * @returns {RoleDisplay}
 */
export function getRoleDisplay(auth) {
  if (auth?.isSystemAdmin) {
    return {
      label: 'Platform Super Admin',
      scopeLabel: 'Platform',
      tone: 'platform',
    }
  }

  const role = auth?.role
  if (!role) {
    return {
      label: 'Signed in',
      scopeLabel: null,
      tone: 'neutral',
    }
  }

  const label = role.name?.trim()
    || CODE_LABELS[role.code]
    || 'User'

  const scopeLabel = SCOPE_LABELS[role.scope] ?? null
  const tone = role.scope === 'platform'
    ? 'platform'
    : role.scope === 'salon'
      ? 'salon'
      : role.scope === 'branch'
        ? 'branch'
        : 'neutral'

  return { label, scopeLabel, tone }
}

export const ROLE_BADGE_CLASSES = {
  platform: 'bg-violet-500/15 text-violet-300 border-violet-400/30',
  salon: 'bg-brand-500/15 text-brand-300 border-brand-400/30',
  branch: 'bg-sky-500/15 text-sky-300 border-sky-400/30',
  neutral: 'bg-slate-500/15 text-slate-300 border-slate-400/30',
}

export const ROLE_BADGE_CLASSES_LIGHT = {
  platform: 'bg-violet-50 text-violet-700 border-violet-200',
  salon: 'bg-brand-50 text-brand-700 border-brand-200',
  branch: 'bg-sky-50 text-sky-700 border-sky-200',
  neutral: 'bg-slate-100 text-slate-600 border-slate-200',
}

/**
 * @param {RoleDisplay['tone']} tone
 * @param {'dark' | 'light'} [variant='light']
 */
export function roleBadgeClassName(tone, variant = 'light') {
  const map = variant === 'dark' ? ROLE_BADGE_CLASSES : ROLE_BADGE_CLASSES_LIGHT
  return map[tone] ?? map.neutral
}

/**
 * @param {{ isSystemAdmin?: boolean, role?: { name?: string, code?: string, scope?: string } | null, user?: { name?: string } | null } | null | undefined} auth
 */
export function getLoginWelcomeMessage(auth) {
  const { label, scopeLabel } = getRoleDisplay(auth)
  const name = auth?.user?.name?.trim()
  const rolePart = scopeLabel ? `${label} (${scopeLabel})` : label

  if (name) {
    return `Signed in as ${name} · ${rolePart}`
  }

  return `Signed in as ${rolePart}`
}
