<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">ربط الواتساب</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">اختر طريقة ربط الواتساب (الباركود الفوري QR أو Meta Cloud API الرسمي).</p>
      </div>

      <UiStatusBadge
        :label="state.connection_status === 'connected' ? 'متصل ويعمل' : state.connection_status === 'scanning' ? 'بانتظار مسح QR' : 'غير متصل'"
        :tone="state.connection_status === 'connected' ? 'emerald' : state.connection_status === 'scanning' ? 'amber' : 'rose'"
        dot
      />
    </div>

    <div v-if="loading" class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-bold text-slate-400">
      جاري فحص حالة الواتساب...
    </div>

    <template v-else>
      <!-- Provider Switch -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <button
          type="button"
          class="flex items-center justify-between rounded-2xl border p-5 text-start transition"
          :class="state.whatsapp_provider === 'waha'
            ? 'border-[#F5B700] bg-amber-50/40 shadow-xs'
            : 'border-gray-100 bg-white hover:border-gray-200'"
          @click="switchProvider('waha')"
        >
          <div>
            <p class="text-sm font-black text-[#181A1F]">ربط الباركود الفوري (WAHA QR)</p>
            <p class="mt-1 text-xs text-slate-500">امسح رمز QR من تطبيق واتساب مباشرة في ثوانٍ.</p>
          </div>
          <QrCode class="h-7 w-7 text-amber-600" />
        </button>

        <button
          type="button"
          class="flex items-center justify-between rounded-2xl border p-5 text-start transition"
          :class="state.whatsapp_provider === 'meta_cloud'
            ? 'border-[#F5B700] bg-amber-50/40 shadow-xs'
            : 'border-gray-100 bg-white hover:border-gray-200'"
          @click="switchProvider('meta_cloud')"
        >
          <div>
            <p class="text-sm font-black text-[#181A1F]">Meta WhatsApp Cloud API الرسمي</p>
            <p class="mt-1 text-xs text-slate-500">ربط رسمي عبر مفاتيح Meta Developers.</p>
          </div>
          <ShieldCheck class="h-7 w-7 text-emerald-600" />
        </button>
      </div>

      <!-- WAHA QR Section -->
      <div v-if="state.whatsapp_provider === 'waha'" class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 space-y-4">
          <h3 class="text-base font-black text-[#181A1F]">حالة جلسة الواتساب</h3>

          <div v-if="state.connection_status === 'connected'" class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 space-y-2">
            <p class="text-sm font-black text-emerald-800">الواتساب متصل وجاهز للرد الآلي!</p>
            <p class="text-xs text-emerald-700">الرقم المربوط: <strong dir="ltr">{{ state.whatsapp_number }}</strong></p>
            <p v-if="state.push_name" class="text-xs text-emerald-700">الاسم: {{ state.push_name }}</p>
            <p class="text-xs text-emerald-600">تاريخ الربط: {{ state.connected_at }}</p>
          </div>

          <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 bg-slate-50 p-6">
            <img v-if="state.qr_code_url" :src="state.qr_code_url" alt="WhatsApp QR" class="h-56 w-56 rounded-2xl border border-gray-200 bg-white p-2 shadow-sm" />
            <p v-else class="text-xs font-bold text-slate-400">اضغط على "تحديث رمز QR" لتوليد رمز الربط.</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-xl bg-[#F5B700] px-4 py-2 text-xs font-black text-[#181A1F] hover:bg-[#E2A800]" @click="checkConnection">
              فحص الاتصال
            </button>
            <button type="button" class="rounded-xl bg-[#1F2227] px-4 py-2 text-xs font-bold text-white hover:bg-[#2A2E35]" @click="generateQr">
              تحديث رمز QR
            </button>
            <button
              v-if="state.connection_status === 'connected'"
              type="button"
              class="rounded-xl bg-rose-50 px-4 py-2 text-xs font-extrabold text-rose-600 hover:bg-rose-100"
              @click="disconnect"
            >
              فصل الجلسة
            </button>
          </div>
        </div>

        <!-- Numbers + Test Send -->
        <div class="space-y-5">
          <div class="rounded-2xl border border-gray-100 bg-white p-6 space-y-4">
            <h3 class="text-base font-black text-[#181A1F]">أرقام الواتساب والدعم</h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-extrabold text-slate-700">رقم الواتساب</label>
                <input v-model="numbersForm.whatsapp_number" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-extrabold text-slate-700">رقم الاستقبال / الدعم</label>
                <input v-model="numbersForm.support_phone" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
              </div>
            </div>
            <button type="button" class="rounded-xl bg-[#1F2227] px-4 py-2 text-xs font-bold text-white" @click="saveNumbers">
              حفظ الأرقام
            </button>
          </div>

          <div class="rounded-2xl border border-gray-100 bg-white p-6 space-y-3">
            <h3 class="text-base font-black text-[#181A1F]">إرسال رسالة اختبار</h3>
            <input v-model="testForm.phone" type="text" dir="ltr" placeholder="967771234567" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
            <textarea v-model="testForm.message" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm"></textarea>
            <button type="button" class="rounded-xl bg-[#F5B700] px-4 py-2 text-xs font-black text-[#181A1F]" @click="sendTest">
              إرسال رسالة تجريبية
            </button>
          </div>
        </div>
      </div>

      <!-- Meta Cloud API Section -->
      <div v-else class="rounded-2xl border border-gray-100 bg-white p-6 space-y-4">
        <h3 class="text-base font-black text-[#181A1F]">بيانات Meta WhatsApp Cloud API</h3>
        <div class="rounded-xl bg-slate-50 p-3 text-xs">
          <p class="font-bold text-slate-500">Webhook URL:</p>
          <p class="mt-0.5 font-mono font-bold text-[#181A1F]" dir="ltr">{{ state.meta_webhook_url }}</p>
          <p class="mt-2 font-bold text-slate-500">Verify Token:</p>
          <p class="mt-0.5 font-mono font-bold text-[#181A1F]" dir="ltr">{{ state.meta_verify_token }}</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-extrabold text-slate-700">Phone Number ID</label>
            <input v-model="metaForm.meta_phone_number_id" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-extrabold text-slate-700">WABA ID</label>
            <input v-model="metaForm.meta_waba_id" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-extrabold text-slate-700">Access Token</label>
            <input v-model="metaForm.meta_access_token" type="password" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" class="rounded-xl bg-[#F5B700] px-5 py-2.5 text-xs font-black text-[#181A1F]" @click="saveMeta">
            حفظ وفحص الربط الرسمي
          </button>
          <button v-if="state.connection_status === 'connected'" type="button" class="rounded-xl bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-600" @click="disconnect">
            فصل الاتصال
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { QrCode, ShieldCheck } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { request, extractErrorMessage } = useApi()
const toast = useToast()

const loading = ref(true)
const state = ref<any>({})
const numbersForm = ref({ whatsapp_number: '', support_phone: '' })
const metaForm = ref({ meta_phone_number_id: '', meta_waba_id: '', meta_access_token: '', meta_verify_token: '' })
const testForm = ref({ phone: '', message: 'مرحباً، هذه رسالة تجريبية من نظام سمارتورا!' })

const applyPayload = (res: any) => {
  state.value = res
  numbersForm.value = { whatsapp_number: res.whatsapp_number || '', support_phone: res.support_phone || '' }
  metaForm.value = {
    meta_phone_number_id: res.meta_phone_number_id || '',
    meta_waba_id: res.meta_waba_id || '',
    meta_access_token: res.meta_access_token || '',
    meta_verify_token: res.meta_verify_token || '',
  }
}

const loadStatus = async () => {
  loading.value = true
  try {
    applyPayload(await request('/company/channels/whatsapp'))
  } catch (err: any) {
    toast.error('تعذر تحميل حالة الواتساب', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const switchProvider = async (provider: string) => {
  try {
    const res = await request<any>('/company/channels/whatsapp/provider', { method: 'POST', body: { provider } })
    applyPayload(res)
    toast.success(res.message)
  } catch (err: any) {
    toast.error('تعذر تغيير المزود', extractErrorMessage(err))
  }
}

const generateQr = async () => {
  try {
    const res = await request<any>('/company/channels/whatsapp/qr', { method: 'POST' })
    applyPayload(res)
    toast.info(res.message)
  } catch (err: any) {
    toast.error('تعذر تحديث QR', extractErrorMessage(err))
  }
}

const checkConnection = async () => {
  try {
    const res = await request<any>('/company/channels/whatsapp/check', { method: 'POST' })
    applyPayload(res)
    if (res.connection_status === 'connected') toast.success(res.message)
    else toast.warning(res.message)
  } catch (err: any) {
    toast.error('فشل الفحص', extractErrorMessage(err))
  }
}

const disconnect = async () => {
  if (!confirm('هل تريد فصل جلسة الواتساب الحالية؟')) return
  try {
    const res = await request<any>('/company/channels/whatsapp/disconnect', { method: 'POST' })
    applyPayload(res)
    toast.warning(res.message)
  } catch (err: any) {
    toast.error('تعذر الفصل', extractErrorMessage(err))
  }
}

const saveNumbers = async () => {
  try {
    const res = await request<any>('/company/channels/whatsapp/numbers', { method: 'PUT', body: numbersForm.value })
    toast.success(res.message)
  } catch (err: any) {
    toast.error('تعذر الحفظ', extractErrorMessage(err))
  }
}

const saveMeta = async () => {
  try {
    const res = await request<any>('/company/channels/whatsapp/meta', { method: 'POST', body: metaForm.value })
    applyPayload(res)
    toast.success(res.message)
  } catch (err: any) {
    toast.error('تعذر التحقق من Meta', extractErrorMessage(err))
  }
}

const sendTest = async () => {
  try {
    const res = await request<any>('/company/channels/whatsapp/test', { method: 'POST', body: testForm.value })
    toast.success(res.message)
  } catch (err: any) {
    toast.error('فشل الإرسال', extractErrorMessage(err))
  }
}

onMounted(loadStatus)
</script>
