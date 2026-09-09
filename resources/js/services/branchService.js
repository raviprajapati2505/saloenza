import { apiGet, apiPost, apiPut, parseItem, parseList } from '../lib/apiHelpers'

export async function fetchBranches(params = {}) {
  const response = await apiGet('/v1/branches', { page: 1, per_page: 100, ...params })
  return {
    branches: parseList(response, 'branches'),
    limits: response?.data?.data?.limits ?? null,
    canCreateBranch: Boolean(response?.data?.data?.can_create_branch),
  }
}

export async function createBranch(payload) {
  const response = await apiPost('/v1/branches', payload)
  return {
    branch: parseItem(response, 'branch'),
    manager: response?.data?.data?.manager ?? null,
    clonedCatalogItems: response?.data?.data?.cloned_catalog_items ?? 0,
    clonedRoles: response?.data?.data?.cloned_roles ?? 0,
  }
}

export async function updateBranch(branchId, payload) {
  const response = await apiPut(`/v1/branches/${branchId}`, payload)
  return parseItem(response, 'branch')
}
