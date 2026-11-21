import { useState } from '#app'

export interface Pharmacy {
  id: number
  name: string
  address: string
  phone: string
}

export const usePharmacies = () => {
  const pharmacies = useState<Pharmacy[]>('company-pharmacies', () => [])
  const loading = useState<boolean>('pharmacies-loading', () => false)

  const fetchPharmacies = async () => {
    loading.value = true
    try {
      const response = await $fetch<any>('http://localhost:8000/api/v1/company/pharmacies', {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
          Accept: 'application/json'
        }
      })
      if (response && response.data) {
        pharmacies.value = response.data
      }
    } catch (err) {
      console.error('Error fetching pharmacies:', err)
    } finally {
      loading.value = false
    }
  }

  return {
    pharmacies,
    loading,
    fetchPharmacies
  }
}
