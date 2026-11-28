<template>
  <div class="space-y-5">
    <!-- Page Header (matching reference /dashboard/orders) -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">الحجوزات والمواعيد</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">
          إدارة طلبات الحجز الواردة من الواتساب وانستقرام وماسنجر مع إمكانية الاعتماد والمزامنة الفورية مع Google Sheets.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <button
          type="button"
          :disabled="syncing"
          class="inline-flex items-center gap-2 rounded-xl bg-[#F5B700] px-4 py-2.5 text-xs font-black text-[#181A1F] shadow-xs transition hover:bg-[#E2A800] disabled:opacity-60 sm:text-sm"
          @click="syncFromSheet"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
          <span>{{ syncing ? 'جاري المزامنة...' : 'مزامنة من Google Sheet' }}</span>
        </button>

        <NuxtLink
          to="/company/booking-settings"
          class="inline-flex items-center gap-2 rounded-xl bg-[#1F2227] px-4 py-2.5 text-xs font-bold text-white transition hover:bg-[#2A2E35] sm:text-sm"
        >
          <SlidersHorizontal class="h-4 w-4" />
          <span>إعدادات الحجز</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Filter & Tabs Card (matching reference Image 3) -->
    <div class="rounded-2xl border border-gray-100 bg-white p-4 sm:p-5 space-y-4">
      <!-- Top Dropdown Filters Row -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <select
          v-model="filters.channel"
          class="rounded-xl border border-gray-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:border-[#F5B700] focus:bg-white focus:outline-none sm:text-sm"
          @change="loadAppointments"
        >
          <option value="all">جميع القنوات</option>
          <option value="whatsapp">واتساب</option>
          <option value="instagram">انستقرام</option>
          <option value="messenger">ماسنجر</option>
        </select>

        <div class="flex items-center gap-2">
          <input
            v-model="filters.date_from"
            type="date"
            class="w-full rounded-xl border border-gray-200 bg-slate-50/50 px-3 py-2.5 text-xs font-bold text-slate-700 focus:border-[#F5B700] focus:bg-white focus:outline-none"
            @change="loadAppointments"
          />
          <span class="text-xs font-bold text-slate-400">إلى</span>
          <input
            v-model="filters.date_to"
            type="date"
            class="w-full rounded-xl border border-gray-200 bg-slate-50/50 px-3 py-2.5 text-xs font-bold text-slate-700 focus:border-[#F5B700] focus:bg-white focus:outline-none"
            @change="loadAppointments"
          />
        </div>

        <button
          v-if="hasActiveFilters"
          type="button"
          class="rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
          @click="resetFilters"
        >
          مسح الفلاتر
        </button>
      </div>

      <!-- Bottom Row: Golden Underline Status Tabs + Search Input -->
      <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="relative w-full lg:max-w-md">
          <Search class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="ابحث باسم العميل، رقم الجوال، أو كود الحجز..."
            class="w-full rounded-xl border border-gray-200 bg-slate-50/50 py-2.5 pr-10 pl-4 text-xs font-medium text-[#181A1F] focus:border-[#F5B700] focus:bg-white focus:outline-none sm:text-sm"
            @input="debouncedSearch"
          />
        </div>

        <div class="flex items-center gap-4 overflow-x-auto pb-1 lg:pb-0">
          <button
            v-for="tab in statusTabs"
            :key="tab.key"
            type="button"
            class="flex shrink-0 items-center gap-2 border-b-2 pb-2 text-xs font-extrabold transition sm:text-sm"
            :class="filters.status === tab.key
              ? 'border-[#F5B700] text-[#181A1F]'
              : 'border-transparent text-slate-400 hover:text-slate-700'"
            @click="filters.status = tab.key; loadAppointments()"
          >
            <span>{{ tab.label }}</span>
            <span
              class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full px-1.5 text-[11px] font-black"
              :class="filters.status === tab.key
                ? 'bg-[#F5B700]/20 text-[#996E00]'
                : 'bg-slate-100 text-slate-500'"
            >
              {{ counts[tab.key] ?? 0 }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Data Table Card -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
      <div v-if="loading" class="p-8 text-center text-sm font-bold text-slate-400">
        جاري تحميل الحجوزات...
      </div>

      <div v-else-if="!appointments.length" class="p-12 text-center">
        <CalendarX2 class="mx-auto h-10 w-10 text-slate-300" />
        <p class="mt-3 text-sm font-extrabold text-[#181A1F]">لا توجد حجوزات مطابقة</p>
        <p class="mt-1 text-xs text-slate-400">ستظهر هنا طلبات الحجز الجديدة فور تسجيلها عبر البوت.</p>
      </div>

      <template v-else>
        <!-- Desktop Table -->
        <div class="hidden overflow-x-auto md:block">
          <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-100 bg-slate-50/70 text-xs font-extrabold text-slate-500">
              <tr>
                <th class="px-4 py-3.5 text-start">الكود</th>
                <th class="px-4 py-3.5 text-center">الدور</th>
                <th class="px-4 py-3.5 text-start">العميل</th>
                <th class="px-4 py-3.5 text-start">تاريخ الموعد</th>
                <th class="px-4 py-3.5 text-start">القناة</th>
                <th class="px-4 py-3.5 text-start">الحالة</th>
                <th class="px-4 py-3.5 text-center">مزامنة Sheet</th>
                <th class="px-4 py-3.5 text-end">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="a in appointments" :key="a.id" class="transition hover:bg-slate-50/60">
                <td class="px-4 py-3.5 font-extrabold text-[#181A1F] whitespace-nowrap">{{ a.booking_code }}</td>
                <td class="px-4 py-3.5 text-center">
                  <span class="inline-flex rounded-lg bg-amber-50 px-2.5 py-0.5 text-xs font-black text-amber-700">#{{ a.queue_number }}</span>
                </td>
                <td class="px-4 py-3.5">
                  <p class="font-bold text-[#181A1F]">{{ a.customer_name || 'عميل' }}</p>
                  <p class="text-xs text-slate-400" dir="ltr">{{ a.customer_phone }}</p>
                </td>
                <td class="px-4 py-3.5 whitespace-nowrap">
                  <p class="font-bold text-[#181A1F]">{{ a.appointment_date }}</p>
                  <p v-if="a.appointment_time" class="text-xs text-slate-400">{{ a.appointment_time }}</p>
                </td>
                <td class="px-4 py-3.5">
                  <UiStatusBadge :label="channelLabel(a.channel)" :tone="channelTone(a.channel)" />
                </td>
                <td class="px-4 py-3.5">
                  <UiStatusBadge :label="a.status_label" :tone="statusTone(a.status)" dot />
                </td>
                <td class="px-4 py-3.5 text-center">
                  <UiStatusBadge
                    :label="a.sheet_row_number ? `صف #${a.sheet_row_number}` : 'غير متزامن'"
                    :tone="a.sheet_row_number ? 'emerald' : 'slate'"
                  />
                </td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      v-if="a.status === 'pending'"
                      type="button"
                      class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-extrabold text-emerald-700 hover:bg-emerald-100"
                      @click="approve(a)"
                    >
                      قبول
                    </button>
                    <button
                      v-if="a.status === 'pending' || a.status === 'approved'"
                      type="button"
                      class="rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs font-extrabold text-rose-700 hover:bg-rose-100"
                      @click="openRejectModal(a)"
                    >
                      رفض
                    </button>
                    <button
                      v-if="a.status === 'pending' || a.status === 'approved'"
                      type="button"
                      class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-200"
                      @click="cancel(a)"
                    >
                      إلغاء
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50"
                      @click="openDetailModal(a)"
                    >
                      <Eye class="h-3.5 w-3.5" />
                      <span>التفاصيل</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards -->
        <div class="divide-y divide-gray-100 md:hidden">
          <div v-for="a in appointments" :key="a.id" class="space-y-3 p-4">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-black text-[#181A1F]">{{ a.customer_name || a.customer_phone }}</p>
                <p class="text-xs text-slate-400">{{ a.booking_code }} · الدور #{{ a.queue_number }}</p>
              </div>
              <UiStatusBadge :label="a.status_label" :tone="statusTone(a.status)" dot />
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600">
              <span>📅 {{ a.appointment_date }}</span>
              <span v-if="a.appointment_time">⏰ {{ a.appointment_time }}</span>
              <UiStatusBadge :label="channelLabel(a.channel)" :tone="channelTone(a.channel)" />
            </div>
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <button
                v-if="a.status === 'pending'"
                type="button"
                class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-extrabold text-emerald-700"
                @click="approve(a)"
              >
                قبول
              </button>
              <button
                v-if="a.status === 'pending' || a.status === 'approved'"
                type="button"
                class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-extrabold text-rose-700"
                @click="openRejectModal(a)"
              >
                رفض
              </button>
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-slate-700"
                @click="openDetailModal(a)"
              >
                عرض التفاصيل
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Appointment Details Modal -->
    <UiModalDialog
      v-model="detailOpen"
      :title="`تفاصيل الحجز ${selectedAppt?.booking_code || ''}`"
      subtitle="كافة البيانات المسجلة والمجمّعة من العميل"
    >
      <div v-if="selectedAppt" class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] font-bold text-slate-400">اسم العميل</p>
            <p class="mt-0.5 text-sm font-extrabold text-[#181A1F]">{{ selectedAppt.customer_name || '—' }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] font-bold text-slate-400">رقم / معرّف العميل</p>
            <p class="mt-0.5 text-sm font-extrabold text-[#181A1F]" dir="ltr">{{ selectedAppt.customer_phone }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] font-bold text-slate-400">تاريخ ووقت الموعد</p>
            <p class="mt-0.5 text-sm font-extrabold text-[#181A1F]">
              {{ selectedAppt.appointment_date }} {{ selectedAppt.appointment_time ? `— ${selectedAppt.appointment_time}` : '' }}
            </p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] font-bold text-slate-400">الحالة والدور</p>
            <p class="mt-0.5 text-sm font-extrabold text-[#181A1F]">{{ selectedAppt.status_label }} · الدور #{{ selectedAppt.queue_number }}</p>
          </div>
        </div>

        <div v-if="selectedAppt.rejection_reason" class="rounded-xl border border-rose-100 bg-rose-50 p-3 text-xs font-bold text-rose-700">
          سبب الرفض: {{ selectedAppt.rejection_reason }}
        </div>

        <div v-if="selectedAppt.form_data && Object.keys(selectedAppt.form_data).length" class="space-y-2">
          <h4 class="text-xs font-extrabold text-slate-500">الحقول المجمّعة (Google Sheet):</h4>
          <div class="divide-y divide-gray-100 rounded-xl border border-gray-100">
            <div v-for="(val, key) in selectedAppt.form_data" :key="key" class="flex items-center justify-between px-4 py-2.5 text-xs">
              <span class="font-bold text-slate-500">{{ key }}</span>
              <span class="font-extrabold text-[#181A1F]">{{ val }}</span>
            </div>
          </div>
        </div>
      </div>
    </UiModalDialog>

    <!-- Reject Modal -->
    <UiModalDialog
      v-model="rejectOpen"
      title="رفض طلب الحجز"
      subtitle="سيتم إشعار العميل بالرفض وإفراغ المقعد في اليوم المحدد"
      size="sm"
    >
      <div class="space-y-3">
        <label class="block text-xs font-extrabold text-slate-700">سبب الرفض (اختياري - يُرسل للعميل)</label>
        <textarea
          v-model="rejectReason"
          rows="3"
          placeholder="مثال: الطبيب غير متاح في هذا الوقت..."
          class="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-[#F5B700] focus:outline-none"
        ></textarea>
      </div>
      <template #footer>
        <button type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-slate-600" @click="rejectOpen = false">
          إلغاء
        </button>
        <button type="button" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-extrabold text-white hover:bg-rose-700" @click="confirmReject">
          تأكيد الرفض
        </button>
      </template>
    </UiModalDialog>
  </div>
</template>

<script setup lang="ts">
import { RefreshCw, SlidersHorizontal, Search, CalendarX2, Eye } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { request, extractErrorMessage } = useApi()
const toast = useToast()
const pendingBadge = useState<number>('pending_appointments_badge', () => 0)

const loading = ref(true)
const syncing = ref(false)
const appointments = ref<any[]>([])
const counts = ref<Record<string, number>>({})

const filters = ref({
  status: 'all',
  channel: 'all',
  date_from: '',
  date_to: '',
  search: '',
})

const statusTabs = [
  { key: 'all', label: 'الكل' },
  { key: 'pending', label: 'قيد المراجعة' },
  { key: 'approved', label: 'مؤكد' },
  { key: 'rejected', label: 'مرفوض' },
  { key: 'cancelled', label: 'ملغي' },
]

const hasActiveFilters = computed(() =>
  filters.value.channel !== 'all' || filters.value.date_from !== '' || filters.value.date_to !== '' || filters.value.search !== ''
)

let searchTimer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(loadAppointments, 300)
}

const loadAppointments = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filters.value.status) params.set('status', filters.value.status)
    if (filters.value.channel) params.set('channel', filters.value.channel)
    if (filters.value.date_from) params.set('date_from', filters.value.date_from)
    if (filters.value.date_to) params.set('date_to', filters.value.date_to)
    if (filters.value.search.trim()) params.set('search', filters.value.search.trim())

    const res = await request<any>(`/company/appointments?${params.toString()}`)
    appointments.value = res.appointments || []
    counts.value = res.counts || {}
    pendingBadge.value = res.counts?.pending ?? 0
  } catch (err: any) {
    toast.error('تعذر تحميل الحجوزات', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.value = { status: 'all', channel: 'all', date_from: '', date_to: '', search: '' }
  loadAppointments()
}

const approve = async (appt: any) => {
  try {
    const res = await request<any>(`/company/appointments/${appt.id}/approve`, { method: 'POST' })
    toast.success(res.message)
    await loadAppointments()
  } catch (err: any) {
    toast.error('فشل قبول الحجز', extractErrorMessage(err))
  }
}

const detailOpen = ref(false)
const selectedAppt = ref<any>(null)
const openDetailModal = (appt: any) => {
  selectedAppt.value = appt
  detailOpen.value = true
}

const rejectOpen = ref(false)
const rejectReason = ref('')
const rejectTarget = ref<any>(null)
const openRejectModal = (appt: any) => {
  rejectTarget.value = appt
  rejectReason.value = ''
  rejectOpen.value = true
}

const confirmReject = async () => {
  if (!rejectTarget.value) return
  try {
    const res = await request<any>(`/company/appointments/${rejectTarget.value.id}/reject`, {
      method: 'POST',
      body: { reason: rejectReason.value },
    })
    toast.warning(res.message)
    rejectOpen.value = false
    await loadAppointments()
  } catch (err: any) {
    toast.error('فشل رفض الحجز', extractErrorMessage(err))
  }
}

const cancel = async (appt: any) => {
  if (!confirm(`هل أنت متأكد من إلغاء الحجز ${appt.booking_code}؟`)) return
  try {
    const res = await request<any>(`/company/appointments/${appt.id}/cancel`, { method: 'POST' })
    toast.success(res.message)
    await loadAppointments()
  } catch (err: any) {
    toast.error('تعذر إلغاء الحجز', extractErrorMessage(err))
  }
}

const syncFromSheet = async () => {
  syncing.value = true
  try {
    const res = await request<any>('/company/appointments/sync-sheets', { method: 'POST' })
    toast.success('تمت المزامنة مع Google Sheet', res.message)
    await loadAppointments()
  } catch (err: any) {
    toast.error('تعذر المزامنة', extractErrorMessage(err))
  } finally {
    syncing.value = false
  }
}

const statusTone = (status: string) => {
  if (status === 'approved') return 'emerald'
  if (status === 'rejected') return 'rose'
  if (status === 'pending') return 'amber'
  return 'slate'
}

const channelLabel = (c: string) => (c === 'instagram' ? 'انستقرام' : c === 'messenger' ? 'ماسنجر' : 'واتساب')
const channelTone = (c: string) => (c === 'instagram' ? 'pink' : c === 'messenger' ? 'sky' : 'emerald')

onMounted(() => {
  loadAppointments()
})
</script>
