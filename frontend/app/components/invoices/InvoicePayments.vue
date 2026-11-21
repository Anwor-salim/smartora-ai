<template>
  <div v-if="payments && payments.length > 0" class="inv-payments">
    <div class="inv-payments__header">
      <div class="inv-payments__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      </div>
      <h4 class="inv-payments__title">سجل المدفوعات</h4>
      <span class="inv-payments__count">{{ payments.length }} دفعة</span>
    </div>

    <div class="inv-payments__list">
      <div v-for="(pay, idx) in payments" :key="pay.id || idx" class="inv-payments__item">
        <div class="inv-payments__item-leading">
          <div class="inv-payments__item-index">{{ idx + 1 }}</div>
          <div class="inv-payments__item-info">
            <span class="inv-payments__item-amount">{{ fmt(pay.amount) }}</span>
            <span class="inv-payments__item-method">{{ methodLabel(pay.method) }}</span>
          </div>
        </div>
        <div class="inv-payments__item-trailing">
          <span class="inv-payments__item-date">{{ fmtDate(pay.created_at) }}</span>
          <span v-if="pay.reference_number" class="inv-payments__item-ref">REF: {{ pay.reference_number }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  payments: any[]
}>()

const fmt = (val: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val || 0)
const fmtDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'

const methodLabel = (m: string) => {
  const map: Record<string, string> = { cash: 'نقدي', card: 'بطاقة', bank: 'تحويل بنكي' }
  return map[m] || m
}
</script>

<style scoped>
.inv-payments {
  margin-top: 2rem;
  border-top: 1px solid #e2e8f0;
  padding-top: 1.5rem;
}

.inv-payments__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.inv-payments__icon {
  width: 28px;
  height: 28px;
  background: #dbeafe;
  color: #2563eb;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.inv-payments__title {
  font-size: 0.82rem;
  font-weight: 800;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.inv-payments__count {
  margin-right: auto;
  font-size: 0.7rem;
  font-weight: 700;
  color: #94a3b8;
  background: #f1f5f9;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
}

.inv-payments__list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.inv-payments__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  transition: background 0.15s;
}

.inv-payments__item:hover {
  background: #f1f5f9;
}

.inv-payments__item-leading {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.inv-payments__item-index {
  width: 24px;
  height: 24px;
  background: var(--color-primary);
  color: white;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 800;
  flex-shrink: 0;
}

.inv-payments__item-info {
  display: flex;
  flex-direction: column;
}

.inv-payments__item-amount {
  font-weight: 800;
  color: #047857;
  font-size: 0.9rem;
  direction: ltr;
  text-align: right;
}

.inv-payments__item-method {
  font-size: 0.7rem;
  color: #94a3b8;
  font-weight: 700;
}

.inv-payments__item-trailing {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  text-align: left;
}

.inv-payments__item-date {
  font-size: 0.78rem;
  color: #64748b;
  font-weight: 700;
}

.inv-payments__item-ref {
  font-size: 0.65rem;
  color: #94a3b8;
  font-family: 'Courier New', monospace;
  direction: ltr;
}

@media print {
  .inv-payments__item-index {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-payments__icon {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
