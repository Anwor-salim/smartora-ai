<template>
  <div class="market-index-page">
    <div class="container">
      
      <!-- Search Banner -->
      <div class="search-banner">
        <div class="banner-content">
          <h1 class="page-title">ابحث عن <span class="text-white">الموردين والشركات المعتمدة</span></h1>
          <p class="page-subtitle">استكشف دليل الشركات المعتمدة وابدأ بشراء منتجاتك بسهولة وثقة.</p>
          
          <div class="search-box-wrapper">
            <input 
              type="text" 
              v-model="searchQuery" 
              class="search-input" 
              placeholder="ابحث باسم الشركة، أو التخصص..."
            />
            <button class="search-btn">
              <span class="text-xl">🔍</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Content Area -->
      <div class="main-content">
        
        <div class="section-title">
          <h2>الشركات المتاحة للطلب</h2>
          <span class="count-badge">{{ filteredCompanies.length }} شركة</span>
        </div>

        <div v-if="filteredCompanies.length === 0" class="empty-state flex-col-center">
          <span class="text-5xl mb-4">🩺</span>
          <h3>عفواً، لا توجد شركات مطابقة لبحثك</h3>
          <p>حاول استخدام كلمات مفتاحية أخرى أو إلغاء البحث.</p>
        </div>

        <div v-else class="company-grid">
          <NuxtLink 
            v-for="company in filteredCompanies" 
            :key="company.id" 
            :to="`/pharmacy/market/${company.id}`" 
            class="company-card style-none"
          >
            <div class="card-header">
              <div class="logo-circle">{{ company.logoText }}</div>
              <div class="badges">
                <span v-if="company.verified" class="badge-verified" title="شركة موثقة بالمنصة">✔️ معتمد</span>
              </div>
            </div>

            <div class="card-body">
              <h3 class="company-name">{{ company.name }}</h3>
              <p class="company-desc">{{ company.description }}</p>
              
              <div class="tags-container">
                <span v-for="tag in company.categories" :key="tag" class="cat-tag">{{ tag }}</span>
              </div>
            </div>

            <div class="card-footer">
              <div class="meta-item">
                <span class="meta-icon">⭐</span>
                <span>{{ company.rating }}</span>
              </div>
              <div class="meta-item">
                <span class="meta-icon">🚚</span>
                <span>{{ company.deliveryTime }}</span>
              </div>
            </div>
            
            <div class="hover-action">
              استكشف المنتجات ➔
            </div>
          </NuxtLink>
        </div>

      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useCompanies } from '~/composables/useCompanies'

definePageMeta({
  layout: 'pharmacy',
  middleware: 'auth'
})

const { companies, fetchCompanies } = useCompanies()
const searchQuery = ref('')

onMounted(() => {
  fetchCompanies()
})

const filteredCompanies = computed(() => {
  if (!searchQuery.value.trim()) return companies.value
  
  const query = searchQuery.value.toLowerCase().trim()
  return companies.value.filter(comp => {
    return comp.name.toLowerCase().includes(query) || 
           comp.categories.some(cat => cat.toLowerCase().includes(query)) ||
           comp.description.toLowerCase().includes(query)
  })
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

.market-index-page {
  font-family: 'Tajawal', sans-serif;
  padding-bottom: 5rem;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

.style-none { text-decoration: none; }
.flex-col-center { display: flex; flex-direction: column; align-items: center; text-align: center; justify-content: center; padding: 4rem 0; }
.text-white { color: white; }
.mb-4 { margin-bottom: 1rem; }

/* Search Banner */
.search-banner {
  background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
  border-radius: 0 0 32px 32px;
  padding: 4rem 2rem;
  margin-bottom: 3rem;
  box-shadow: 0 10px 30px rgba(37, 99, 235, 0.15);
  text-align: center;
  position: relative;
  overflow: hidden;
}

.search-banner::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -10%;
  width: 50%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
  transform: rotate(-45deg);
  pointer-events: none;
}

.banner-content {
  position: relative;
  z-index: 10;
  max-width: 700px;
  margin: 0 auto;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 900;
  color: #bfdbfe;
  margin: 0 0 1rem;
  letter-spacing: -1px;
}

.page-subtitle {
  font-size: 1.15rem;
  color: #eff6ff;
  margin: 0 0 2.5rem;
  line-height: 1.6;
}

.search-box-wrapper {
  display: flex;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 100px;
  padding: 0.5rem;
  transition: all 0.3s ease;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.search-box-wrapper:focus-within {
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.search-input {
  flex-grow: 1;
  background: transparent;
  border: none;
  padding: 1rem 1.5rem;
  font-size: 1.1rem;
  color: white;
  font-family: inherit;
  font-weight: 700;
}
.search-input::placeholder { color: rgba(255, 255, 255, 0.7); }
.search-box-wrapper:focus-within .search-input { color: #0f172a; }
.search-box-wrapper:focus-within .search-input::placeholder { color: #94a3b8; }
.search-input:focus { outline: none; }

.search-btn {
  background: #2563eb;
  border: none;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
}
.search-btn:hover { background: #1d4ed8; transform: scale(1.05); }

/* Main Content */
.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}
.section-title h2 { font-size: 1.6rem; font-weight: 900; color: #1e3a8a; margin: 0; }

.count-badge {
  background: #dbeafe;
  color: #1e40af;
  padding: 0.4rem 1rem;
  border-radius: 99px;
  font-weight: 800;
  font-size: 0.9rem;
}

.empty-state h3 { font-size: 1.4rem; color: #1e293b; margin: 0 0 0.5rem; font-weight: 800; }
.empty-state p { color: #64748b; margin: 0; }

/* Grid & Cards */
.company-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 2rem;
}

.company-card {
  background: white;
  border-radius: 24px;
  border: 1px solid #e2e8f0;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  position: relative;
  overflow: hidden;
}

.company-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px -15px rgba(37, 99, 235, 0.15);
  border-color: #bfdbfe;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.logo-circle {
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 6px rgba(0,0,0,0.02);
}

.badge-verified {
  background: #ecfdf5;
  color: #059669;
  padding: 0.4rem 0.8rem;
  border-radius: 8px;
  font-weight: 800;
  font-size: 0.8rem;
  border: 1px solid #a7f3d0;
}

.card-body { flex-grow: 1; }

.company-name { font-size: 1.4rem; font-weight: 900; color: #0f172a; margin: 0 0 0.5rem; }
.company-desc { color: #64748b; font-size: 0.95rem; line-height: 1.6; margin: 0 0 1.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.cat-tag {
  background: #f1f5f9;
  color: #475569;
  padding: 0.3rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
}

.card-footer {
  display: flex;
  gap: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px dashed #cbd5e1;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  color: #334155;
  font-weight: 700;
  font-size: 0.9rem;
}
.meta-icon { font-size: 1.1rem; }

.hover-action {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(transparent, #2563eb);
  color: white;
  padding: 2rem 1.5rem 1.5rem;
  font-weight: 900;
  text-align: center;
  transform: translateY(100%);
  transition: transform 0.3s ease;
  opacity: 0;
}

.company-card:hover .hover-action {
  transform: translateY(0);
  opacity: 1;
}

@media (max-width: 768px) {
  .search-banner { padding: 3rem 1.5rem; border-radius: 0 0 24px 24px; }
  .page-title { font-size: 2rem; }
}
</style>
