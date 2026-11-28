export const useLocale = () => {
  const locale = useState<'ar' | 'en'>('app_locale', () => 'ar')

  const applyHtmlAttrs = (lang: 'ar' | 'en') => {
    if (process.client && typeof document !== 'undefined') {
      document.documentElement.lang = lang
      document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr'
    }
  }

  const initLocale = () => {
    if (process.client) {
      const saved = localStorage.getItem('smartora_locale')
      if (saved === 'en' || saved === 'ar') {
        locale.value = saved
      }
      applyHtmlAttrs(locale.value)
    }
  }

  const toggleLocale = () => {
    const next: 'ar' | 'en' = locale.value === 'ar' ? 'en' : 'ar'
    locale.value = next
    if (process.client) {
      localStorage.setItem('smartora_locale', next)
    }
    applyHtmlAttrs(next)
  }

  const dict: Record<string, { ar: string; en: string }> = {
    dashboard: { ar: 'لوحة التحكم', en: 'Dashboard' },
    appointments: { ar: 'الحجوزات والمواعيد', en: 'Appointments' },
    conversations: { ar: 'محادثات العملاء', en: 'Conversations' },
    knowledge: { ar: 'قاعدة المعرفة', en: 'Knowledge Base' },
    bookingSettings: { ar: 'إعدادات الحجز و Sheets', en: 'Booking & Sheets' },
    whatsapp: { ar: 'ربط الواتساب', en: 'WhatsApp' },
    instagram: { ar: 'ربط انستقرام', en: 'Instagram' },
    messenger: { ar: 'ربط ماسنجر فيسبوك', en: 'Messenger' },
    simulator: { ar: 'محاكي البوت', en: 'Bot Simulator' },
    settings: { ar: 'الإعدادات', en: 'Settings' },
    logout: { ar: 'تسجيل الخروج', en: 'Sign Out' },
    switchLangLabel: { ar: 'English', en: 'العربية' },
  }

  const t = (key: string) => dict[key]?.[locale.value] ?? key

  return {
    locale,
    initLocale,
    toggleLocale,
    t,
  }
}
