<template>
  <div class="inv-page">
    <div v-if="invoice" class="inv-page__wrapper">

      <!-- ─── Action Bar (Hidden on Print) ─── -->
      <div class="inv-page__actions no-print">
        <div class="inv-page__actions-right">
          <NuxtLink to="/company/invoices" class="inv-btn inv-btn--back">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </NuxtLink>
          <div>
            <h1 class="inv-page__title">معاينة الفاتورة</h1>
            <p class="inv-page__subtitle">#{{ invoice.invoice_number }}</p>
          </div>
        </div>
        <div class="inv-page__actions-left">
          <button @click="printInvoice" class="inv-btn inv-btn--print">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            طباعة / PDF
          </button>
          <button @click="shareWhatsApp" class="inv-btn inv-btn--whatsapp">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            واتساب
          </button>
        </div>
      </div>

      <!-- ─── Invoice Document ─── -->
      <div class="inv-document" id="invoice-print-area">
        
        <InvoiceHeader 
          :profile="profile" 
          :invoiceNumber="invoice.invoice_number" 
          :date="formatDate(invoice.created_at)"
          :status="invoice.status"
        />

        <InvoiceCustomerInfo 
          :customerName="invoice.customer_name"
          :address="invoice.customer_address"
          :phone="invoice.customer_phone"
          :orderNumber="invoice.order_reference_number"
          :orderDate="formatDate(invoice.order_date)"
          :paymentType="invoice.type"
        />

        <InvoiceItemsTable :items="invoice.items || []" />

        <!-- Summary + Payments Side by Side -->
        <div class="inv-document__bottom">
          <div class="inv-document__bottom-payments">
            <InvoicePayments :payments="invoice.payments || []" />
          </div>
          <div class="inv-document__bottom-summary">
            <InvoiceSummary 
              :subtotal="invoice.subtotal || 0"
              :discount="invoice.discount_total || 0"
              :tax="invoice.tax_total || 0"
              :total="invoice.total_amount || 0"
              :paidAmount="invoice.paid_amount"
            />
          </div>
        </div>

        <InvoiceFooter :notes="invoice.notes" />
      </div>

      <!-- ─── Payment Form (Hidden on Print) ─── -->
      <div v-if="invoice.remaining_amount > 0" class="inv-page__payment-form no-print">
        <PaymentForm 
          :maxAmount="invoice.remaining_amount" 
          :loading="paymentLoading"
          @submit="submitPayment" 
        />
      </div>

    </div>

    <!-- Loading State -->
    <div v-else class="inv-page__loading">
      <div class="inv-page__loading-spinner"></div>
      <p>جاري تحميل الفاتورة...</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useInvoiceStore } from '~/stores/invoiceStore'
import { useCompanyProfile } from '~/composables/useCompanyProfile'

import InvoiceHeader from '~/components/invoices/InvoiceHeader.vue'
import InvoiceCustomerInfo from '~/components/invoices/InvoiceCustomerInfo.vue'
import InvoiceItemsTable from '~/components/invoices/InvoiceItemsTable.vue'
import InvoiceSummary from '~/components/invoices/InvoiceSummary.vue'
import InvoicePayments from '~/components/invoices/InvoicePayments.vue'
import InvoiceFooter from '~/components/invoices/InvoiceFooter.vue'
import PaymentForm from '~/components/invoices/PaymentForm.vue'

definePageMeta({
  layout: 'company',
  middleware: 'auth'
})

const route = useRoute()
const store = useInvoiceStore()
const { profile } = useCompanyProfile()
const paymentLoading = ref(false)

const invoice = computed(() => store.currentInvoice)

onMounted(() => {
  store.fetchInvoiceById(route.params.id as string)
})

const formatDate = (date?: string) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const submitPayment = async (paymentData: any) => {
  paymentLoading.value = true
  try {
    await store.addPayment(Number(route.params.id), paymentData)
  } catch (error) {
    alert('فشل تسجيل الدفعة')
  } finally {
    paymentLoading.value = false
  }
}

const printInvoice = () => window.print()

const shareWhatsApp = () => {
  if (!invoice.value) return
  const inv = invoice.value
  const text = [
    `📋 *فاتورة ضريبية*`,
    `━━━━━━━━━━━━━━━━`,
    `🏷️ *رقم:* ${inv.invoice_number}`,
    `👤 *العميل:* ${inv.customer_name}`,
    `🏢 *من:* ${profile.value.name}`,
    `💰 *المبلغ:* ${inv.total_amount} ر.س`,
    `📌 *الحالة:* ${inv.status === 'paid' ? '✅ مدفوعة' : '⏳ بانتظار السداد'}`,
    `━━━━━━━━━━━━━━━━`,
    `🔗 ${window.location.href}`
  ].join('%0A')
  window.open(`https://wa.me/?text=${text}`, '_blank')
}
</script>

<style>
/* ─── Page Container ─── */
.inv-page {
  min-height: 100vh;
  background: #eef2f7;
  padding: 2rem 1rem;
}

.inv-page__wrapper {
  max-width: 900px;
  margin: 0 auto;
}

/* ─── Action Bar ─── */
.inv-page__actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.inv-page__actions-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.inv-page__actions-left {
  display: flex;
  gap: 0.6rem;
}

.inv-page__title {
  font-size: 1.25rem;
  font-weight: 900;
  color: var(--color-secondary);
  line-height: 1.2;
}

.inv-page__subtitle {
  font-size: 0.78rem;
  color: #94a3b8;
  font-weight: 700;
  font-family: 'Courier New', monospace;
  direction: ltr;
  text-align: right;
}

/* ─── Buttons ─── */
.inv-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.6rem 1.15rem;
  border-radius: 10px;
  font-weight: 800;
  font-size: 0.82rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-family: inherit;
}

.inv-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.inv-btn--back {
  width: 38px;
  height: 38px;
  padding: 0;
  justify-content: center;
  background: white;
  color: #334155;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.inv-btn--print {
  background: var(--color-secondary);
  color: white;
}

.inv-btn--whatsapp {
  background: #25d366;
  color: white;
}

/* ─── Invoice Document ─── */
.inv-document {
  background: white;
  border-radius: 16px;
  padding: 3rem;
  box-shadow: 
    0 1px 3px rgba(0,0,0,0.04),
    0 6px 24px rgba(0,0,0,0.06);
}

.inv-document__bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
  margin-top: 1.5rem;
}

.inv-document__bottom-payments {
  flex: 1;
  min-width: 0;
}

.inv-document__bottom-summary {
  width: 340px;
  flex-shrink: 0;
}

/* ─── Payment Form Section ─── */
.inv-page__payment-form {
  max-width: 380px;
  margin: 1.5rem auto 0 0;
}

/* ─── Loading ─── */
.inv-page__loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  gap: 1rem;
  color: #94a3b8;
  font-weight: 800;
}

.inv-page__loading-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #e2e8f0;
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: inv-spin 0.7s linear infinite;
}

@keyframes inv-spin {
  to { transform: rotate(360deg); }
}

/* ─── Responsive ─── */
@media (max-width: 768px) {
  .inv-document {
    padding: 1.5rem;
    border-radius: 12px;
  }

  .inv-document__bottom {
    flex-direction: column-reverse;
  }

  .inv-document__bottom-summary {
    width: 100%;
  }

  .inv-page__actions {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .inv-page__actions-left {
    justify-content: stretch;
  }

  .inv-page__actions-left .inv-btn {
    flex: 1;
    justify-content: center;
  }
}

/* ═══════════════════════════════════ */
/*           PRINT STYLES             */
/* ═══════════════════════════════════ */
@media print {
  @page {
    size: A4 portrait;
    margin: 12mm 15mm;
  }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  html, body {
    background: white !important;
    color: black !important;
    font-size: 11pt !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Hide everything except the invoice */
  .no-print,
  header, nav, aside, footer,
  .sidebar, .nav-bar, .layout-sidebar,
  .inv-page__payment-form {
    display: none !important;
  }

  .inv-page {
    background: white !important;
    padding: 0 !important;
    min-height: auto !important;
  }

  .inv-page__wrapper {
    max-width: 100% !important;
    margin: 0 !important;
  }

  .inv-document {
    box-shadow: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  /* Prevent page breaks inside elements */
  .inv-table, .inv-summary, .inv-parties, .inv-footer {
    page-break-inside: avoid;
  }

  /* Ensure table header repeats on multi-page */
  thead {
    display: table-header-group;
  }
}
</style>
