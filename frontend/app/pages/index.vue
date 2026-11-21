<template>
  <div class="welcome-container pointer-events-none">
    <!-- Animated background layers -->
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    
    <div class="container min-h-screen flex flex-col items-center justify-center animate-fade-in pointer-events-auto relative z-10">
      
      <!-- Brand & Intro -->
      <header class="text-center mb-12">
        <div class="logo-wrapper">
          <span class="logo-icon">⚕️</span>
        </div>
        <h1 class="brand-title">رابط فارما</h1>
        <p class="brand-subtitle">المنصة الأولى لربط الصيدليات بشركات الأدوية المعتمدة</p>
      </header>

      <!-- Selection Cards -->
      <div class="cards-grid">
        <!-- Pharmacy Card -->
        <div class="role-card glass-panel group" @click="navigateToRole('pharmacy')">
          <div class="card-icon-bg">
            <span class="card-icon">🏥</span>
          </div>
          <h2 class="card-title">أنا صيدلي</h2>
          <p class="card-desc">تصفح منتجات الشركات، اطلب الأدوية، وتابع شحناتك بسهولة.</p>
          <div class="card-action">
            <span>تسجيل الدخول / إنشاء حساب</span>
            <span class="arrow">←</span>
          </div>
        </div>

        <!-- Pharmaceutical Company Card -->
        <div class="role-card glass-panel group" @click="navigateToRole('company')">
          <div class="card-icon-bg company-bg">
            <span class="card-icon">🏢</span>
          </div>
          <h2 class="card-title">أنا شركة أدوية</h2>
          <p class="card-desc">اعرض منتجاتك، استقبل الطلبات، وتوصل مع الصيدليات مباشرة.</p>
          <div class="card-action">
            <span>تسجيل الدخول / إنشاء حساب</span>
            <span class="arrow">←</span>
          </div>
        </div>
      </div>
      
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

const navigateToRole = (role: 'pharmacy' | 'company') => {
  router.push(`/auth/${role}`)
}
</script>

<style scoped>
.pointer-events-none {
  pointer-events: none;
}
.pointer-events-auto {
  pointer-events: auto;
}
.relative {
  position: relative;
}
.z-10 {
  z-index: 10;
}
.welcome-container {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
}

/* Background Animated Blobs */
.bg-blob {
  position: absolute;
  filter: blur(80px);
  z-index: 0;
  opacity: 0.6;
  border-radius: 50%;
  animation: float 10s infinite ease-in-out alternate;
}

.blob-1 {
  top: -10%;
  left: -10%;
  width: 500px;
  height: 500px;
  background: rgba(0, 118, 139, 0.3); /* Primary */
}

.blob-2 {
  bottom: -20%;
  right: -10%;
  width: 600px;
  height: 600px;
  background: rgba(5, 150, 105, 0.2); /* Accent */
  animation-delay: -5s;
}

@keyframes float {
  0% { transform: translateY(0) scale(1); }
  100% { transform: translateY(50px) scale(1.1); }
}

/* Brand Styles */
.mb-12 {
  margin-bottom: 3rem;
}

.logo-wrapper {
  width: 80px;
  height: 80px;
  margin: 0 auto 1.5rem;
  background: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 25px rgba(0, 118, 139, 0.2);
  border: 2px solid var(--color-primary-light);
}

.logo-icon {
  font-size: 2.5rem;
}

.brand-title {
  font-size: 3rem;
  font-weight: 800;
  color: var(--color-primary-dark);
  margin-bottom: 0.5rem;
  background: linear-gradient(to right, var(--color-primary-dark), var(--color-primary));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.brand-subtitle {
  font-size: 1.25rem;
  color: var(--text-muted);
  max-width: 600px;
  margin: 0 auto;
}

/* Cards Grid */
.cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
  width: 100%;
  max-width: 900px;
}

@media (min-width: 768px) {
  .cards-grid {
    grid-template-columns: 1fr 1fr;
  }
}

/* Card Styles */
.role-card {
  padding: 2.5rem 2rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  position: relative;
  overflow: hidden;
}

.role-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--color-primary);
  transform: scaleX(0);
  transition: transform 0.4s ease;
}

.role-card:hover {
  transform: translateY(-10px);
  box-shadow: 0 20px 40px rgba(0, 118, 139, 0.15);
  border-color: rgba(0, 118, 139, 0.3);
}

.role-card:hover::before {
  transform: scaleX(1);
}

.card-icon-bg {
  width: 90px;
  height: 90px;
  margin: 0 auto 1.5rem;
  background: rgba(0, 118, 139, 0.1);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.company-bg {
  background: rgba(5, 150, 105, 0.1);
}

.role-card:hover .card-icon-bg {
  transform: scale(1.1) rotate(5deg);
}

.card-icon {
  font-size: 3rem;
}

.card-title {
  font-size: 1.75rem;
  margin-bottom: 1rem;
  color: var(--color-secondary);
}

.card-desc {
  color: var(--text-muted);
  margin-bottom: 2rem;
  line-height: 1.7;
}

/* Card Action Button */
.card-action {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--color-primary);
  font-weight: 700;
  font-size: 1.1rem;
  padding: 0.75rem 1.5rem;
  border-radius: 30px;
  background: rgba(0, 118, 139, 0.05);
  transition: all 0.3s ease;
}

.role-card:hover .card-action {
  background: var(--color-primary);
  color: white;
}

.arrow {
  transition: transform 0.3s ease;
}

.role-card:hover .arrow {
  transform: translateX(-5px); /* RTL moves left */
}
</style>