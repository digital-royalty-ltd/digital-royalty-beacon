import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig({
  plugins: [
    tailwindcss(),
    react(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './assets/admin/src'),
    },
  },
  build: {
    outDir: 'dist/admin',
    manifest: true,
    rollupOptions: {
      input: 'assets/admin/src/main.tsx',
    },
  },
})
