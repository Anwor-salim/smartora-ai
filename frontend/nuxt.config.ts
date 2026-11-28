import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  future: {
    compatibilityVersion: 4
  },
  ssr: false,
  devtools: { enabled: false },
  css: [
    '~/assets/css/main.css'
  ],
  modules: [
    '@pinia/nuxt'
  ],
  vite: {
    plugins: [tailwindcss()]
  },
  app: {
    head: {
      title: 'سمارتورا AI — لوحة تحكم المنشأة',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap' },
      ],
      htmlAttrs: {
        dir: 'rtl',
        lang: 'ar'
      }
    }
  },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8088/api'
    }
  }
})
