import { useState } from '#app'

export interface Product {
  id: string
  name: string
  scientificName: string
  price: number
  stock: number
  category: string
  productionDate?: string
  expiryDate?: string
  strength?: string
  batchNumber?: string
  description?: string
}

export const useProducts = () => {
  const products = useState<Product[]>('company-products', () => [])
  const loading = useState<boolean>('products-loading', () => false)

  const fetchPharmacyMarketProducts = async (companyId: string | number) => {
    loading.value = true
    try {
      const response = await $fetch<any>(`http://localhost:8000/api/v1/pharmacy/companies/${companyId}/products`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
          Accept: 'application/json'
        }
      })
      if (response && response.data) {
        products.value = response.data.map((p: any) => ({
          id: p.id,
          name: p.name,
          scientificName: p.scientific_name,
          price: parseFloat(p.price),
          stock: p.stock_quantity,
          category: p.dosage_form || 'أدوية',
          productionDate: p.production_date,
          expiryDate: p.expiry_date,
          strength: p.strength,
          batchNumber: p.batch_number,
          description: p.description,
          companyId: p.company_id
        }))
      }
    } catch (err) {
      console.error('Error fetching market products:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchCompanyProducts = async () => {
    loading.value = true
    try {
      const response = await $fetch<any>(`http://localhost:8000/api/v1/company/products`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
          Accept: 'application/json'
        }
      })
      if (response && response.data) {
        products.value = response.data.map((p: any) => ({
          id: p.id,
          name: p.name,
          scientificName: p.scientific_name,
          price: parseFloat(p.price),
          stock: p.stock_quantity,
          category: p.dosage_form || 'أدوية',
          productionDate: p.production_date,
          expiryDate: p.expiry_date,
          strength: p.strength,
          batchNumber: p.batch_number,
          description: p.description
        }))
      }
    } catch (err) {
      console.error('Error fetching products:', err)
    } finally {
      loading.value = false
    }
  }

  const addProduct = async (productData: any) => {
    loading.value = true
    try {
      const response = await $fetch<any>('http://localhost:8000/api/v1/company/products', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
          Accept: 'application/json'
        },
        body: productData
      })
      // Refresh list after adding
      await fetchCompanyProducts()
      return response.data
    } catch (err) {
      console.error('Error adding product:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    products,
    loading,
    fetchCompanyProducts,
    fetchPharmacyMarketProducts,
    addProduct
  }
}
