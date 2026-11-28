<template>
  <div class="min-h-screen bg-[#F6F7F9] text-[#181A1F] flex">
    <!-- Mobile Backdrop -->
    <div
      v-if="mobileMenuOpen"
      class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-xs lg:hidden"
      @click="mobileMenuOpen = false"
    ></div>

    <!-- Dark Charcoal Sidebar (وقت اللزوم style) -->
    <aside
      class="fixed inset-y-0 z-50 flex w-64 shrink-0 flex-col bg-[#1F2227] text-slate-300 transition-transform duration-200 lg:static lg:translate-x-0"
      :class="[
        locale === 'ar' ? 'right-0' : 'left-0',
        mobileMenuOpen
          ? 'translate-x-0'
          : (locale === 'ar' ? 'translate-x-full lg:translate-x-0' : '-translate-x-full lg:translate-x-0')
      ]"
    >
      <!-- Sidebar Brand -->
      <div class="flex h-16 items-center justify-between border-b border-white/10 px-4">
        <NuxtLink to="/company" class="flex items-center gap-2.5 min-w-0">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#F5B700] text-[#181A1F] shadow-sm">
            <Zap class="h-5 w-5 fill-current" />
          </div>
          <div class="min-w-0">
            <p class="truncate text-sm font-black tracking-tight text-white">SmartOra AI</p>
            <p class="truncate text-[11px] font-medium text-slate-400">
              {{ activeCompany?.name || 'لوحة المنشأة' }}
            </p>
          </div>
        </NuxtLink>

        <button
          type="button"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden"
          @click="mobileMenuOpen = false"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <!-- Navigation List -->
      <nav class="sidebar-scroll flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <NuxtLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm transition duration-150"
          :class="isActiveRoute(item.to, item.exact)
            ? 'bg-[#F5B700] font-extrabold text-[#181A1F] shadow-sm'
            : 'font-medium text-slate-300 hover:bg-[#2A2E35] hover:text-white'"
        >
          <span class="flex items-center gap-3 min-w-0">
            <component :is="item.icon" class="h-4.5 w-4.5 shrink-0" />
            <span class="truncate">{{ item.label }}</span>
          </span>
          <span
            v-if="item.badge && item.badge > 0"
            class="rounded-full px-2 py-0.5 text-[11px] font-extrabold"
            :class="isActiveRoute(item.to, item.exact)
              ? 'bg-[#181A1F] text-[#F5B700]'
              : 'bg-[#F5B700]/20 text-[#F5B700]'"
          >
            {{ item.badge }}
          </span>
        </NuxtLink>
      </nav>

      <!-- Bottom Pinned Settings -->
      <div class="border-t border-white/10 p-3 space-y-1">
        <NuxtLink
          to="/company/settings"
          class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm transition duration-150"
          :class="isActiveRoute('/company/settings', false)
            ? 'bg-[#F5B700] font-extrabold text-[#181A1F] shadow-sm'
            : 'font-medium text-slate-300 hover:bg-[#2A2E35] hover:text-white'"
        >
          <Settings class="h-4.5 w-4.5 shrink-0" />
          <span>{{ t('settings') }}</span>
        </NuxtLink>
      </div>
    </aside>

    <!-- Main Column -->
    <div class="flex min-w-0 flex-1 flex-col">
      <!-- White Top Header -->
      <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-100 bg-white px-4 sm:px-6 lg:px-8">
        <!-- Mobile Menu Toggle + Active Company Pill -->
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-slate-700 hover:bg-slate-50 lg:hidden"
            @click="mobileMenuOpen = true"
          >
            <Menu class="h-5 w-5" />
          </button>

          <div v-if="isSuperAdmin && availableCompanies.length" class="hidden sm:flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-1.5 text-xs">
            <span class="font-bold text-amber-800">وضع المشرف:</span>
            <select
              :value="activeCompany?.id"
              class="bg-transparent font-extrabold text-[#181A1F] focus:outline-none"
              @change="onSwitchCompany"
            >
              <option v-for="c in availableCompanies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <!-- User Profile & Language Toggle -->
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
            @click="toggleLocale"
          >
            <Globe class="h-4 w-4 text-slate-500" />
            <span>{{ t('switchLangLabel') }}</span>
          </button>

          <!-- User Dropdown -->
          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-2.5 rounded-2xl border border-gray-100 bg-slate-50/60 px-2.5 py-1.5 transition hover:bg-slate-100"
              @click="profileMenuOpen = !profileMenuOpen"
            >
              <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#F5B700] text-sm font-black text-[#181A1F]">
                {{ userInitial }}
              </div>
              <div class="hidden text-start sm:block">
                <p class="max-w-[140px] truncate text-xs font-extrabold text-[#181A1F]">{{ user?.name || 'مدير المنشأة' }}</p>
                <p class="max-w-[140px] truncate text-[11px] text-slate-400">{{ user?.email }}</p>
              </div>
              <ChevronDown class="h-4 w-4 text-slate-400" />
            </button>

            <div
              v-if="profileMenuOpen"
              class="absolute top-full mt-2 w-60 rounded-2xl border border-gray-100 bg-white p-2 shadow-xl"
              :class="locale === 'ar' ? 'left-0' : 'right-0'"
            >
              <div class="border-b border-gray-100 px-3 py-2">
                <p class="truncate text-xs font-extrabold text-[#181A1F]">{{ activeCompany?.name }}</p>
                <p class="truncate text-[11px] text-slate-400">{{ user?.email }}</p>
              </div>

              <div v-if="isSuperAdmin && availableCompanies.length" class="border-b border-gray-100 p-2 sm:hidden">
                <label class="mb-1 block text-[11px] font-bold text-slate-500">تبديل المنشأة</label>
                <select
                  :value="activeCompany?.id"
                  class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs font-bold"
                  @change="onSwitchCompany"
                >
                  <option v-for="c in availableCompanies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>

              <NuxtLink
                to="/company/settings"
                class="mt-1 flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                @click="profileMenuOpen = false"
              >
                <Settings class="h-4 w-4 text-slate-400" />
                <span>{{ t('settings') }}</span>
              </NuxtLink>
              <button
                type="button"
                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50"
                @click="logout"
              >
                <LogOut class="h-4 w-4" />
                <span>{{ t('logout') }}</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Page Content Canvas -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-[1380px]">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  LayoutDashboard,
  CalendarCheck,
  MessageSquare,
  FileSpreadsheet,
  SlidersHorizontal,
  QrCode,
  Instagram,
  MessagesSquare,
  Bot,
  Settings,
  Menu,
  X,
  Zap,
  Globe,
  ChevronDown,
  LogOut,
} from 'lucide-vue-next'

const route = useRoute()
const { user, activeCompany, isSuperAdmin, switchCompany, logout } = useAuth()
const { locale, toggleLocale, t } = useLocale()
const { request, extractErrorMessage } = useApi()
const toast = useToast()

const mobileMenuOpen = ref(false)
const profileMenuOpen = ref(false)
const pendingAppointmentsCount = useState<number>('pending_appointments_badge', () => 0)

const availableCompanies = computed(() => user.value?.available_companies || [])
const userInitial = computed(() => {
  const name = (user.value?.name || 'س').trim()
  return name.charAt(0).toUpperCase()
})

const navItems = computed(() => [
  { to: '/company', label: t('dashboard'), icon: LayoutDashboard, exact: true },
  { to: '/company/appointments', label: t('appointments'), icon: CalendarCheck, exact: false, badge: pendingAppointmentsCount.value },
  { to: '/company/conversations', label: t('conversations'), icon: MessageSquare, exact: false },
  { to: '/company/knowledge', label: t('knowledge'), icon: FileSpreadsheet, exact: false },
  { to: '/company/booking-settings', label: t('bookingSettings'), icon: SlidersHorizontal, exact: false },
  { to: '/company/channels/whatsapp', label: t('whatsapp'), icon: QrCode, exact: false },
  { to: '/company/channels/instagram', label: t('instagram'), icon: Instagram, exact: false },
  { to: '/company/channels/messenger', label: t('messenger'), icon: MessagesSquare, exact: false },
  { to: '/company/simulator', label: t('simulator'), icon: Bot, exact: false },
])

const isActiveRoute = (path: string, exact = false) => {
  if (exact) return route.path === path
  return route.path === path || route.path.startsWith(path + '/')
}

const refreshPendingBadge = async () => {
  try {
    const res = await request<any>('/company/appointments?status=pending')
    pendingAppointmentsCount.value = res?.counts?.pending ?? 0
  } catch {
    // ignore badge fetch errors
  }
}

const onSwitchCompany = async (event: Event) => {
  const id = Number((event.target as HTMLSelectElement).value)
  if (!id) return
  try {
    const res = await switchCompany(id)
    toast.success(res.message)
    profileMenuOpen.value = false
    window.location.reload()
  } catch (err: any) {
    toast.error('تعذر التبديل', extractErrorMessage(err))
  }
}

watch(() => route.fullPath, () => {
  mobileMenuOpen.value = false
  profileMenuOpen.value = false
})

onMounted(() => {
  refreshPendingBadge()
})
</script>
