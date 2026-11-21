<template>
  <div class="summary-card glass-panel p-6">
    <h3 class="text-xl font-bold mb-6 border-b pb-2">ملخص الحسابات</h3>
    
    <div class="summary-rows">
      <div class="summary-row">
        <span>المجموع الفرعي:</span>
        <span class="val">{{ formatCurrency(totals.subtotal) }}</span>
      </div>
      <div class="summary-row">
        <span>إجمالي الخصم:</span>
        <span class="val text-danger">- {{ formatCurrency(totals.discount) }}</span>
      </div>
      <div class="summary-row">
        <span>إجمالي الضريبة (15%):</span>
        <span class="val">{{ formatCurrency(totals.tax) }}</span>
      </div>
      
      <div class="summary-row total-grand">
        <span>المبلغ الإجمالي النهائي:</span>
        <span class="val">{{ formatCurrency(totals.total) }}</span>
      </div>

      <template v-if="showBalance">
        <div class="summary-row paid-row">
          <span>المبلغ المدفوع:</span>
          <span class="val text-accent">{{ formatCurrency(paidAmount || 0) }}</span>
        </div>
        <div class="summary-row balance-row">
          <span>المبلغ المتبقي (الرصيد):</span>
          <span class="val">{{ formatCurrency((totals.total - (paidAmount || 0))) }}</span>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  totals: {
    subtotal: number
    discount: number
    tax: number
    total: number
  }
  showBalance?: boolean
  paidAmount?: number
}>()

const formatCurrency = (val: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val)
</script>

<style scoped>
.summary-card {
  height: 100%;
}

.summary-rows {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  font-weight: 600;
  color: var(--text-muted);
}

.summary-row .val {
  color: var(--color-secondary);
}

.text-danger { color: #dc2626; }
.text-accent { color: var(--color-accent); }

.total-grand {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 2px solid #e2e8f0;
  font-size: 1.25rem;
  font-weight: 900;
  color: var(--color-primary-dark) !important;
}

.total-grand .val {
  color: var(--color-primary-dark) !important;
}

.balance-row {
  font-size: 1.1rem;
  font-weight: 800;
  color: #1e293b;
}

.p-6 { padding: 1.5rem; }
.mb-6 { margin-bottom: 1.5rem; }
.pb-2 { padding-bottom: 0.5rem; }
.border-b { border-bottom: 1px solid #e2e8f0; }
</style>
