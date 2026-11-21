<template>
  <div class="orders-page">
    <div class="container">
      
      <div class="header-section">
        <h1 class="page-title">إدارة الطلبات الواردة</h1>
      </div>

      <!-- Custom Tab Navigation -->
      <div class="tabs-wrapper">
        <button 
          class="tab-btn" 
          :class="{ active: currentTab === 'in_progress' }" 
          @click="currentTab = 'in_progress'"
        >
          <span class="icon">⏳</span>
          <span class="fw-bold">قيد التنفيذ</span>
          <span class="tab-badge" v-if="inProgressOrders.length">{{ inProgressOrders.length }}</span>
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: currentTab === 'ready' }" 
          @click="currentTab = 'ready'"
        >
          <span class="icon">📦</span>
          <span class="fw-bold">جاهزة للتسليم</span>
          <span class="tab-badge" v-if="readyOrders.length">{{ readyOrders.length }}</span>
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: currentTab === 'completed' }" 
          @click="currentTab = 'completed'"
        >
          <span class="icon">✅</span>
          <span class="fw-bold">مكتملة</span>
        </button>
      </div>

      <!-- Orders List based on Tabs -->
      <div class="orders-list">
        
        <div v-if="displayedOrders.length === 0" class="empty-state">
          <div class="empty-icon text-gray-300">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
          </div>
          <p class="empty-title">لا يوجد طلبات في هذه الخانة حالياً.</p>
        </div>

        <div v-for="order in displayedOrders" :key="order.id" class="order-card row-layout">
          
          <div class="order-primary-info">
            <div class="order-header">
              <span class="order-id">#{{ order.id }}</span>
              <h3 class="pharmacy-name">{{ order.pharmacyName }}</h3>
            </div>
            
            <div class="order-meta">
              <div class="meta-item">
                <span class="meta-icon">📅</span>
                <span>{{ new Date(order.date).toLocaleDateString('ar-SA') }}</span>
              </div>
              <div class="meta-item">
                <span class="meta-icon">💊</span>
                <span>{{ order.items }} عبوة/صنف</span>
              </div>
              <div class="meta-item highlight">
                <span class="meta-label">الإجمالي:</span>
                <span class="meta-value">{{ order.total.toFixed(2) }} SAR</span>
              </div>
            </div>
          </div>
          
          <div class="order-actions-area">
            <div class="status-indicator">
              <span class="status-badge" :class="order.status">
                {{ getStatusLabel(order.status) }}
              </span>
            </div>
            
            <div class="action-buttons">
              <button 
                v-if="order.status === 'pending'" 
                @click="approveOrder(order.id)" 
                class="btn-action primary-action"
              >
                قبول الطلب
              </button>

              <button 
                v-if="order.status === 'processing'" 
                @click="markReady(order.id)" 
                class="btn-action primary-action"
              >
                تحديد كـ "جاهز / مشحون"
              </button>

              <button 
                v-if="order.status === 'shipped'" 
                @click="markCompleted(order.id)" 
                class="btn-action success-action"
              >
                تأكيد التسليم
              </button>

              <span v-if="order.status === 'delivered'" class="completed-text">
                <svg class="w-5 h-5 inline-block ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                تم الإنتهاء بنجاح
              </span>

              <NuxtLink 
                :to="`/company/invoices/${order.id}`" 
                class="btn-action"
                :class="order.status === 'delivered' || order.status === 'shipped' ? 'invoice-action' : 'secondary-action'"
              >
                {{ order.status === 'delivered' || order.status === 'shipped' ? 'عرض الفاتورة' : 'مراجعة وتفاصيل الطلب' }}
              </NuxtLink>
            </div>
          </div>
          
        </div>

      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useOrders } from '~/composables/useOrders'

definePageMeta({
  layout: 'company',
  middleware: 'auth'
})

const { 
  orders, 
  fetchCompanyOrders, 
  approveOrder, 
  shipOrder, 
  deliverOrder, 
  loading, 
  error,
  mapOrderData 
} = useOrders()

const currentTab = ref<'in_progress' | 'ready' | 'completed'>('in_progress')

onMounted(() => {
  fetchCompanyOrders()
})

const inProgressOrders = computed(() => 
  orders.value.filter(o => o.status === 'processing' || o.status === 'pending').map(o => mapOrderData(o))
)

const readyOrders = computed(() => 
  orders.value.filter(o => o.status === 'shipped').map(o => mapOrderData(o))
)

const completedOrders = computed(() => 
  orders.value.filter(o => o.status === 'delivered').map(o => mapOrderData(o))
)

const displayedOrders = computed(() => {
  if (currentTab.value === 'in_progress') return inProgressOrders.value
  if (currentTab.value === 'ready') return readyOrders.value
  return completedOrders.value
})

const markReady = async (id: number) => {
  await shipOrder(id)
}

const markCompleted = async (id: number) => {
  await deliverOrder(id)
}

const getStatusLabel = (status: string) => {
  switch (status) {
    case 'pending': return 'طلب جديد (بإنتظار القبول)'
    case 'processing': return 'قيد التجهيز الآن'
    case 'shipped': return 'تم الشحن / جاهز للتسليم'
    case 'delivered': return 'مُكتمل ومُسلم'
    case 'cancelled': return 'ملغي'
    default: return status
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.orders-page {
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
  margin-bottom: 2.5rem;
}

.page-title {
  font-size: 2.2rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.5px;
}

/* Tabs */
.tabs-wrapper {
  display: flex;
  gap: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
  margin-bottom: 2.5rem;
  padding-bottom: 0px;
}

.tab-btn {
  padding: 1.25rem 2rem;
  color: #64748b;
  border: none;
  background: transparent;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1.1rem;
  font-family: inherit;
  opacity: 0.8;
}

.tab-btn:hover {
  color: #0f766e;
  opacity: 1;
}

.tab-btn.active {
  color: #0f766e;
  border-bottom-color: #0d9488;
  opacity: 1;
}

.tab-btn .icon {
  font-size: 1.2rem;
}

.fw-bold {
  font-weight: 800;
}

.tab-badge {
  background: #f1f5f9;
  color: #475569;
  padding: 0.2rem 0.8rem;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 800;
  margin-right: 0.5rem;
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
  animation: fadeIn 0.4s ease-out forwards;
}

.empty-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 1.5rem;
}

.empty-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: #475569;
  margin: 0;
}

/* Orders List */
.orders-list {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Horizontal Order Card */
.order-card {
  background: #ffffff;
  border-radius: 20px;
  padding: 1.75rem 2rem;
  box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(226, 232, 240, 0.6);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
  animation: fadeIn 0.4s ease-out forwards;
}

.order-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 15px 30px -10px rgba(13, 148, 136, 0.1);
  border-color: #99f6e4;
}

/* Primary Info (Right side RTL) */
.order-primary-info {
  flex: 1;
}

.order-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.order-id {
  background: #f1f5f9;
  color: #64748b;
  font-family: monospace;
  font-weight: 800;
  font-size: 0.9rem;
  padding: 0.3rem 0.8rem;
  border-radius: 8px;
  letter-spacing: 1px;
}

.pharmacy-name {
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.order-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 2rem;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #475569;
  font-size: 0.95rem;
  font-weight: 600;
}

.meta-item.highlight {
  background: #f0fdf4;
  padding: 0.5rem 1rem;
  border-radius: 12px;
  border: 1px solid #dcfce7;
}

.meta-label {
  color: #0f766e;
  font-size: 0.85rem;
}

.meta-value {
  color: #15803d;
  font-weight: 900;
  font-size: 1.1rem;
  letter-spacing: 0.5px;
}

/* Actions Area (Left side RTL) */
.order-actions-area {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1.5rem;
}

.status-badge {
  display: inline-block;
  padding: 0.4rem 1.2rem;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 800;
}

.status-badge.in_progress {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fef3c7;
}

.status-badge.ready {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #dcfce7;
}

.status-badge.completed {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.btn-action {
  padding: 0.8rem 1.5rem;
  border-radius: 12px;
  font-family: inherit;
  font-weight: 800;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
}

.primary-action {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
  box-shadow: 0 4px 10px rgba(217, 119, 6, 0.2);
}
.primary-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(217, 119, 6, 0.3);
}

.success-action {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  box-shadow: 0 4px 10px rgba(5, 150, 105, 0.2);
}
.success-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(5, 150, 105, 0.3);
}

.secondary-action {
  background: #ffffff;
  color: #64748b;
  border: 2px solid #e2e8f0;
}
.secondary-action:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #cbd5e1;
}

.invoice-action {
  background: #0f766e;
  text-decoration: none;
  color: white;
  box-shadow: 0 4px 10px rgba(13, 148, 136, 0.2);
}
.invoice-action:hover {
  background: #0d9488;
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(13, 148, 136, 0.3);
}

.completed-text {
  color: #10b981;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 1.05rem;
  margin-left: 1rem;
}

.w-5 { width: 1.25rem; }
.h-5 { height: 1.25rem; }

/* Responsive Adjustments */
@media (max-width: 900px) {
  .order-card.row-layout {
    flex-direction: column;
    align-items: stretch;
    gap: 1.5rem;
  }
  
  .order-actions-area {
    align-items: flex-start;
    flex-direction: row-reverse;
    justify-content: space-between;
    border-top: 1px solid #f1f5f9;
    padding-top: 1.5rem;
  }
  
  .action-buttons {
    flex-wrap: wrap;
  }
}
</style>
