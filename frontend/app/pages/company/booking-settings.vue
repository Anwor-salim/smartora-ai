<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">إعدادات الحجز و Google Sheets</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">
          تحكم في تفعيل الحجز التلقائي، السعة اليومية، أيام العمل، والربط المباشر مع Google Sheets.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <button
          type="button"
          :disabled="syncing"
          class="inline-flex items-center gap-2 rounded-xl bg-[#F5B700] px-4 py-2.5 text-xs font-black text-[#181A1F] shadow-xs transition hover:bg-[#E2A800] disabled:opacity-60 sm:text-sm"
          @click="syncHeaders"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
          <span>{{ syncing ? 'جاري الفحص...' : 'فحص الربط ومزامنة الأعمدة' }}</span>
        </button>

        <button
          type="button"
          :disabled="saving"
          class="inline-flex items-center gap-2 rounded-xl bg-[#1F2227] px-4 py-2.5 text-xs font-bold text-white transition hover:bg-[#2A2E35] disabled:opacity-60 sm:text-sm"
          @click="saveSettings"
        >
          <Save class="h-4 w-4" />
          <span>{{ saving ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}</span>
        </button>
      </div>
    </div>

    <div v-if="loading" class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-bold text-slate-400">
      جاري تحميل إعدادات الحجز...
    </div>

    <div v-else class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 space-y-6">
      <!-- Golden Underline Tabs -->
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

      <!-- TAB 1: Activation & Approval -->
      <div v-if="activeTab === 'activation'" class="space-y-5">
        <label class="flex items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-slate-50/50 p-4 cursor-pointer">
          <div>
            <p class="text-sm font-extrabold text-[#181A1F]">تفعيل الحجز التلقائي عبر البوت</p>
            <p class="mt-0.5 text-xs text-slate-500">عند التفعيل، يجمع البوت حقول التسجيل من العميل ويفحص السعة اليومية ويسجل الحجز تلقائياً.</p>
          </div>
          <input v-model="form.booking_enabled" type="checkbox" class="mt-1 h-5 w-5 accent-[#F5B700]" />
        </label>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">نظام اعتماد الطلبات</label>
            <select
              v-model="form.booking_approval_mode"
              class="w-full rounded-xl border border-gray-200 bg-slate-50/50 px-3.5 py-2.5 text-sm font-bold focus:border-[#F5B700] focus:bg-white focus:outline-none"
            >
              <option value="reception_approval">مراجعة الاستقبال أولاً (قيد المراجعة ← تأكيد بعد القبول)</option>
              <option value="auto_confirm">تأكيد فوري تلقائي مباشرة</option>
            </select>
          </div>

          <label class="flex items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-slate-50/50 p-4 cursor-pointer">
            <div>
              <p class="text-sm font-extrabold text-[#181A1F]">إرسال تنبيه واتساب للاستقبال</p>
              <p class="mt-0.5 text-xs text-slate-500">يُرسل التنبيه إلى رقم الدعم/المبيعات المسجل في بيانات المنشأة.</p>
            </div>
            <input v-model="form.booking_notify_reception_whatsapp" type="checkbox" class="mt-1 h-5 w-5 accent-[#F5B700]" />
          </label>
        </div>
      </div>

      <!-- TAB 2: Capacity & Working Days -->
      <div v-else-if="activeTab === 'capacity'" class="space-y-5">
        <div class="max-w-sm">
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">الحد الأقصى لعدد الحجوزات في اليوم الواحد</label>
          <input
            v-model.number="form.booking_daily_limit"
            type="number"
            min="1"
            max="500"
            class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm font-bold focus:border-[#F5B700] focus:outline-none"
          />
          <p class="mt-1 text-xs text-slate-400">عند اكتمال هذا العدد، يقترح البوت تلقائياً أقرب يوم متاح تالٍ.</p>
        </div>

        <div>
          <label class="mb-2 block text-xs font-extrabold text-slate-700">أيام العمل المتاحة للحجز</label>
          <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-7">
            <label
              v-for="day in weekDays"
              :key="day.key"
              class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-xs font-bold transition"
              :class="form.booking_working_days.includes(day.key)
                ? 'border-[#F5B700] bg-amber-50/60 text-[#181A1F]'
                : 'border-gray-200 text-slate-500'"
            >
              <input v-model="form.booking_working_days" type="checkbox" :value="day.key" class="accent-[#F5B700]" />
              <span>{{ day.label }}</span>
            </label>
          </div>
        </div>

        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-extrabold text-slate-700">استثناءات تواريخ محددة (اختياري)</p>
              <p class="text-[11px] text-slate-400">0 = إجازة / مغلق، أو حدد سعة مخصصة ليوم معين.</p>
            </div>
            <button type="button" class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-bold hover:bg-slate-50" @click="addOverride">
              + إضافة تاريخ
            </button>
          </div>

          <div v-for="(ov, idx) in overridesList" :key="idx" class="flex flex-wrap items-center gap-2">
            <input v-model="ov.date" type="date" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold" />
            <input v-model.number="ov.limit" type="number" min="0" placeholder="السعة" class="w-28 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold" />
            <button type="button" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600" @click="overridesList.splice(idx, 1)">
              حذف
            </button>
          </div>
        </div>
      </div>

      <!-- TAB 3: Google Sheets -->
      <div v-else class="space-y-5">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">بريد الربط (شارِك ملف Google Sheet مع هذا البريد كـ Editor)</label>
          <div class="flex items-center gap-2">
            <input
              :value="serviceAccountEmail"
              readonly
              class="w-full rounded-xl border border-gray-200 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-600"
              dir="ltr"
            />
            <button
              v-if="serviceAccountConfigured"
              type="button"
              class="shrink-0 rounded-xl border border-gray-200 px-3 py-2.5 text-xs font-bold hover:bg-slate-50"
              @click="copyEmail"
            >
              نسخ
            </button>
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">رابط أو معرّف ملف Google Sheet</label>
          <input
            v-model="form.booking_sheet_url"
            type="text"
            placeholder="https://docs.google.com/spreadsheets/d/..."
            dir="ltr"
            class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">اسم الورقة / التبويب (اختياري)</label>
            <input
              v-model="form.booking_sheet_tab"
              type="text"
              placeholder="اتركه فارغاً لاستخدام الورقة الأولى"
              class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-xs font-extrabold text-slate-700">الحقول الافتراضية (عند عدم ربط Google Sheet)</label>
            <input
              v-model="form.booking_custom_fields"
              type="text"
              placeholder="الاسم، العمر، التاريخ، الوقت"
              class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none"
            />
          </div>
        </div>

        <div v-if="sheetHeaders.length" class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4">
          <p class="text-xs font-extrabold text-emerald-800">الأعمدة المتزامنة من Google Sheet:</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="h in sheetHeaders" :key="h" class="rounded-lg bg-white px-2.5 py-1 text-xs font-bold text-emerald-700 shadow-2xs">
              {{ h }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RefreshCw, Save } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { request, extractErrorMessage } = useApi()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const syncing = ref(false)
const activeTab = ref<'activation' | 'capacity' | 'sheets'>('activation')

const tabs = [
  { key: 'activation' as const, label: 'التفعيل والاعتماد' },
  { key: 'capacity' as const, label: 'السعة اليومية وأيام العمل' },
  { key: 'sheets' as const, label: 'ربط Google Sheets (درايف)' },
]

const weekDays = [
  { key: 'sat', label: 'السبت' },
  { key: 'sun', label: 'الأحد' },
  { key: 'mon', label: 'الإثنين' },
  { key: 'tue', label: 'الثلاثاء' },
  { key: 'wed', label: 'الأربعاء' },
  { key: 'thu', label: 'الخميس' },
  { key: 'fri', label: 'الجمعة' },
]

const serviceAccountEmail = ref('')
const serviceAccountConfigured = ref(false)
const sheetHeaders = ref<string[]>([])
const overridesList = ref<Array<{ date: string; limit: number }>>([])

const form = ref({
  booking_enabled: false,
  booking_approval_mode: 'reception_approval',
  booking_notify_reception_whatsapp: false,
  booking_daily_limit: 10,
  booking_working_days: ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
  booking_sheet_url: '',
  booking_sheet_tab: '',
  booking_custom_fields: 'الاسم، العمر، التاريخ، الوقت',
})

const loadSettings = async () => {
  loading.value = true
  try {
    const res = await request<any>('/company/booking-settings')
    form.value = {
      booking_enabled: Boolean(res.booking_enabled),
      booking_approval_mode: res.booking_approval_mode || 'reception_approval',
      booking_notify_reception_whatsapp: Boolean(res.booking_notify_reception_whatsapp),
      booking_daily_limit: Number(res.booking_daily_limit || 10),
      booking_working_days: Array.isArray(res.booking_working_days) ? res.booking_working_days : ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
      booking_sheet_url: res.booking_sheet_url || '',
      booking_sheet_tab: res.booking_sheet_tab || '',
      booking_custom_fields: res.booking_custom_fields || 'الاسم، العمر، التاريخ، الوقت',
    }
    serviceAccountEmail.value = res.service_account_email || ''
    serviceAccountConfigured.value = Boolean(res.service_account_configured)
    sheetHeaders.value = Array.isArray(res.booking_sheet_headers) ? res.booking_sheet_headers : []

    const ovObj = res.booking_date_overrides || {}
    overridesList.value = Object.entries(ovObj).map(([date, limit]) => ({
      date,
      limit: Number(limit),
    }))
  } catch (err: any) {
    toast.error('تعذر تحميل إعدادات الحجز', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const addOverride = () => {
  overridesList.value.push({ date: '', limit: 0 })
}

const buildOverridesPayload = () => {
  const out: Record<string, number> = {}
  for (const row of overridesList.value) {
    if (row.date) {
      out[row.date] = Math.max(0, Number(row.limit || 0))
    }
  }
  return out
}

const saveSettings = async () => {
  saving.value = true
  try {
    const res = await request<any>('/company/booking-settings', {
      method: 'PUT',
      body: {
        ...form.value,
        booking_date_overrides: buildOverridesPayload(),
      },
    })
    toast.success(res.message)
  } catch (err: any) {
    toast.error('تعذر الحفظ', extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

const syncHeaders = async () => {
  syncing.value = true
  try {
    const res = await request<any>('/company/booking-settings/sync-headers', {
      method: 'POST',
      body: {
        booking_sheet_url: form.value.booking_sheet_url,
        booking_sheet_tab: form.value.booking_sheet_tab,
      },
    })
    sheetHeaders.value = res.headers || []
    toast.success('تمت مزامنة الأعمدة بنجاح', res.message)
  } catch (err: any) {
    toast.error('تعذر المزامنة', extractErrorMessage(err))
  } finally {
    syncing.value = false
  }
}

const copyEmail = async () => {
  if (process.client && navigator.clipboard) {
    await navigator.clipboard.writeText(serviceAccountEmail.value)
    toast.success('تم نسخ بريد الربط')
  }
}

onMounted(loadSettings)
</script>
