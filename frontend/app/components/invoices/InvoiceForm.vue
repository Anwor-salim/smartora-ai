<template>
  <div class="invoice-form glass-panel p-6">
    <div class="grid-cols-2">
      <div class="form-group">
        <label>العميل (الصيدلية)</label>
        <select v-model="form.customer_id" class="form-select" required>
          <option value="" disabled>اختر العميل</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div class="form-group">
        <label>نوع الفاتورة</label>
        <div class="radio-group">
          <label class="radio-label">
            <input type="radio" v-model="form.type" value="cash" /> نقدي
          </label>
          <label class="radio-label">
            <input type="radio" v-model="form.type" value="credit" /> آجل
          </label>
          <label class="radio-label">
            <input type="radio" v-model="form.type" value="draft" /> مسودة
          </label>
        </div>
      </div>
    </div>

    <div class="form-group mt-4">
      <label>ملاحظات</label>
      <textarea v-model="form.notes" class="form-textarea" rows="3" placeholder="أضف أي ملاحظات إضافية هنا..."></textarea>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  form: any
  customers: any[]
}>()
</script>

<style scoped>
.grid-cols-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

@media (max-width: 768px) {
  .grid-cols-2 { grid-template-columns: 1fr; }
}

.form-group label {
  display: block;
  font-weight: 700;
  margin-bottom: 0.5rem;
  color: var(--color-secondary);
}

.form-select, .form-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.75rem;
  background: white;
  font-family: inherit;
}

.radio-group {
  display: flex;
  gap: 1.5rem;
  padding: 0.5rem 0;
}

.radio-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-weight: 600;
}

.p-6 { padding: 1.5rem; }
.mt-4 { margin-top: 1rem; }
</style>
