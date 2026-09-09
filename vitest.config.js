import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/setup.js'],
    include: ['resources/js/**/__tests__/**/*.{test,spec}.{js,jsx}'],
    css: false,
    testTimeout: 15000,
    fileParallelism: false,
    maxWorkers: 1,
  },
})
