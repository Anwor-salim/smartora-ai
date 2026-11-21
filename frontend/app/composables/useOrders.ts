import { ref, computed } from 'vue'

const BASE_URL = 'http://localhost:8000/api/v1'

function getToken(): string {
  if (process.client) {
    return localStorage.getItem('auth_token') || ''
  }
  return ''
}

export interface Order {
  id: number
  pharmacyName?: string
  companyName?: string
  pharmacyLocation?: string
  date: string
  status: string
  items: number
  total: number
  subTotal?: number
  tax?: number
  orderItems?: any[]
  notes?: string
}

const globalOrders   = ref<any[]>([])
const globalLoading  = ref(false)
const globalError    = ref<string | null>(null)
const globalMeta     = ref<any>(null)

export const useOrders = () => {
  const orders   = globalOrders
  const loading  = globalLoading
  const error    = globalError
  const meta     = globalMeta

  // Company-specific: Filter pending orders for notice bells/dashboards
  const pendingOrders = computed(() => 
    orders.value.filter((o: any) => o.status === 'pending')
      .map((o: any) => mapOrderData(o))
  )

  const mapOrderData = (o: any): Order => ({
    id: o.id,
    pharmacyName: o.pharmacy?.name || 'صيدلية غير معروفة',
    companyName: o.company?.name || 'شركة غير معروفة',
    pharmacyLocation: o.pharmacy?.address || 'الموقع غير محدد',
    date: o.created_at,
    status: o.status,
    items: o.items_count || (o.items ? o.items.length : 0),
    total: parseFloat(o.total_amount || 0),
    subTotal: parseFloat(o.total_amount || 0) / 1.15,
    tax: parseFloat(o.total_amount || 0) - (parseFloat(o.total_amount || 0) / 1.15),
    orderItems: o.items || [],
    notes: o.notes || ''
  })

  // Fetch orders for a company
  const fetchCompanyOrders = async (status: string = '') => {
    loading.value = true
    error.value   = null
    try {
      const params = status ? `?status=${status}` : ''
      const response = await $fetch<any>(`${BASE_URL}/company/orders${params}`, {
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        }
      })
      orders.value = Array.isArray(response.data) ? response.data : (response.data?.data || [])
      meta.value   = response.data
    } catch (err: any) {
      error.value = err.data?.message || 'فشل تحميل طلبات الشركة'
    } finally {
      loading.value = false
    }
  }

  // Fetch orders for a pharmacy
  const fetchPharmacyOrders = async () => {
    loading.value = true
    error.value   = null
    try {
      const response = await $fetch<any>(`${BASE_URL}/pharmacy/orders`, {
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        }
      })
      orders.value = Array.isArray(response.data) ? response.data : (response.data?.data || [])
      meta.value   = response.data
    } catch (err: any) {
      error.value = err.data?.message || 'فشل تحميل طلبات الصيدلية'
    } finally {
      loading.value = false
    }
  }

  // Place a new order (Pharmacy)
  const placeOrder = async (payload: any) => {
    loading.value = true
    error.value   = null
    try {
      const response = await $fetch<any>(`${BASE_URL}/pharmacy/orders`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        },
        body: payload
      })
      return response.data
    } catch (err: any) {
      error.value = err.data?.message || 'فشل إرسال الطلب'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Update order status (Company)
  const updateOrderStatus = async (orderId: number, status: string) => {
    loading.value = true
    error.value   = null
    try {
      const response = await $fetch<any>(`${BASE_URL}/company/orders/${orderId}/status`, {
        method: 'PATCH',
        body: { status },
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        }
      })
      const idx = orders.value.findIndex((o: any) => o.id === orderId)
      if (idx !== -1) orders.value[idx] = response.data
      return response.data
    } catch (err: any) {
      error.value = err.data?.message || 'فشل تحديث الحالة'
      throw err
    } finally {
      loading.value = false
    }
  }

  const approveOrder = (id: number) => updateOrderStatus(id, 'processing')
  const rejectOrder = (id: number) => updateOrderStatus(id, 'cancelled')
  const shipOrder = (id: number) => updateOrderStatus(id, 'shipped')
  const deliverOrder = (id: number) => updateOrderStatus(id, 'delivered')

  return {
    orders,
    pendingOrders,
    loading,
    error,
    meta,
    mapOrderData,
    fetchCompanyOrders,
    fetchPharmacyOrders,
    placeOrder,
    updateOrderStatus,
    approveOrder,
    rejectOrder,
    shipOrder,
    deliverOrder
  }
}
