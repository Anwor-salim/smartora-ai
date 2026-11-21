import { ref } from 'vue'

export const useAuth = () => {
  const loading = ref(false)
  const error = ref<string | null>(null)
  
  // Set your Laravel API URL here
  const apiBaseUri = 'http://localhost:8000/api/v1/auth'

  const registerCompany = async (payload: any) => {
    loading.value = true
    error.value = null
    try {
      const response = await $fetch<any>(`${apiBaseUri}/register/company`, {
        method: 'POST',
        body: payload
      })
      
      if (response && response.data && response.data.token) {
        // Store token somewhere (localStorage or Nuxt state)
        if (process.client) {
          localStorage.setItem('auth_token', response.data.token)
        }
        return response.data
      }
    } catch (err: any) {
      // Extract the first validation error message from Laravel's 422 response
      const data = err.data || err.response?.data
      if (data?.errors) {
        const firstKey = Object.keys(data.errors)[0]
        error.value = data.errors[firstKey][0]
      } else {
        error.value = data?.message || err.message || 'حدث خطأ في التسجيل'
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const registerPharmacy = async (payload: any) => {
    loading.value = true
    error.value = null
    try {
      const response = await $fetch<any>(`${apiBaseUri}/register/pharmacy`, {
        method: 'POST',
        body: payload
      })
      
      if (response && response.data && response.data.token) {
        if (process.client) {
          localStorage.setItem('auth_token', response.data.token)
        }
        return response.data
      }
    } catch (err: any) {
      const data = err.data || err.response?.data
      if (data?.errors) {
        const firstKey = Object.keys(data.errors)[0]
        error.value = data.errors[firstKey][0]
      } else {
        error.value = data?.message || err.message || 'حدث خطأ في التسجيل'
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const login = async (payload: any) => {
    loading.value = true
    error.value = null
    try {
      const response = await $fetch<any>(`${apiBaseUri}/login`, {
        method: 'POST',
        body: payload
      })
      
      if (response && response.data && response.data.token) {
        if (process.client) {
          localStorage.setItem('auth_token', response.data.token)
        }
        return response.data
      }
    } catch (err: any) {
      const data = err.data || err.response?.data
      if (data?.errors) {
        const firstKey = Object.keys(data.errors)[0]
        error.value = data.errors[firstKey][0]
      } else {
        error.value = data?.message || err.message || 'بيانات الدخول غير صحيحة'
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    try {
      if (process.client) {
        const token = localStorage.getItem('auth_token')
        if (token) {
          await $fetch<any>(`${apiBaseUri}/logout`, {
            method: 'POST',
            headers: {
              Authorization: `Bearer ${token}`
            }
          })
        }
      }
    } catch (err) {
      console.warn('Backend logout failed or token already invalid', err)
    } finally {
      if (process.client) {
        localStorage.removeItem('auth_token')
      }
    }
  }

  return {
    loading,
    error,
    registerCompany,
    registerPharmacy,
    login,
    logout
  }
}
