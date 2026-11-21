// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  future: {
    compatibilityVersion: 4
  },
  devtools: { enabled: false },
  css: [
    '~/assets/css/main.css'
  ],
  modules: [
    '@pinia/nuxt'
  ],
  app: {
    head: {
      title: 'سمارتورا AI - منصة الرد الآلي الذكي للشركات',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap' },
      ],
      htmlAttrs: {
        dir: 'rtl',
        lang: 'ar'
      }
    }
  },
  runtimeConfig: {
    public: {
      apiBase: 'http://127.0.0.1:8088/api'
    }
  }
})