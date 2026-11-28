export interface AuthCompany {
  id: number
  name: string
  slug?: string
  status: string
  whatsapp_number?: string | null
  subscription_expires_at?: string | null
  is_active: boolean
}

export interface AuthUser {
  id: number
  name: string
  email: string
  role: string
  phone?: string | null
  company_id: number | null
  company: AuthCompany | null
  available_companies?: Array<{ id: number; name: string; status: string }>
}

export const useAuth = () => {
  const { request, extractErrorMessage } = useApi()

  const token = useState<string | null>('auth_token', () => null)
  const user = useState<AuthUser | null>('auth_user', () => null)
  const loading = useState<boolean>('auth_loading', () => false)
  const initialized = useState<boolean>('auth_initialized', () => false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => Boolean(token.value && user.value))
  const activeCompany = computed(() => user.value?.company ?? null)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')

  const hydrateToken = () => {
    if (process.client && !token.value) {
      const saved = localStorage.getItem('auth_token')
      if (saved) token.value = saved
    }
  }

  const login = async (email: string, password: string) => {
    loading.value = true
    error.value = null
    try {
      const res = await request<{ token: string; user: AuthUser; message: string }>('/auth/login', {
        method: 'POST',
        body: { email, password },
      })

      token.value = res.token
      user.value = res.user
      initialized.value = true

      if (process.client) {
        localStorage.setItem('auth_token', res.token)
      }

      return res
    } catch (err: any) {
      error.value = extractErrorMessage(err, 'بيانات الدخول غير صحيحة')
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchMe = async (force = false): Promise<AuthUser | null> => {
    hydrateToken()
    if (!token.value) {
      initialized.value = true
      user.value = null
      return null
    }
    if (user.value && !force) {
      initialized.value = true
      return user.value
    }

    try {
      const res = await request<{ user: AuthUser }>('/auth/me')
      user.value = res.user
      initialized.value = true
      return res.user
    } catch {
      token.value = null
      user.value = null
      initialized.value = true
      if (process.client) {
        localStorage.removeItem('auth_token')
      }
      return null
    }
  }

  const switchCompany = async (companyId: number) => {
    const res = await request<{ company: AuthCompany; message: string }>('/company/switch', {
      method: 'POST',
      body: { company_id: companyId },
    })
    if (user.value) {
      user.value = {
        ...user.value,
        company_id: res.company.id,
        company: res.company,
      }
    }
    return res
  }

  const logout = async () => {
    try {
      if (token.value) {
        await request('/auth/logout', { method: 'POST' })
      }
    } catch {
      // ignore invalid token errors during logout
    } finally {
      token.value = null
      user.value = null
      initialized.value = true
      if (process.client) {
        localStorage.removeItem('auth_token')
      }
      await navigateTo('/login')
    }
  }

  return {
    token,
    user,
    loading,
    error,
    initialized,
    isAuthenticated,
    activeCompany,
    isSuperAdmin,
    hydrateToken,
    login,
    fetchMe,
    switchCompany,
    logout,
  }
}
