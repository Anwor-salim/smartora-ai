<template>
  <div class="inv-header">
    <!-- Top Decorative Line -->
    <div class="inv-header__accent"></div>

    <div class="inv-header__content">
      <!-- Company Branding (Right) -->
      <div class="inv-header__brand">
        <div v-if="profile.logoBase64" class="inv-header__logo">
          <img :src="profile.logoBase64" alt="Logo" />
        </div>
        <div v-else class="inv-header__logo-placeholder">
          <span>{{ profile.name?.charAt(0) }}</span>
        </div>
        <div class="inv-header__company">
          <h2 class="inv-header__company-name">{{ profile.name }}</h2>
          <div class="inv-header__company-details">
            <span>{{ profile.location }}</span>
            <span class="inv-header__separator">|</span>
            <span>ض.ر: {{ profile.taxNumber }}</span>
          </div>
        </div>
      </div>

      <!-- Invoice Identity (Left) -->
      <div class="inv-header__identity">
        <div class="inv-header__type-label">فاتورة ضريبية</div>
        <div class="inv-header__type-en">TAX INVOICE</div>
        
        <div class="inv-header__meta">
          <div class="inv-header__meta-row">
            <span class="inv-header__meta-label">رقم الفاتورة</span>
            <span class="inv-header__meta-value inv-header__meta-value--mono">{{ invoiceNumber }}</span>
          </div>
          <div class="inv-header__meta-row">
            <span class="inv-header__meta-label">تاريخ الإصدار</span>
            <span class="inv-header__meta-value">{{ date }}</span>
          </div>
          <div class="inv-header__meta-row">
            <span class="inv-header__meta-label">حالة الفاتورة</span>
            <span :class="['inv-header__status', `inv-header__status--${status}`]">
              {{ statusLabel }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  profile: any
  invoiceNumber: string
  date: string
  status: string
}>()

const statusLabel = computed(() => {
  const map: Record<string, string> = {
    paid: 'مدفوعة',
    unpaid: 'غير مدفوعة',
    partially_paid: 'مدفوعة جزئياً',
    draft: 'مسودة',
    returned: 'مرتجعة'
  }
  return map[props.status] || props.status
})
</script>

<style scoped>
.inv-header {
  margin-bottom: 2.5rem;
}

.inv-header__accent {
  height: 4px;
  background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-primary-light) 50%, var(--color-accent) 100%);
  border-radius: 2px;
  margin-bottom: 2rem;
}

.inv-header__content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.inv-header__brand {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.inv-header__logo img {
  width: 64px;
  height: 64px;
  object-fit: contain;
  border-radius: 12px;
}

.inv-header__logo-placeholder {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.75rem;
  font-weight: 900;
  flex-shrink: 0;
}

.inv-header__company-name {
  font-size: 1.4rem;
  font-weight: 900;
  color: var(--color-secondary);
  margin-bottom: 0.15rem;
  line-height: 1.3;
}

.inv-header__company-details {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.inv-header__separator {
  color: #cbd5e1;
}

.inv-header__identity {
  text-align: left;
  flex-shrink: 0;
}

.inv-header__type-label {
  font-size: 1.5rem;
  font-weight: 900;
  color: var(--color-primary);
  line-height: 1.2;
}

.inv-header__type-en {
  font-size: 0.65rem;
  letter-spacing: 0.25em;
  color: #cbd5e1;
  font-weight: 800;
  text-transform: uppercase;
  margin-bottom: 1rem;
}

.inv-header__meta {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.inv-header__meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  font-size: 0.82rem;
}

.inv-header__meta-label {
  color: #94a3b8;
  font-weight: 700;
}

.inv-header__meta-value {
  color: var(--color-secondary);
  font-weight: 800;
}

.inv-header__meta-value--mono {
  font-family: 'Courier New', monospace;
  letter-spacing: 0.03em;
  direction: ltr;
}

.inv-header__status {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.65rem;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 800;
}

.inv-header__status--paid {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}

.inv-header__status--unpaid {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.inv-header__status--partially_paid {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
}

.inv-header__status--draft {
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.inv-header__status--returned {
  background: #f5f3ff;
  color: #6d28d9;
  border: 1px solid #ddd6fe;
}

@media print {
  .inv-header__accent {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-header__logo-placeholder {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-header__status {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
