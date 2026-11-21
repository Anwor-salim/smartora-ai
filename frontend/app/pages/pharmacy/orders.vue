<template>
  <div class="pharmacy-orders-page">
    <div class="container">
      
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">طلباتي وفواتيري</h1>
        <p class="page-subtitle">تتبع حالة طلباتك الحالية واستخرج فواتيرك الضريبية السابقة.</p>
      </div>

      <!-- Custom Tabs -->
      <div class="tabs-wrapper">
        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'current' }"
          @click="activeTab = 'current'"
        >
          <span class="icon">📦</span> الطلبات الحالية ({{ currentOrders.length }})
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'history' }"
          @click="activeTab = 'history'"
        >
          <span class="icon">🧾</span> سجل المشتريات والفواتير ({{ completedOrders.length }})
        </button>
      </div>

      <!-- Tab Content: Current Orders -->
      <transition name="fade" mode="out-in">
        <div v-if="activeTab === 'current'" class="tab-content" key="current">
          <div v-if="currentOrders.length === 0" class="empty-state">
            <span class="text-5xl block mb-2">🛒</span>
            <h3>لا توجد طلبات جارية حالياً</h3>
            <NuxtLink to="/pharmacy/market" class="btn-primary mt-4 inline-block">تسوق الآن</NuxtLink>
          </div>
          
          <div v-else class="orders-grid">
            <div v-for="order in currentOrders" :key="order.id" class="order-card p-current">
              
              <div class="order-header">
                <span class="order-id">{{ order.id }}</span>
                <span class="order-date">{{ new Date(order.date).toLocaleDateString('ar-SA') }}</span>
              </div>
              
              <div class="order-status-tracker">
                <div class="tracker-step" :class="getTrackerState(order.status, 'pending')">
                  <div class="step-dot"></div>
                  <span>تأكيد المورد</span>
                </div>
                <div class="tracker-line" :class="getTrackerState(order.status, 'processing')"></div>
                <div class="tracker-step" :class="getTrackerState(order.status, 'processing')">
                  <div class="step-dot"></div>
                  <span>قيد التجهيز</span>
                </div>
                <div class="tracker-line" :class="getTrackerState(order.status, 'shipped')"></div>
                <div class="tracker-step" :class="getTrackerState(order.status, 'shipped')">
                  <div class="step-dot"></div>
                  <span>في الطريق</span>
                </div>
              </div>

              <div class="order-body">
                <div class="stat-row">
                  <span>الأصناف المطلوبة</span>
                  <span class="fw-bold">{{ order.items }} عبوة</span>
                </div>
                <div class="stat-row total-row">
                  <span>مبلغ الفاتورة الكلي</span>
                  <span class="fw-bold text-blue">{{ order.total.toFixed(2) }} SAR</span>
                </div>
              </div>

              <div class="order-footer">
                <p class="status-alert" :class="order.status">
                  <span v-if="order.status === 'pending'">⚠️ بانتظار اعتماد فاتورتك من قبل المورد.</span>
                  <span v-else-if="order.status === 'processing'">⚙️ جارٍ تجهيز الأصناف في المستودع بالمواصفات الطبية.</span>
                  <span v-else-if="order.status === 'shipped'">🚚 انطلق المندوب لتوصيل الطلب إلى الصيدلية!</span>
                </p>
              </div>

            </div>
          </div>
        </div>

        <!-- Tab Content: History -->
        <div v-else-if="activeTab === 'history'" class="tab-content" key="history">
          <div v-if="completedOrders.length === 0" class="empty-state">
            <span class="text-5xl block mb-2">📄</span>
            <h3>لا توجد فواتير سابقة</h3>
          </div>
          
          <div v-else class="orders-grid">
            <div v-for="order in completedOrders" :key="order.id" class="order-card p-history">
              
              <div class="order-header">
                <div class="flex items-center gap-2">
                  <span class="badge-completed">مكتمل ومدفوع</span>
                  <span class="order-id">{{ order.id }}</span>
                </div>
                <span class="order-date">{{ new Date(order.date).toLocaleDateString('ar-SA') }}</span>
              </div>
              
              <div class="order-body border-y">
                <div class="stat-row">
                  <span>المورد</span>
                  <span class="fw-bold">{{ order.companyName }}</span>
                </div>
                <div class="stat-row">
                  <span>مبلغ الفاتورة (شامل الضريبة)</span>
                  <span class="fw-bold text-blue">{{ order.total.toFixed(2) }} SAR</span>
                </div>
              </div>

              <div class="order-footer">
                <NuxtLink :to="`/pharmacy/invoices/${order.id}`" class="btn-invoice">
                  عرض وطباعة الفاتورة 📄
                </NuxtLink>
              </div>

            </div>
          </div>
        </div>
      </transition>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useOrders } from '~/composables/useOrders'

definePageMeta({
  layout: 'pharmacy',
  middleware: 'auth'
})

const { orders, fetchPharmacyOrders, loading, error, mapOrderData } = useOrders()

const activeTab = ref('current')

onMounted(() => {
  fetchPharmacyOrders()
})

const mappedOrders = computed(() => orders.value.map(o => mapOrderData(o)))

const currentOrders = computed(() => {
  return mappedOrders.value.filter(o => o.status !== 'delivered' && o.status !== 'cancelled')
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const completedOrders = computed(() => {
  return mappedOrders.value.filter(o => o.status === 'delivered')
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const getTrackerState = (currentStatus: string, checkStatus: string) => {
  const ranks = { 'pending': 1, 'processing': 2, 'shipped': 3, 'delivered': 4 }
  const currRank = ranks[currentStatus as keyof typeof ranks] || 0
  const checkRank = ranks[checkStatus as keyof typeof ranks] || 0
  
  if (currRank > checkRank) return 'passed'
  if (currRank === checkRank) return 'active'
  return 'waiting'
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.pharmacy-orders-page {
  font-family: 'Tajawal', sans-serif;
  padding: 2rem;
  padding-bottom: 5rem;
}

.container {
  max-width: 1100px;
  margin: 0 auto;
}

.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.block { display: block; }
.inline-block { display: inline-block; }
.text-center { text-align: center; }
.fw-bold { font-weight: 800; }
.text-blue { color: #1e3a8a; }

/* Header */
.page-header {
  margin-bottom: 3rem;
}
.page-title { font-size: 2.2rem; font-weight: 900; color: #1e3a8a; margin: 0 0 0.5rem; }
.page-subtitle { color: #64748b; font-size: 1.1rem; margin: 0; }

/* Tabs */
.tabs-wrapper {
  display: flex;
  background: white;
  padding: 0.5rem;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  margin-bottom: 2rem;
  border: 1px solid #e2e8f0;
}

.tab-btn {
  flex: 1;
  background: transparent;
  border: none;
  padding: 1rem;
  border-radius: 12px;
  font-family: inherit;
  font-weight: 800;
  font-size: 1.1rem;
  color: #64748b;
  cursor: pointer;
  transition: all 0.3s;
}

.tab-btn.active {
  background: #eff6ff;
  color: #1e3a8a;
  box-shadow: 0 2px 4px rgba(37,99,235,0.05);
}

.tab-btn:hover:not(.active) {
  background: #f8fafc;
}

/* Empty State */
.empty-state {
  background: white;
  border-radius: 20px;
  padding: 5rem 2rem;
  text-align: center;
  border: 1px dashed #cbd5e1;
}
.empty-state h3 { font-size: 1.5rem; color: #1e293b; margin: 0; font-weight: 800; }

.btn-primary {
  background: linear-gradient(135deg, #1d4ed8, #3b82f6);
  color: white;
  padding: 0.85rem 2rem;
  border-radius: 12px;
  font-weight: 800;
  text-decoration: none;
  box-shadow: 0 4px 10px rgba(37,99,235,0.2);
  transition: all 0.2s;
}
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(37,99,235,0.3);
}

/* Grids */
.orders-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
  gap: 1.5rem;
}

/* Cards */
.order-card {
  background: white;
  border-radius: 20px;
  border: 1px solid #e0e7ff;
  box-shadow: 0 4px 15px -5px rgba(0,0,0,0.05);
  display: flex;
  flex-direction: column;
}

.order-header {
  padding: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  border-radius: 20px 20px 0 0;
  border-bottom: 1px solid #f1f5f9;
}

.order-id { font-family: monospace; font-weight: 800; color: #64748b; font-size: 1.1rem; }
.order-date { color: #94a3b8; font-size: 0.9rem; font-weight: 600; }

/* Tracker Line */
.order-status-tracker {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
}

.tracker-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #cbd5e1;
  position: relative;
  z-index: 2;
}

.step-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #cbd5e1;
  transition: all 0.3s;
}

.tracker-line {
  flex-grow: 1;
  height: 3px;
  background: #cbd5e1;
  margin: 0 -10px 20px;
  z-index: 1;
  transition: all 0.3s;
}

/* Tracker States */
.tracker-step.passed .step-dot { background: #3b82f6; box-shadow: 0 0 0 3px #dbeafe; }
.tracker-step.passed span { color: #3b82f6; }

.tracker-step.active .step-dot { background: #1d4ed8; box-shadow: 0 0 0 4px #bfdbfe; }
.tracker-step.active span { color: #1d4ed8; }

.tracker-line.passed, .tracker-line.active { background: #3b82f6; }

/* Body */
.order-body { padding: 1.5rem; }
.border-y { border-bottom: 1px dashed #e2e8f0; }

.stat-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.95rem;
  color: #64748b;
  margin-bottom: 0.75rem;
}
.total-row {
  margin-bottom: 0;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px dashed #cbd5e1;
}

/* Footer / Alerts */
.order-footer { padding: 0 1.5rem 1.5rem; }

.status-alert {
  margin: 0;
  padding: 0.85rem;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 700;
}
.status-alert.pending { background: #fef08a; color: #854d0e; }
.status-alert.in_progress { background: #e0e7ff; color: #3730a3; }
.status-alert.ready { background: #dcfce7; color: #166534; }

.badge-completed {
  background: #dcfce7;
  color: #166534;
  padding: 0.3rem 0.8rem;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 800;
}

.btn-invoice {
  display: block;
  width: 100%;
  text-align: center;
  background: #eff6ff;
  color: #2563eb;
  padding: 1rem;
  border-radius: 12px;
  font-weight: 800;
  text-decoration: none;
  transition: all 0.2s;
  border: 1px solid #bfdbfe;
}
.btn-invoice:hover {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 768px) {
  .orders-grid { grid-template-columns: 1fr; }
}
</style>
