<template>
  <div class="inv-table">
    <table>
      <thead>
        <tr>
          <th class="inv-table__th inv-table__th--num">#</th>
          <th class="inv-table__th inv-table__th--product">الصنف / المنتج</th>
          <th class="inv-table__th inv-table__th--center">الكمية</th>
          <th class="inv-table__th inv-table__th--center">سعر الوحدة</th>
          <th class="inv-table__th inv-table__th--center">الخصم</th>
          <th class="inv-table__th inv-table__th--center">الضريبة</th>
          <th class="inv-table__th inv-table__th--total">الإجمالي</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, idx) in items" :key="idx" class="inv-table__row">
          <td class="inv-table__td inv-table__td--num">{{ idx + 1 }}</td>
          <td class="inv-table__td inv-table__td--product">{{ item.product_name }}</td>
          <td class="inv-table__td inv-table__td--center">{{ item.quantity }}</td>
          <td class="inv-table__td inv-table__td--center">{{ fmt(item.unit_price) }}</td>
          <td class="inv-table__td inv-table__td--center inv-table__td--discount">
            <span v-if="item.discount > 0">{{ item.discount }}%</span>
            <span v-else class="inv-table__td--zero">—</span>
          </td>
          <td class="inv-table__td inv-table__td--center inv-table__td--tax">{{ item.tax_percent }}%</td>
          <td class="inv-table__td inv-table__td--total">{{ fmt(item.total) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="inv-table__count-row">
          <td colspan="2" class="inv-table__td">
            <span class="inv-table__count">إجمالي الأصناف: <strong>{{ items.length }}</strong></span>
          </td>
          <td class="inv-table__td inv-table__td--center">
            <strong>{{ totalQty }}</strong>
          </td>
          <td colspan="3"></td>
          <td class="inv-table__td inv-table__td--total">
            <strong>{{ fmt(grandTotal) }}</strong>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  items: any[]
}>()

const fmt = (val: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val || 0)

const totalQty = computed(() => props.items.reduce((s, i) => s + (i.quantity || 0), 0))
const grandTotal = computed(() => props.items.reduce((s, i) => s + (i.total || 0), 0))
</script>

<style scoped>
.inv-table {
  margin-bottom: 2rem;
  overflow-x: auto;
}

.inv-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

/* Head */
.inv-table__th {
  padding: 0.85rem 1rem;
  font-weight: 800;
  font-size: 0.72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: var(--color-primary);
  color: white;
  white-space: nowrap;
}

.inv-table__th:first-child { border-radius: 0 8px 0 0; }
.inv-table__th:last-child { border-radius: 8px 0 0 0; }
.inv-table__th--num { width: 3rem; text-align: center; }
.inv-table__th--product { text-align: right; }
.inv-table__th--center { text-align: center; }
.inv-table__th--total { text-align: left; }

/* Body */
.inv-table__row {
  transition: background 0.15s;
}

.inv-table__row:nth-child(even) {
  background: #f8fafc;
}

.inv-table__row:hover {
  background: #f1f5f9;
}

.inv-table__td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.inv-table__td--num {
  text-align: center;
  color: #94a3b8;
  font-weight: 700;
  font-size: 0.78rem;
}

.inv-table__td--product {
  font-weight: 700;
  color: var(--color-secondary);
}

.inv-table__td--center {
  text-align: center;
  font-weight: 600;
  color: #475569;
}

.inv-table__td--discount span {
  color: #dc2626;
  font-weight: 700;
}

.inv-table__td--zero {
  color: #cbd5e1 !important;
}

.inv-table__td--tax {
  color: #64748b !important;
  font-weight: 600 !important;
}

.inv-table__td--total {
  text-align: left;
  font-weight: 800;
  color: var(--color-secondary);
  white-space: nowrap;
  direction: ltr;
}

/* Footer */
.inv-table__count-row {
  background: #f8fafc;
  border-top: 2px solid #e2e8f0;
}

.inv-table__count-row .inv-table__td {
  padding: 0.65rem 1rem;
  border-bottom: none;
}

.inv-table__count {
  font-size: 0.78rem;
  color: #64748b;
  font-weight: 700;
}

.inv-table__count strong {
  color: var(--color-primary);
}

@media print {
  .inv-table__th {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-table__row:nth-child(even) {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-table__count-row {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .inv-table__row:hover {
    background: inherit;
  }
}
</style>
