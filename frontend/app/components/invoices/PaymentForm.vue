<template>
  <div class="payment-form glass-panel p-6">
    <h3 class="text-xl font-bold mb-4">إضافة دفعة مالية</h3>
    
    <form @submit.prevent="$emit('submit', payment)">
      <div class="grid-cols-2 gap-4">
        <div class="form-group">
          <label>المبلغ</label>
          <input 
            v-model.number="payment.amount" 
            type="number" 
            step="0.01" 
            class="form-input" 
            required 
            :max="maxAmount"
          />
        </div>

        <div class="form-group">
          <label>طريقة الدفع</label>
          <select v-model="payment.method" class="form-select" required>
            <option value="cash">نقدي</option>
            <option value="card">بطاقة صراف</option>
            <option value="bank">تحويل بنكي</option>
          </select>
        </div>
      </div>

      <div class="form-group mt-4">
        <label>رقم المرجع (اختياري)</label>
        <input 
          v-model="payment.reference_number" 
          type="text" 
          class="form-input" 
          placeholder="رقم العملية أو الشيك..."
        />
      </div>

      <button type="submit" class="btn-accent w-full mt-6" :disabled="loading">
        {{ loading ? 'جاري الحفظ...' : 'تسجيل الدفعة' }}
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'

const props = defineProps<{
  maxAmount: number
  loading?: boolean
}>()

defineEmits(['submit'])

const payment = reactive({
  amount: props.maxAmount,
  method: 'cash',
  reference_number: ''
})
</script>

<style scoped>
.grid-cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

.form-group label {
  display: block;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.form-input, .form-select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.75rem;
}

.btn-accent {
  background: var(--color-accent);
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 0.75rem;
  font-weight: 800;
  cursor: pointer;
  transition: opacity 0.2s;
}

.btn-accent:disabled { opacity: 0.5; cursor: not-allowed; }

.w-full { width: 100%; }
.mt-4 { margin-top: 1rem; }
.mt-6 { margin-top: 1.5rem; }
.p-6 { padding: 1.5rem; }
.mb-4 { margin-bottom: 1rem; }
</style>
