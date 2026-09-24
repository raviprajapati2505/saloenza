import { apiGet } from '../lib/apiHelpers'

export async function fetchBranchBenchmarks(params = {}) {
  const response = await apiGet('/v1/analytics/benchmarks', params)
  return response?.data?.data?.comparison ?? response?.data?.comparison ?? null
}
