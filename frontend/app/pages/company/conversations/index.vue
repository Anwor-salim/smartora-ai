<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">محادثات العملاء</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">
          متابعة جميع المحادثات الواردة، حالة الرد الآلي، والتحكم الفوري بإيقاف أو تفعيل البوت لكل عميل.
        </p>
      </div>
      <NuxtLink
        to="/company/simulator"
        class="inline-flex items-center gap-2 self-start rounded-xl bg-[#F5B700] px-4 py-2.5 text-xs font-black text-[#181A1F] shadow-xs transition hover:bg-[#E2A800] sm:text-sm"
      >
        <Bot class="h-4 w-4" />
        <span>تجربة محاكي البوت</span>
      </NuxtLink>
    </div>

    <!-- Filters & Tabs Card -->
    <div class="rounded-2xl border border-gray-100 bg-white p-4 sm:p-5 space-y-4">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <select
          v-model="filters.state"
          class="rounded-xl border border-gray-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:border-[#F5B700] focus:bg-white focus:outline-none sm:text-sm"
          @change="loadConversations"
        >
          <option value="all">جميع المراحل</option>
          <option value="booking">طلب حجز</option>
          <option value="inquiring">استفسار</option>
          <option value="human_handoff">تحويل لموظف</option>
          <option value="browsing">تصفح عام</option>
        </select>

        <select
          v-model="filters.ai_paused"
          class="rounded-xl border border-gray-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:border-[#F5B700] focus:bg-white focus:outline-none sm:text-sm"
          @change="loadConversations"
        >
          <option value="all">حالة الرد الآلي: الكل</option>
          <option value="active">نشط (يرد تلقائياً)</option>
          <option value="paused">متوقف يدوياً</option>
        </select>
      </div>

      <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="relative w-full lg:max-w-md">
          <Search class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="ابحث برقم العميل، الاسم، أو نص الرسالة..."
            class="w-full rounded-xl border border-gray-200 bg-slate-50/50 py-2.5 pr-10 pl-4 text-xs font-medium text-[#181A1F] focus:border-[#F5B700] focus:bg-white focus:outline-none sm:text-sm"
            @input="debouncedSearch"
          />
        </div>

        <div class="flex items-center gap-5 overflow-x-auto pb-1 lg:pb-0">
          <button
            v-for="tab in channelTabs"
            :key="tab.key"
            type="button"
            class="flex shrink-0 items-center gap-2 border-b-2 pb-2 text-xs font-extrabold transition sm:text-sm"
            :class="filters.channel === tab.key
              ? 'border-[#F5B700] text-[#181A1F]'
              : 'border-transparent text-slate-400 hover:text-slate-700'"
            @click="filters.channel = tab.key; loadConversations()"
          >
            <span>{{ tab.label }}</span>
            <span
              class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full px-1.5 text-[11px] font-black"
              :class="filters.channel === tab.key ? 'bg-[#F5B700]/20 text-[#996E00]' : 'bg-slate-100 text-slate-500'"
            >
              {{ counts[tab.key] ?? 0 }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
      <div v-if="loading" class="p-8 text-center text-sm font-bold text-slate-400">جاري تحميل المحادثات...</div>
      <div v-else-if="!conversations.length" class="p-12 text-center">
        <MessageSquare class="mx-auto h-10 w-10 text-slate-300" />
        <p class="mt-3 text-sm font-extrabold text-[#181A1F]">لا توجد محادثات مطابقة</p>
      </div>

      <template v-else>
        <div class="hidden overflow-x-auto md:block">
          <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-100 bg-slate-50/70 text-xs font-extrabold text-slate-500">
              <tr>
                <th class="px-4 py-3.5 text-start">القناة</th>
                <th class="px-4 py-3.5 text-start">العميل</th>
                <th class="px-4 py-3.5 text-start">آخر رسالة</th>
                <th class="px-4 py-3.5 text-start">المرحلة</th>
                <th class="px-4 py-3.5 text-start">الرد الآلي</th>
                <th class="px-4 py-3.5 text-center">الرسائل</th>
                <th class="px-4 py-3.5 text-start">آخر تواصل</th>
                <th class="px-4 py-3.5 text-end">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="c in conversations" :key="c.id" class="transition hover:bg-slate-50/60">
                <td class="px-4 py-3.5">
                  <UiStatusBadge :label="channelLabel(c.channel)" :tone="channelTone(c.channel)" />
                </td>
                <td class="px-4 py-3.5">
                  <p class="font-bold text-[#181A1F]">{{ c.customer_name || c.clean_identifier }}</p>
                  <p class="text-xs text-slate-400" dir="ltr">{{ c.clean_identifier }}</p>
                </td>
                <td class="max-w-xs truncate px-4 py-3.5 text-xs text-slate-600">{{ c.last_message || '—' }}</td>
                <td class="px-4 py-3.5">
                  <UiStatusBadge :label="stateLabel(c.conversation_state)" :tone="stateTone(c.conversation_state)" />
                </td>
                <td class="px-4 py-3.5">
                  <UiStatusBadge :label="c.ai_paused ? 'متوقف' : 'نشط'" :tone="c.ai_paused ? 'rose' : 'emerald'" dot />
                </td>
                <td class="px-4 py-3.5 text-center font-extrabold text-slate-600">{{ c.messages_count }}</td>
                <td class="px-4 py-3.5 text-xs text-slate-400 whitespace-nowrap">{{ c.last_interaction_human || '—' }}</td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-end gap-1.5">
                    <NuxtLink
                      :to="`/company/conversations/${c.id}`"
                      class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50"
                    >
                      <Eye class="h-3.5 w-3.5" />
                      <span>عرض</span>
                    </NuxtLink>
                    <button
                      type="button"
                      class="rounded-lg px-2.5 py-1.5 text-xs font-extrabold"
                      :class="c.ai_paused ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'"
                      @click="togglePause(c)"
                    >
                      {{ c.ai_paused ? 'تفعيل' : 'إيقاف' }}
                    </button>
                    <button
                      type="button"
                      class="rounded-lg bg-slate-100 p-1.5 text-slate-600 hover:bg-slate-200"
                      title="تصفير السياق"
                      @click="resetContext(c)"
                    >
                      <RotateCcw class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards -->
        <div class="divide-y divide-gray-100 md:hidden">
          <div v-for="c in conversations" :key="c.id" class="space-y-2.5 p-4">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-black text-[#181A1F]">{{ c.customer_name || c.clean_identifier }}</p>
                <p class="text-xs text-slate-400" dir="ltr">{{ c.clean_identifier }}</p>
              </div>
              <UiStatusBadge :label="channelLabel(c.channel)" :tone="channelTone(c.channel)" />
            </div>
            <p class="line-clamp-2 text-xs text-slate-600">{{ c.last_message || '—' }}</p>
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
              <div class="flex items-center gap-1.5">
                <UiStatusBadge :label="stateLabel(c.conversation_state)" :tone="stateTone(c.conversation_state)" />
                <UiStatusBadge :label="c.ai_paused ? 'متوقف' : 'نشط'" :tone="c.ai_paused ? 'rose' : 'emerald'" dot />
              </div>
              <NuxtLink
                :to="`/company/conversations/${c.id}`"
                class="rounded-lg bg-[#F5B700] px-3 py-1.5 text-xs font-black text-[#181A1F]"
              >
                فتح المحادثة
              </NuxtLink>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Bot, Search, MessageSquare, Eye, RotateCcw } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { request, extractErrorMessage } = useApi()
const toast = useToast()

const loading = ref(true)
const conversations = ref<any[]>([])
const counts = ref<Record<string, number>>({})
const filters = ref({ channel: 'all', state: 'all', ai_paused: 'all', search: '' })

const channelTabs = [
  { key: 'all', label: 'الكل' },
  { key: 'whatsapp', label: 'واتساب' },
  { key: 'instagram', label: 'انستقرام' },
  { key: 'messenger', label: 'ماسنجر' },
]

let timer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(loadConversations, 300)
}

const loadConversations = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('channel', filters.value.channel)
    params.set('state', filters.value.state)
    params.set('ai_paused', filters.value.ai_paused)
    if (filters.value.search.trim()) params.set('search', filters.value.search.trim())

    const res = await request<any>(`/company/conversations?${params.toString()}`)
    conversations.value = res.conversations || []
    counts.value = res.counts || {}
  } catch (err: any) {
    toast.error('تعذر تحميل المحادثات', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const togglePause = async (c: any) => {
  try {
    const res = await request<any>(`/company/conversations/${c.id}/toggle-pause`, { method: 'POST' })
    c.ai_paused = res.ai_paused
    toast.success(res.message)
  } catch (err: any) {
    toast.error('فشل التحديث', extractErrorMessage(err))
  }
}

const resetContext = async (c: any) => {
  if (!confirm('هل تريد تصفير ذاكرة وسياق هذه المحادثة؟')) return
  try {
    const res = await request<any>(`/company/conversations/${c.id}/reset-context`, { method: 'POST' })
    toast.success(res.message)
    await loadConversations()
  } catch (err: any) {
    toast.error('فشل تصفير السياق', extractErrorMessage(err))
  }
}

const channelLabel = (c: string) => (c === 'instagram' ? 'انستقرام' : c === 'messenger' ? 'ماسنجر' : 'واتساب')
const channelTone = (c: string) => (c === 'instagram' ? 'pink' : c === 'messenger' ? 'sky' : 'emerald')

const stateLabel = (s: string) => {
  if (s === 'booking') return 'طلب حجز'
  if (s === 'inquiring') return 'استفسار'
  if (s === 'human_handoff') return 'تحويل لموظف'
  return 'تصفح'
}

const stateTone = (s: string) => {
  if (s === 'booking') return 'amber'
  if (s === 'inquiring') return 'sky'
  if (s === 'human_handoff') return 'rose'
  return 'slate'
}

onMounted(loadConversations)
</script>
