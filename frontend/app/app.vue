<template>
  <div class="app-root">
    <!-- Top Navigation Bar -->
    <header class="top-nav">
      <div class="container nav-content">
        <div class="brand">
          <div class="logo-icon">⚡</div>
          <div>
            <h1 class="logo-title">SMARTORA <span class="gradient-text">AI</span></h1>
            <p class="logo-subtitle">منصة إدارة الرد الآلي الذكي للشركات</p>
          </div>
        </div>

        <div class="nav-actions">
          <div class="view-pill">
            <button class="pill-btn" :class="{ active: currentView === 'admin' }" @click="currentView = 'admin'">
              👑 لوحة السوبر أدمن
            </button>
            <button class="pill-btn" :class="{ active: currentView === 'company' }" @click="currentView = 'company'">
              🏢 لوحة المشترك (الشركة)
            </button>
          </div>

          <button class="btn btn-primary btn-sm" @click="showSimulator = true">
            <span>💬 محاكي الواتساب الذكي</span>
          </button>

          <div class="notif-wrapper">
            <button class="icon-btn" @click="showNotifs = !showNotifs">
              🔔
              <span v-if="unreadCount > 0" class="notif-badge">{{ unreadCount }}</span>
            </button>

            <div v-if="showNotifs" class="notif-dropdown glass-card">
              <div class="notif-header">
                <h4>التنبيهات اللحظية</h4>
                <span class="text-muted text-xs">{{ notifications.length }} تنبيه</span>
              </div>
              <div class="notif-list">
                <div v-for="n in notifications" :key="n.id" class="notif-item" :class="{ unread: !n.is_read }">
                  <div class="notif-icon">
                    <span v-if="n.type === 'new_customer'">👤</span>
                    <span v-else-if="n.type === 'success'">✅</span>
                    <span v-else>ℹ️</span>
                  </div>
                  <div class="notif-body">
                    <strong>{{ n.title }}</strong>
                    <p>{{ n.message }}</p>
                    <span class="notif-time">{{ formatDate(n.created_at) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="user-pill">
            <div class="avatar">{{ currentView === 'admin' ? 'A' : 'C' }}</div>
            <div class="user-text">
              <span class="user-name">{{ currentView === 'admin' ? 'أنور سالم (السوبر أدمن)' : activeCompany?.name || 'شركة مشتركة' }}</span>
              <span class="user-role">{{ currentView === 'admin' ? 'مدير المنصة العام' : 'حساب المشترك' }}</span>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-body container">
      <!-- 1. SUPER ADMIN VIEW -->
      <section v-if="currentView === 'admin'" class="admin-view">
        <div class="stats-grid">
          <div class="stat-card glass-card">
            <div class="stat-icon icon-indigo">🏢</div>
            <div class="stat-info">
              <span class="stat-label">إجمالي الشركات المسجلة</span>
              <h2 class="stat-value">{{ stats.total_companies || companies.length }}</h2>
            </div>
          </div>

          <div class="stat-card glass-card">
            <div class="stat-icon icon-emerald">✅</div>
            <div class="stat-info">
              <span class="stat-label">الاشتراكات النشطة</span>
              <h2 class="stat-value text-emerald">{{ stats.active_companies || activeCompaniesCount }}</h2>
            </div>
          </div>

          <div class="stat-card glass-card">
            <div class="stat-icon icon-rose">⏳</div>
            <div class="stat-info">
              <span class="stat-label">المتوقفة أو المنتهية</span>
              <h2 class="stat-value text-rose">{{ stats.suspended_companies || 0 }}</h2>
            </div>
          </div>

          <div class="stat-card glass-card">
            <div class="stat-icon icon-purple">💬</div>
            <div class="stat-info">
              <span class="stat-label">إجمالي محادثات الواتساب</span>
              <h2 class="stat-value text-purple">{{ stats.total_conversations || 0 }}</h2>
            </div>
          </div>
        </div>

        <div class="action-bar glass-card">
          <div class="search-box">
            <input v-model="searchQuery" type="text" placeholder="🔍 ابحث بالاسم، رقم الواتساب، الهاتف..." class="form-control" />
          </div>

          <div class="filter-group">
            <button class="filter-chip" :class="{ active: filterStatus === 'all' }" @click="filterStatus = 'all'">
              الكل ({{ companies.length }})
            </button>
            <button class="filter-chip" :class="{ active: filterStatus === 'active' }" @click="filterStatus = 'active'">
              النشطة فقط
            </button>
            <button class="filter-chip" :class="{ active: filterStatus === 'suspended' }" @click="filterStatus = 'suspended'">
              المتوقفة
            </button>
          </div>

          <button class="btn btn-primary" @click="showAddCompanyModal = true">
            <span>➕ إضافة شركة جديدة باشتراك</span>
          </button>
        </div>

        <div class="glass-card table-section">
          <div class="table-title">
            <h3>قائمة الشركات والمشتركين في خدمة الرد الآلي</h3>
            <span class="text-muted text-sm">إدارة الاشتراكات والتحكم اللحظي بصلاحية الرد عبر الواتساب</span>
          </div>

          <div class="table-wrapper">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>الشركة</th>
                  <th>رقم الواتساب المربوط</th>
                  <th>حساب المسؤول</th>
                  <th>الباقة</th>
                  <th>مدة الاشتراك</th>
                  <th>المستندات المرفقة</th>
                  <th>حالة الرد الآلي</th>
                  <th>إجراءات التحكم</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="comp in filteredCompanies" :key="comp.id">
                  <td>
                    <div class="company-cell">
                      <strong>{{ comp.name }}</strong>
                      <span class="text-muted text-xs">{{ comp.address || 'بدون عنوان محدد' }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="phone-pill">📱 {{ comp.whatsapp_number || 'لم يُربط بعد' }}</span>
                  </td>
                  <td>
                    <span class="text-xs">{{ comp.admin_user?.email || comp.email || '—' }}</span>
                  </td>
                  <td><span class="badge badge-info">{{ comp.subscription_plan }}</span></td>
                  <td>
                    <div class="date-cell">
                      <span>ينتهي: {{ comp.subscription_expires_at || 'مفتوح' }}</span>
                      <small v-if="comp.days_remaining !== null" :class="comp.days_remaining <= 5 ? 'text-rose' : 'text-emerald'">
                        (متبقي {{ comp.days_remaining }} يوم)
                      </small>
                    </div>
                  </td>
                  <td><span class="badge badge-secondary">{{ comp.documents_count || 0 }} ملف</span></td>
                  <td>
                    <span v-if="comp.is_active" class="badge badge-success">● يعمل بنشاط</span>
                    <span v-else class="badge badge-danger">✕ متوقف</span>
                  </td>
                  <td>
                    <div class="btn-group">
                      <button class="btn btn-sm" :class="comp.status === 'active' ? 'btn-danger' : 'btn-success'" @click="toggleCompanyStatus(comp)">
                        {{ comp.status === 'active' ? 'إيقاف' : 'تفعيل' }}
                      </button>
                      <button class="btn btn-secondary btn-sm" @click="extendCompanySubscription(comp)">+30 يوم</button>
                      <button class="btn btn-secondary btn-sm" @click="selectCompanyPortal(comp)">إدارة 📁</button>
                      <button class="btn btn-danger btn-sm" @click="deleteCompany(comp)">🗑️</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
      <!-- 2. COMPANY PORTAL VIEW -->
      <section v-else class="company-view">
        <div class="company-banner glass-card">
          <div class="banner-info">
            <div class="banner-badge">🏢 لوحة تحكم المنشأة</div>
            <h2>{{ activeCompany?.name || 'شركة سمارتورا' }}</h2>
            <p class="text-secondary text-sm">
              رقم الواتساب الحالي: <strong>{{ activeCompany?.whatsapp_number || 'غير محدد' }}</strong> | 
              حالة الاشتراك: 
              <span :class="activeCompany?.is_active ? 'text-emerald' : 'text-rose'">
                {{ activeCompany?.is_active ? 'نشط ويعمل' : 'متوقف / منتهي' }}
              </span>
              (متبقي {{ activeCompany?.days_remaining ?? '—' }} يوم)
            </p>
          </div>
          <div class="banner-actions">
            <button class="btn btn-primary" @click="showSimulator = true">
              ⚡ تجربة الرد الذكي الآن
            </button>
          </div>
        </div>

        <div class="portal-nav glass-card">
          <button class="tab-btn" :class="{ active: companyTab === 'knowledge' }" @click="companyTab = 'knowledge'">
            📊 البيانات وقاعدة المعرفة (Excel & PDF)
          </button>
          <button class="tab-btn" :class="{ active: companyTab === 'settings' }" @click="companyTab = 'settings'">
            ⚙️ إعدادات التواصل ورقم الواتساب
          </button>
          <button class="tab-btn" :class="{ active: companyTab === 'chats' }" @click="companyTab = 'chats'">
            💬 محادثات العملاء الواردة
          </button>
        </div>

        <!-- TAB 1: KNOWLEDGE BASE -->
        <div v-if="companyTab === 'knowledge'" class="tab-content">
          <div class="upload-card glass-card">
            <div class="upload-header">
              <h3>📂 إرفاق وتحديث بيانات الرد الآلي (Excel / PDF)</h3>
              <p class="text-secondary text-sm">
                ارفع جداول الأسعار، قوائم الأصناف، الفواتير، الكتالوجات، أو الخدمات. يقوم محرك سمارتورا باستخراج النصوص والأسعار تلقائياً ليجيب الذكاء الاصطناعي منها بدقة.
              </p>
            </div>

            <div class="drop-zone" @click="$refs.fileInput.click()">
              <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv,.pdf,.txt" class="hidden-file-input" @change="handleFileUpload" />
              <div class="drop-icon">📑</div>
              <h4>انقر هنا لرفع ملف (Excel أو PDF)</h4>
              <p class="text-muted text-xs">يدعم ملفات: XLSX, CSV, PDF, TXT (الحد الأقصى 20 ميجابايت)</p>
              <div v-if="uploading" class="uploading-spinner">
                ⏳ جاري قراءة واستخراج البيانات والأسعار...
              </div>
            </div>
          </div>

          <div class="glass-card docs-section">
            <div class="section-title">
              <h3>المستندات والملفات المعتمدة في الرد الآلي ({{ documents.length }})</h3>
            </div>

            <div v-if="documents.length === 0" class="empty-state">
              <p>لم تقم برفع أي ملفات حتى الآن. ابدأ برفع ملف إكسل أو PDF بالأصناف والأسعار ليتمكن الذكاء الاصطناعي من الإجابة عنها فوراً!</p>
            </div>

            <div v-else class="table-wrapper">
              <table class="custom-table">
                <thead>
                  <tr>
                    <th>اسم المستند</th>
                    <th>نوع الملف</th>
                    <th>الحجم</th>
                    <th>الأصناف / البيانات المستخرجة</th>
                    <th>تاريخ الرفع</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="doc in documents" :key="doc.id">
                    <td><strong>{{ doc.title }}</strong></td>
                    <td>
                      <span class="badge" :class="doc.file_type === 'pdf' ? 'badge-danger' : 'badge-success'">
                        {{ doc.file_type.toUpperCase() }}
                      </span>
                    </td>
                    <td>{{ doc.formatted_size }}</td>
                    <td><span class="badge badge-info">{{ doc.total_items }} صنف/عنصر</span></td>
                    <td>{{ doc.created_at }}</td>
                    <td><span class="badge badge-success">✓ جاهز للرد</span></td>
                    <td>
                      <button class="btn btn-danger btn-sm" @click="deleteDocument(doc.id)">حذف 🗑️</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 2: SETTINGS -->
        <div v-if="companyTab === 'settings'" class="tab-content">
          <div class="glass-card settings-card">
            <h3>⚙️ معلومات التواصل ورقم الواتساب للبوت</h3>
            <p class="text-secondary text-sm mb-4">هذه البيانات يتم الرجوع إليها تلقائياً عندما يسأل العميل عن الموقع، الأوقات، أو أرقام التواصل.</p>

            <form @submit.prevent="saveCompanySettings" class="settings-form">
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">رقم الواتساب المربوط بالرد الآلي</label>
                  <input v-model="companyForm.whatsapp_number" type="text" class="form-control" placeholder="967771314738" />
                </div>
                <div class="form-group">
                  <label class="form-label">رقم الهاتف البديل</label>
                  <input v-model="companyForm.phone" type="text" class="form-control" />
                </div>
                <div class="form-group">
                  <label class="form-label">البريد الإلكتروني للشركة</label>
                  <input v-model="companyForm.email" type="email" class="form-control" />
                </div>
                <div class="form-group">
                  <label class="form-label">أوقات وساعات الدوام</label>
                  <input v-model="companyForm.working_hours" type="text" class="form-control" />
                </div>
                <div class="form-group full-width">
                  <label class="form-label">العنوان والموقع الجغرافي</label>
                  <input v-model="companyForm.address" type="text" class="form-control" />
                </div>
                <div class="form-group full-width">
                  <label class="form-label">رابط خرائط جوجل (Google Maps)</label>
                  <input v-model="companyForm.google_maps_url" type="url" class="form-control" />
                </div>
                <div class="form-group full-width">
                  <label class="form-label">رسالة الترحيب التلقائية للعميل الجديد</label>
                  <textarea v-model="companyForm.greeting_message" rows="2" class="form-control"></textarea>
                </div>
                <div class="form-group full-width">
                  <label class="form-label">تلقين مخصص للبوت الذكي (Prompt)</label>
                  <textarea v-model="companyForm.custom_system_prompt" rows="3" class="form-control" placeholder="تعليمات خاصة بطريقة إجابة البوت..."></textarea>
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary" :disabled="savingSettings">
                  {{ savingSettings ? 'جاري الحفظ...' : '💾 حفظ وتحديث الإعدادات' }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- TAB 3: CHATS -->
        <div v-if="companyTab === 'chats'" class="tab-content">
          <div class="glass-card">
            <h3>💬 سجل رسائل الزبائن مع البوت ({{ conversations.length }})</h3>
            <div v-if="conversations.length === 0" class="empty-state">
              <p>لا توجد محادثات مسجلة حتى الآن. عندما يرسل أي زبون رسالة، ستظهر هنا فوراً!</p>
            </div>
            <div v-else class="table-wrapper">
              <table class="custom-table">
                <thead>
                  <tr>
                    <th>رقم الزبون</th>
                    <th>الاسم</th>
                    <th>آخر رسالة</th>
                    <th>تاريخ آخر تفاعل</th>
                    <th>الحالة</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in conversations" :key="c.id">
                    <td><strong>{{ c.customer_phone }}</strong></td>
                    <td>{{ c.customer_name || 'عميل واتساب' }}</td>
                    <td class="truncate-cell">{{ c.last_message }}</td>
                    <td>{{ formatDate(c.last_interaction_at) }}</td>
                    <td><span class="badge badge-success">نشط</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- MODAL: ADD COMPANY -->
    <div v-if="showAddCompanyModal" class="modal-overlay" @click.self="showAddCompanyModal = false">
      <div class="modal-card glass-card">
        <div class="modal-header">
          <h3>➕ إضافة شركة جديدة ومنح اشتراك</h3>
          <button class="close-btn" @click="showAddCompanyModal = false">✕</button>
        </div>
        <form @submit.prevent="createCompany" class="modal-form">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">اسم الشركة *</label>
              <input v-model="newCompany.name" type="text" required class="form-control" />
            </div>
            <div class="form-group">
              <label class="form-label">رقم الواتساب المربوط *</label>
              <input v-model="newCompany.whatsapp_number" type="text" required class="form-control" placeholder="967771234567" />
            </div>
            <div class="form-group">
              <label class="form-label">مدة الاشتراك *</label>
              <select v-model="newCompany.subscription_months" class="form-control">
                <option value="1">شهر واحد</option>
                <option value="3">3 أشهر</option>
                <option value="6">6 أشهر</option>
                <option value="12">سنة كاملة</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">الهاتف</label>
              <input v-model="newCompany.phone" type="text" class="form-control" />
            </div>
            <div class="form-group full-width">
              <label class="form-label">العنوان</label>
              <input v-model="newCompany.address" type="text" class="form-control" />
            </div>
            <div class="divider full-width"><span>بيانات حساب دخول الشركة</span></div>
            <div class="form-group">
              <label class="form-label">اسم المسؤول *</label>
              <input v-model="newCompany.user_name" type="text" required class="form-control" />
            </div>
            <div class="form-group">
              <label class="form-label">بريد تسجيل الدخول *</label>
              <input v-model="newCompany.user_email" type="email" required class="form-control" />
            </div>
            <div class="form-group full-width">
              <label class="form-label">كلمة المرور *</label>
              <input v-model="newCompany.user_password" type="password" required class="form-control" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showAddCompanyModal = false">إلغاء</button>
            <button type="submit" class="btn btn-primary" :disabled="creatingCompany">
              {{ creatingCompany ? 'جاري التفعيل...' : '✓ إنشاء وتفعيل الاشتراك فوراً' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: AI SIMULATOR -->
    <div v-if="showSimulator" class="modal-overlay" @click.self="showSimulator = false">
      <div class="chat-modal glass-card">
        <div class="chat-header">
          <div class="chat-user">
            <div class="chat-avatar">🤖</div>
            <div>
              <strong>مساعد الواتساب الذكي (محاكاة حية)</strong>
              <p class="text-xs text-muted">يجيب بناءً على بيانات وملفات الشركة المحددة</p>
            </div>
          </div>
          <div class="chat-controls">
            <select v-model="selectedSimCompanyId" class="form-control form-control-sm">
              <option v-for="c in companies" :key="c.id" :value="c.id">
                🏢 {{ c.name }} ({{ c.is_active ? 'نشط' : 'متوقف' }})
              </option>
            </select>
            <button class="close-btn" @click="showSimulator = false">✕</button>
          </div>
        </div>

        <div class="chat-screen" ref="chatScreen">
          <div class="chat-bubble bot">
            <div class="bubble-content">
              👋 مرحباً بك! أنا مساعد الذكاء الاصطناعي لـ ({{ getSimCompanyName() }}).
              تفضل بطرح أي سؤال عن الأسعار، الأصناف، الموقع، أو الخدمات وسأجيبك فوراً استناداً إلى البيانات المرفقة.
            </div>
          </div>
          <div v-for="(m, idx) in simMessages" :key="idx" class="chat-bubble" :class="m.sender">
            <div class="bubble-content">{{ m.text }}</div>
          </div>
          <div v-if="simLoading" class="chat-bubble bot typing">
            <div class="bubble-content">⏳ جاري مراجعة البيانات وصياغة الرد...</div>
          </div>
        </div>

        <div class="quick-prompts">
          <button @click="sendQuickPrompt('ما هي أوقات دوامكم وعنوانكم؟')">📍 الموقع والدوام؟</button>
          <button @click="sendQuickPrompt('ما هي الأسعار والأصناف المتوفرة لديكم؟')">💰 الأسعار والأصناف؟</button>
          <button @click="sendQuickPrompt('كيف يمكنني التواصل معكم؟')">📞 التواصل؟</button>
        </div>

        <form @submit.prevent="sendSimMessage" class="chat-input-bar">
          <input v-model="simInput" type="text" placeholder="اكتب استفساراً كما لو كنت عميلاً على الواتساب..." class="form-control" :disabled="simLoading" />
          <button type="submit" class="btn btn-primary" :disabled="simLoading || !simInput.trim()">إرسال 🚀</button>
        </form>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'

const apiBase = 'http://127.0.0.1:8088/api'
let authToken = ''

const currentView = ref('admin')
const companyTab = ref('knowledge')

const companies = ref([])
const activeCompany = ref(null)
const stats = ref({})
const notifications = ref([])
const unreadCount = ref(0)
const documents = ref([])
const conversations = ref([])

const showAddCompanyModal = ref(false)
const showSimulator = ref(false)
const showNotifs = ref(false)

const searchQuery = ref('')
const filterStatus = ref('all')
const uploading = ref(false)
const creatingCompany = ref(false)
const savingSettings = ref(false)

const newCompany = ref({
  name: '',
  phone: '',
  whatsapp_number: '',
  address: '',
  subscription_months: 1,
  subscription_plan: 'monthly_pro',
  user_name: '',
  user_email: '',
  user_password: 'password123'
})

const companyForm = ref({
  name: '',
  phone: '',
  whatsapp_number: '',
  email: '',
  address: '',
  google_maps_url: '',
  working_hours: '',
  greeting_message: '',
  custom_system_prompt: '',
})

const selectedSimCompanyId = ref(null)
const simInput = ref('')
const simLoading = ref(false)
const simMessages = ref([])
const chatScreen = ref(null)

const activeCompaniesCount = computed(() => companies.value.filter(c => c.is_active).length)

const filteredCompanies = computed(() => {
  return companies.value.filter(c => {
    const matchesSearch = !searchQuery.value || 
      c.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      c.whatsapp_number?.includes(searchQuery.value) ||
      c.phone?.includes(searchQuery.value)
      
    const matchesFilter = filterStatus.value === 'all' ||
      (filterStatus.value === 'active' && c.is_active) ||
      (filterStatus.value === 'suspended' && !c.is_active)

    return matchesSearch && matchesFilter
  })
})

function getSimCompanyName() {
  const found = companies.value.find(c => c.id === selectedSimCompanyId.value)
  return found ? found.name : 'المنشأة'
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('ar-YE', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function loginAsAdmin() {
  try {
    const res = await fetch(`${apiBase}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: 'admin@smartora.com', password: 'password123' })
    })
    const data = await res.json()
    if (data.token) {
      authToken = data.token
    }
  } catch (err) {
    console.error('Login error:', err)
  }
}

async function fetchAdminDashboard() {
  try {
    const [dashRes, compRes, notifRes] = await Promise.all([
      fetch(`${apiBase}/admin/dashboard`, { headers: { 'Authorization': `Bearer ${authToken}` } }),
      fetch(`${apiBase}/admin/companies`, { headers: { 'Authorization': `Bearer ${authToken}` } }),
      fetch(`${apiBase}/admin/notifications`, { headers: { 'Authorization': `Bearer ${authToken}` } }),
    ])

    const dashData = await dashRes.json()
    const compData = await compRes.json()
    const notifData = await notifRes.json()

    stats.value = dashData.stats || {}
    companies.value = compData.companies || []
    notifications.value = notifData.notifications || []
    unreadCount.value = notifications.value.filter(n => !n.is_read).length

    if (companies.value.length > 0 && !selectedSimCompanyId.value) {
      selectedSimCompanyId.value = companies.value[0].id
      selectCompanyPortal(companies.value[0])
    }
  } catch (err) {
    console.error('Fetch error:', err)
  }
}

function selectCompanyPortal(company) {
  activeCompany.value = company
  selectedSimCompanyId.value = company.id
  
  companyForm.value = {
    name: company.name,
    phone: company.phone || '',
    whatsapp_number: company.whatsapp_number || '',
    email: company.email || '',
    address: company.address || '',
    google_maps_url: company.google_maps_url || '',
    working_hours: company.working_hours || '',
    greeting_message: company.settings?.greeting_message || '',
    custom_system_prompt: company.custom_system_prompt || '',
  }

  fetchCompanyDocuments(company.id)
  fetchCompanyConversations(company.id)
  currentView.value = 'company'
}

async function fetchCompanyDocuments(companyId) {
  try {
    const res = await fetch(`${apiBase}/company/documents`, {
      headers: { 'Authorization': `Bearer ${authToken}` }
    })
    const data = await res.json()
    documents.value = data.documents || []
  } catch (err) {
    console.error('Fetch docs error:', err)
  }
}

async function fetchCompanyConversations(companyId) {
  try {
    const res = await fetch(`${apiBase}/company/conversations`, {
      headers: { 'Authorization': `Bearer ${authToken}` }
    })
    const data = await res.json()
    conversations.value = data.conversations || []
  } catch (err) {
    console.error('Fetch convs error:', err)
  }
}

async function handleFileUpload(e) {
  const file = e.target.files[0]
  if (!file) return

  uploading.value = true
  const formData = new FormData()
  formData.append('file', file)
  formData.append('title', file.name)

  try {
    const res = await fetch(`${apiBase}/company/documents/upload`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${authToken}` },
      body: formData
    })
    const data = await res.json()
    if (res.ok) {
      alert('✅ ' + data.message)
      await fetchCompanyDocuments(activeCompany.value?.id)
      await fetchAdminDashboard()
    } else {
      alert('❌ ' + (data.message || 'فشلت معالجة الملف'))
    }
  } catch (err) {
    alert('حدث خطأ أثناء رفع الملف')
  } finally {
    uploading.value = false
    e.target.value = ''
  }
}

async function deleteDocument(id) {
  if (!confirm('هل أنت متأكد من حذف هذا الملف من قاعدة المعرفة؟')) return

  try {
    await fetch(`${apiBase}/company/documents/${id}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${authToken}` }
    })
    documents.value = documents.value.filter(d => d.id !== id)
    await fetchAdminDashboard()
  } catch (err) {
    console.error('Delete doc error:', err)
  }
}

async function saveCompanySettings() {
  savingSettings.value = true
  try {
    const res = await fetch(`${apiBase}/company/profile`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`
      },
      body: JSON.stringify(companyForm.value)
    })
    const data = await res.json()
    if (res.ok) {
      alert('✅ تم حفظ وتحديث بيانات الشركة بنجاح!')
      await fetchAdminDashboard()
    }
  } catch (err) {
    alert('حدث خطأ أثناء حفظ الإعدادات')
  } finally {
    savingSettings.value = false
  }
}

async function toggleCompanyStatus(company) {
  try {
    const res = await fetch(`${apiBase}/admin/companies/${company.id}/toggle-status`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${authToken}` }
    })
    const data = await res.json()
    if (res.ok) {
      company.status = data.status
      company.is_active = data.is_active
      await fetchAdminDashboard()
    }
  } catch (err) {
    console.error('Toggle status error:', err)
  }
}

async function extendCompanySubscription(company) {
  const days = prompt('كم يوماً تريد تمديد الاشتراك؟', '30')
  if (!days) return

  try {
    const res = await fetch(`${apiBase}/admin/companies/${company.id}/extend-subscription`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`
      },
      body: JSON.stringify({ days: parseInt(days) })
    })
    const data = await res.json()
    if (res.ok) {
      alert('✅ ' + data.message)
      await fetchAdminDashboard()
    }
  } catch (err) {
    console.error('Extend error:', err)
  }
}

async function deleteCompany(company) {
  if (!confirm(`هل أنت متأكد من حذف شركة (${company.name}) نهائياً؟`)) return

  try {
    await fetch(`${apiBase}/admin/companies/${company.id}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${authToken}` }
    })
    await fetchAdminDashboard()
  } catch (err) {
    console.error('Delete error:', err)
  }
}

async function createCompany() {
  creatingCompany.value = true
  try {
    const res = await fetch(`${apiBase}/admin/companies`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`
      },
      body: JSON.stringify(newCompany.value)
    })
    const data = await res.json()
    if (res.ok) {
      alert('✅ تم إنشاء الشركة وتفعيل اشتراكها بنجاح!')
      showAddCompanyModal.value = false
      newCompany.value = {
        name: '',
        phone: '',
        whatsapp_number: '',
        address: '',
        subscription_months: 1,
        subscription_plan: 'monthly_pro',
        user_name: '',
        user_email: '',
        user_password: 'password123'
      }
      await fetchAdminDashboard()
    } else {
      alert('❌ ' + (data.message || 'حدث خطأ أثناء إنشاء الشركة'))
    }
  } catch (err) {
    alert('حدث خطأ في الاتصال بالخادم')
  } finally {
    creatingCompany.value = false
  }
}

function sendQuickPrompt(prompt) {
  simInput.value = prompt
  sendSimMessage()
}

async function sendSimMessage() {
  const text = simInput.value.trim()
  if (!text || simLoading.value) return

  simMessages.value.push({ sender: 'user', text })
  simInput.value = ''
  simLoading.value = true

  await scrollToBottom()

  try {
    const res = await fetch(`${apiBase}/ai/test-chat`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`
      },
      body: JSON.stringify({
        message: text,
        company_id: selectedSimCompanyId.value
      })
    })

    const data = await res.json()
    simMessages.value.push({
      sender: 'bot',
      text: data.reply || 'تمت معالجة الاستفسار بنجاح'
    })
  } catch (err) {
    simMessages.value.push({
      sender: 'bot',
      text: '⚠️ تعذر الاتصال بمحرك الذكاء الاصطناعي، يرجى المحاولة ثانية.'
    })
  } finally {
    simLoading.value = false
    await scrollToBottom()
  }
}

async function scrollToBottom() {
  await nextTick()
  if (chatScreen.value) {
    chatScreen.value.scrollTop = chatScreen.value.scrollHeight
  }
}

onMounted(async () => {
  await loginAsAdmin()
  await fetchAdminDashboard()
})
</script>

<style scoped>
.top-nav { position: sticky; top: 0; z-index: 100; background: rgba(10, 13, 20, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); padding: 14px 0; }
.nav-content { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.brand { display: flex; align-items: center; gap: 12px; }
.logo-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--accent-gradient); display: flex; align-items: center; justify-content: center; font-size: 22px; }
.logo-title { font-size: 20px; font-weight: 800; letter-spacing: 0.5px; }
.logo-subtitle { font-size: 11px; color: var(--text-muted); }
.nav-actions { display: flex; align-items: center; gap: 16px; }
.view-pill { display: flex; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); padding: 4px; border-radius: 30px; gap: 4px; }
.pill-btn { background: transparent; border: none; color: var(--text-secondary); padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; }
.pill-btn.active { background: var(--accent-primary); color: #fff; }
.icon-btn { background: rgba(255, 255, 255, 0.06); border: 1px solid var(--border-color); color: var(--text-primary); width: 40px; height: 40px; border-radius: 10px; cursor: pointer; position: relative; font-size: 16px; display: flex; align-items: center; justify-content: center; }
.notif-badge { position: absolute; top: -4px; left: -4px; background: var(--danger); color: #fff; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 10px; }
.notif-wrapper { position: relative; }
.notif-dropdown { position: absolute; top: 50px; left: 0; width: 320px; max-height: 400px; overflow-y: auto; z-index: 1000; box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5); }
.notif-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); margin-bottom: 10px; }
.notif-item { display: flex; gap: 10px; padding: 10px; border-radius: 8px; margin-bottom: 8px; background: rgba(255, 255, 255, 0.02); font-size: 13px; }
.notif-item.unread { background: rgba(99, 102, 241, 0.1); border-right: 3px solid var(--accent-primary); }
.notif-time { display: block; font-size: 11px; color: var(--text-muted); margin-top: 4px; }
.user-pill { display: flex; align-items: center; gap: 10px; background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color); padding: 6px 14px; border-radius: 30px; }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-primary); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #fff; }
.user-text { display: flex; flex-direction: column; }
.user-name { font-size: 13px; font-weight: 700; }
.user-role { font-size: 11px; color: var(--text-muted); }
.main-body { padding-top: 32px; padding-bottom: 60px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 28px; }
.stat-card { display: flex; align-items: center; gap: 18px; }
.stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.icon-indigo { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
.icon-emerald { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.icon-rose { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.icon-purple { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
.stat-label { font-size: 13px; color: var(--text-secondary); }
.stat-value { font-size: 28px; font-weight: 800; margin-top: 4px; }
.text-emerald { color: #34d399; }
.text-rose { color: #f87171; }
.text-purple { color: #c084fc; }
.action-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.search-box { flex: 1; min-width: 280px; }
.filter-group { display: flex; gap: 8px; }
.filter-chip { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 8px 16px; border-radius: 10px; font-size: 13px; cursor: pointer; }
.filter-chip.active { background: rgba(99, 102, 241, 0.2); color: var(--accent-primary); border-color: var(--accent-primary); font-weight: 700; }
.table-section { padding: 24px; }
.table-title { margin-bottom: 20px; }
.company-cell { display: flex; flex-direction: column; }
.phone-pill { font-family: monospace; direction: ltr; display: inline-block; background: rgba(255, 255, 255, 0.05); padding: 4px 8px; border-radius: 6px; font-size: 13px; }
.date-cell { display: flex; flex-direction: column; font-size: 13px; }
.btn-group { display: flex; gap: 6px; }
.company-banner { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.08) 100%); border: 1px solid rgba(99, 102, 241, 0.25); }
.banner-badge { font-size: 12px; color: var(--accent-primary); font-weight: 700; margin-bottom: 6px; }
.portal-nav { display: flex; gap: 12px; padding: 10px; margin-bottom: 24px; }
.tab-btn { flex: 1; background: transparent; border: none; color: var(--text-secondary); padding: 12px 18px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; }
.tab-btn.active { background: rgba(99, 102, 241, 0.15); color: #fff; border: 1px solid rgba(99, 102, 241, 0.3); }
.upload-card { margin-bottom: 24px; }
.upload-header { margin-bottom: 18px; }
.drop-zone { border: 2px dashed rgba(99, 102, 241, 0.4); border-radius: 16px; padding: 40px; text-align: center; cursor: pointer; background: rgba(99, 102, 241, 0.03); }
.drop-zone:hover { background: rgba(99, 102, 241, 0.08); border-color: var(--accent-primary); }
.drop-icon { font-size: 48px; margin-bottom: 12px; }
.hidden-file-input { display: none; }
.uploading-spinner { margin-top: 14px; font-weight: 700; color: var(--accent-primary); }
.empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.full-width { grid-column: span 2; }
.divider { display: flex; align-items: center; margin: 16px 0 8px; color: var(--accent-primary); font-weight: 700; font-size: 14px; }
.form-actions { margin-top: 24px; display: flex; justify-content: flex-end; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
.modal-card { width: 100%; max-width: 650px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
.close-btn { background: transparent; border: none; color: var(--text-muted); font-size: 20px; cursor: pointer; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); }
.chat-modal { width: 100%; max-width: 580px; height: 650px; display: flex; flex-direction: column; padding: 0; overflow: hidden; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.7); }
.chat-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: rgba(255, 255, 255, 0.03); border-bottom: 1px solid var(--border-color); }
.chat-user { display: flex; align-items: center; gap: 12px; }
.chat-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--accent-gradient); display: flex; align-items: center; justify-content: center; font-size: 20px; }
.chat-controls { display: flex; align-items: center; gap: 10px; }
.chat-screen { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; background: rgba(0, 0, 0, 0.2); }
.chat-bubble { max-width: 80%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; }
.chat-bubble.bot { align-self: flex-start; background: rgba(255, 255, 255, 0.06); border: 1px solid var(--border-color); color: var(--text-primary); border-bottom-right-radius: 4px; }
.chat-bubble.user { align-self: flex-end; background: var(--accent-gradient); color: #fff; border-bottom-left-radius: 4px; }
.quick-prompts { display: flex; gap: 8px; padding: 8px 16px; overflow-x: auto; background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border-color); }
.quick-prompts button { white-space: nowrap; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 6px 12px; border-radius: 20px; font-size: 12px; cursor: pointer; }
.chat-input-bar { display: flex; gap: 10px; padding: 14px 18px; border-top: 1px solid var(--border-color); background: rgba(18, 24, 38, 0.95); }
@media (max-width: 900px) { .nav-content { flex-direction: column; align-items: stretch; } .form-grid { grid-template-columns: 1fr; } .full-width { grid-column: span 1; } }
</style>