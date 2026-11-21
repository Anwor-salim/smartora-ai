<template>
  <div class="erp-table-container glass-panel">
    <table class="erp-table">
      <thead>
        <tr>
          <th>رقم الفاتورة</th>
          <th>العميل</th>
          <th>النوع</th>
          <th>الحالة</th>
          <th>الإجمالي</th>
          <th>المدفوع</th>
          <th>المتبقي</th>
          <th>التاريخ</th>
          <th>الإجراءات</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="9" class="text-center py-8">
            <div class="loader">جاري التحميل...</div>
          </td>
        </tr>
        <tr v-else v-for="invoice in invoices" :key="invoice.id">
          <td class="font-mono">{{ invoice.invoice_number }}</td>
          <td>{{ invoice.customer_name }}</td>
          <td>
            <StatusBadge :type="invoice.type" :label="getTypeLabel(invoice.type)" size="sm" />
          </td>
          <td>
            <StatusBadge :type="invoice.status" :label="getStatusLabel(invoice.status)" size="sm" />
          </td>
          <td class="font-bold">{{ formatCurrency(invoice.total_amount) }}</td>
          <td class="text-accent">{{ formatCurrency(invoice.paid_amount) }}</td>
          <td class="text-danger">{{ formatCurrency(invoice.remaining_amount) }}</td>
          <td>{{ formatDate(invoice.created_at) }}</td>
          <td class="actions">
            <NuxtLink :to="`/company/invoices/${invoice.id}`" class="btn-icon" title="عرض">
              👁️
            </NuxtLink>
            <button v-if="invoice.status === 'draft'" @click="$emit('confirm', invoice.id)" class="btn-icon text-accent" title="تأكيد">
              ✅
            </button>
            <button @click="$emit('delete', invoice.id)" class="btn-icon text-danger" title="حذف">
              🗑️
            </button>
          </td>
        </tr>
        <tr v-if="!loading && invoices.length === 0">
          <td colspan="9" class="text-center py-8 text-muted">لا توجد فواتير مطابقة للبحث</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import StatusBadge from './StatusBadge.vue'

defineProps<{
  invoices: any[]
  loading: boolean
}>()

defineEmits(['confirm', 'delete'])

const formatCurrency = (val: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val)
const formatDate = (date?: string) => date ? new Date(date).toLocaleDateString('ar-SA') : '-'

const getTypeLabel = (type: string) => {
  const labels: any = { cash: 'نقدي', credit: 'آجل', return: 'مرتجع', draft: 'مسودة' }
  return labels[type] || type
}

const getStatusLabel = (status: string) => {
  const labels: any = { paid: 'مدفوع', unpaid: 'غير مدفوع', partially_paid: 'مدفوع جزئياً', draft: 'مسودة' }
  return labels[status] || status
}
</script>

<style scoped>
.erp-table-container {
  overflow-x: auto;
  border-radius: 1rem;
  background: white;
}

.erp-table {
  width: 100%;
  border-collapse: collapse;
  text-align: right;
}

.erp-table th {
  background: #f1f5f9;
  padding: 1rem;
  font-weight: 800;
  color: var(--color-secondary);
  border-bottom: 2px solid #e2e8f0;
}

.erp-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.erp-table tr:hover {
  background-color: #f8fafc;
}

.font-mono { font-family: monospace; font-weight: 600; }
.font-bold { font-weight: 700; }
.text-accent { color: var(--color-accent); }
.text-danger { color: #dc2626; }

.actions {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.25rem;
  border-radius: 0.5rem;
  transition: background 0.2s;
}

.btn-icon:hover {
  background: #e2e8f0;
}

.loader {
  color: var(--text-muted);
  font-weight: 600;
}
</style>
