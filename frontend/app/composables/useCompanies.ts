import { useState } from '#app'

export interface MedicalCompany {
  id: string
  name: string
  description: string
  logoText: string
  verified: boolean
  rating: number
  deliveryTime: string
  categories: string[]
}

export const useCompanies = () => {
  const companies = useState<MedicalCompany[]>('medical-companies', () => [])
  const loading = useState<boolean>('companies-loading', () => false)

  const fetchCompanies = async () => {
    loading.value = true
    try {
      const response = await $fetch<any>('http://localhost:8000/api/v1/pharmacy/companies', {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('auth_token')}`
        }
      })
      if (response && response.data) {
        // Map backend fields to frontend interface if necessary
        companies.value = response.data.map((c: any) => ({
          id: c.id,
          name: c.name,
          description: c.address || 'لا يوجد وصف متاح',
          logoText: '🏢',
          verified: c.status === 1 || c.status === true,
          rating: 4.8,
          deliveryTime: '24 ساعة',
          categories: ['أدوية']
        }))
      }
    } catch (err) {
      console.error('Error fetching companies:', err)
    } finally {
      loading.value = false
    }
  }

  return {
    companies,
    loading,
    fetchCompanies
  }
}
