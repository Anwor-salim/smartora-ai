<template>
  <div class="company-layout font-sans bg-gray-50 min-h-screen dir-rtl">
    <!-- Header -->
    <header class="company-header glass-panel sticky top-0 z-50">
      <div class="container mx-auto px-4 h-20 flex items-center justify-between">
        
        <!-- Logo -->
        <NuxtLink to="/company" class="flex items-center gap-2 group style-none">
          <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center group-hover-bg transition-colors">
            <span class="text-xl">🏢</span>
          </div>
          <span class="font-bold text-xl text-teal-800">بوابة الشركات</span>
        </NuxtLink>

        <!-- Navigation & Actions -->
        <div class="flex items-center gap-6">
          
          <!-- Navigation -->
          <nav class="hidden md:flex items-center gap-4">
            <NuxtLink to="/company" class="nav-link" exact-active-class="nav-active">الرئيسية</NuxtLink>
            <NuxtLink to="/company/orders" class="nav-link" active-class="nav-active">الطلبات</NuxtLink>
            <NuxtLink to="/company/products" class="nav-link" active-class="nav-active">المنتجات</NuxtLink>
            <NuxtLink to="/company/invoices" class="nav-link" active-class="nav-active">الفواتير</NuxtLink>
          </nav>

          <div class="h-8 w-px bg-gray-200 hidden md:block"></div>

          <!-- Notification Bell -->
          <div class="relative" ref="notificationRef">
            <button @click="toggleNotifications" class="bell-btn">
              <span class="text-xl">🔔</span>
              <span v-if="pendingOrders.length > 0" class="badge animate-pulse">
                {{ pendingOrders.length }}
              </span>
            </button>

            <!-- Dropdown -->
            <transition name="dropdown">
              <div v-if="showNotifications" class="dropdown-menu glass-panel">
                <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                  <h3 class="font-bold text-gray-800 m-0">التنبيهات</h3>
                  <span class="text-xs badge-teal">{{ pendingOrders.length }} جديد</span>
                </div>
                
                <div class="p-2 bg-gray-50 border-b border-gray-100 mb-2">
                  <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">تنبيهات النظام</span>
                  <!-- System Notification Demo -->
                  <div class="flex items-center gap-2 p-2 bg-white rounded-lg border border-gray-100">
                    <span class="text-yellow-500 text-lg">⚠️</span>
                    <p class="text-xs font-semibold text-gray-700 m-0">تنبيه: اقترب نفاذ مخزون "بانادول إكسترا"</p>
                  </div>
                </div>

                <div class="notif-body">
                  <div v-if="pendingOrders.length === 0" class="empty-notif">
                    <span class="text-3xl block mb-2">📭</span>
                    لا يوجد طلبات جديدة
                  </div>
                  
                  <div v-for="order in pendingOrders" :key="order.id" class="notification-item">
                    <div class="flex-1">
                      <span class="text-[10px] font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded inline-block mb-1">طلب اعتماد مستعجل</span>
                      <p class="font-semibold text-gray-800 m-0">{{ order.pharmacyName }}</p>
                      <p class="text-sm text-gray-500 m-0 mt-1">المطلوب: {{ order.items }} منتج</p>
                      <p class="text-xs font-black text-teal-700 m-0 mt-1">{{ order.total.toFixed(2) }} ريال</p>
                    </div>
                    <button @click="openPendingModal(order)" class="view-btn">
                      مراجعة
                    </button>
                  </div>
                </div>
                
                <div class="p-2 border-t border-gray-100 text-center">
                  <NuxtLink to="/company/orders" class="text-sm link-all">إدارة كل الطلبات</NuxtLink>
                </div>
              </div>
            </transition>
          </div>

          <!-- User Profile -->
          <NuxtLink to="/company/profile" class="user-avatar" title="إعدادات الشركة">
            <span class="text-gray-500 text-sm">🏢</span>
          </NuxtLink>

          <!-- Logout Button -->
          <button @click="handleLogout" class="logout-btn" title="تسجيل الخروج">
            <span class="text-red-500 text-xl">🚪</span>
          </button>

        </div>
      </div>
    </header>

    <!-- Modal for Pending Order Review -->
    <transition name="fade">
      <div v-if="selectedOrder" class="modal-overlay" @click.self="closeModal">
        <div class="modal-content invoice-style">
          
          <div class="modal-header">
            <h2>مراجعة طلب جديد</h2>
            <button @click="closeModal" class="btn-close">×</button>
          </div>

          <div class="modal-body">
            <div class="inv-top">
              <div>
                <p class="meta-label">مقدم الطلب</p>
                <h3 class="pharma-name">{{ selectedOrder.pharmacyName }}</h3>
                <p class="meta-value">📍 {{ selectedOrder.pharmacyLocation }}</p>
              </div>
              <div class="text-left">
                <p class="meta-label">تاريخ الطلب</p>
                <p class="meta-value">{{ new Date(selectedOrder.date).toLocaleDateString('ar-SA') }}</p>
                <p class="meta-label mt-2">رقم المرجع</p>
                <p class="fw-bold">{{ selectedOrder.id }}</p>
              </div>
            </div>

            <div class="inv-table-wrapper">
              <table class="inv-table">
                <thead>
                  <tr>
                    <th>الصنف الموصوف</th>
                    <th class="text-center">الكمية</th>
                    <th class="text-center">سعر الوحدة</th>
                    <th class="text-left">الإجمالي</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in selectedOrder.orderItems" :key="item.id">
                    <td>{{ item.name }}</td>
                    <td class="text-center">{{ item.quantity }}</td>
                    <td class="text-center">{{ item.unitPrice.toFixed(2) }} SAR</td>
                    <td class="text-left fw-bold">{{ item.total.toFixed(2) }} SAR</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="inv-totals">
              <div class="tot-row">
                <span>المجموع والتكلفة:</span>
                <span>{{ selectedOrder.subTotal?.toFixed(2) || '0.00' }} SAR</span>
              </div>
              <div class="tot-row">
                <span>ضريبة القيمة المضافة (15%):</span>
                <span>{{ selectedOrder.tax?.toFixed(2) || '0.00' }} SAR</span>
              </div>
              <div class="tot-row mb-0 grand-tot">
                <span>المطلوب دفعه للإعتماد:</span>
                <span class="text-teal-700">{{ selectedOrder.total.toFixed(2) }} SAR</span>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button @click="handleReject" class="btn-reject">رفض الطلب ✕</button>
            <button @click="handleApprove" class="btn-approve">قبول الطلب وتجهيزه ✓</button>
          </div>

        </div>
      </div>
    </transition>

    <!-- Page Content -->
    <main class="page-content">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="company-footer bg-white border-t border-gray-200 mt-auto py-6">
      <div class="container mx-auto px-4 text-center">
        <p class="text-gray-500 font-semibold text-sm m-0">
          © {{ new Date().getFullYear() }} جميع الحقوق محفوظة لـ <span class="text-teal-700">رابط فارما</span>. منصة إدارة الشركات.
        </p>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOrders, type Order } from '~/composables/useOrders'
import { useAuth } from '~/composables/useAuth'

const { pendingOrders, approveOrder, rejectOrder, fetchCompanyOrders } = useOrders()
const { logout } = useAuth()
const router = useRouter()
const showNotifications = ref(false)
const notificationRef = ref<HTMLElement | null>(null)

const selectedOrder = ref<Order | null>(null)

const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
}

const handleLogout = async () => {
  await logout()
  router.push('/')
}

const openPendingModal = (order: Order) => {
  selectedOrder.value = order
  showNotifications.value = false
}

const closeModal = () => {
  selectedOrder.value = null
}

const handleApprove = () => {
  if (selectedOrder.value) {
    approveOrder(selectedOrder.value.id)
    closeModal()
  }
}

const handleReject = () => {
  if (selectedOrder.value) {
    if (confirm('تأكيد رفض هذا الطلب وإزالته نهائياً السيستم؟')) {
      rejectOrder(selectedOrder.value.id)
      closeModal()
    }
  }
}

// Click outside to close implementation
const handleClickOutside = (e: MouseEvent) => {
  if (notificationRef.value && !notificationRef.value.contains(e.target as Node)) {
    showNotifications.value = false
  }
}

let pollingInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  fetchCompanyOrders()
  document.addEventListener('click', handleClickOutside)
  
  // تحديث تلقائي للإشعارات (Polling) كل 10 ثوانٍ
  pollingInterval = setInterval(() => {
    fetchCompanyOrders()
  }, 10000)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  if (pollingInterval) clearInterval(pollingInterval)
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.dir-rtl {
  direction: rtl;
  font-family: 'Tajawal', sans-serif;
}

.style-none { text-decoration: none; }
.m-0 { margin: 0; }
.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mb-0 { margin-bottom: 0 !important; }
.text-center { text-align: center; }
.text-left { text-align: left; }
.fw-bold { font-weight: 700; }
.flex-1 { flex: 1; }
.d-flex { display: flex; }

.glass-panel {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05);
}

.nav-link {
  font-weight: 700;
  color: #64748b;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.nav-link:hover {
  color: #0f766e;
  background: #f0fdf4;
}

.nav-active {
  color: #0f766e;
  background: #ecfdf5;
}

.bell-btn {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s ease;
}
.bell-btn:hover { background: #f1f5f9; }

.badge {
  position: absolute;
  top: 0;
  right: 0;
  background: #ef4444;
  color: white;
  font-size: 0.7rem;
  font-weight: 800;
  height: 18px;
  min-width: 18px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
}

.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e2e8f0;
  border: 2px solid white;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.logout-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #fef2f2;
  border: 1px solid #fecaca;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.logout-btn:hover { background: #fecaca; }

/* Dropdown */
.dropdown-menu {
  position: absolute;
  top: calc(100% + 5px);
  left: 0;
  width: 340px;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.15);
  transform-origin: top left;
  z-index: 50;
}

.dropdown-enter-active, .dropdown-leave-active { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: scale(0.95) translateY(-5px); }

.badge-teal { background: #ccfbf1; color: #0f766e; padding: 0.2rem 0.6rem; border-radius: 999px; font-weight: 800; }

.notif-body { max-height: 350px; overflow-y: auto; }

.empty-notif { padding: 2rem; text-align: center; color: #94a3b8; font-weight: 600; }

.notification-item {
  padding: 1.25rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: background 0.2s ease;
}
.notification-item:last-child { border-bottom: none; }
.notification-item:hover { background: #f8fafc; }

.view-btn {
  background: #f1f5f9;
  color: #0f766e;
  border: 1px solid #ccfbf1;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 800;
  font-size: 0.85rem;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.2s ease;
}
.view-btn:hover { background: #0f766e; color: white; transform: translateY(-1px); }

.link-all { color: #0f766e; font-weight: 700; text-decoration: none; }
.link-all:hover { text-decoration: underline; }

/* Modal Styles */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.modal-content.invoice-style {
  background: white;
  border-radius: 24px;
  width: 100%;
  max-width: 650px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.modal-header {
  padding: 1.5rem 2rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-header h2 { margin: 0; font-size: 1.4rem; font-weight: 900; color: #0f172a; }
.btn-close { background: transparent; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer; line-height: 1; margin-top: -5px; }
.btn-close:hover { color: #dc2626; }

.modal-body {
  padding: 2rem;
  overflow-y: auto;
}

.inv-top {
  display: flex;
  justify-content: space-between;
  margin-bottom: 2rem;
}

.meta-label { color: #94a3b8; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; margin: 0 0 0.2rem; }
.pharma-name { margin: 0 0 0.5rem; font-size: 1.4rem; font-weight: 900; color: #0f172a; }
.meta-value { color: #475569; margin: 0; font-size: 0.95rem; }

.inv-table-wrapper {
  margin-bottom: 2rem;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.inv-table { width: 100%; border-collapse: collapse; }
.inv-table th { background: #f8fafc; padding: 0.75rem 1rem; color: #64748b; font-weight: 800; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; }
.inv-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 0.95rem; }
.inv-table tr:last-child td { border-bottom: none; }

.inv-totals {
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.tot-row { display: flex; justify-content: space-between; color: #475569; font-weight: 600; font-size: 0.95rem; }
.grand-tot { margin-top: 0.5rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1; font-weight: 900; font-size: 1.1rem; color: #0f172a; }

.modal-footer {
  padding: 1.5rem 2rem;
  background: white;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.btn-reject, .btn-approve {
  padding: 0.85rem 1.5rem;
  border-radius: 12px;
  font-family: inherit;
  font-weight: 800;
  font-size: 1rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-reject {
  background: #fef2f2;
  color: #dc2626;
}
.btn-reject:hover { background: #fee2e2; transform: translateY(-1px); }

.btn-approve {
  background: linear-gradient(135deg, #0d9488, #14b8a6);
  color: white;
  box-shadow: 0 4px 10px rgba(13, 148, 136, 0.2);
}
.btn-approve:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(13, 148, 136, 0.3); }

</style>
