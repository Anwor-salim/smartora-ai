<template>
  <div class="dashboard-page">
    <div class="container">
      
      <!-- Welcome Header -->
      <div class="welcome-header">
        <div class="welcome-text">
          <h1 class="page-title">مرحباً مجدداً، <span class="highlight">رابط فارما</span> 👋</h1>
          <p class="page-subtitle">إليك نظرة عامة على أداء عمليات التوزيع والمبيعات اليوم.</p>
        </div>
        <div class="header-actions">
          <NuxtLink to="/company/products" class="btn-primary">إضافة منتج جديد +</NuxtLink>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <!-- Stat Card 1 -->
        <div class="stat-card">
          <div class="stat-icon sales-icon">💰</div>
          <div class="stat-details">
            <h3 class="stat-label">إجمالي المبيعات (المُكتملة)</h3>
            <div class="stat-value">{{ totalSales.toFixed(2) }} <span class="currency">SAR</span></div>
            <div class="stat-trend positive">↑ 12.5% من الشهر الماضي</div>
          </div>
        </div>

        <!-- Stat Card 2 -->
        <div class="stat-card">
          <div class="stat-icon orders-icon">📦</div>
          <div class="stat-details">
            <h3 class="stat-label">الطلبات النشطة</h3>
            <div class="stat-value">{{ activeOrdersCount }} <span class="text-sm">طلب</span></div>
            <div class="stat-trend neutral">قيد التنفيذ والتجهيز</div>
          </div>
        </div>

        <!-- Stat Card 3 -->
        <div class="stat-card">
          <div class="stat-icon products-icon">💊</div>
          <div class="stat-details">
            <h3 class="stat-label">إجمالي المنتجات بالمخزون</h3>
            <div class="stat-value">{{ products.length }} <span class="text-sm">صنف</span></div>
            <div class="stat-trend positive">مُتاح للشركات والصيدليات</div>
          </div>
        </div>
        
        <!-- Stat Card 4 -->
        <div class="stat-card border-card">
          <div class="stat-icon pending-icon">🔔</div>
          <div class="stat-details">
            <h3 class="stat-label">طلبات بانتظار الموافقة</h3>
            <div class="stat-value text-red">{{ pendingOrders.length }} <span class="text-sm">طلب جديد</span></div>
            <div class="stat-trend warning">يرجى المراجعة في التنبيهات</div>
          </div>
        </div>
      </div>

      <!-- Main Content Split -->
      <div class="content-split">
        <!-- Left Side (RTL) - Recent Orders -->
        <div class="panel recent-orders">
          <div class="panel-header">
            <h2>أحدث الطلبات الواردة</h2>
            <NuxtLink to="/company/orders" class="link-view-all">عرض الكل</NuxtLink>
          </div>
          <div class="panel-body">
            <div v-if="recentOrders.length === 0" class="empty-panel">لا توجد طلبات حديثة</div>
            
            <div v-for="order in recentOrders" :key="order.id" class="mini-order-row">
              <div class="mo-info">
                <span class="mo-pharma">{{ order.pharmacyName }}</span>
                <span class="mo-meta">{{ new Date(order.date).toLocaleDateString('ar-SA') }}</span>
              </div>
              <div class="mo-status">
                <span :class="['status-dot', order.status]"></span>
                <span class="mo-total">{{ order.total.toFixed(2) }} SAR</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Right Side (RTL) - System Alerts / Info -->
        <div class="panel system-info">
          <div class="panel-header">
            <h2>ملخص النظام</h2>
          </div>
          <div class="panel-body">
            <div class="info-block">
              <h4>نظام الفواتير الآلي</h4>
              <p>تم تفعيل نظام استخراج الفواتير بشكل آلي (PDF) لجميع الطلبات المكتملة حديثاً.</p>
            </div>
            <div class="info-block">
              <h4>الربط مع الصيدليات</h4>
              <p>المخزون الخاص بك يظهر الآن بشكل لحظي لجميع الصيدليات المسجلة بالمنصة.</p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

definePageMeta({
  layout: 'company',
  middleware: 'auth'
})

// Temporary static data
const products = ref([
  { id: '1', name: 'Product 1' },
  { id: '2', name: 'Product 2' },
  { id: '3', name: 'Product 3' }
])

const completedOrders = ref([
  { total: 100 },
  { total: 200 },
  { total: 150 }
])

const pendingOrders = ref([
  { id: '1' },
  { id: '2' }
])

const inProgressOrders = ref([
  { id: '1' },
  { id: '2' },
  { id: '3' }
])

const readyOrders = ref([
  { id: '1' }
])

const orders = ref([
  { id: '1', pharmacyName: 'صيدلية الأمل', date: '2024-01-01', status: 'pending', total: 100 },
  { id: '2', pharmacyName: 'صيدلية الشفاء', date: '2024-01-02', status: 'in_progress', total: 200 }
])

const totalSales = computed(() => {
  return completedOrders.value.reduce((acc, order) => acc + order.total, 0)
})

const activeOrdersCount = computed(() => {
  return inProgressOrders.value.length + readyOrders.value.length
})

// Get top 4 most recent orders
const recentOrders = computed(() => {
  return [...orders.value].sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime()).slice(0, 4)
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.dashboard-page {
  font-family: 'Tajawal', sans-serif;
  background-color: #f8fafc;
  min-height: 100vh;
  padding: 2.5rem 2rem;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Header */
.welcome-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 3rem;
}

.page-title {
  font-size: 2.4rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 0.5rem;
}

.highlight {
  color: #0f766e;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

.btn-primary {
  background: linear-gradient(135deg, #0d9488, #14b8a6);
  color: white;
  padding: 0.85rem 1.5rem;
  border-radius: 12px;
  font-weight: 800;
  text-decoration: none;
  font-size: 1rem;
  box-shadow: 0 4px 10px rgba(13, 148, 136, 0.2);
  transition: all 0.3s ease;
}
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(13, 148, 136, 0.3);
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 3rem;
}

.stat-card {
  background: white;
  border-radius: 20px;
  padding: 1.5rem;
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;
  box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(226, 232, 240, 0.8);
  transition: transform 0.3s ease;
}
.stat-card:hover {
  transform: translateY(-4px);
  border-color: #99f6e4;
}

.stat-card.border-card {
  border: 2px solid #fef08a;
  background: #fffbeb;
}

.stat-icon {
  width: 54px;
  height: 54px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  flex-shrink: 0;
}

.sales-icon { background: #dcfce7; color: #15803d; }
.orders-icon { background: #e0e7ff; color: #4f46e5; }
.products-icon { background: #f3e8ff; color: #7e22ce; }
.pending-icon { background: #fee2e2; color: #dc2626; }

.stat-details { flex-grow: 1; }

.stat-label {
  font-size: 0.95rem;
  color: #64748b;
  font-weight: 700;
  margin: 0 0 0.5rem;
}

.stat-value {
  font-size: 1.8rem;
  font-weight: 900;
  color: #0f172a;
  margin: 0 0 0.5rem;
  letter-spacing: -0.5px;
}
.stat-value.text-red { color: #dc2626; }

.currency { font-size: 1rem; color: #0f766e; }
.text-sm { font-size: 0.9rem; color: #94a3b8; }

.stat-trend {
  font-size: 0.85rem;
  font-weight: 800;
}
.positive { color: #10b981; }
.neutral { color: #64748b; }
.warning { color: #d97706; }

/* Panels Layout */
.content-split {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 2rem;
}

.panel {
  background: white;
  border-radius: 24px;
  padding: 2rem;
  box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 1rem;
}

.panel-header h2 {
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.link-view-all {
  color: #0f766e;
  font-weight: 800;
  text-decoration: none;
  font-size: 0.95rem;
}
.link-view-all:hover { text-decoration: underline; }

.empty-panel {
  text-align: center;
  padding: 3rem;
  color: #94a3b8;
  font-weight: 600;
}

/* Mini Order Row */
.mini-order-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 0;
  border-bottom: 1px solid #f8fafc;
}
.mini-order-row:last-child { border-bottom: none; }

.mo-info {
  display: flex;
  flex-direction: column;
}

.mo-pharma {
  font-weight: 800;
  color: #1e293b;
  font-size: 1.1rem;
}

.mo-meta {
  color: #94a3b8;
  font-size: 0.85rem;
}

.mo-status {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}
.status-dot.pending { background: #ef4444; }
.status-dot.in_progress { background: #f59e0b; }
.status-dot.ready { background: #10b981; }
.status-dot.completed { background: #94a3b8; }

.mo-total {
  font-weight: 900;
  color: #0f766e;
  font-size: 1.1rem;
}

/* Info Blocks */
.info-block {
  background: #f8fafc;
  padding: 1.25rem;
  border-radius: 12px;
  margin-bottom: 1rem;
  border-right: 4px solid #14b8a6;
}
.info-block:last-child { margin-bottom: 0; border-right-color: #8b5cf6; }

.info-block h4 {
  margin: 0 0 0.25rem;
  color: #1e293b;
  font-weight: 800;
}
.info-block p {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
  line-height: 1.5;
}

/* Responsive */
@media (max-width: 900px) {
  .content-split {
    grid-template-columns: 1fr;
  }
}
</style>
