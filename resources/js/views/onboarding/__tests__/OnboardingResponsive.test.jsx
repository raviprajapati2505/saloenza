import React from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders, findByClass, makeAuthMock } from '../../../test-utils/responsive.jsx'

vi.mock('../../../stores/auth', () => ({
  useAuthStore: () => makeAuthMock({
    shouldOnboard: true,
    onboarding_completed_at: null,
  }),
}))

vi.mock('../../../lib/api', () => ({
  api: {
    get: vi.fn(async () => ({ data: { data: {} } })),
    post: vi.fn(async () => ({ data: { data: {} } })),
  },
}))

vi.mock('../steps/WeeklyHoursInput.jsx', () => ({
  default: () => <div>hours</div>,
}))

import OnboardingWizard from '../OnboardingWizard.jsx'

describe('Onboarding wizard responsive contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('wizard uses stacked mobile shell and lg:grid-cols-3 split on desktop', () => {
    const { container } = renderWithProviders(<OnboardingWizard />, { route: '/onboarding' })

    expect(findByClass(container, 'min-h-screen') || findByClass(container, 'px-4')).toBeTruthy()
    expect(
      findByClass(container, 'lg:grid-cols-3')
      || findByClass(container, 'lg:col-span-2')
      || findByClass(container, 'lg:col-span-1'),
    ).toBeTruthy()
  })
})
