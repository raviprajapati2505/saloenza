import { apiGet, apiPost, apiPut, parseItem, parseList } from '../lib/apiHelpers'

export async function fetchAdminBranches(params = {}) {
  const response = await apiGet('/v1/admin/branches', {
    page: 1,
    per_page: 50,
    ...params,
  })

  return {
    branches: parseList(response, 'branches'),
    meta: response?.data?.data?.meta ?? response?.data?.meta ?? null,
  }
}

export async function createAdminBranch(payload) {
  const response = await apiPost('/v1/admin/branches', payload)
  return {
    branch: parseItem(response, 'branch'),
    manager: response?.data?.data?.manager ?? null,
    clonedCatalogItems: response?.data?.data?.cloned_catalog_items ?? 0,
    clonedRoles: response?.data?.data?.cloned_roles ?? 0,
  }
}

export async function updateAdminBranch(branchId, payload) {
  const response = await apiPut(`/v1/admin/branches/${branchId}`, payload)
  return parseItem(response, 'branch')
}

export async function fetchSalonBranchCapacity(saloonId) {
  const response = await apiGet(`/v1/admin/saloons/${saloonId}/branch-capacity`)
  return response?.data?.data ?? null
}
