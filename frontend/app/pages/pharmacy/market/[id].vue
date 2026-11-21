<template>
  <div class="company-market-page">
    
    <div v-if="!company" class="container py-20 text-center">
      <h2 class="text-3xl text-red-600 font-bold">الشركة غير موجودة</h2>
      <NuxtLink to="/pharmacy/market" class="text-blue-500 underline mt-4 block">العودة لدليل الشركات</NuxtLink>
    </div>

    <div v-else class="container">
      
      <!-- Company Header -->
      <div class="company-header-card">
        <div class="ch-left">
          <div class="logo-circle">{{ company.logoText }}</div>
          <div>
            <div class="flex items-center gap-2 mb-2">
              <h1 class="company-name">{{ company.name }}</h1>
              <span v-if="company.verified" class="badge-verified">✔️</span>
            </div>
            <p class="company-desc">{{ company.description }}</p>
            <div class="tags-container">
              <span v-for="tag in company.categories" :key="tag" class="cat-tag">{{ tag }}</span>
            </div>
          </div>
        </div>
        
        <div class="ch-right hidden md:flex">
          <div class="stat-block">
            <span class="stat-val">⭐ {{ company.rating }}</span>
            <span class="stat-lbl">تقييم المورد</span>
          </div>
          <div class="stat-block">
            <span class="stat-val">🚚 {{ company.deliveryTime }}</span>
            <span class="stat-lbl">سُرعة التوصيل</span>
          </div>
        </div>
      </div>

      <!-- Marketplace Section (Specific to this company) -->
      <div class="market-section">
        <div class="section-header">
          <h2>منتجات {{ company.name }}</h2>
          <NuxtLink to="/pharmacy/market" class="link-back">➔ عودة للدليل</NuxtLink>
        </div>

        <div v-if="companyProducts.length === 0" class="empty-market">
           <span class="text-4xl block mb-2">📦</span>
           لا توجد منتجات مسجلة لهذه الشركة حالياً.
        </div>

        <div v-else class="product-grid">
          <div v-for="product in companyProducts" :key="product.id" class="product-card">
            <div class="product-header">
              <span class="badge-stock" :class="getStockClass(product.stock)">
                {{ product.stock > 0 ? `متوفر: ${product.stock}` : 'نفذت الكمية' }}
              </span>
            </div>

            <div class="product-icon">
              <span class="text-5xl">{{ getCategoryIcon(product.category) }}</span>
            </div>
            
            <div class="product-details">
              <span class="category-name">{{ product.category }}</span>
              <h3 class="product-name">{{ product.name }}</h3>
              
              <div class="price-row">
                <span class="price-value">{{ product.price.toFixed(2) }} <small>SAR</small></span>
              </div>
            </div>

            <div class="product-actions">
              <button 
                class="btn-add-cart" 
                @click="handleAddToCart(product)"
                :disabled="product.stock <= 0"
                :class="{'out-of-stock': product.stock <= 0}"
              >
                {{ product.stock > 0 ? '+ أضف للسلة' : 'غير متوفر' }}
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCompanies, type MedicalCompany } from '~/composables/useCompanies'
import { useProducts } from '~/composables/useProducts'
import { useCart } from '~/composables/useCart'

definePageMeta({
  layout: 'pharmacy',
  middleware: 'auth'
})

const route = useRoute()
const { companies, fetchCompanies } = useCompanies()
const { products, fetchPharmacyMarketProducts } = useProducts()
const { addToCart } = useCart()

onMounted(async () => {
  if (companies.value.length === 0) {
    await fetchCompanies()
  }
  await fetchPharmacyMarketProducts(route.params.id as string)
})

const company = computed<MedicalCompany | undefined>(() => {
  return companies.value.find(c => c.id == route.params.id)
})

// Filter products: If company is Comp-1 (رابط فارما), show base products, else filter explicitly
const companyProducts = computed(() => {
  return products.value
})

const handleAddToCart = (product: any) => {
  addToCart(product, 1)
}

const getCategoryIcon = (category: string) => {
  const map: Record<string, string> = {
    'أدوية': '💊',
    'مستحضرات تجميل': '🧴',
    'مستلزمات طبية': '🩹'
  }
  return map[category] || '📦'
}

const getStockClass = (stock: number) => {
  if (stock > 50) return 'stock-high'
  if (stock > 0) return 'stock-low'
  return 'stock-empty'
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.company-market-page {
  font-family: 'Tajawal', sans-serif;
  padding: 2rem;
  padding-bottom: 5rem;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
.block { display: block; }
.text-center { text-align: center; }

/* Company Header */
.company-header-card {
  background: white;
  border-radius: 24px;
  padding: 2.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.1);
  border: 1px solid #dbeafe;
  margin-bottom: 3rem;
}

.ch-left {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.logo-circle {
  width: 90px;
  height: 90px;
  border-radius: 24px;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  border: 2px solid #f1f5f9;
}

.company-name {
  font-size: 2rem;
  font-weight: 900;
  color: #1e3a8a;
  margin: 0;
}

.badge-verified {
  background: #ecfdf5;
  color: #059669;
  font-weight: 800;
  padding: 0.2rem 0.5rem;
  border-radius: 8px;
  font-size: 0.8rem;
}

.company-desc {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0 0 1rem;
  max-width: 600px;
}

.tags-container {
  display: flex;
  gap: 0.5rem;
}

.cat-tag {
  background: #eff6ff;
  color: #2563eb;
  padding: 0.3rem 0.8rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 800;
}

.ch-right {
  display: flex;
  gap: 1.5rem;
}

.stat-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: #f8fafc;
  padding: 1rem 1.5rem;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
}

.stat-val { font-size: 1.3rem; font-weight: 900; color: #0f172a; margin-bottom: 0.2rem; }
.stat-lbl { color: #64748b; font-size: 0.85rem; font-weight: 700; }

/* Marketplace */
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.section-header h2 {
  font-size: 1.6rem;
  font-weight: 900;
  color: #0f172a;
  margin: 0;
}

.link-back {
  color: #64748b;
  font-weight: 800;
  text-decoration: none;
  font-size: 0.95rem;
  transition: color 0.2s;
}
.link-back:hover { color: #2563eb; }

.empty-market {
  text-align: center;
  padding: 4rem;
  color: #64748b;
  font-weight: 700;
  background: white;
  border-radius: 20px;
  border: 1px dashed #cbd5e1;
}

/* Product Grid */
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
}

.product-card {
  background: white;
  border-radius: 20px;
  padding: 1.5rem;
  box-shadow: 0 4px 15px -5px rgba(0,0,0,0.05);
  border: 1px solid #e0e7ff;
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  display: flex;
  flex-direction: column;
}
.product-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px -10px rgba(37, 99, 235, 0.15);
  border-color: #bfdbfe;
}

.product-header {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
}

.badge-stock {
  font-size: 0.75rem;
  font-weight: 800;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
}
.stock-high { background: #dcfce7; color: #166534; }
.stock-low { background: #fef08a; color: #854d0e; }
.stock-empty { background: #fee2e2; color: #991b1b; }

.product-icon {
  height: 120px;
  background: #f8fafc;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
  border: 1px dashed #cbd5e1;
}

.product-details {
  flex-grow: 1;
}

.category-name {
  color: #3b82f6;
  font-size: 0.8rem;
  font-weight: 800;
  text-transform: uppercase;
}

.product-name {
  font-size: 1.2rem;
  font-weight: 900;
  color: #0f172a;
  margin: 0.25rem 0 0.5rem;
}

.price-value {
  font-size: 1.5rem;
  font-weight: 900;
  color: #1e3a8a;
}
.price-value small { font-size: 60%; color: #64748b; }

.product-actions {
  margin-top: 1.5rem;
}

.btn-add-cart {
  width: 100%;
  padding: 0.85rem;
  border-radius: 12px;
  background: linear-gradient(135deg, #1d4ed8, #3b82f6);
  color: white;
  font-weight: 800;
  border: none;
  cursor: pointer;
  font-family: inherit;
  font-size: 1.05rem;
  transition: all 0.2s;
  box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
}
.btn-add-cart:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
}
.btn-add-cart.out-of-stock {
  background: #f1f5f9;
  color: #94a3b8;
  box-shadow: none;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .company-header-card { flex-direction: column; text-align: center; }
  .ch-left { flex-direction: column; }
}
</style>
