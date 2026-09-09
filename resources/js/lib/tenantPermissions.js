/** Tenant-scoped permission codes (mirror ApplicationPermissionSeeder). */
export const TENANT_PERMISSIONS = {
  ROLES_VIEW: 'roles.view',
  ROLES_CREATE: 'roles.create',
  ROLES_UPDATE: 'roles.update',
  ROLES_DELETE: 'roles.delete',
  ASSIGN_PERMISSIONS_VIEW: 'assign_permissions.view',
  ASSIGN_PERMISSIONS_UPDATE: 'assign_permissions.update',
  CATEGORIES_VIEW: 'categories.view',
  CATEGORIES_CREATE: 'categories.create',
  CATEGORIES_UPDATE: 'categories.update',
  CATEGORIES_DELETE: 'categories.delete',
  SERVICES_VIEW: 'services.view',
  SERVICES_CREATE: 'services.create',
  SERVICES_UPDATE: 'services.update',
  SERVICES_DELETE: 'services.delete',
  PRODUCTS_VIEW: 'products.view',
  PRODUCTS_CREATE: 'products.create',
  PRODUCTS_UPDATE: 'products.update',
  PRODUCTS_DELETE: 'products.delete',
  STAFF_VIEW: 'staff.view',
  STAFF_CREATE: 'staff.create',
  STAFF_UPDATE: 'staff.update',
  STAFF_DELETE: 'staff.delete',
  BRANCHES_VIEW: 'branches.view',
  BRANCHES_CREATE: 'branches.create',
  BRANCHES_UPDATE: 'branches.update',
  BRANCHES_DELETE: 'branches.delete',
  SETTINGS_VIEW: 'settings.view',
  SETTINGS_UPDATE: 'settings.update',

  APPOINTMENTS_VIEW: 'appointments.view',
  APPOINTMENTS_CREATE: 'appointments.create',
  APPOINTMENTS_UPDATE: 'appointments.update',
  APPOINTMENTS_DELETE: 'appointments.delete',

  // Legacy route codes remain defined so removed modules fail closed.
  CUSTOMERS_VIEW: 'customers.view',
  CUSTOMERS_MANAGE: 'customers.manage',
  CUSTOMERS_CONTACTS_VIEW: 'customers.contacts.view',
  // Billing module is gated separately; subscription UI uses settings permissions.
  BILLING_VIEW: 'settings.view',
  BILLING_MANAGE: 'settings.update',
  ANALYTICS_VIEW: 'analytics.view',
  EXPENSES_VIEW: 'expenses.view',
  EXPENSES_MANAGE: 'expenses.manage',
  INVENTORY_VIEW: 'inventory.view',
  INVENTORY_MANAGE: 'inventory.manage',
  REFERRALS_VIEW: 'referrals.view',

  STAFF_EARNINGS_VIEW: 'staff.earnings.view',
  QUEUE_VIEW: 'queue.view',
  QUEUE_VIEW_ALL: 'queue.view_all',
  BOOKING_LINKS_VIEW: 'booking_links.view',
  BOOKING_LINKS_MANAGE: 'booking_links.manage',
}

export function isPlatformPermissionCode(code) {
  return typeof code === 'string' && code.startsWith('platform.')
}
