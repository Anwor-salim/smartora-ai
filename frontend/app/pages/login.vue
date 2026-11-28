<template>
  <div class="flex min-h-screen bg-[#F6F7F9]">
    <!-- Right / Brand Dark Panel (desktop) -->
    <div class="hidden lg:flex lg:w-[44%] flex-col justify-between bg-[#1F2227] p-10 text-white">
      <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#F5B700] text-[#181A1F]">
          <Zap class="h-6 w-6 fill-current" />
        </div>
        <div>
          <h1 class="text-lg font-black tracking-tight">SmartOra AI</h1>
          <p class="text-xs text-slate-400">منصة الرد الآلي وحجز المواعيد الذكي</p>
        </div>
      </div>

      <div class="space-y-6">
        <span class="inline-flex items-center gap-2 rounded-full bg-[#F5B700]/15 px-3.5 py-1 text-xs font-extrabold text-[#F5B700]">
          لوحة تحكم المنشأة المطوّرة
        </span>
        <h2 class="text-3xl font-black leading-snug">
          أدِر محادثاتك وحجوزاتك وملفات معرفتك من مكان واحد بسرعة واحترافية.
        </h2>
        <div class="space-y-3 text-sm text-slate-300">
          <div class="flex items-center gap-3">
            <span class="h-2 w-2 rounded-full bg-[#F5B700]"></span>
            <span>ربط مباشر مع واتساب (QR أو Meta)، انستقرام، وماسنجر فيسبوك</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="h-2 w-2 rounded-full bg-[#F5B700]"></span>
            <span>نظام حجز مواعيد ذكي متزامن مع Google Sheets</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="h-2 w-2 rounded-full bg-[#F5B700]"></span>
            <span>تحديث فوري لقاعدة المعرفة والرد من أحدث الملفات المرفوعة</span>
          </div>
        </div>
      </div>

      <p class="text-xs text-slate-500">© {{ new Date().getFullYear() }} SmartOra AI · جميع الحقوق محفوظة</p>
    </div>

    <!-- Login Form Side -->
    <div class="flex flex-1 items-center justify-center p-6 sm:p-10">
      <div class="w-full max-w-md rounded-3xl border border-gray-100 bg-white p-7 shadow-xl shadow-slate-900/5 sm:p-9">
        <div class="mb-7 flex items-center gap-3 lg:hidden">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F5B700] text-[#181A1F]">
            <Zap class="h-5 w-5 fill-current" />
          </div>
          <div>
            <p class="text-base font-black text-[#181A1F]">SmartOra AI</p>
            <p class="text-xs text-slate-400">لوحة تحكم المنشأة</p>
          </div>
        </div>

        <h1 class="text-2xl font-black text-[#181A1F]">تسجيل الدخول</h1>
        <p class="mt-1.5 text-sm text-slate-500">أدخل بيانات حساب المنشأة للوصول إلى لوحة التحكم.</p>

        <div
          v-if="error"
          class="mt-5 flex items-start gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 p-3.5 text-xs font-bold text-rose-700"
        >
          <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
          <span>{{ error }}</span>
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="onSubmit">
          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">البريد الإلكتروني</label>
            <input
              v-model="email"
              type="email"
              required
              autocomplete="email"
              placeholder="name@company.com"
              class="w-full rounded-xl border border-gray-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-[#181A1F] transition focus:border-[#F5B700] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F5B700]/25"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">كلمة المرور</label>
            <input
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="w-full rounded-xl border border-gray-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-[#181A1F] transition focus:border-[#F5B700] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F5B700]/25"
            />
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-[#F5B700] px-5 py-3 text-sm font-black text-[#181A1F] shadow-sm transition hover:bg-[#E2A800] disabled:opacity-60"
          >
            <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
            <span>{{ loading ? 'جاري التحقق...' : 'دخول إلى لوحة التحكم' }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Zap, AlertCircle, Loader2 } from 'lucide-vue-next'

const route = useRoute()
const { login, loading, error, hydrateToken, fetchMe, isAuthenticated } = useAuth()
const toast = useToast()

const email = ref('')
const password = ref('')

onMounted(async () => {
  hydrateToken()
  const existing = await fetchMe()
  if (existing && isAuthenticated.value) {
    await navigateTo('/company', { replace: true })
  }
})

const onSubmit = async () => {
  try {
    await login(email.value.trim(), password.value)
    toast.success('مرحباً بك مجدداً!')
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/company')
      ? route.query.redirect
      : '/company'
    await navigateTo(redirect, { replace: true })
  } catch {
    // error displayed cleanly in alert box
  }
}
</script>
