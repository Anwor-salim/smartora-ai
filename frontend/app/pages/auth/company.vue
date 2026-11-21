<template>
  <div class="auth-wrapper font-sans">
    <!-- Animated Background -->
    <div class="bg-blob blob-1 company-blob"></div>
    <div class="bg-blob blob-2 company-blob-2"></div>

    <div class="container relative z-10 min-h-screen flex items-center justify-center py-10 px-4">
      <div class="auth-card glass-panel group">
        
        <!-- Back Button -->
        <button @click="router.push('/')" class="back-btn" title="العودة للصفحة الرئيسية">
          &rarr;
        </button>

        <header class="text-center mb-8">
          <div class="card-icon-bg mx-auto company-bg">
            <span class="text-4xl" style="color: #0d9488;">🏢</span>
          </div>
          <h1 class="text-3xl font-bold" style="background: linear-gradient(to right, #0f766e, #14b8a6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            بوابة شركات الأدوية
          </h1>
          <p class="text-[var(--text-muted)] mt-2">
            {{ isLogin ? 'سجل دخولك لإدارة منتجاتك وعملائك' : 'سجل شركتك وابدأ بالتوريد للصيدليات' }}
          </p>
        </header>

        <!-- Toggle Switch -->
        <div class="toggle-container glass-panel-inner company-inner mb-8 flex relative">
          <button 
            class="toggle-btn" 
            :class="{ 'active company-active': isLogin }" 
            @click="isLogin = true"
          >
            تسجيل الدخول
          </button>
          <button 
            class="toggle-btn" 
            :class="{ 'active company-active': !isLogin }" 
            @click="isLogin = false"
          >
            حساب جديد
          </button>
        </div>

        <!-- Forms Container -->
        <div class="form-container">
          
          <!-- Login Form -->
          <form v-if="isLogin" @submit.prevent="handleLogin" class="form-fade-in flex flex-col gap-4">
            <div v-if="error" class="text-red-500 text-sm mb-2 text-center p-2 bg-red-50 rounded-lg">{{ error }}</div>

            <div class="input-group">
              <label>البريد الإلكتروني</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">✉</span>
                <input type="email" v-model="loginForm.email" placeholder="contact@company.com" required dir="ltr">
              </div>
            </div>
            
            <div class="input-group">
              <label>كلمة المرور</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">🔑</span>
                <input type="password" v-model="loginForm.password" placeholder="••••••••" required dir="ltr">
              </div>
              <div class="text-left mt-1">
                <a href="#" class="text-sm font-medium hover:underline" style="color: #0d9488;">نسيت كلمة المرور؟</a>
              </div>
            </div>

            <button type="submit" class="submit-btn company-btn mt-2" :disabled="loading">
              <span v-if="loading">جاري الدخول...</span>
              <span v-else>دخول</span>
            </button>
          </form>

          <!-- Register Form -->
          <form v-else @submit.prevent="handleRegister" class="form-fade-in flex flex-col gap-4">
            <div v-if="error" class="text-red-500 text-sm mb-2 text-center p-2 bg-red-50 rounded-lg">{{ error }}</div>

            <div class="input-group">
              <label>اسم الشركة التجاري</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">🏢</span>
                <input type="text" v-model="registerForm.company_name" placeholder="مثال: شركة النور للأدوية" required>
              </div>
            </div>

            <div class="input-group">
              <label>السجل التجاري</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">📄</span>
                <input type="text" v-model="registerForm.commercial_register" placeholder="1010123456" required dir="ltr">
              </div>
            </div>

            <div class="input-group">
              <label>رقم الهاتف</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">📞</span>
                <input type="text" v-model="registerForm.phone" placeholder="0500000000" required dir="ltr">
              </div>
            </div>

            <div class="input-group">
              <label>البريد الإلكتروني</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">✉</span>
                <input type="email" v-model="registerForm.email" placeholder="contact@company.com" required dir="ltr">
              </div>
            </div>

            <div class="input-group">
              <label>كلمة المرور</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">🔑</span>
                <input type="password" v-model="registerForm.password" placeholder="••••••••" required dir="ltr">
              </div>
            </div>

            <div class="input-group">
              <label>تأكيد كلمة المرور</label>
              <div class="input-wrapper company-wrapper">
                <span class="icon">🔑</span>
                <input type="password" v-model="registerForm.password_confirmation" placeholder="••••••••" required dir="ltr">
              </div>
            </div>

            <button type="submit" class="submit-btn company-btn mt-2" :disabled="loading">
              <span v-if="loading">جاري التسجيل...</span>
              <span v-else>تسجيل الشركة</span>
            </button>
          </form>

        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '~/composables/useAuth'

const router = useRouter()
const isLogin = ref<boolean>(true)
const { login, registerCompany, loading, error } = useAuth()

const loginForm = reactive({
  email: '',
  password: '',
  account_type: 'company'
})

const registerForm = reactive({
  company_name: '',
  commercial_register: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: ''
})

const handleLogin = async () => {
  try {
    error.value = null
    await login(loginForm)
    router.push('/company')
  } catch (e) {
    // Error is handled by the composable
  }
}

const handleRegister = async () => {
  try {
    error.value = null
    const payload = {
      ...registerForm,
      name: registerForm.company_name,
      address: 'عنوان غير محدد'
    }
    await registerCompany(payload)
    router.push('/company')
  } catch (e) {
    // Error is handled by the composable
  }
}
</script>

<style scoped>
.auth-wrapper {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
  direction: rtl;
}

.bg-blob {
  position: absolute;
  filter: blur(80px);
  z-index: 0;
  opacity: 0.6;
  border-radius: 50%;
  animation: float 10s infinite ease-in-out alternate;
}
.company-blob {
  top: -10%; left: -10%; width: 500px; height: 500px;
  background: rgba(5, 150, 105, 0.3);
}
.company-blob-2 {
  bottom: -20%; right: -10%; width: 600px; height: 600px;
  background: rgba(4, 120, 87, 0.2);
  animation-delay: -5s;
}

@keyframes float {
  0% { transform: translateY(0) scale(1); }
  100% { transform: translateY(50px) scale(1.1); }
}

.glass-panel {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 30px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
}

.auth-card {
  width: 100%;
  max-width: 480px;
  margin: 0 auto;
  padding: 3rem 2.5rem;
  position: relative;
}

.back-btn {
  position: absolute;
  top: 1.5rem;
  right: 1.5rem;
  background: rgba(5, 150, 105, 0.05);
  color: #059669;
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  cursor: pointer;
  transition: all 0.3s ease;
}
.back-btn:hover {
  background: #059669;
  color: white;
  transform: translateX(-3px);
}

.card-icon-bg {
  width: 80px;
  height: 80px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}
.company-bg {
  background: rgba(5, 150, 105, 0.1);
}

.glass-panel-inner {
  background: rgba(255, 255, 255, 0.6);
  border-radius: 15px;
  padding: 0.5rem;
  border: 1px solid rgba(5, 150, 105, 0.1);
}

.toggle-btn {
  flex: 1;
  padding: 0.75rem 0;
  text-align: center;
  border-radius: 10px;
  font-weight: 600;
  color: #9ca3af;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
  background: transparent;
  cursor: pointer;
}

.toggle-btn.company-active {
  background: white;
  color: #047857;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.form-fade-in {
  animation: fadeIn 0.4s ease-out forwards;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.input-group label {
  display: block;
  font-size: 0.9rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrapper .icon {
  position: absolute;
  right: 1rem;
  color: #9ca3af;
  font-size: 1.1rem;
}

.input-wrapper input {
  width: 100%;
  padding: 0.85rem 3rem 0.85rem 1rem;
  border-radius: 12px;
  border: 1.5px solid #e5e7eb;
  background: #f9fafb;
  font-size: 1rem;
  transition: all 0.3s ease;
  outline: none;
}

.company-wrapper input:focus {
  border-color: #059669;
  background: white;
  box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
}

.input-wrapper input[dir="ltr"] {
  padding: 0.85rem 3rem 0.85rem 1rem;
}

.submit-btn {
  width: 100%;
  padding: 1rem;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1.1rem;
  color: white;
  border: none;
  cursor: pointer;
  transition: all 0.3s ease;
}

.company-btn {
  background: linear-gradient(to right, #047857, #10b981);
}

.company-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(5, 150, 105, 0.2);
}

.submit-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.submit-btn:active {
  transform: translateY(0);
}
</style>
