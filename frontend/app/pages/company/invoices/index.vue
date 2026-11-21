<template>
  <div class="invoices-page py-8">
    <div class="container">
      
      <!-- Header -->
      <div class="flex justify-between items-center mb-8">
        <div>
          <h1 class="text-3xl font-extrabold text-slate-900">إدارة الفواتير (ERP)</h1>
          <p class="text-slate-500">استعرض الفواتير، تابع التحصيل، وقم بإصدار فواتير جديدة.</p>
        </div>
        <NuxtLink to="/company/invoices/create" class="btn-primary">
          + إنشاء فاتورة جديدة
        </NuxtLink>
      </div>

      <!-- Filters -->
      <div class="filters-bar glass-panel p-4 mb-6 flex gap-4 items-end">
        <div class="filter-group">
          <label>البحث بالعميل</label>
          <input v-model="store.filters.customer" type="text" placeholder="اسم العميل..." class="filter-input" @input="debouncedFetch" />
        </div>
        <div class="filter-group">
          <label>النوع</label>
          <select v-model="store.filters.type" class="filter-select" @change="store.fetchInvoices">
            <option value="">الكل</option>
            <option value="cash">نقدي</option>
            <option value="credit">آجل</option>
            <option value="return">مرتجع</option>
          </select>
        </div>
        <div class="filter-group">
          <label>الحالة</label>
          <select v-model="store.filters.status" class="filter-select" @change="store.fetchInvoices">
            <option value="">الكل</option>
            <option value="paid">مدفوع</option>
            <option value="unpaid">غير مدفوع</option>
            <option value="partially_paid">مدفوع جزئياً</option>
            <option value="draft">مسودة</option>
          </select>
        </div>
        <button @click="resetFilters" class="btn-outline-sm">إعادة ضبط</button>
      </div>

      <!-- Table Content -->
      <InvoiceTable 
        :invoices="store.invoices" 
        :loading="store.loading"
        @confirm="confirmInvoice"
        @delete="deleteInvoice"
      />

    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useInvoiceStore } from '~/stores/invoiceStore'
import InvoiceTable from '~/components/invoices/InvoiceTable.vue'

definePageMeta({
  layout: 'company',
  middleware: 'auth'
})

const store = useInvoiceStore()

onMounted(() => {
  store.fetchInvoices()
})

const resetFilters = () => {
  store.filters = { type: '', status: '', customer: '' }
  store.fetchInvoices()
}

let debounceTimer: any
const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => store.fetchInvoices(), 500)
}

const confirmInvoice = async (id: number) => {
  if (confirm('هل أنت متأكد من تأكيد هذه الفاتورة؟ لن تتمكن من تعديلها كمسودة لاحقاً.')) {
    await store.confirmInvoice(id)
  }
}

const deleteInvoice = async (id: number) => {
  if (confirm('هل أنت متأكد من حذف هذه الفاتورة؟')) {
    await store.deleteInvoice(id)
  }
}
</script>

<style scoped>
.invoices-page {
  background-color: #f8fafc;
  min-height: 100vh;
}

.btn-primary {
  background: var(--color-primary);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 0.75rem;
  font-weight: 800;
  text-decoration: none;
  box-shadow: var(--shadow-md);
  transition: transform 0.2s;
}

.btn-primary:hover { transform: translateY(-2px); }

.filters-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.filter-group label {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-muted);
}

.filter-input, .filter-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  font-family: inherit;
}

.btn-outline-sm {
  background: white;
  border: 1px solid #cbd5e1;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 600;
}

.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.mb-8 { margin-bottom: 2rem; }
.mb-6 { margin-bottom: 1.5rem; }
.gap-4 { gap: 1rem; }
.items-end { align-items: flex-end; }
</style>
