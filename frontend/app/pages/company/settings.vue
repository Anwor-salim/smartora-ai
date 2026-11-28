<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">بيانات المنشأة وإعدادات الذكاء</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">تحديث معلومات المنشأة الأساسية، أرقام التواصل، وسلوك الذكاء الاصطناعي.</p>
      </div>
      <button
        type="button"
        :disabled="saving"
        class="inline-flex items-center gap-2 self-start rounded-xl bg-[#F5B700] px-5 py-2.5 text-xs font-black text-[#181A1F] shadow-xs transition hover:bg-[#E2A800] disabled:opacity-60 sm:text-sm"
        @click="saveProfile"
      >
        <Save class="h-4 w-4" />
        <span>{{ saving ? 'جاري الحفظ...' : 'حفظ وتحديث البيانات' }}</span>
      </button>
    </div>

    <div v-if="loading" class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-bold text-slate-400">
      جاري تحميل بيانات المنشأة...
    </div>

    <div v-else class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 space-y-6">
      <div class="flex flex-wrap items-center gap-6 border-b border-gray-100">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="border-b-2 pb-3 text-xs font-extrabold transition sm:text-sm"
          :class="activeTab === tab.key
            ? 'border-[#F5B700] text-[#181A1F]'
            : 'border-transparent text-slate-400 hover:text-slate-700'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- TAB 1: Profile -->
      <div v-if="activeTab === 'profile'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">اسم المنشأة *</label>
          <input v-model="form.name" type="text" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">أوقات وساعات الدوام</label>
          <input v-model="form.working_hours" type="text" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
        <div class="md:col-span-2">
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">نبذة تعريفية عن المنشأة</label>
          <textarea v-model="form.about_us" rows="3" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none"></textarea>
        </div>
        <div class="md:col-span-2">
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">نبذة عن الخدمات والمنتجات</label>
          <textarea v-model="form.services_offered" rows="3" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none"></textarea>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">الموقع والعنوان الفعلي</label>
          <input v-model="form.address" type="text" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">رابط خرائط جوجل</label>
          <input v-model="form.google_maps_url" type="url" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
      </div>

      <!-- TAB 2: Contact -->
      <div v-else-if="activeTab === 'contact'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">رقم الواتساب لاستقبال الردود</label>
          <input v-model="form.whatsapp_number" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">رقم مسؤول الدعم / التحويل البشري</label>
          <input v-model="form.support_phone" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">هاتف الاتصال</label>
          <input v-model="form.phone" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">البريد الإلكتروني الرسمي</label>
          <input v-model="form.email" type="email" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
      </div>

      <!-- TAB 3: AI Settings -->
      <div v-else class="space-y-4">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">رسالة الترحيب التلقائية للعميل</label>
          <input v-model="form.greeting_message" type="text" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">التلقين المخصص للذكاء الاصطناعي (System Prompt)</label>
          <textarea v-model="form.custom_system_prompt" rows="4" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none"></textarea>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">نبرة الرد</label>
            <select v-model="form.response_tone" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-bold">
              <option value="professional_friendly">ودود ومهني</option>
              <option value="formal">رسمي ومباشر</option>
              <option value="concise">مختصر وسريع</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">عمق ذاكرة المحادثة</label>
            <select v-model.number="form.memory_messages_limit" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-bold">
              <option :value="6">آخر 6 رسائل</option>
              <option :value="10">آخر 10 رسائل</option>
              <option :value="12">آخر 12 رسالة</option>
              <option :value="16">آخر 16 رسالة</option>
              <option :value="20">آخر 20 رسالة</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">المنطقة الزمنية</label>
            <select v-model="form.timezone" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-bold">
              <option value="Asia/Riyadh">الرياض (Asia/Riyadh)</option>
              <option value="Asia/Aden">عدن / صنعاء (Asia/Aden)</option>
              <option value="Asia/Dubai">دبي (Asia/Dubai)</option>
              <option value="Asia/Kuwait">الكويت (Asia/Kuwait)</option>
              <option value="Africa/Cairo">القاهرة (Africa/Cairo)</option>
              <option value="UTC">UTC</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label class="flex items-center justify-between rounded-xl border border-gray-200 p-3.5 cursor-pointer">
            <span class="text-xs font-extrabold text-[#181A1F]">إيقاف البوت تلقائياً عند طلب موظف بشري</span>
            <input v-model="form.auto_pause_on_handoff" type="checkbox" class="h-5 w-5 accent-[#F5B700]" />
          </label>
          <label class="flex items-center justify-between rounded-xl border border-gray-200 p-3.5 cursor-pointer">
            <span class="text-xs font-extrabold text-[#181A1F]">تفعيل تنسيق حجز المواعيد عبر البوت</span>
            <input v-model="form.booking_enabled" type="checkbox" class="h-5 w-5 accent-[#F5B700]" />
          </label>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">مفتاح Google Gemini API Key (اختياري)</label>
          <input v-model="form.gemini_api_key" type="password" dir="ltr" placeholder="AIza... أو AQ...." class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Save } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { request, extractErrorMessage } = useApi()
const { fetchMe } = useAuth()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const activeTab = ref<'profile' | 'contact' | 'ai'>('profile')

const tabs = [
  { key: 'profile' as const, label: 'تعريف المنشأة والخدمات' },
  { key: 'contact' as const, label: 'أرقام الواتساب والدعم' },
  { key: 'ai' as const, label: 'التلقين الذكي وإعدادات AI' },
]

const form = ref({
  name: '',
  about_us: '',
  services_offered: '',
  working_hours: '',
  address: '',
  google_maps_url: '',
  whatsapp_number: '',
  support_phone: '',
  phone: '',
  email: '',
  greeting_message: '',
  custom_system_prompt: '',
  gemini_api_key: '',
  response_tone: 'professional_friendly',
  memory_messages_limit: 12,
  timezone: 'Asia/Riyadh',
  auto_pause_on_handoff: false,
  booking_enabled: false,
})

const loadProfile = async () => {
  loading.value = true
  try {
    const res = await request<any>('/company/profile')
    const c = res.company
    form.value = {
      name: c.name || '',
      about_us: c.about_us || '',
      services_offered: c.services_offered || '',
      working_hours: c.working_hours || '',
      address: c.address || '',
      google_maps_url: c.google_maps_url || '',
      whatsapp_number: c.whatsapp_number || '',
      support_phone: c.support_phone || '',
      phone: c.phone || '',
      email: c.email || '',
      greeting_message: c.settings?.greeting_message || '',
      custom_system_prompt: c.custom_system_prompt || '',
      gemini_api_key: c.settings?.gemini_api_key || '',
      response_tone: c.settings?.response_tone || 'professional_friendly',
      memory_messages_limit: Number(c.settings?.memory_messages_limit || 12),
      timezone: c.settings?.timezone || 'Asia/Riyadh',
      auto_pause_on_handoff: Boolean(c.settings?.auto_pause_on_handoff),
      booking_enabled: Boolean(c.settings?.booking_enabled),
    }
  } catch (err: any) {
    toast.error('تعذر تحميل بيانات المنشأة', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const saveProfile = async () => {
  saving.value = true
  try {
    const res = await request<any>('/company/profile', { method: 'PUT', body: form.value })
    if (res.warning) toast.warning('تنبيه', res.message)
    else toast.success(res.message)
    await fetchMe(true)
  } catch (err: any) {
    toast.error('تعذر الحفظ', extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

onMounted(loadProfile)
</script>
