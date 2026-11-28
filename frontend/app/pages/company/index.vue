<template>
  <div class="space-y-6">
    <!-- Top Page Header (وقت اللزوم style) -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">لوحة التحكم</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">
          إحصائيات وتحليلات أداء منشأتك في الرد الآلي، المحادثات، الحجوزات، وقنوات التواصل المتصلة.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <NuxtLink
          to="/company/appointments"
          class="inline-flex items-center gap-2 rounded-xl bg-[#F5B700] px-4 py-2.5 text-xs font-black text-[#181A1F] shadow-xs transition hover:bg-[#E2A800] sm:text-sm"
        >
          <CalendarCheck class="h-4 w-4" />
          <span>الحجوزات</span>
        </NuxtLink>

        <NuxtLink
          to="/company/settings"
          class="inline-flex items-center gap-2 rounded-xl bg-[#1F2227] px-4 py-2.5 text-xs font-bold text-white transition hover:bg-[#2A2E35] sm:text-sm"
        >
          <Building2 class="h-4 w-4" />
          <span>بيانات المنشأة</span>
        </NuxtLink>

        <select
          v-model="range"
          class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:border-[#F5B700] focus:outline-none sm:text-sm"
          @change="loadDashboard"
        >
          <option value="all">كل الأوقات (الافتراضي)</option>
          <option value="today">اليوم</option>
          <option value="7d">آخر 7 أيام</option>
          <option value="30d">آخر 30 يوماً</option>
        </select>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading && !data" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="n in 6" :key="n" class="h-28 animate-pulse rounded-2xl border border-gray-100 bg-white"></div>
    </div>

    <template v-else-if="data">
      <!-- Row 1: 3 Primary KPI Cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <UiKpiCard
          label="إجمالي الحجوزات"
          :value="data.kpis.total_appointments"
          unit="حجز"
          tone="amber"
          to="/company/appointments"
          :dots="[
            { label: `${data.kpis.approved_appointments} مؤكد`, color: '#10B981' },
            { label: `${data.kpis.pending_appointments} قيد المراجعة`, color: '#F5B700' }
          ]"
        >
          <template #icon><CalendarCheck class="h-6 w-6" /></template>
        </UiKpiCard>

        <UiKpiCard
          label="إجمالي محادثات العملاء"
          :value="data.kpis.total_conversations"
          unit="محادثة"
          tone="sky"
          to="/company/conversations"
          :dots="[
            { label: `${data.kpis.today_conversations} محادثة اليوم`, color: '#0EA5E9' }
          ]"
        >
          <template #icon><MessageSquare class="h-6 w-6" /></template>
        </UiKpiCard>

        <UiKpiCard
          label="حالة الاشتراك والمنشأة"
          :value="data.company.is_active ? 'نشط' : 'متوقف'"
          tone="emerald"
          to="/company/settings"
          :subtext="data.company.days_remaining !== null ? `${data.company.plan_label} · متبقي ${data.company.days_remaining} يوم` : data.company.plan_label"
        >
          <template #icon><BadgeCheck class="h-6 w-6" /></template>
        </UiKpiCard>
      </div>

      <!-- Row 2: 4 Secondary KPI Cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <UiKpiCard
          label="القنوات المتصلة"
          :value="`${data.kpis.connected_channels} / 3`"
          tone="sky"
          to="/company/channels/whatsapp"
          :dots="[
            { label: 'واتساب', color: data.kpis.channels.whatsapp ? '#10B981' : '#CBD5E1' },
            { label: 'انستقرام', color: data.kpis.channels.instagram ? '#EC4899' : '#CBD5E1' },
            { label: 'ماسنجر', color: data.kpis.channels.messenger ? '#0EA5E9' : '#CBD5E1' }
          ]"
        >
          <template #icon><Wifi class="h-5 w-5" /></template>
        </UiKpiCard>

        <UiKpiCard
          label="قاعدة المعرفة والملفات"
          :value="data.kpis.ready_documents"
          unit="ملف"
          tone="indigo"
          to="/company/knowledge"
          :subtext="`${data.kpis.total_items} صنف ومعلومة مستخرجة`"
        >
          <template #icon><FileSpreadsheet class="h-5 w-5" /></template>
        </UiKpiCard>

        <UiKpiCard
          label="الرد الآلي النشط"
          :value="data.kpis.active_ai_conversations"
          unit="محادثة"
          tone="teal"
          to="/company/conversations"
          :dots="[
            { label: `${data.kpis.paused_ai_conversations} متوقف يدوياً`, color: '#94A3B8' }
          ]"
        >
          <template #icon><Bot class="h-5 w-5" /></template>
        </UiKpiCard>

        <UiKpiCard
          label="تحويلات الدعم البشري"
          :value="data.kpis.handoff_conversations"
          unit="طلب"
          tone="slate"
          to="/company/conversations"
          :subtext="`${data.kpis.pending_appointments} حجز بانتظار الاعتماد`"
        >
          <template #icon><Headphones class="h-5 w-5" /></template>
        </UiKpiCard>
      </div>

      <!-- Onboarding Progress (if not 100%) -->
      <div
        v-if="data.onboarding && !data.onboarding.is_fully_onboarded"
        class="rounded-2xl border border-amber-200/70 bg-white p-5"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-extrabold text-[#181A1F]">خطوات تفعيل المنشأة ({{ data.onboarding.percentage }}%)</h3>
            <p class="text-xs text-slate-500">أكمل الخطوات المتبقية لتفعيل الرد الآلي بكامل كفاءته.</p>
          </div>
          <div class="h-2.5 w-44 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-[#F5B700] transition-all" :style="{ width: `${data.onboarding.percentage}%` }"></div>
          </div>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
          <NuxtLink
            v-for="(step, key) in data.onboarding.steps"
            :key="key"
            :to="mapOnboardingUrl(step.url)"
            class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-xs font-bold transition"
            :class="step.completed
              ? 'border-emerald-100 bg-emerald-50/60 text-emerald-700'
              : 'border-gray-200 bg-slate-50/70 text-slate-700 hover:border-[#F5B700]'"
          >
            <CheckCircle2 v-if="step.completed" class="h-4 w-4 shrink-0 text-emerald-600" />
            <Circle v-else class="h-4 w-4 shrink-0 text-amber-500" />
            <span class="truncate">{{ step.title }}</span>
          </NuxtLink>
        </div>
      </div>

      <!-- Analytics Row: 2 Cards Side by Side (matching reference) -->
      <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Right Card: Horizontal Bar Chart -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6">
          <div class="mb-6 flex items-center justify-between">
            <div>
              <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-amber-600">
                <span class="h-1.5 w-1.5 rounded-full bg-[#F5B700]"></span>
                توزيع القنوات
              </span>
              <h3 class="mt-0.5 text-base font-black text-[#181A1F]">نشاط القنوات وتوزيع المحادثات</h3>
            </div>
            <NuxtLink to="/company/conversations" class="text-xs font-bold text-slate-500 hover:text-[#181A1F]">
              عرض الكل ←
            </NuxtLink>
          </div>

          <UiHorizontalBarChart :items="data.charts.channels_bar" />
        </div>

        <!-- Left Card: Segmented Tabs + Donut Breakdown -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6">
          <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
            <div>
              <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-amber-600">
                <span class="h-1.5 w-1.5 rounded-full bg-[#F5B700]"></span>
                التقرير التشغيلي
              </span>
              <h3 class="mt-0.5 text-base font-black text-[#181A1F]">تحليل الحجوزات والمحادثات</h3>
            </div>

            <div class="flex items-center gap-4 text-xs font-bold">
              <button
                type="button"
                class="border-b-2 pb-1.5 transition"
                :class="donutTab === 'appointments'
                  ? 'border-[#F5B700] text-[#181A1F]'
                  : 'border-transparent text-slate-400 hover:text-slate-700'"
                @click="donutTab = 'appointments'"
              >
                تحليل الحجوزات
              </button>
              <button
                type="button"
                class="border-b-2 pb-1.5 transition"
                :class="donutTab === 'conversations'
                  ? 'border-[#F5B700] text-[#181A1F]'
                  : 'border-transparent text-slate-400 hover:text-slate-700'"
                @click="donutTab = 'conversations'"
              >
                تحليل المحادثات
              </button>
            </div>
          </div>

          <UiDonutChart
            v-if="donutTab === 'appointments'"
            :items="data.charts.appointments_breakdown.items"
            :total="data.charts.appointments_breakdown.total"
            center-label="إجمالي الحجوزات"
            unit-label="حجز"
          />
          <UiDonutChart
            v-else
            :items="data.charts.conversations_breakdown.items"
            :total="data.charts.conversations_breakdown.total"
            center-label="إجمالي المحادثات"
            unit-label="محادثة"
          />
        </div>
      </div>

      <!-- Recent Activity Row -->
      <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Recent Appointments -->
        <div class="rounded-2xl border border-gray-100 bg-white p-5">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-black text-[#181A1F]">أحدث طلبات الحجز</h3>
            <NuxtLink to="/company/appointments" class="text-xs font-bold text-amber-600 hover:underline">
              إدارة الحجوزات
            </NuxtLink>
          </div>
          <div v-if="!data.recent_appointments.length" class="py-8 text-center text-xs text-slate-400">
            لا توجد حجوزات مسجلة حتى الآن.
          </div>
          <div v-else class="divide-y divide-gray-100">
            <div v-for="a in data.recent_appointments" :key="a.id" class="flex items-center justify-between gap-3 py-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-bold text-[#181A1F]">
                  {{ a.customer_name || a.customer_phone }}
                  <span class="mx-1 text-xs font-semibold text-slate-400">({{ a.booking_code }})</span>
                </p>
                <p class="text-xs text-slate-500">
                  {{ a.appointment_date }} {{ a.appointment_time ? `· ${a.appointment_time}` : '' }} · الدور #{{ a.queue_number }}
                </p>
              </div>
              <UiStatusBadge :label="a.status_label" :tone="appointmentTone(a.status)" />
            </div>
          </div>
        </div>

        <!-- Recent Conversations -->
        <div class="rounded-2xl border border-gray-100 bg-white p-5">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-black text-[#181A1F]">أحدث محادثات العملاء</h3>
            <NuxtLink to="/company/conversations" class="text-xs font-bold text-amber-600 hover:underline">
              عرض المحادثات
            </NuxtLink>
          </div>
          <div v-if="!data.recent_conversations.length" class="py-8 text-center text-xs text-slate-400">
            لا توجد محادثات بعد.
          </div>
          <div v-else class="divide-y divide-gray-100">
            <NuxtLink
              v-for="c in data.recent_conversations"
              :key="c.id"
              :to="`/company/conversations/${c.id}`"
              class="flex items-center justify-between gap-3 py-3 transition hover:bg-slate-50/70 rounded-xl px-2"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-bold text-[#181A1F]">{{ c.customer_name || c.customer_phone }}</p>
                <p class="truncate text-xs text-slate-500">{{ c.last_message || '—' }}</p>
              </div>
              <div class="flex shrink-0 flex-col items-end gap-1">
                <UiStatusBadge :label="channelLabel(c.channel)" :tone="channelTone(c.channel)" />
                <span class="text-[11px] text-slate-400">{{ c.last_interaction_human }}</span>
              </div>
            </NuxtLink>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import {
  CalendarCheck,
  Building2,
  MessageSquare,
  BadgeCheck,
  Wifi,
  FileSpreadsheet,
  Bot,
  Headphones,
  CheckCircle2,
  Circle,
} from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { request, extractErrorMessage } = useApi()
const toast = useToast()

const range = ref('all')
const loading = ref(true)
const data = ref<any>(null)
const donutTab = ref<'appointments' | 'conversations'>('appointments')

const loadDashboard = async () => {
  loading.value = true
  try {
    data.value = await request(`/company/dashboard?range=${range.value}`)
  } catch (err: any) {
    toast.error('تعذر تحميل الإحصائيات', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const mapOnboardingUrl = (legacyUrl: string) => {
  if (legacyUrl.includes('company-settings')) return '/company/settings'
  if (legacyUrl.includes('knowledge-documents')) return '/company/knowledge'
  if (legacyUrl.includes('whats-app-connection')) return '/company/channels/whatsapp'
  if (legacyUrl.includes('whats-app-simulator')) return '/company/simulator'
  return '/company'
}

const appointmentTone = (status: string) => {
  if (status === 'approved') return 'emerald'
  if (status === 'rejected') return 'rose'
  if (status === 'pending') return 'amber'
  return 'slate'
}

const channelLabel = (channel: string) => {
  if (channel === 'instagram') return 'انستقرام'
  if (channel === 'messenger') return 'ماسنجر'
  return 'واتساب'
}

const channelTone = (channel: string) => {
  if (channel === 'instagram') return 'pink'
  if (channel === 'messenger') return 'sky'
  return 'emerald'
}

onMounted(() => {
  loadDashboard()
})
</script>
