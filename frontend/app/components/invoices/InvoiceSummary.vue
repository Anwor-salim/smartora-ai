<template>
  <div class="inv-summary">
    <!-- Summary Lines -->
    <div class="inv-summary__lines">
      <div class="inv-summary__row">
        <span class="inv-summary__label">المجموع الفرعي</span>
        <span class="inv-summary__value">{{ fmt(subtotal) }}</span>
      </div>
      <div class="inv-summary__row inv-summary__row--discount">
        <span class="inv-summary__label">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          الخصم
        </span>
        <span class="inv-summary__value">- {{ fmt(discount) }}</span>
      </div>
      <div class="inv-summary__row">
        <span class="inv-summary__label">ضريبة القيمة المضافة (15%)</span>
        <span class="inv-summary__value">{{ fmt(tax) }}</span>
      </div>
    </div>

    <!-- Grand Total -->
    <div class="inv-summary__total">
      <div class="inv-summary__total-inner">
        <span class="inv-summary__total-label">المبلغ المستحق</span>
        <span class="inv-summary__total-value">{{ fmt(total) }}</span>
      </div>
      <span class="inv-summary__total-words">{{ totalWords }}</span>
    </div>

    <!-- Balance (if payment exists) -->
    <div v-if="paidAmount !== undefined && paidAmount !== null" class="inv-summary__balance">
      <div class="inv-summary__balance-row inv-summary__balance-row--paid">
        <span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          المدفوع
        </span>
        <span>{{ fmt(paidAmount) }}</span>
      </div>
      <div class="inv-summary__balance-row inv-summary__balance-row--remaining">
        <span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          المتبقي
        </span>
        <span>{{ fmt(total - paidAmount) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  subtotal: number
  discount: number
  tax: number
  total: number
  paidAmount?: number
}>()

const fmt = (val: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val || 0)

const totalWords = computed(() => {
  const t = Math.round(props.total || 0)
  return `فقط ${t.toLocaleString('ar-SA')} ريال سعودي لا غير`
})
</script>

<style scoped>
.inv-summary {
  width: 100%;
}

.inv-summary__lines {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  margin-bottom: 0.75rem;
}

.inv-summary__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.55rem 0;
  font-size: 0.85rem;
}

.inv-summary__label {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  color: #64748b;
  font-weight: 700;
}

.inv-summary__value {
  font-weight: 800;
  color: #334155;
  direction: ltr;
  white-space: nowrap;
}

.inv-summary__row--discount {
  color: #dc2626;
}

.inv-summary__row--discount .inv-summary__label {
  color: #dc2626;
}

.inv-summary__row--discount .inv-summary__value {
  color: #dc2626;
}

/* Grand Total */
.inv-summary__total {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
  border-radius: 12px;
  padding: 1.15rem 1.25rem;
  margin-bottom: 0.75rem;
}

.inv-summary__total-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.inv-summary__total-label {
  color: rgba(255, 255, 255, 0.8);
  font-weight: 700;
  font-size: 0.9rem;
}

.inv-summary__total-value {
  color: white;
  font-weight: 900;
  font-size: 1.55rem;
  direction: ltr;
  letter-spacing: -0.02em;
}

.inv-summary__total-words {
  display: block;
  color: rgba(255, 255, 255, 0.55);
  font-size: 0.68rem;
  font-weight: 700;
  margin-top: 0.3rem;
}

/* Balance */
.inv-summary__balance {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding-top: 0.25rem;
}

.inv-summary__balance-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.45rem 0.75rem;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 800;
}

.inv-summary__balance-row span {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.inv-summary__balance-row--paid {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}

.inv-summary__balance-row--remaining {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

@media print {
  .inv-summary__total {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-summary__balance-row {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
