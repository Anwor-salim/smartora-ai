<template>
  <div class="items-editor glass-panel p-6 mt-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-bold">الأصناف / المنتجات</h3>
      <button @click="addItem" class="btn-primary-sm">
        <span>+</span> إضافة صنف
      </button>
    </div>

    <div class="table-responsive">
      <table class="items-table">
        <thead>
          <tr>
            <th style="width: 35%">المنتج</th>
            <th>الكمية</th>
            <th>السعر</th>
            <th>الخصم (%)</th>
            <th>الضريبة (%)</th>
            <th>الإجمالي</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in items" :key="index">
            <td>
              <select 
                v-model="item.product_id" 
                class="form-select"
                @change="onProductSelect(item)"
                required
              >
                <option :value="null" disabled>اختر المنتج...</option>
                <option v-for="p in products" :key="p.id" :value="p.id">
                  {{ p.name }} ({{ p.scientificName }})
                </option>
              </select>
            </td>
            <td>
              <input 
                v-model.number="item.quantity" 
                type="number" 
                min="1" 
                class="form-input text-center"
                @input="calculateItem(item)"
              />
            </td>
            <td>
              <input 
                v-model.number="item.unit_price" 
                type="number" 
                step="0.01" 
                class="form-input text-center"
                @input="calculateItem(item)"
              />
            </td>
            <td>
              <input 
                v-model.number="item.discount" 
                type="number" 
                max="100" 
                class="form-input text-center"
                @input="calculateItem(item)"
              />
            </td>
            <td>
              <input 
                v-model.number="item.tax_percent" 
                type="number" 
                class="form-input text-center"
                @input="calculateItem(item)"
              />
            </td>
            <td class="font-bold text-teal-700">
              {{ formatCurrency(item.total) }}
            </td>
            <td>
              <button @click="removeItem(index)" class="btn-delete">×</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="items.length === 0" class="empty-items">
      لا توجد أصناف مضافة. قم بإضافة صنف واحد على الأقل.
    </div>
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue'

const props = defineProps<{
  modelValue: any[]
  products: any[]
}>()

const emit = defineEmits(['update:modelValue', 'change'])

const items = reactive([...props.modelValue])

const addItem = () => {
  items.push({
    product_id: null,
    product_name: '',
    quantity: 1,
    unit_price: 0,
    discount: 0,
    tax_percent: 15,
    total: 0
  })
}

const onProductSelect = (item: any) => {
  const product = props.products.find(p => p.id === item.product_id)
  if (product) {
    item.product_name = product.name
    item.unit_price = product.price
    calculateItem(item)
  }
}

const removeItem = (index: number) => {
  items.splice(index, 1)
}

const calculateItem = (item: any) => {
  const sub = (item.quantity || 0) * (item.unit_price || 0)
  const discAmount = sub * ((item.discount || 0) / 100)
  const afterDisc = sub - discAmount
  const taxAmount = afterDisc * ((item.tax_percent || 0) / 100)
  item.total = afterDisc + taxAmount
  emit('change')
}

const formatCurrency = (val: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val)

watch(items, (newVal) => {
  emit('update:modelValue', newVal)
}, { deep: true })
</script>

<style scoped>
.items-table {
  width: 100%;
  border-collapse: collapse;
}

.items-table th {
  padding: 0.75rem;
  text-align: right;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.9rem;
}

.items-table td {
  padding: 0.75rem 0.25rem;
  border-bottom: 1px solid #f1f5f9;
}

.form-input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.5rem;
  font-family: inherit;
}

.text-center { text-align: center; }

.btn-primary-sm {
  background: var(--color-primary);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 700;
}

.btn-delete {
  background: #fee2e2;
  color: #991b1b;
  border: none;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 1.2rem;
  line-height: 1;
}

.empty-items {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
  font-style: italic;
}

.p-6 { padding: 1.5rem; }
.mt-6 { margin-top: 1.5rem; }
.mb-4 { margin-bottom: 1rem; }
</style>
