import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

// Cleanup after each test (unmount React components)
afterEach(() => {
  cleanup()
})

// Mock window.matchMedia (used by some UI components)
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
})

// Mock window.scrollTo (used by onboarding forms)
Object.defineProperty(window, 'scrollTo', {
  writable: true,
  value: vi.fn(),
})

// Mock framer-motion (prevents animation timers hanging tests)
vi.mock('framer-motion', () => {
  const React = require('react')
  const MockMotion = ({ children, ...props }) => React.createElement('div', props, children)
  return {
    motion: new Proxy({}, { get: () => MockMotion }),
    AnimatePresence: ({ children }) => children,
  }
})

// Mock canvas-confetti (used by success screens)
vi.mock('canvas-confetti', () => ({
  default: vi.fn(),
}))

// Mock IntersectionObserver
class IntersectionObserverMock {
  constructor() {
    this.observe = vi.fn()
    this.unobserve = vi.fn()
    this.disconnect = vi.fn()
  }
}
Object.defineProperty(window, 'IntersectionObserver', {
  writable: true,
  value: IntersectionObserverMock,
})

// Mock ResizeObserver
class ResizeObserverMock {
  constructor() {
    this.observe = vi.fn()
    this.unobserve = vi.fn()
    this.disconnect = vi.fn()
  }
}
Object.defineProperty(window, 'ResizeObserver', {
  writable: true,
  value: ResizeObserverMock,
})
