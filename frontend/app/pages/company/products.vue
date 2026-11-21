<template>
  <div class="products-page">
    <div class="container">
      
      <div class="header-section">
        <h1 class="page-title">إدارة المنتجات</h1>
      </div>

      <!-- Custom Tab Navigation -->
      <div class="tabs-container">
        <button 
          class="tab-btn" 
          :class="{ active: currentTab === 'list' }" 
          @click="resetToTabs('list')"
        >
          <span class="fw-bold">مخزون المنتجات</span>
          <span class="tab-badge" v-if="products.length">{{ products.length }}</span>
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: currentTab === 'add' }" 
          @click="resetToTabs('add')"
        >
          <span class="icon-plus" v-if="!editingId">+</span>
          <span class="icon-edit" v-else>✏️</span>
          <span class="fw-bold">{{ editingId ? 'تحديث بيانات المنتج' : 'إضافة إلى المخزون' }}</span>
        </button>
      </div>

      <!-- View: Products List -->
      <div v-if="currentTab === 'list'" class="products-wrapper">
        
        <div v-if="products.length === 0" class="empty-state">
          <div class="empty-icon">⚕️</div>
          <p class="empty-title">لا يوجد منتجات حالياً بمخزونك.</p>
          <p class="empty-subtitle">ابدأ بإضافة منتجاتك بكل سهولة لتصل لآلاف الصيدليات.</p>
        </div>

        <div class="products-grid">
          <div v-for="product in products" :key="product.id" class="premium-card">
            
            <div class="card-header">
              <div class="corner-actions">
                <button @click="handleEdit(product)" class="action-btn edit" title="تعديل">✏️</button>
                <button @click="handleDelete(product.id)" class="action-btn delete" title="حذف">🗑️</button>
              </div>
              
              <div class="category-badge">
                {{ product.category }}
              </div>
            </div>
            
            <div class="card-body">
              <h3 class="product-name">{{ product.name }}</h3>
              <p class="product-scientific" dir="ltr">{{ product.scientificName }}</p>

              <div class="product-stats">
                <div class="stat-box">
                  <span class="stat-label">السعر</span>
                  <div class="stat-value price">
                    <span class="currency">SAR</span>
                    {{ Number(product.price).toFixed(2) }}
                  </div>
                </div>
                
                <div class="stat-box align-left">
                  <div class="stock-header">
                    <span class="stat-label">المخزون</span>
                    <span class="stock-count">{{ product.stock }} عبوة</span>
                  </div>
                  <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: Math.min(100, (product.stock / 1000) * 100) + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- View: Add/Edit Product Form -->
      <div v-if="currentTab === 'add'" class="form-wrapper">
        
        <h2 class="form-title">
          <div class="form-icon">
            <span v-if="!editingId">📦</span>
            <span v-else>✏️</span>
          </div>
          {{ editingId ? 'تعديل السجل الطبي' : 'تسجيل صنف طبي جديد' }}
        </h2>
        
        <form @submit.prevent="submitProduct" class="premium-form">
          <div class="form-row">
            <div class="input-group">
              <label>الاسم التجاري للمنتج</label>
              <input v-model="form.name" type="text" placeholder="مثال: بانادول إكسترا" required class="form-input">
            </div>
            <div class="input-group">
              <label>التركيبة أو الاسم العلمي</label>
              <input v-model="form.scientific_name" type="text" placeholder="Paracetamol" required class="form-input" dir="ltr">
            </div>
          </div>

          <div class="form-row three-cols">
            <div class="input-group">
              <label>القوة (Strength)</label>
              <input v-model="form.strength" type="text" placeholder="500mg" required class="form-input" dir="ltr">
            </div>
            <div class="input-group">
              <label>رقم التشغيلة (Batch)</label>
              <input v-model="form.batch_number" type="text" placeholder="BN-2024-001" required class="form-input" dir="ltr">
            </div>
            <div class="input-group">
              <label>الشكل الصيدلاني</label>
              <select v-model="form.dosage_form" required class="form-input select-input">
                <option value="" disabled>اختر الشكل...</option>
                <option value="حبوب">حبوب (Tablets)</option>
                <option value="شراب">شراب (Syrup)</option>
                <option value="حقن">حقن (Injection)</option>
                <option value="مرهم">مرهم (Ointment)</option>
                <option value="كبسولات">كبسولات (Capsules)</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="input-group">
              <label>تاريخ الإنتاج</label>
              <input v-model="form.production_date" type="date" required class="form-input">
            </div>
            <div class="input-group">
              <label>تاريخ الانتهاء</label>
              <input v-model="form.expiry_date" type="date" required class="form-input">
            </div>
          </div>

          <div class="input-group">
            <label>وصف المنتج (اختياري)</label>
            <textarea v-model="form.description" rows="3" placeholder="أدخل تفاصيل إضافية عن المنتج..." class="form-input"></textarea>
          </div>

          <div class="form-row">
            <div class="input-group">
              <label>سعر التوريد (ريال)</label>
              <div class="input-with-icon">
                <input v-model.number="form.price" type="number" min="0" step="0.01" placeholder="0.00" required class="form-input with-prefix">
                <span class="input-prefix">SAR</span>
              </div>
            </div>
            <div class="input-group">
              <label>الكمية المتوفرة (Stock)</label>
              <input v-model.number="form.stock_quantity" type="number" min="1" placeholder="0" required class="form-input">
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit" :disabled="loading">
              <span v-if="loading">جاري المعالجة...</span>
              <span v-else>{{ editingId ? 'تأكيد التعديلات' : 'اعتماد المنتج' }}</span>
            </button>
            <button type="button" @click="resetToTabs('list')" class="btn-cancel">
              تراجع
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useProducts, type Product } from '~/composables/useProducts'

definePageMeta({
  layout: 'company',
  middleware: 'auth'
})

const { products, addProduct, deleteProduct, updateProduct, fetchCompanyProducts, loading } = useProducts()
const currentTab = ref<'list' | 'add'>('list')
const editingId = ref<string | null>(null)

onMounted(() => {
  fetchCompanyProducts()
})

const form = reactive({
  name: '',
  scientific_name: '',
  price: 0,
  stock_quantity: 0,
  dosage_form: '',
  strength: '',
  batch_number: '',
  production_date: '',
  expiry_date: '',
  description: ''
})

const resetForm = () => {
  form.name = ''
  form.scientific_name = ''
  form.price = 0
  form.stock_quantity = 0
  form.dosage_form = ''
  form.strength = ''
  form.batch_number = ''
  form.production_date = ''
  form.expiry_date = ''
  form.description = ''
  editingId.value = null
}

const resetToTabs = (tab: 'list' | 'add') => {
  if (tab === 'list') {
    resetForm()
  }
  currentTab.value = tab
}

const submitProduct = () => {
  if (editingId.value) {
    updateProduct(editingId.value, { ...form })
  } else {
    addProduct({ ...form })
  }
  resetToTabs('list')
}

const handleDelete = (id: string) => {
  if (confirm('تأكيد مسح المنتج؟ لا يمكن التراجع عن هذا الإجراء.')) {
    deleteProduct(id)
  }
}

const handleEdit = (product: Product) => {
  editingId.value = product.id
  form.name = product.name
  form.scientific_name = product.scientificName
  form.price = product.price
  form.stock_quantity = product.stock
  form.dosage_form = product.category
  form.strength = product.strength || ''
  form.batch_number = product.batchNumber || ''
  form.production_date = product.productionDate || ''
  form.expiry_date = product.expiryDate || ''
  
  form.description = product.description || ''
  
  currentTab.value = 'add'
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.products-page {
  font-family: 'Tajawal', sans-serif;
  background-color: #f8fafc;
  min-height: 100vh;
  padding: 2rem;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Header */
.header-section {
  margin-bottom: 2rem;
}

.page-title {
  font-size: 2.2rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

/* Tabs */
.tabs-container {
  display: flex;
  gap: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 2.5rem;
}

.tab-btn {
  padding: 1rem 0.5rem;
  color: #64748b;
  border: none;
  background: transparent;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -1px;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1.1rem;
  font-family: inherit;
}

.tab-btn:hover {
  color: #0f766e;
}

.tab-btn.active {
  color: #0f766e;
  border-bottom-color: #0d9488;
}

.fw-bold {
  font-weight: 700;
}

.tab-badge {
  background: #f1f5f9;
  color: #475569;
  padding: 0.2rem 0.8rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 800;
}

.tab-btn.active .tab-badge {
  background: #ccfbf1;
  color: #0f766e;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 5rem 2rem;
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px);
  border-radius: 24px;
  border: 1px dashed #cbd5e1;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.empty-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem;
}

.empty-subtitle {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

/* Products Grid */
.products-wrapper {
  animation: fadeIn 0.4s ease-out forwards;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 2rem;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Premium Card */
.premium-card {
  background: #ffffff;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(226, 232, 240, 0.8);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  display: flex;
  flex-direction: column;
}

.premium-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px -15px rgba(13, 148, 136, 0.15);
  border-color: #99f6e4;
}

/* Card Header */
.card-header {
  height: 120px;
  background: linear-gradient(135deg, #f0fdf4 0%, #ccfbf1 100%);
  position: relative;
  padding: 1.5rem;
}

.corner-actions {
  position: absolute;
  top: 1rem;
  left: 1rem;
  display: flex;
  gap: 0.5rem;
  opacity: 0;
  transform: translateY(-10px);
  transition: all 0.3s ease;
}

.premium-card:hover .corner-actions {
  opacity: 1;
  transform: translateY(0);
}

.action-btn {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  border: none;
  background: rgba(255, 255, 255, 0.9);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  transition: all 0.2s;
}

.action-btn.edit:hover {
  background: #ffffff;
  transform: scale(1.1);
}

.action-btn.delete {
  background: rgba(254, 226, 226, 0.9);
}

.action-btn.delete:hover {
  background: #fee2e2;
  transform: scale(1.1);
}

.category-badge {
  position: absolute;
  bottom: -15px;
  right: 1.5rem;
  background: #ffffff;
  color: #0f766e;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 0.4rem 1rem;
  border-radius: 999px;
  box-shadow: 0 4px 10px rgba(13, 148, 136, 0.1);
  border: 1px solid #ccfbf1;
  letter-spacing: 0.5px;
  z-index: 2;
}

/* Card Body */
.card-body {
  padding: 2rem 1.5rem 1.5rem;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

.product-name {
  font-size: 1.3rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 0.2rem;
  line-height: 1.3;
}

.product-scientific {
  font-size: 0.85rem;
  color: #64748b;
  margin: 0 0 1.5rem;
  font-family: serif;
  font-style: italic;
}

.product-stats {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-top: 1px solid #f1f5f9;
  padding-top: 1rem;
  margin-top: auto;
}

.stat-box {
  display: flex;
  flex-direction: column;
}

.stat-box.align-left {
  align-items: flex-start;
  width: 45%;
}

.stat-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  font-weight: 800;
  color: #94a3b8;
  margin-bottom: 0.2rem;
}

.stat-value.price {
  font-size: 1.6rem;
  font-weight: 900;
  color: #0f766e;
}

.currency {
  font-size: 0.8rem;
  font-weight: 700;
  margin-right: 0.1rem;
}

.stock-header {
  display: flex;
  justify-content: space-between;
  width: 100%;
  margin-bottom: 0.4rem;
}

.stock-count {
  font-size: 0.85rem;
  font-weight: 800;
  color: #334155;
}

.progress-bar {
  width: 100%;
  height: 6px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #14b8a6, #0d9488);
  border-radius: 999px;
}

/* Form Styles */
.form-wrapper {
  max-width: 800px;
  margin: 0 auto;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(20px);
  padding: 3rem;
  border-radius: 32px;
  box-shadow: 0 20px 50px -20px rgba(13, 148, 136, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.8);
  animation: fadeIn 0.4s ease-out forwards;
}

.form-title {
  font-size: 1.8rem;
  font-weight: 900;
  color: #0f172a;
  margin: 0 0 2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.form-icon {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #0d9488, #14b8a6);
  color: white;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  box-shadow: 0 8px 16px rgba(13, 148, 136, 0.3);
}

.premium-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.form-row.three-cols {
  grid-template-columns: 1fr 1fr 1fr;
}

.input-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 800;
  color: #475569;
  margin-bottom: 0.5rem;
}

.form-input {
  width: 100%;
  padding: 1rem 1.25rem;
  border-radius: 16px;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  font-size: 1rem;
  color: #0f172a;
  font-family: inherit;
  font-weight: 600;
  transition: all 0.3s ease;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #14b8a6;
  background: #ffffff;
  box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
}

.input-with-icon {
  position: relative;
}

.form-input.with-prefix {
  padding-left: 3.5rem;
}

.input-prefix {
  position: absolute;
  left: 1.25rem;
  top: 50%;
  transform: translateY(-50%);
  color: #0d9488;
  font-weight: 800;
  font-size: 0.9rem;
}

.select-input {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: left 1rem center;
  background-size: 1.5em;
  padding-left: 3rem;
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 2rem;
  border-top: 1px solid #f1f5f9;
}

.btn-submit {
  flex: 1;
  padding: 1.25rem;
  border-radius: 16px;
  background: linear-gradient(135deg, #0d9488, #14b8a6);
  color: white;
  font-size: 1.1rem;
  font-weight: 800;
  font-family: inherit;
  border: none;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 10px 20px -5px rgba(13, 148, 136, 0.4);
}

.btn-submit:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 25px -5px rgba(13, 148, 136, 0.5);
}

.btn-submit:active {
  transform: translateY(0);
}

.btn-cancel {
  padding: 1.25rem 2.5rem;
  border-radius: 16px;
  background: #ffffff;
  color: #64748b;
  font-size: 1.1rem;
  font-weight: 800;
  font-family: inherit;
  border: 2px solid #e2e8f0;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-cancel:hover {
  background: #f8fafc;
  color: #334155;
  border-color: #cbd5e1;
}

/* Responsive */
@media (max-width: 768px) {
  .form-row, .form-row.three-cols {
    grid-template-columns: 1fr;
  }
}
</style>
