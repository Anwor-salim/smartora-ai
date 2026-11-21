<template>
  <div class="create-invoice-page py-8">
    <div class="container">
      
      <div class="flex items-center gap-4 mb-8">
        <NuxtLink to="/company/invoices" class="btn-back">←</NuxtLink>
        <h1 class="text-3xl font-extrabold">{{ isReturn ? 'إنشاء فاتورة مرتجع' : 'إنشاء فاتورة جديدة' }}</h1>
      </div>

      <div class="layout-grid">
        <div class="main-content">
          <InvoiceForm :form="form" :customers="customers" />
          
          <InvoiceItemsEditor 
            v-model="form.items" 
            :products="products"
            @change="calculateTotals" 
          />
        </div>

        <div class="sidebar">
          <InvoiceSummaryCard :totals="totals" />
          
          <div class="actions-panel mt-6">
            <button @click="saveInvoice" class="btn-save w-full" :disabled="loading">
              {{ loading ? 'جاري الحفظ...' : 'حفظ وإصدار الفاتورة' }}
            </button>
            <button @click="saveDraft" class="btn-draft w-full mt-2" :disabled="loading">
              حفظ كمسودة
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useInvoiceStore } from '~/stores/invoiceStore'
import { usePharmacies } from '~/composables/usePharmacies'
import { useProducts } from '~/composables/useProducts'
import InvoiceForm from '~/components/invoices/InvoiceForm.vue'
import InvoiceItemsEditor from '~/components/invoices/InvoiceItemsEditor.vue'
import InvoiceSummaryCard from '~/components/invoices/InvoiceSummaryCard.vue'

definePageMeta({
  layout: 'company',
  middleware: 'auth'
})

const router = useRouter()
const route = useRoute()
const store = useInvoiceStore()
const { pharmacies: customers, fetchPharmacies } = usePharmacies()
const { products, fetchCompanyProducts } = useProducts()

const isReturn = computed(() => route.query.type === 'return')
const loading = ref(false)

const form = reactive({
  customer_id: '',
  type: isReturn.value ? 'return' : 'cash',
  notes: '',
  original_invoice_id: route.query.original_id || null,
  items: [] as any[]
})

const totals = reactive({
  subtotal: 0,
  discount: 0,
  tax: 0,
  total: 0
})

onMounted(async () => {
  loading.value = true
  await Promise.all([
    fetchPharmacies(),
    fetchCompanyProducts()
  ])
  loading.value = false
  
  if (isReturn.value && route.query.original_id) {
    loadOriginalInvoice(route.query.original_id as string)
  }
})

const loadOriginalInvoice = async (id: string) => {
    loading.value = true
    try {
        const response = await $fetch<any>(`http://localhost:8000/api/v1/invoices/${id}`, {
            headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        const inv = response.data
        form.customer_id = inv.customer_id
        form.items = inv.items.map((it: any) => ({ 
          product_id: it.product_id,
          product_name: it.product_name,
          quantity: it.quantity,
          unit_price: it.unit_price,
          discount: it.discount || 0,
          tax_percent: it.tax_percent || 15,
          total: it.total
        }))
        calculateTotals()
    } catch (error) {
        console.error('Failed to load original invoice', error)
    } finally {
        loading.value = false
    }
}

const calculateTotals = () => {
  let sub = 0
  let disc = 0
  let tax = 0
  
  form.items.forEach(item => {
    const itemSub = (item.quantity || 0) * (item.unit_price || 0)
    const itemDisc = itemSub * ((item.discount || 0) / 100)
    const itemAfterDisc = itemSub - itemDisc
    const itemTax = itemAfterDisc * ((item.tax_percent || 0) / 100)
    
    sub += itemSub
    disc += itemDisc
    tax += itemTax
  })
  
  totals.subtotal = sub
  totals.discount = disc
  totals.tax = tax
  totals.total = sub - disc + tax
}

const saveInvoice = async () => {
  if (!validateForm()) return
  
  loading.value = true
  try {
    const payload = { 
      ...form, 
      ...totals,
      is_confirmed: form.type !== 'draft'
    }
    
    let result
    if (isReturn.value) {
        result = await store.createReturnInvoice(payload)
    } else {
        result = await store.createInvoice(payload)
    }
    router.push(`/company/invoices/${result.id}`)
  } catch (error: any) {
    console.error('Save error:', error)
    const msg = error.data?.message || 'حدث خطأ أثناء الحفظ. يرجى التأكد من تعبئة جميع الحقول بشكل صحيح.'
    alert(msg)
  } finally {
    loading.value = false
  }
}

const saveDraft = async () => {
  form.type = 'draft'
  await saveInvoice()
}

const validateForm = () => {
  if (!form.customer_id) { alert('يرجى اختيار العميل'); return false; }
  if (form.items.length === 0) { alert('يرجى إضافة صنف واحد على الأقل'); return false; }
  return true
}
</script>

<style scoped>
.layout-grid {
  display: grid;
  grid-template-columns: 1fr 350px;
  gap: 2rem;
}

@media (max-width: 1024px) {
  .layout-grid { grid-template-columns: 1fr; }
}

.btn-back {
  background: white;
  border: 1px solid #e2e8f0;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 1.5rem;
  text-decoration: none;
  color: var(--color-secondary);
}

.btn-save {
  background: var(--color-primary);
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 0.75rem;
  font-weight: 800;
  cursor: pointer;
}

.btn-draft {
  background: white;
  border: 1px solid var(--color-primary);
  color: var(--color-primary);
  padding: 0.75rem;
  border-radius: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.w-full { width: 100%; }
.mt-2 { margin-top: 0.5rem; }
.mt-6 { margin-top: 1.5rem; }
</style>
