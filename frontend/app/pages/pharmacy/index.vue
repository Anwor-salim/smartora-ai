<template>
  <div class="pharmacy-home">
    <div class="container">
      
      <!-- Hero / Welcome Banner -->
      <div class="hero-banner">
        <div class="hero-content">
          <h1>مرحباً بك في <span class="highlight">السوق الطبي</span> 👋</h1>
          <p>أعد تعبئة مخزون صيدليتك بنقرة واحدة، وتصفح أحدث عروض الشركات المعتمدة.</p>
          <div class="hero-actions">
            <button @click="scrollToMarket" class="btn-primary">استكشف المنتجات</button>
            <NuxtLink to="/pharmacy/orders" class="btn-secondary">متابعة طلباتي</NuxtLink>
          </div>
        </div>
        <div class="hero-graphic hidden md:block">
          <!-- Abstract representation of pharmacy goods -->
          <div class="floating-pill pill-1">💊</div>
          <div class="floating-pill pill-2">🧴</div>
          <div class="floating-pill pill-3">🏥</div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="icon-box pending-box">⏳</div>
          <div class="stat-info">
            <p class="stat-label">طلبات قيد التجهيز</p>
            <p class="stat-value">2 <span class="text-xs font-normal text-gray-500">طلب</span></p>
          </div>
        </div>
        <div class="stat-card">
          <div class="icon-box spend-box">💵</div>
          <div class="stat-info">
            <p class="stat-label">مشتريات هذا الشهر</p>
            <p class="stat-value">4,520 <span class="text-xs font-normal text-gray-500">SAR</span></p>
          </div>
        </div>
        <div class="stat-card">
          <div class="icon-box new-box">🎉</div>
          <div class="stat-info">
            <p class="stat-label">منتجات وصلت حديثاً</p>
            <p class="stat-value text-blue-600">+12</p>
          </div>
        </div>
      </div>

      <!-- Marketplace Section -->
      <div class="market-section" id="marketArea">
        <div class="section-header">
          <h2>المنتجات المتاحة من الشركات المتصلة</h2>
          <div class="filters">
            <span class="filter active">الكل</span>
            <span class="filter">الأدوية</span>
            <span class="filter">مستحضرات التجميل</span>
          </div>
        </div>

        <div v-if="products.length === 0" class="empty-market">
           <span class="text-4xl block mb-2">🏭</span>
           لا توجد منتجات مسجلة من الشركات حتى الآن.
        </div>

        <div v-else class="product-grid">
          <div v-for="product in products" :key="product.id" class="product-card">
            <div class="product-header">
              <span class="badge-stock" :class="getStockClass(product.stock)">
                {{ product.stock > 0 ? `متوفر: ${product.stock}` : 'نفذت الكمية' }}
              </span>
              <div class="brand-tag">{{ product.companyId || 'رابط فارما' }}</div>
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
import { useProducts } from '~/composables/useProducts'
import { useCart } from '~/composables/useCart'

definePageMeta({
  layout: 'pharmacy',
  middleware: 'auth'
})

const { products } = useProducts()
const { addToCart } = useCart()

const scrollToMarket = () => {
  document.getElementById('marketArea')?.scrollIntoView({ behavior: 'smooth' })
}

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

.pharmacy-home {
  font-family: 'Tajawal', sans-serif;
  padding: 2rem;
  padding-bottom: 5rem;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Hero */
.hero-banner {
  background: linear-gradient(135deg, #1e3a8a, #3b82f6);
  border-radius: 24px;
  padding: 3rem 4rem;
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
  margin-bottom: 3rem;
}

.hero-content {
  position: relative;
  z-index: 10;
  max-width: 600px;
}

.hero-content h1 {
  font-size: 2.8rem;
  font-weight: 900;
  margin: 0 0 1rem;
  letter-spacing: -1px;
}

.highlight {
  color: #bfdbfe;
}

.hero-content p {
  font-size: 1.2rem;
  color: #eff6ff;
  margin: 0 0 2rem;
  line-height: 1.6;
}

.hero-actions {
  display: flex;
  gap: 1rem;
}

.btn-primary {
  background: white;
  color: #1e3a8a;
  padding: 1rem 2rem;
  border-radius: 12px;
  font-weight: 900;
  font-size: 1.1rem;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.btn-primary:hover {
  transform: translateY(-2px);
  background: #f8fafc;
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.15);
  color: white;
  padding: 1rem 2rem;
  border-radius: 12px;
  font-weight: 800;
  font-size: 1.1rem;
  text-decoration: none;
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.2s;
  backdrop-filter: blur(5px);
}
.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.25);
}

.hero-graphic {
  position: relative;
  width: 200px;
  height: 200px;
}

.floating-pill {
  position: absolute;
  font-size: 4rem;
  filter: drop-shadow(0 10px 10px rgba(0,0,0,0.2));
  animation: float 6s ease-in-out infinite;
}
.pill-1 { top: 0; right: 20px; animation-delay: 0s; }
.pill-2 { bottom: 20px; left: 0; font-size: 3.5rem; animation-delay: 1s; }
.pill-3 { top: 40px; left: -40px; font-size: 2.5rem; animation-delay: 2s; }

@keyframes float {
  0% { transform: translateY(0px) rotate(0deg); }
  50% { transform: translateY(-15px) rotate(5deg); }
  100% { transform: translateY(0px) rotate(0deg); }
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
  margin-bottom: 4rem;
}

.stat-card {
  background: white;
  border-radius: 20px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 15px -5px rgba(0,0,0,0.05);
  border: 1px solid #e0e7ff;
}

.icon-box {
  width: 50px;
  height: 50px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}
.pending-box { background: #fef3c7; }
.spend-box { background: #dcfce7; }
.new-box { background: #dbeafe; }

.stat-label {
  margin: 0 0 0.2rem;
  color: #64748b;
  font-weight: 700;
  font-size: 0.9rem;
}

.stat-value {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 900;
  color: #0f172a;
}

/* Marketplace */
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.section-header h2 {
  font-size: 1.8rem;
  font-weight: 900;
  color: #1e3a8a;
  margin: 0;
}

.filters {
  display: flex;
  gap: 0.5rem;
}

.filter {
  padding: 0.5rem 1.5rem;
  border-radius: 99px;
  background: white;
  color: #64748b;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  border: 1px solid #e2e8f0;
  transition: all 0.2s;
}
.filter:hover { background: #f8fafc; }
.filter.active { background: #2563eb; color: white; border-color: #2563eb; }

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
  transition: all 0.3s ease;
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
  justify-content: space-between;
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

.brand-tag {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 700;
  background: #f1f5f9;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}

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
  background: #eff6ff;
  color: #2563eb;
  font-weight: 800;
  border: 1px solid #bfdbfe;
  cursor: pointer;
  font-family: inherit;
  font-size: 1rem;
  transition: all 0.2s;
}
.btn-add-cart:hover:not(:disabled) {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}
.btn-add-cart.out-of-stock {
  background: #f1f5f9;
  color: #94a3b8;
  border-color: #e2e8f0;
  cursor: not-allowed;
}

/* Responsiveness */
@media (max-width: 900px) {
  .stats-grid { grid-template-columns: 1fr; }
  .hero-banner { padding: 2rem; flex-direction: column; text-align: center; }
  .hero-actions { justify-content: center; }
}
</style>
