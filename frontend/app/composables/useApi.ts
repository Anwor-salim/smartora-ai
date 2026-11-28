export const useApi = () => {
  const config = useRuntimeConfig()
  const apiBase = String(config.public.apiBase || 'http://127.0.0.1:8088/api').replace(/\/+$/, '')

  const getToken = (): string | null => {
    const tokenState = useState<string | null>('auth_token', () => null)
    if (tokenState.value) return tokenState.value
    if (process.client) {
      const stored = localStorage.getItem('auth_token')
      if (stored) {
        tokenState.value = stored
        return stored
      }
    }
    return null
  }

  const request = async <T = any>(path: string, options: any = {}): Promise<T> => {
    const token = getToken()
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(options.headers || {}),
    }

    if (token) {
      headers.Authorization = `Bearer ${token}`
    }

    const cleanPath = path.startsWith('/') ? path : `/${path}`

    try {
      return await $fetch<T>(`${apiBase}${cleanPath}`, {
        ...options,
        headers,
      })
    } catch (err: any) {
      const status = err?.statusCode || err?.response?.status
      if (status === 401 && process.client) {
        localStorage.removeItem('auth_token')
        const tokenState = useState<string | null>('auth_token', () => null)
        const userState = useState<any>('auth_user', () => null)
        tokenState.value = null
        userState.value = null
        if (!window.location.pathname.startsWith('/login')) {
          navigateTo('/login')
        }
      }
      throw err
    }
  }

  const extractErrorMessage = (err: any, fallback = 'حدث خطأ غير متوقع'): string => {
    const data = err?.data || err?.response?._data || err?.response?.data
    if (data?.errors && typeof data.errors === 'object') {
      const firstKey = Object.keys(data.errors)[0]
      if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
        return data.errors[firstKey][0]
      }
    }
    return data?.message || err?.message || fallback
  }

  return {
    apiBase,
    request,
    extractErrorMessage,
  }
}
