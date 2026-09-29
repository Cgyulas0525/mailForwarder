import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    port: 5175,
    strictPort: true,
    watch: { usePolling: true },
    hmr: {
      host: 'localhost',
      clientPort: Number(process.env.VITE_HMR_CLIENT_PORT || 28090),
      protocol: 'ws',
    },
  },
})
