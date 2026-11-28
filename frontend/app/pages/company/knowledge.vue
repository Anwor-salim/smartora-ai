<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#181A1F] sm:text-[28px]">قاعدة المعرفة والأسعار</h1>
        <p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">
          ارفع ملفات Excel أو CSV أو PDF بالمنتجات أو الخدمات أو العيادات؛ ويعتمدها الذكاء الاصطناعي فوراً في الردود.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 self-start rounded-xl bg-[#F5B700] px-4 py-2.5 text-xs font-black text-[#181A1F] shadow-xs transition hover:bg-[#E2A800] sm:text-sm"
        @click="uploadModalOpen = true"
      >
        <UploadCloud class="h-4 w-4" />
        <span>رفع ملف معرفة جديد</span>
      </button>
    </div>

    <!-- Filter & Tabs Card -->
    <div class="rounded-2xl border border-gray-100 bg-white p-4 sm:p-5">
      <div class="flex flex-col-reverse gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="relative w-full lg:max-w-md">
          <Search class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="ابحث باسم الملف..."
            class="w-full rounded-xl border border-gray-200 bg-slate-50/50 py-2.5 pr-10 pl-4 text-xs font-medium focus:border-[#F5B700] focus:bg-white focus:outline-none sm:text-sm"
            @input="debouncedLoad"
          />
        </div>

        <div class="flex items-center gap-5 overflow-x-auto pb-1 lg:pb-0">
          <button
            v-for="tab in typeTabs"
            :key="tab.key"
            type="button"
            class="shrink-0 border-b-2 pb-2 text-xs font-extrabold transition sm:text-sm"
            :class="fileType === tab.key
              ? 'border-[#F5B700] text-[#181A1F]'
              : 'border-transparent text-slate-400 hover:text-slate-700'"
            @click="fileType = tab.key; loadDocuments()"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
      <div v-if="loading" class="p-8 text-center text-sm font-bold text-slate-400">جاري تحميل الملفات...</div>
      <div v-else-if="!documents.length" class="p-12 text-center">
        <FileSpreadsheet class="mx-auto h-10 w-10 text-slate-300" />
        <p class="mt-3 text-sm font-extrabold text-[#181A1F]">لا توجد ملفات معرفة مرفوعة بعد</p>
        <p class="mt-1 text-xs text-slate-400">ابدأ برفع ملف Excel أو PDF ليتمكن البوت من الإجابة بدقة.</p>
      </div>

      <template v-else>
        <div class="hidden overflow-x-auto md:block">
          <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-100 bg-slate-50/70 text-xs font-extrabold text-slate-500">
              <tr>
                <th class="px-4 py-3.5 text-start">الملف</th>
                <th class="px-4 py-3.5 text-start">النوع</th>
                <th class="px-4 py-3.5 text-center">الأصناف</th>
                <th class="px-4 py-3.5 text-start">الحالة</th>
                <th class="px-4 py-3.5 text-start">تاريخ الرفع</th>
                <th class="px-4 py-3.5 text-end">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="d in documents" :key="d.id" class="transition hover:bg-slate-50/60">
                <td class="px-4 py-3.5">
                  <p class="font-bold text-[#181A1F]">{{ d.title }}</p>
                  <p class="text-xs text-slate-400">{{ d.file_name }} · {{ d.formatted_size }}</p>
                </td>
                <td class="px-4 py-3.5">
                  <UiStatusBadge :label="(d.file_type || 'FILE').toUpperCase()" :tone="d.file_type === 'pdf' ? 'rose' : 'emerald'" />
                </td>
                <td class="px-4 py-3.5 text-center">
                  <span class="rounded-lg bg-amber-50 px-2.5 py-0.5 text-xs font-black text-amber-700">{{ d.total_items }} صنف</span>
                </td>
                <td class="px-4 py-3.5">
                  <UiStatusBadge :label="d.status === 'ready' ? 'جاهز' : d.status === 'error' ? 'خطأ' : 'قيد المعالجة'" :tone="d.status === 'ready' ? 'emerald' : d.status === 'error' ? 'rose' : 'amber'" dot />
                </td>
                <td class="px-4 py-3.5 text-xs text-slate-500">{{ d.created_at_human || d.created_at }}</td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50"
                      @click="openPreview(d)"
                    >
                      <Eye class="h-3.5 w-3.5" />
                      <span>معاينة</span>
                    </button>
                    <button
                      type="button"
                      class="rounded-lg bg-amber-50 p-1.5 text-amber-700 hover:bg-amber-100"
                      title="إعادة التحليل"
                      @click="reparseDoc(d)"
                    >
                      <RefreshCw class="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg bg-rose-50 p-1.5 text-rose-600 hover:bg-rose-100"
                      title="حذف الملف"
                      @click="deleteDoc(d)"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards -->
        <div class="divide-y divide-gray-100 md:hidden">
          <div v-for="d in documents" :key="d.id" class="space-y-3 p-4">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-black text-[#181A1F]">{{ d.title }}</p>
                <p class="text-xs text-slate-400">{{ d.file_name }} · {{ d.formatted_size }}</p>
              </div>
              <UiStatusBadge :label="(d.file_type || 'FILE').toUpperCase()" :tone="d.file_type === 'pdf' ? 'rose' : 'emerald'" />
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-amber-700">{{ d.total_items }} صنف</span>
              <div class="flex items-center gap-2">
                <button type="button" class="rounded-lg border border-gray-200 px-2.5 py-1 font-bold" @click="openPreview(d)">معاينة</button>
                <button type="button" class="rounded-lg bg-rose-50 px-2.5 py-1 font-bold text-rose-600" @click="deleteDoc(d)">حذف</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Upload Modal -->
    <UiModalDialog
      v-model="uploadModalOpen"
      title="رفع ملف معرفة جديد"
      subtitle="يدعم صيغ .xlsx و .xls و .csv و .pdf و .txt (حتى 50 ميجابايت)"
      size="md"
    >
      <form class="space-y-4" @submit.prevent="submitUpload">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">عنوان / وصف الملف</label>
          <input
            v-model="uploadForm.title"
            type="text"
            placeholder="مثال: دليل العيادات والأسعار 2026"
            class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-[#F5B700] focus:outline-none"
          />
        </div>

        <div>
          <label class="mb-1.5 block text-xs font-extrabold text-slate-700">اختر الملف *</label>
          <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-slate-50/70 p-6 text-center transition hover:border-[#F5B700]">
            <UploadCloud class="h-8 w-8 text-amber-500" />
            <p class="mt-2 text-xs font-extrabold text-[#181A1F]">
              {{ uploadForm.file ? uploadForm.file.name : 'انقر لاختيار ملف Excel أو PDF أو CSV' }}
            </p>
            <input type="file" accept=".xlsx,.xls,.csv,.pdf,.txt" class="hidden" @change="onFileSelect" />
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-slate-600" @click="uploadModalOpen = false">
            إلغاء
          </button>
          <button
            type="submit"
            :disabled="uploading || !uploadForm.file"
            class="rounded-xl bg-[#F5B700] px-5 py-2 text-xs font-black text-[#181A1F] hover:bg-[#E2A800] disabled:opacity-60"
          >
            {{ uploading ? 'جاري التحليل والرفع...' : 'رفع ومعالجة الآن' }}
          </button>
        </div>
      </form>
    </UiModalDialog>

    <!-- Preview Modal -->
    <UiModalDialog
      v-model="previewOpen"
      :title="`المحتوى المستخرج من: ${previewDoc?.title || ''}`"
      :subtitle="`${previewDoc?.total_items || 0} صنف مستخرج`"
      size="xl"
    >
      <div v-if="previewDoc" class="space-y-4">
        <div v-if="previewDoc.preview_data?.length" class="overflow-x-auto rounded-xl border border-gray-100">
          <table class="w-full text-start text-xs">
            <thead class="bg-slate-50 font-extrabold text-slate-500">
              <tr>
                <th v-for="col in previewColumns" :key="col" class="px-3 py-2.5 text-start">{{ col }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(row, idx) in previewDoc.preview_data" :key="idx">
                <td v-for="col in previewColumns" :key="col" class="px-3 py-2 text-slate-700">{{ row[col] ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="previewDoc.extracted_text">
          <p class="mb-1.5 text-xs font-extrabold text-slate-500">النص الخام المستخرج (Context Snippet):</p>
          <pre class="max-h-56 overflow-y-auto whitespace-pre-wrap rounded-xl border border-gray-100 bg-slate-50 p-3 text-xs text-slate-700">{{ previewDoc.extracted_text }}</pre>
        </div>
      </div>
    </UiModalDialog>
  </div>
</template>

<script setup lang="ts">
import { UploadCloud, Search, FileSpreadsheet, Eye, RefreshCw, Trash2 } from 'lucide-vue-next'

definePageMeta({
  layout: 'company',
  middleware: 'auth',
})

const { request, extractErrorMessage } = useApi()
const toast = useToast()

const loading = ref(true)
const uploading = ref(false)
const documents = ref<any[]>([])
const fileType = ref('all')
const search = ref('')

const uploadModalOpen = ref(false)
const uploadForm = ref<{ title: string; file: File | null }>({ title: '', file: null })

const previewOpen = ref(false)
const previewDoc = ref<any>(null)

const typeTabs = [
  { key: 'all', label: 'الكل' },
  { key: 'pdf', label: 'PDF' },
  { key: 'excel', label: 'Excel' },
  { key: 'csv', label: 'CSV' },
]

const previewColumns = computed(() => {
  const first = previewDoc.value?.preview_data?.[0]
  return first ? Object.keys(first) : []
})

let timer: ReturnType<typeof setTimeout> | null = null
const debouncedLoad = () => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(loadDocuments, 300)
}

const loadDocuments = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (fileType.value !== 'all') params.set('file_type', fileType.value)
    if (search.value.trim()) params.set('search', search.value.trim())
    const res = await request<any>(`/company/documents?${params.toString()}`)
    documents.value = res.documents || []
  } catch (err: any) {
    toast.error('تعذر تحميل الملفات', extractErrorMessage(err))
  } finally {
    loading.value = false
  }
}

const onFileSelect = (e: Event) => {
  const input = e.target as HTMLInputElement
  const f = input.files?.[0] || null
  uploadForm.value.file = f
  if (f && !uploadForm.value.title) {
    uploadForm.value.title = f.name.replace(/\.[^.]+$/, '')
  }
}

const submitUpload = async () => {
  if (!uploadForm.value.file) return
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', uploadForm.value.file)
    if (uploadForm.value.title.trim()) {
      fd.append('title', uploadForm.value.title.trim())
    }
    const res = await request<any>('/company/documents/upload', { method: 'POST', body: fd })
    toast.success('تم رفع الملف بنجاح', res.message)
    uploadModalOpen.value = false
    uploadForm.value = { title: '', file: null }
    await loadDocuments()
  } catch (err: any) {
    toast.error('فشلت معالجة الملف', extractErrorMessage(err))
  } finally {
    uploading.value = false
  }
}

const openPreview = (doc: any) => {
  previewDoc.value = doc
  previewOpen.value = true
}

const reparseDoc = async (doc: any) => {
  try {
    const res = await request<any>(`/company/documents/${doc.id}/reparse`, { method: 'POST' })
    toast.success(res.message)
    await loadDocuments()
  } catch (err: any) {
    toast.error('فشل التحليل', extractErrorMessage(err))
  }
}

const deleteDoc = async (doc: any) => {
  if (!confirm(`هل أنت متأكد من حذف الملف "${doc.title}"؟ سيتم حذفه نهائياً وتحديث ذاكرة البوت.`)) return
  try {
    const res = await request<any>(`/company/documents/${doc.id}`, { method: 'DELETE' })
    toast.success(res.message)
    documents.value = documents.value.filter(d => d.id !== doc.id)
  } catch (err: any) {
    toast.error('تعذر الحذف', extractErrorMessage(err))
  }
}

onMounted(loadDocuments)
</script>
