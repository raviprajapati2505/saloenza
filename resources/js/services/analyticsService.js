import { apiGet, parseItem } from '../lib/apiHelpers'

export async function fetchAnalyticsSummary(params = {}) {
  const response = await apiGet('/v1/analytics/summary', params)
  return parseItem(response, 'summary')
}

export async function fetchOutstandingPaymentsReport(params = {}) {
  const response = await apiGet('/v1/reports/outstanding-payments', params)
  return parseItem(response, 'summary')
}

export async function fetchBusinessCloseReport(params = {}) {
  const response = await apiGet('/v1/reports/business-close', params)
  return parseItem(response, 'summary')
}

export async function fetchBranchBenchmarks(params = {}) {
  const response = await apiGet('/v1/analytics/benchmarks', params)
  return parseItem(response, 'comparison')
}
