<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <NuxtLink
          to="/company/conversations"
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-slate-700 hover:bg-slate-50"
        >
          <ArrowRight class="h-4 w-4" />
        </NuxtLink>
        <div>
          <h1 class="text-xl font-black text-[#181A1F]">{{ conversation?.customer_name || conversation?.clean_identifier || 'تفاصيل المحادثة' }}</h1>
          <p class="text-xs text-slate-400" dir="ltr">{{ conversation?.clean_identifier }}</p>
        </div>
      </div>

      <div v-if="conversation" class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-extrabold transition"
          :class="conversation.ai_paused ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-[#F5B700] text-[#181A1F] hover:bg-[#E2A800]'"
          @click="togglePause"
        >
          <Play v-if="conversation.ai_paused" class="h-4 w-4" />
          <Pause v-else class="h-4 w-4" />
          <span>{{ conversation.ai_paused ? 'إعادة تفعيل البوت' : 'إيقاف البوت مؤقتاً' }}</span>
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-[#1F2227] px-4 py-2 text-xs font-bold text-white hover:bg-[#2A2E35]"
          @click="resetContext"
        >
          <RotateCcw class="h-4 w-4" />
          <span>تصفير السياق</span>
        </button>
      </div>
    </div>

    <div v-if="loading" class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-bold text-slate-400">
      جاري تحميل سجل المحادثة...
    </div>

    <template v-else-if="conversation">
      <!-- Info Card -->
      <div class="grid grid-cols-1 gap-3 rounded-2xl border border-gray-100 bg-white p-5 sm:grid-cols-3">
        <div class="rounded-xl bg-slate-50 p-3">
          <p class="text-[11px] font-bold text-slate-400">القناة</p>
          <p class="mt-1 text-sm font-extrabold text-[#181A1F]">{{ channelLabel(conversation.channel) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-3">
          <p class="text-[11px] font-bold text-slate-400">معرّف العميل</p>
          <p class="mt-1 text-sm font-extrabold text-[#181A1F]" dir="ltr">{{ conversation.clean_identifier }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-3">
          <p class="text-[11px] font-bold text-slate-400">حالة الرد الآلي</p>
          <p class="mt-1 text-sm font-extrabold" :class="conversation.ai_paused ? 'text-rose-600' : 'text-emerald-600'">
            {{ conversation.ai_paused ? 'متوقف يدوياً' : 'نشط ويعمل' }}
          </p>
        </div>
      </div>

      <!-- Messages Thread -->
      <div class="rounded-2xl border border-gray-100 bg-white p-5">
        <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3">
          <h3 class="text-sm font-black text-[#181A1F]">سجل المحادثة ({{ conversation.messages.length }} رسالة)</h3>
          <span class="text-xs text-slate-400">{{ conversation.last_interaction_at }}</span>
        </div>

        <div class="space-y-3">
          <div
            v-for="msg in conversation.messages"
            :key="msg.id"
            class="flex flex-col"
            :class="msg.sender === 'customer' ? 'items-start' : 'items-end'"
          >
            <div
              class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap sm:max-w-[70%]"
              :class="msg.sender === 'customer'
                ? 'bg-slate-100 text-[#181A1F] rounded-tr-xs'
                : 'bg-[#1F2227] text-white rounded-tl-xs'"
            >
              <p
                class="mb-1 text-[11px] font-extrabold"
                :class="msg.sender === 'customer' ? 'text-slate-500' : 'text-[#F5B700]'"
              >
                {{ msg.sender === 'customer' ? 'العميل' : 'البوت الذكي' }}
              </p>
              <p>{{ msg.message }}</p>
              <p
                class="mt-1.5 text-[10px]"
                :class="msg.sender === 'customer' ? 'text-slate-400' : 'text-slate-400'"
              >
                {{ msg.created_at }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ArrowRight, Play, Pause, RotateCcw } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const route = useRoute()
const { request, extractErrorMessage } = useApi()
const toast = useToast()

const loading = ref(true)
const conversation = ref<any>(null)

const loadDetail = async () => {
  loading.value = true
  try {
    const res = await request<any>(`/company/conversations/${route.params.id}`)
    conversation.value = res.conversation
  } catch (err: any) {
    toast.error('تعذر تحميل المحادثة', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const togglePause = async () => {
  if (!conversation.value) return
  try {
    const res = await request<any>(`/company/conversations/${conversation.value.id}/toggle-pause`, { method: 'POST' })
    conversation.value.ai_paused = res.ai_paused
    toast.success(res.message)
  } catch (err: any) {
    toast.error('فشل التحديث', extractErrorMessage(err))
  }
}

const resetContext = async () => {
  if (!conversation.value || !confirm('هل تريد تصفير ذاكرة وسياق هذه المحادثة؟')) return
  try {
    const res = await request<any>(`/company/conversations/${conversation.value.id}/reset-context`, { method: 'POST' })
    toast.success(res.message)
    await loadDetail()
  } catch (err: any) {
    toast.error('فشل تصفير السياق', extractErrorMessage(err))
  }
}

const channelLabel = (c: string) => (c === 'instagram' ? 'انستقرام' : c === 'messenger' ? 'ماسنجر' : 'واتساب')

onMounted(loadDetail)
</script>
