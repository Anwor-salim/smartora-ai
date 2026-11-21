<template>
  <div class="pharmacy-layout font-sans min-h-screen dir-rtl">
    
    <!-- Pharmacy Native Navigation -->
    <header class="pharmacy-header glass-panel sticky top-0 z-50">
      <div class="container mx-auto px-4 h-20 flex items-center justify-between">
        
        <!-- Logo -->
        <NuxtLink to="/pharmacy" class="flex items-center gap-2 group style-none">
          <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center group-hover-bg transition-colors">
            <span class="text-xl">⚕️</span>
          </div>
          <span class="font-bold text-xl text-blue-900 mx-1">بوابة الصيدليات</span>
        </NuxtLink>

        <!-- Navigation Links -->
        <div class="flex items-center gap-6">
          <nav class="hidden md:flex items-center gap-4">
            <NuxtLink to="/pharmacy" class="nav-link" exact-active-class="nav-active">الرئيسية</NuxtLink>
            <NuxtLink to="/pharmacy/market" class="nav-link" active-class="nav-active">السوق الطبي</NuxtLink>
            <NuxtLink to="/pharmacy/orders" class="nav-link" active-class="nav-active">طلباتي وفواتيري</NuxtLink>
          </nav>

          <div class="h-8 w-px bg-blue-100 hidden md:block"></div>

          <!-- Actions -->
          <div class="flex items-center gap-4">
            <!-- Wallet Badge -->
            <div class="wallet-badge hidden sm:flex">
              <span class="text-indigo-600 text-sm fw-800">الرصيد المتاح:</span>
              <span class="text-blue-900 font-bold ml-1">50,000 ر.س</span>
            </div>

            <!-- Cart Icon -->
            <button class="cart-btn" @click="toggleCart">
              <span class="text-xl">🛒</span>
              <span v-if="cartCount > 0" class="badge animate-pop">
                {{ cartCount }}
              </span>
            </button>
            
            <NuxtLink to="/pharmacy/profile" class="user-avatar" title="ملف الصيدلية">
              <span class="text-blue-600 text-sm">🏪</span>
            </NuxtLink>
            
            <!-- Logout Button -->
            <button @click="handleLogout" class="logout-btn" title="تسجيل الخروج">
              <span class="text-red-500 text-xl">🚪</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Slide-over Cart View (Aesthetic placeholder for future linking) -->
    <transition name="slide-card">
      <div v-if="showCart" class="cart-sidebar">
        <div class="cart-header">
          <h3>سلة المشتريات</h3>
          <button @click="toggleCart" class="btn-close">✕</button>
        </div>
        
        <div class="cart-body">
          <div v-if="cartItems.length === 0" class="empty-cart flex-col-center">
            <span class="text-4xl mb-2">🛍️</span>
            <p>سلتك فارغة، استكشف السوق الطبي الآن!</p>
            <NuxtLink to="/pharmacy/market" @click="toggleCart" class="btn-explore">تصفح المنتجات</NuxtLink>
          </div>
          
          <div v-else class="cart-items-wrapper">
             <div v-for="item in cartItems" :key="item.productId" class="mini-cart-item">
               <div class="mini-details">
                 <p class="mc-name">{{ item.name }}</p>
                 <div class="mc-qty-controls">
                   <button @click="updateQuantity(item.productId, item.quantity - 1)" :disabled="item.quantity <= 1">-</button>
                   <span>{{ item.quantity }}</span>
                   <button @click="updateQuantity(item.productId, item.quantity + 1)" :disabled="item.quantity >= item.stock">+</button>
                 </div>
               </div>
               <div class="mc-price-action">
                 <p class="mc-total fw-bold">{{ (item.quantity * item.unitPrice).toFixed(2) }} SAR</p>
                 <button @click="removeFromCart(item.productId)" class="btn-remove">🗑️</button>
               </div>
             </div>
          </div>
        </div>
        
        <div v-if="cartItems.length > 0" class="cart-footer">
          <div class="flex justify-between w-full mb-3 text-sm text-gray-500">
            <span>الضريبة (15%):</span>
            <span>{{ taxTotal.toFixed(2) }} SAR</span>
          </div>
          <div class="flex justify-between w-full mb-4 text-blue-900 font-black text-lg">
            <span>الإجمالي المستحق:</span>
            <span>{{ finalTotal.toFixed(2) }} SAR</span>
          </div>
          <button @click="handleCheckout" class="btn-checkout w-full" :disabled="isPlacingOrder">
            {{ isPlacingOrder ? 'جاري الإرسال...' : 'إرسال الطلب للمورد' }}
          </button>
        </div>
      </div>
    </transition>
    
    <!-- Backdrop overlay for Cart -->
    <transition name="fade">
      <div v-if="showCart" class="cart-overlay" @click="toggleCart"></div>
    </transition>

    <!-- Page Content -->
    <main class="page-content">
      <slot />
    </main>

  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCart } from '~/composables/useCart'
import { useAuth } from '~/composables/useAuth'

const { cartItems, cartCount, taxTotal, finalTotal, updateQuantity, removeFromCart, clearCart } = useCart()
const { placeOrder } = useOrders()
const { logout } = useAuth()
const router = useRouter()

const showCart = ref(false)
const isPlacingOrder = ref(false)

const toggleCart = () => {
  showCart.value = !showCart.value
}

const handleLogout = async () => {
  await logout()
  router.push('/')
}

const handleCheckout = async () => {
  if (cartItems.value.length === 0) return
  isPlacingOrder.value = true
  
  try {
    const payload = {
      company_id: cartItems.value[0].companyId,
      items: cartItems.value.map(item => ({
        product_id: item.productId,
        quantity: item.quantity
      }))
    }
    
    await placeOrder(payload)
    
    // Clear cart and go to orders page
    clearCart()
    showCart.value = false
    router.push('/pharmacy/orders')
    alert('تم إرسال الطب للمورد بنجاح!')
  } catch (err: any) {
    alert(err.data?.message || 'فشل إرسال الطلب. تأكد من الكمية أو اتصالك.')
  } finally {
    isPlacingOrder.value = false
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.pharmacy-layout {
  background-color: #f0fdf4; /* A very soft mint/blue fallback */
  background: linear-gradient(180deg, #f0fdfa 0%, #f1f5f9 100%);
}

.dir-rtl {
  direction: rtl;
  font-family: 'Tajawal', sans-serif;
}

.style-none { text-decoration: none; }
.fw-800 { font-weight: 800; }
.fw-bold { font-weight: 700; }
.flex { display: flex; }
.flex-col-center { display: flex; flex-direction: column; align-items: center; text-align: center; justify-content: center; height: 100%; }
.justify-between { justify-content: space-between; }
.w-full { width: 100%; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.ml-1 { margin-left: 0.25rem; }
.mx-1 { margin-left: 0.25rem; margin-right: 0.25rem; }

/* Header Glass Layout */
.glass-panel {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(224, 231, 255, 0.7);
  box-shadow: 0 4px 20px -5px rgba(59, 130, 246, 0.08);
}

/* Nav Links - Blue Theme */
.nav-link {
  font-weight: 700;
  color: #64748b;
  padding: 0.5rem 1rem;
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.nav-link:hover {
  color: #1e40af;
  background: #eff6ff;
}

.nav-active {
  color: #1e3a8a;
  background: #e0e7ff;
  font-weight: 800;
}

/* Specific elements */
.wallet-badge {
  background: white;
  border: 1px solid #dbeafe;
  padding: 0.35rem 0.8rem;
  border-radius: 99px;
  align-items: center;
  gap: 0.2rem;
  box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.cart-btn {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 14px;
  border: none;
  background: white;
  border: 1px solid #dbeafe;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 5px rgba(59, 130, 246, 0.05);
}
.cart-btn:hover { background: #eff6ff; border-color: #bfdbfe; transform: translateY(-1px); }

.badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #2563eb;
  color: white;
  font-size: 0.75rem;
  font-weight: 900;
  height: 20px;
  min-width: 20px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
}

.animate-pop { animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes popIn { 0% { transform: scale(0); } 100% { transform: scale(1); } }

.user-avatar {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: #e0e7ff;
  border: 1px solid #c7d2fe;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
}
.user-avatar:hover { background: #c7d2fe; }

.logout-btn {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.logout-btn:hover { background: #fecaca; }

/* Slide Cart Sidebar */
.cart-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.3);
  backdrop-filter: blur(3px);
  z-index: 99;
}

.cart-sidebar {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0; /* Left side alignment for Arabic specific UX maybe? Actually lets put it on Left since RTL makes main flow Right-to-Left, sidebars often open from Left in RTL */
  width: 100%;
  max-width: 380px;
  background: white;
  z-index: 100;
  box-shadow: 10px 0 25px rgba(0,0,0,0.1);
  display: flex;
  flex-direction: column;
}

.slide-card-enter-active, .slide-card-leave-active { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-card-enter-from, .slide-card-leave-to { transform: translateX(-100%); }

.cart-header {
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.cart-header h3 { margin: 0; font-size: 1.2rem; font-weight: 800; color: #1e3a8a; }
.btn-close { background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer; }

.cart-body { flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8fafc; }

.empty-cart p { color: #64748b; font-weight: 600; margin-bottom: 1.5rem; }
.btn-explore { background: #3b82f6; color: white; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 800; text-decoration: none; }

.mini-cart-item {
  background: white;
  padding: 1rem;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  margin-bottom: 0.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mc-name { margin: 0 0 0.25rem; font-weight: 700; color: #0f172a; font-size: 0.95rem; }
.mc-price { margin: 0; color: #64748b; font-size: 0.85rem; }
.mc-price-action {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
}
.mc-total { color: #2563eb; }

.mc-qty-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.5rem;
  background: #f1f5f9;
  border-radius: 8px;
  padding: 0.2rem;
  width: fit-content;
}
.mc-qty-controls button {
  background: white;
  border: 1px solid #e2e8f0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
  color: #0f172a;
}
.mc-qty-controls button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.mc-qty-controls span {
  font-weight: 800;
  font-size: 0.9rem;
  min-width: 20px;
  text-align: center;
}

.btn-remove {
  background: #fee2e2;
  border: none;
  font-size: 0.9rem;
  cursor: pointer;
  border-radius: 6px;
  padding: 0.2rem 0.4rem;
  transition: all 0.2s;
}
.btn-remove:hover {
  background: #fecaca;
  transform: scale(1.05);
}

.cart-footer {
  padding: 1.5rem;
  background: white;
  border-top: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.btn-checkout {
  background: linear-gradient(135deg, #1d4ed8, #3b82f6);
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 12px;
  font-weight: 900;
  font-size: 1.1rem;
  cursor: pointer;
  box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
