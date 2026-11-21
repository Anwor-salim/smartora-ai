<template>
  <div class="pharmacy-invoice-page min-h-screen py-8 bg-slate-50 print:bg-white print:py-0">
    <div class="container mx-auto" v-if="invoice">
      
      <!-- Actions Section (Hidden on Print) -->
      <div class="no-print flex justify-between items-center mb-8 px-4 max-w-4xl mx-auto">
        <div class="flex items-center gap-4">
          <NuxtLink to="/pharmacy/orders" class="btn-back">←</NuxtLink>
          <h1 class="text-2xl font-black text-slate-800">فاتورة طلب #{{ invoice.id }}</h1>
        </div>
        <div class="flex gap-3">
          <button @click="printInvoice" class="btn-action bg-slate-900 text-white">
            <span>🖨️</span> طباعة / تصدير PDF
          </button>
        </div>
      </div>

      <!-- Professional Invoice Document -->
      <div class="invoice-document bg-white shadow-2xl rounded-3xl p-12 max-w-4xl mx-auto print:shadow-none print:rounded-none print:p-0 print:max-w-none">
        
        <!-- Header -->
        <div class="inv-header flex justify-between items-start border-b pb-8 mb-8">
          <div class="company-brand">
            <template v-if="profile.logoBase64">
              <img :src="profile.logoBase64" alt="Logo" class="h-16 mb-4" />
            </template>
            <h2 class="text-2xl font-black text-primary">{{ invoice.companyName }}</h2>
            <p class="text-slate-500 font-bold">المورد المعتمد</p>
          </div>
          
          <div class="invoice-meta text-left">
            <h1 class="text-4xl font-black text-slate-100 uppercase mb-4 tracking-tighter">TAX INVOICE</h1>
            <div class="meta-details space-y-1">
              <div class="flex justify-between gap-8">
                <span class="text-slate-400 font-bold">رقم الفاتورة:</span>
                <span class="font-mono font-bold">#{{ invoice.id }}</span>
              </div>
              <div class="flex justify-between gap-8">
                <span class="text-slate-400 font-bold">التاريخ:</span>
                <span class="font-bold">{{ formatDate(invoice.date) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Customer & Order Info -->
        <div class="grid grid-cols-2 gap-12 mb-12">
          <div class="bill-to border-r pr-8">
            <h4 class="section-label">فاتورة إلى:</h4>
            <p class="text-xl font-bold text-slate-900">{{ invoice.pharmacyName }}</p>
            <p class="text-slate-500 leading-relaxed">{{ invoice.pharmacyLocation }}</p>
          </div>
          <div class="order-ref pr-8">
            <h4 class="section-label">تفاصيل الطلب:</h4>
            <div class="flex justify-between max-w-xs">
              <span class="text-slate-400 font-bold text-sm">حالة الطلب:</span>
              <span class="badge-status">{{ invoice.status === 'delivered' ? 'مكتمل' : invoice.status }}</span>
            </div>
            <div class="flex justify-between max-w-xs mt-2">
              <span class="text-slate-400 font-bold text-sm">طريقة الدفع:</span>
              <span class="font-bold text-slate-700">دفع عند الاستلام</span>
            </div>
          </div>
        </div>

        <!-- Items Table -->
        <div class="items-table-wrapper mb-12">
          <table class="w-full text-right inv-table">
            <thead>
              <tr>
                <th class="w-12">#</th>
                <th>الصنف / المنتج</th>
                <th class="text-center">الكمية</th>
                <th class="text-center">سعر الوحدة</th>
                <th class="text-left">الإجمالي</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in invoice.orderItems" :key="idx">
                <td class="text-slate-400 font-bold">{{ idx + 1 }}</td>
                <td class="font-bold text-slate-800">{{ item.product_name || 'صنف غير محدد' }}</td>
                <td class="text-center">{{ item.quantity }}</td>
                <td class="text-center">{{ formatCurrency(item.unit_price) }}</td>
                <td class="text-left font-black text-slate-900">{{ formatCurrency(item.total_price || (item.quantity * item.unit_price)) }}</td>
              </tr>
              <!-- If no items but has order data, show a summary row -->
              <tr v-if="!invoice.orderItems || invoice.orderItems.length === 0">
                <td class="text-slate-400 font-bold">1</td>
                <td class="font-bold text-slate-800">أدوية ومستلزمات طبية (الطلبية #{{ invoice.id }})</td>
                <td class="text-center">{{ invoice.items }}</td>
                <td class="text-center">-</td>
                <td class="text-left font-black text-slate-900">{{ formatCurrency(invoice.total / 1.15) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="flex justify-end">
          <div class="w-full max-w-xs space-y-3">
            <div class="flex justify-between">
              <span class="text-slate-500 font-bold">المجموع الفرعي:</span>
              <span class="font-bold">{{ formatCurrency(invoice.subTotal) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500 font-bold">ضريبة القيمة المضافة (15%):</span>
              <span class="font-bold">{{ formatCurrency(invoice.tax) }}</span>
            </div>
            <div class="flex justify-between items-center pt-4 border-t-2 border-slate-900 mt-4">
              <span class="text-xl font-black text-slate-900">الإجمالي:</span>
              <span class="text-3xl font-black text-primary">{{ formatCurrency(invoice.total) }}</span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="mt-16 pt-12 border-t text-center">
          <p class="text-slate-400 text-sm">أُصدرت هذه الفاتورة إلكترونياً وهي معتمدة للطلبية رقم #{{ invoice.id }}</p>
          <div class="thank-you mt-8">
            <p class="text-xl font-bold text-slate-300">نشكركم لتعاملكم معنا</p>
          </div>
        </div>
        
      </div>
    </div>

    <div v-else class="text-center py-20">
      <div class="loader-erp">جاري جلب تفاصيل الفاتورة...</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useOrders } from '~/composables/useOrders'
import { useCompanyProfile } from '~/composables/useCompanyProfile'

definePageMeta({
  layout: 'pharmacy',
  middleware: 'auth'
})

const route = useRoute()
const { orders, fetchPharmacyOrders, mapOrderData } = useOrders()
const { profile } = useCompanyProfile()

onMounted(() => {
  fetchPharmacyOrders()
})

const invoice = computed(() => {
  const rawOrder = orders.value.find(o => String(o.id) === String(route.params.id))
  return rawOrder ? mapOrderData(rawOrder) : null
})

const formatDate = (date: string) => new Date(date).toLocaleDateString('ar-SA')
const formatCurrency = (val: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val || 0)

const printInvoice = () => {
  window.print()
}
</script>

<style scoped>
.pharmacy-invoice-page { min-height: 100vh; }
.text-primary { color: var(--color-primary); }

.invoice-document {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
}

.section-label {
  font-size: 0.75rem;
  font-weight: 900;
  color: #94a3b8;
  text-transform: uppercase;
  margin-bottom: 0.75rem;
  letter-spacing: 0.05em;
}

.inv-table th {
  padding: 1rem;
  font-size: 0.85rem;
  font-weight: 900;
  color: #64748b;
  border-bottom: 2px solid #e2e8f0;
}

.inv-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.btn-action {
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    border: none;
}

.btn-back {
    background: white;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 1.25rem;
    color: #1e293b;
    text-decoration: none;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.badge-status {
    background: #dcfce7;
    color: #166534;
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 800;
}

@media print {
  @page { size: A4; margin: 0; }
  .no-print { display: none !important; }
  .invoice-document { box-shadow: none !important; padding: 1.5cm !important; border-radius: 0 !important; }
}
</style>
