<template>
  <div class="profile-page">
    <div class="container">
      
      <div class="header-section">
        <h1 class="page-title">إعدادات ملف الشركة</h1>
        <p class="page-subtitle">أدر تخصيص واجهة شركتك وبياناتك الرسمية للفواتير والمنصة.</p>
      </div>

      <div class="profile-grid">
        
        <!-- Left Column: Visuals / Logo -->
        <div class="panel logo-panel">
          <h2 class="section-title text-center">شعار الشركة (اللوجو)</h2>
          
          <div class="logo-preview-box">
            <template v-if="form.logoBase64">
              <img :src="form.logoBase64" alt="Company Logo" class="logo-img" />
              <button @click="removeLogo" class="btn-remove-logo" title="حذف الشعار">✕</button>
            </template>
            <template v-else>
              <div class="logo-placeholder">مساحة مخصصة للشعار</div>
            </template>
          </div>

          <div class="upload-wrapper">
            <input type="file" id="logoUpload" accept="image/png, image/jpeg" class="hidden-input" @change="handleFileUpload" />
            <label for="logoUpload" class="btn-outline-upload">
              <span class="icon">📁</span>
              اختر صورة من جهازك
            </label>
            <p class="upload-hint">ينصح برفع صورة ذات خلفية شفافة أو بيضاء ليناسب تنسيق الفواتير الـ A4.</p>
          </div>
        </div>

        <!-- Right Column (RTL logic): Core Data -->
        <div class="panel data-panel">
          <h2 class="section-title">البيانات الأساسية</h2>
          
          <form @submit.prevent="saveProfile" class="profile-form">
            
            <div class="input-group">
              <label>الاسم الرسمي (للفواتير)</label>
              <input v-model="form.name" type="text" placeholder="مثال: شركة رابط فارما المحدودة" required class="form-input">
            </div>

            <div class="input-group">
              <label>العنوان التفصيلي</label>
              <input v-model="form.location" type="text" placeholder="الرياض، حي العليا، شارع..." required class="form-input">
            </div>

            <div class="input-group">
              <label>الرقم الضريبي</label>
              <input v-model="form.taxNumber" type="text" placeholder="3000XXXXXXX0003" required class="form-input" dir="ltr">
            </div>

            <div class="form-actions mt-4 text-left">
              <button type="submit" class="btn-primary" :disabled="isSaving" :class="{'is-saving': isSaving}">
                {{ isSaving ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
              </button>
            </div>
            
            <transition name="fade">
              <div v-if="successMessage" class="alert-success">
                <span class="icon">✅</span>
                تم حفظ وتحديث بيانات الشركة بنجاح!
              </div>
            </transition>

          </form>
        </div>

      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useCompanyProfile } from '~/composables/useCompanyProfile'

definePageMeta({
  layout: 'company',
  middleware: 'auth'
})

const { profile, updateProfile } = useCompanyProfile()

const form = ref({
  name: '',
  location: '',
  taxNumber: '',
  logoBase64: null as string | null
})

const isSaving = ref(false)
const successMessage = ref(false)

onMounted(() => {
  // Load data immediately into the form clone
  form.value.name = profile.value.name
  form.value.location = profile.value.location
  form.value.taxNumber = profile.value.taxNumber
  form.value.logoBase64 = profile.value.logoBase64
})

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    const file = target.files[0]
    const reader = new FileReader()
    
    reader.onload = (e) => {
      form.value.logoBase64 = e.target?.result as string
    }
    
    reader.readAsDataURL(file)
  }
}

const removeLogo = () => {
  form.value.logoBase64 = null
}

const saveProfile = () => {
  isSaving.value = true
  // Mock artificial delay to show state
  setTimeout(() => {
    updateProfile({
      name: form.value.name,
      location: form.value.location,
      taxNumber: form.value.taxNumber,
      logoBase64: form.value.logoBase64
    })
    
    isSaving.value = false
    successMessage.value = true
    
    setTimeout(() => {
      successMessage.value = false
    }, 3000)
    
  }, 600)
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.profile-page {
  font-family: 'Tajawal', sans-serif;
  background-color: #f8fafc;
  min-height: 100vh;
  padding: 2.5rem 2rem;
}

.container {
  max-width: 1000px; /* Centered tight for settings */
  margin: 0 auto;
}

.header-section {
  margin-bottom: 3rem;
}

.page-title {
  font-size: 2.4rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 0.5rem;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

/* Layout */
.profile-grid {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 2rem;
}

.panel {
  background: white;
  border-radius: 24px;
  padding: 2rem;
  box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

.section-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 1rem;
}
.text-center { text-align: center; }
.text-left { text-align: left; }
.mt-4 { margin-top: 1.5rem; }

/* Logo Panel */
.logo-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.logo-preview-box {
  width: 180px;
  height: 180px;
  border-radius: 20px;
  border: 2px dashed #cbd5e1;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
}

.logo-placeholder {
  color: #94a3b8;
  font-size: 0.9rem;
  font-weight: 600;
  text-align: center;
  padding: 1rem;
}

.logo-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  background: white;
}

.btn-remove-logo {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(239, 68, 68, 0.9);
  color: white;
  border: none;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.btn-remove-logo:hover {
  background: #dc2626;
  transform: scale(1.1);
}

.upload-wrapper {
  width: 100%;
  text-align: center;
}

.hidden-input { display: none; }

.btn-outline-upload {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: 2px solid #0f766e;
  color: #0f766e;
  background: transparent;
  border-radius: 12px;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-bottom: 1rem;
}
.btn-outline-upload:hover {
  background: #f0fdf4;
}

.upload-hint {
  font-size: 0.8rem;
  color: #94a3b8;
  line-height: 1.5;
  margin: 0;
}

/* Data Panel / Form */
.profile-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.input-group label {
  display: block;
  font-size: 0.9rem;
  font-weight: 800;
  color: #475569;
  margin-bottom: 0.6rem;
}

.form-input {
  width: 100%;
  padding: 1rem 1.25rem;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
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

.btn-primary {
  background: linear-gradient(135deg, #0d9488, #14b8a6);
  color: white;
  padding: 1rem 2.5rem;
  border-radius: 12px;
  font-weight: 800;
  font-family: inherit;
  font-size: 1.1rem;
  border: none;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 10px rgba(13, 148, 136, 0.2);
}
.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(13, 148, 136, 0.3);
}

.is-saving {
  opacity: 0.7;
  cursor: not-allowed;
  background: #94a3b8;
  box-shadow: none;
}

.alert-success {
  margin-top: 1rem;
  padding: 1rem;
  background: #f0fdf4;
  border: 1px solid #dcfce7;
  border-radius: 12px;
  color: #15803d;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 768px) {
  .profile-grid { grid-template-columns: 1fr; }
}
</style>
