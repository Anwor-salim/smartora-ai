<template>
  <div class="inv-parties">
    <div class="inv-parties__grid">
      <!-- Bill To -->
      <div class="inv-parties__card">
        <div class="inv-parties__card-header">
          <div class="inv-parties__icon inv-parties__icon--customer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <span class="inv-parties__card-label">فاتورة إلى</span>
        </div>
        <h3 class="inv-parties__name">{{ customerName }}</h3>
        <div class="inv-parties__details">
          <div v-if="address" class="inv-parties__detail-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>{{ address }}</span>
          </div>
          <div v-if="phone" class="inv-parties__detail-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <span dir="ltr">{{ phone }}</span>
          </div>
        </div>
      </div>

      <!-- Order Reference -->
      <div class="inv-parties__card">
        <div class="inv-parties__card-header">
          <div class="inv-parties__icon inv-parties__icon--order">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <span class="inv-parties__card-label">مرجع الطلب</span>
        </div>
        <div class="inv-parties__ref-grid">
          <div class="inv-parties__ref-item">
            <span class="inv-parties__ref-key">رقم الطلب</span>
            <span class="inv-parties__ref-val">{{ orderNumber ? '#' + orderNumber : '—' }}</span>
          </div>
          <div class="inv-parties__ref-item">
            <span class="inv-parties__ref-key">تاريخ الطلب</span>
            <span class="inv-parties__ref-val">{{ orderDate || '—' }}</span>
          </div>
          <div class="inv-parties__ref-item" v-if="paymentType">
            <span class="inv-parties__ref-key">طريقة الدفع</span>
            <span class="inv-parties__ref-val">{{ paymentType === 'cash' ? 'نقدي' : paymentType === 'credit' ? 'آجل' : paymentType }}</span>
          </div>
        </div>
        <div v-if="orderNumber" class="inv-parties__order-badge">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          تم إصدارها من طلب نظام
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  customerName: string
  address?: string
  phone?: string
  orderNumber?: string
  orderDate?: string
  paymentType?: string
}>()
</script>

<style scoped>
.inv-parties {
  margin-bottom: 2.5rem;
}

.inv-parties__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.inv-parties__card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
}

.inv-parties__card-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.inv-parties__icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.inv-parties__icon--customer {
  background: #dbeafe;
  color: #2563eb;
}

.inv-parties__icon--order {
  background: #ede9fe;
  color: #7c3aed;
}

.inv-parties__card-label {
  font-size: 0.7rem;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.inv-parties__name {
  font-size: 1.15rem;
  font-weight: 900;
  color: var(--color-secondary);
  margin-bottom: 0.5rem;
}

.inv-parties__details {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.inv-parties__detail-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.82rem;
  color: #64748b;
  font-weight: 600;
}

.inv-parties__detail-row svg {
  color: #94a3b8;
  flex-shrink: 0;
}

.inv-parties__ref-grid {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.inv-parties__ref-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.82rem;
}

.inv-parties__ref-key {
  color: #94a3b8;
  font-weight: 700;
}

.inv-parties__ref-val {
  color: var(--color-secondary);
  font-weight: 800;
}

.inv-parties__order-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin-top: 0.75rem;
  padding: 0.25rem 0.6rem;
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 800;
}

@media (max-width: 640px) {
  .inv-parties__grid {
    grid-template-columns: 1fr;
  }
}

@media print {
  .inv-parties__card {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-parties__icon {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-parties__order-badge {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
