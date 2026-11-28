<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">ربط ماسنجر فيسبوك</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">إدارة الرد التلقائي على رسائل صفحة فيسبوك والتعليقات على المنشورات.</p>
      </div>
      <UiStatusBadge
        :label="state.connection_status === 'connected' ? 'متصل' : 'غير متصل'"
        :tone="state.connection_status === 'connected' ? 'emerald' : 'rose'"
        dot
      />
    </div>

    <div v-if="loading" class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-bold text-slate-400">
      جاري تحميل إعدادات ماسنجر...
    </div>

    <div v-else class="grid grid-cols-1 gap-5 lg:grid-cols-2">
      <div class="rounded-2xl border border-gray-100 bg-white p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-black text-[#181A1F]">بيانات الصفحة</h3>
          <a
            v-if="state.is_oauth_configured && state.oauth_url"
            :href="state.oauth_url"
            class="rounded-xl bg-[#F5B700] px-3.5 py-2 text-xs font-black text-[#181A1F] hover:bg-[#E2A800]"
          >
            ربط بنقرة واحدة (OAuth)
          </a>
        </div>

        <div class="rounded-xl bg-slate-50 p-3 text-xs space-y-1">
          <p class="font-bold text-slate-500">Webhook URL:</p>
          <p class="font-mono font-bold text-[#181A1F] break-all" dir="ltr">{{ state.webhook_url }}</p>
          <p class="pt-1 font-bold text-slate-500">Verify Token:</p>
          <p class="font-mono font-bold text-[#181A1F]" dir="ltr">{{ state.messenger_verify_token }}</p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="mb-1 block text-xs font-extrabold text-slate-700">Facebook Page ID</label>
            <input v-model="credForm.messenger_page_id" type="text" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-extrabold text-slate-700">اسم الصفحة</label>
            <input v-model="credForm.messenger_page_name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-extrabold text-slate-700">Page Access Token</label>
            <input v-model="credForm.messenger_access_token" type="password" dir="ltr" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" class="rounded-xl bg-[#F5B700] px-4 py-2.5 text-xs font-black text-[#181A1F]" @click="saveCredentials">
            حفظ وفحص الربط
          </button>
          <button v-if="state.connection_status === 'connected'" type="button" class="rounded-xl bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-600" @click="disconnect">
            فصل الصفحة
          </button>
        </div>
      </div>

      <div class="space-y-5">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 space-y-4">
          <h3 class="text-base font-black text-[#181A1F]">أتمتة تعليقات فيسبوك (Comment-to-DM)</h3>
          <label class="flex items-center justify-between rounded-xl border border-gray-200 p-3 cursor-pointer">
            <span class="text-xs font-extrabold text-[#181A1F]">إرسال الرد تلقائياً على الخاص عند التعليق</span>
            <input v-model="commentForm.comment_reply_enabled" type="checkbox" class="h-5 w-5 accent-[#F5B700]" />
          </label>
          <div>
            <label class="mb-1 block text-xs font-extrabold text-slate-700">كلمات مفتاحية (اختياري)</label>
            <input v-model="commentForm.comment_keywords" type="text" placeholder="السعر، التفاصيل، حجز" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-extrabold text-slate-700">الرد العام أسفل التعليق</label>
            <input v-model="commentForm.comment_public_reply" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          </div>
          <button type="button" class="rounded-xl bg-[#1F2227] px-4 py-2 text-xs font-bold text-white" @click="saveComments">
            حفظ إعدادات التعليقات
          </button>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-6 space-y-3">
          <h3 class="text-base font-black text-[#181A1F]">محاكاة رد ماسنجر</h3>
          <input v-model="simMsg" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
          <button type="button" class="rounded-xl bg-[#F5B700] px-4 py-2 text-xs font-black text-[#181A1F]" @click="simulate">
            اختبار الرد
          </button>
          <div v-if="simReply" class="rounded-xl bg-slate-50 p-3 text-xs whitespace-pre-wrap text-[#181A1F]">{{ simReply }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const route = useRoute()
const { request, extractErrorMessage } = useApi()
const toast = useToast()

const loading = ref(true)
const state = ref<any>({})
const credForm = ref({ messenger_page_id: '', messenger_page_name: '', messenger_access_token: '', messenger_verify_token: '' })
const commentForm = ref({ comment_reply_enabled: false, comment_keywords: '', comment_public_reply: '' })
const simMsg = ref('ما هي المنتجات والأسعار المتوفرة لديكم؟')
const simReply = ref('')

const applyPayload = (res: any) => {
  state.value = res
  credForm.value = {
    messenger_page_id: res.messenger_page_id || '',
    messenger_page_name: res.messenger_page_name || '',
    messenger_access_token: res.messenger_access_token || '',
    messenger_verify_token: res.messenger_verify_token || '',
  }
  commentForm.value = {
    comment_reply_enabled: Boolean(res.comment_reply_enabled),
    comment_keywords: res.comment_keywords || '',
    comment_public_reply: res.comment_public_reply || '',
  }
}

const loadStatus = async () => {
  loading.value = true
  try {
    applyPayload(await request('/company/channels/messenger'))
  } catch (err: any) {
    toast.error('تعذر تحميل البيانات', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const saveCredentials = async () => {
  try {
    const res = await request<any>('/company/channels/messenger/credentials', { method: 'POST', body: credForm.value })
    applyPayload(res)
    toast.success(res.message)
  } catch (err: any) {
    toast.error('فشل التحقق', extractErrorMessage(err))
  }
}

const disconnect = async () => {
  try {
    const res = await request<any>('/company/channels/messenger/disconnect', { method: 'POST' })
    applyPayload(res)
    toast.warning(res.message)
  } catch (err: any) {
    toast.error('تعذر الفصل', extractErrorMessage(err))
  }
}

const saveComments = async () => {
  try {
    const res = await request<any>('/company/channels/messenger/comments', { method: 'PUT', body: commentForm.value })
    applyPayload(res)
    toast.success(res.message)
  } catch (err: any) {
    toast.error('تعذر الحفظ', extractErrorMessage(err))
  }
}

const simulate = async () => {
  try {
    const res = await request<any>('/company/channels/messenger/simulate', { method: 'POST', body: { message: simMsg.value } })
    simReply.value = res.reply || ''
  } catch (err: any) {
    toast.error('فشلت المحاكاة', extractErrorMessage(err))
  }
}

onMounted(() => {
  if (route.query.oauth === 'success') toast.success('تم ربط صفحة ماسنجر فيسبوك بنجاح!')
  if (route.query.oauth === 'error') toast.error('فشل الربط', String(route.query.message || ''))
  loadStatus()
})
</script>
