<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">محاكي البوت الذكي</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">اختبر ردود الذكاء الاصطناعي وتدفق الحجز مباشرة من لوحة التحكم قبل النشر على العملاء.</p>
      </div>

      <button
        type="button"
        class="inline-flex items-center gap-2 self-start rounded-xl bg-[#1F2227] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#2A2E35]"
        @click="resetChat"
      >
        <RotateCcw class="h-4 w-4" />
        <span>بدء محادثة جديدة</span>
      </button>
    </div>

    <div class="flex h-[calc(100vh-15rem)] min-h-[480px] flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white">
      <!-- Chat Header -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-[#1F2227] px-5 py-3.5 text-white">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#F5B700] text-[#181A1F]">
            <Bot class="h-5 w-5" />
          </div>
          <div>
            <p class="text-sm font-extrabold">{{ activeCompany?.name || 'مساعد سمارتورا' }}</p>
            <p class="text-[11px] text-emerald-400">● متصل بمحرك الذكاء والحجوزات الفعلي</p>
          </div>
        </div>
        <div class="flex items-center gap-2 text-xs">
          <span class="text-slate-400">رقم الاختبار:</span>
          <input
            v-model="customerPhone"
            type="text"
            dir="ltr"
            class="w-36 rounded-lg border border-white/15 bg-white/10 px-2.5 py-1 text-xs font-bold text-white focus:border-[#F5B700] focus:outline-none"
          />
        </div>
      </div>

      <!-- Messages -->
      <div ref="scrollBox" class="flex-1 space-y-3 overflow-y-auto bg-slate-50/70 p-4 sm:p-6">
        <div
          v-for="(m, idx) in messages"
          :key="idx"
          class="flex"
          :class="m.sender === 'user' ? 'justify-start' : 'justify-end'"
        >
          <div
            class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap shadow-2xs sm:max-w-[70%]"
            :class="m.sender === 'user'
              ? 'bg-white border border-gray-200 text-[#181A1F] rounded-tr-xs'
              : 'bg-[#1F2227] text-white rounded-tl-xs'"
          >
            <p>{{ m.message }}</p>
            <p class="mt-1 text-[10px] text-slate-400">{{ m.time }}</p>
          </div>
        </div>

        <div v-if="sending" class="flex justify-end">
          <div class="rounded-2xl bg-[#1F2227] px-4 py-3 text-xs font-bold text-[#F5B700]">
            جاري صياغة الرد...
          </div>
        </div>
      </div>

      <!-- Quick Prompts + Input -->
      <div class="border-t border-gray-100 bg-white p-3.5 sm:p-4 space-y-2.5">
        <div class="flex flex-wrap gap-2">
          <button
            v-for="p in quickPrompts"
            :key="p"
            type="button"
            class="rounded-full border border-gray-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600 transition hover:border-[#F5B700] hover:bg-amber-50/50"
            @click="sendText(p)"
          >
            {{ p }}
          </button>
        </div>

        <form class="flex items-center gap-2" @submit.prevent="sendText(input)">
          <input
            v-model="input"
            type="text"
            :disabled="sending"
            placeholder="اكتب رسالتك هنا كأنك عميل على الواتساب..."
            class="flex-1 rounded-xl border border-gray-200 bg-slate-50/50 px-4 py-2.5 text-sm focus:border-[#F5B700] focus:bg-white focus:outline-none"
          />
          <button
            type="submit"
            :disabled="sending || !input.trim()"
            class="inline-flex items-center gap-1.5 rounded-xl bg-[#F5B700] px-5 py-2.5 text-xs font-black text-[#181A1F] transition hover:bg-[#E2A800] disabled:opacity-60 sm:text-sm"
          >
            <Send class="h-4 w-4" />
            <span>إرسال</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Bot, RotateCcw, Send } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { activeCompany } = useAuth()
const { request, extractErrorMessage } = useApi()
const toast = useToast()

const customerPhone = ref('96777000888')
const input = ref('')
const sending = ref(false)
const scrollBox = ref<HTMLElement | null>(null)
const messages = ref<Array<{ sender: 'user' | 'bot'; message: string; time: string }>>([])

const quickPrompts = [
  'السلام عليكم',
  'ما هي الخدمات والأسعار المتوفرة؟',
  'أبغى أحجز موعد',
  'أين موقعكم وأوقات الدوام؟',
]

const scrollToBottom = () => {
  nextTick(() => {
    if (scrollBox.value) {
      scrollBox.value.scrollTop = scrollBox.value.scrollHeight
    }
  })
}

const resetChat = async () => {
  try {
    const res = await request<any>('/company/simulator/reset', {
      method: 'POST',
      body: { customer_phone: customerPhone.value },
    })
    messages.value = [{ sender: 'bot', message: res.greeting, time: res.time }]
    scrollToBottom()
  } catch (err: any) {
    toast.error('تعذر إعادة الضبط', extractErrorMessage(err))
  }
}

const sendText = async (text: string) => {
  const clean = text.trim()
  if (!clean || sending.value) return
  input.value = ''

  messages.value.push({
    sender: 'user',
    message: clean,
    time: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
  })
  scrollToBottom()

  sending.value = true
  try {
    const res = await request<any>('/company/simulator/send', {
      method: 'POST',
      body: { message: clean, customer_phone: customerPhone.value },
    })
    messages.value.push({
      sender: 'bot',
      message: res.reply,
      time: res.time,
    })
  } catch (err: any) {
    toast.error('فشل الإرسال', extractErrorMessage(err))
  } finally {
    sending.value = false
    scrollToBottom()
  }
}

onMounted(resetChat)
</script>
