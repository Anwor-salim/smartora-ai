import { defineStore } from 'pinia'

export interface InvoiceItem {
  id?: number
  product_id: number | null
  product_name: string
  quantity: number
  unit_price: number
  discount: number
  tax_percent: number
  total: number
}

export interface Payment {
  id?: number
  amount: number
  method: 'cash' | 'card' | 'bank'
  reference_number?: string
  created_at?: string
}

export interface Invoice {
  id?: number
  invoice_number: string
  customer_id: number | null
  customer_name?: string
  type: 'cash' | 'credit' | 'return' | 'draft'
  status: 'paid' | 'unpaid' | 'partially_paid' | 'draft'
  notes?: string
  items: InvoiceItem[]
  subtotal: number
  discount_total: number
  tax_total: number
  total_amount: number
  paid_amount: number
  remaining_amount: number
  created_at?: string
}

export const useInvoiceStore = defineStore('invoice', {
  state: () => ({
    invoices: [] as Invoice[],
    currentInvoice: null as Invoice | null,
    loading: false,
    filters: {
      type: '',
      status: '',
      customer: ''
    }
  }),

  actions: {
    async fetchInvoices() {
      this.loading = true
      try {
        const query = new URLSearchParams(this.filters as any).toString()
        const response = await $fetch<any>(`http://localhost:8000/api/v1/invoices?${query}`, {
            headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        this.invoices = response.data
      } catch (error) {
        console.error('Failed to fetch invoices', error)
      } finally {
        this.loading = false
      }
    },

    async fetchInvoiceById(id: string | number) {
      this.loading = true
      try {
        const response = await $fetch<any>(`http://localhost:8000/api/v1/invoices/${id}`, {
            headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        this.currentInvoice = response.data
      } catch (error) {
        console.error('Failed to fetch invoice', error)
      } finally {
        this.loading = false
      }
    },

    async createInvoice(payload: any) {
      this.loading = true
      try {
        const response = await $fetch<any>('http://localhost:8000/api/v1/invoices', {
          method: 'POST',
          body: payload,
          headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        return response.data
      } catch (error) {
        console.error('Failed to create invoice', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async confirmInvoice(id: number) {
      try {
        await $fetch(`http://localhost:8000/api/v1/invoices/${id}/confirm`, { 
            method: 'POST',
            headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        await this.fetchInvoiceById(id)
      } catch (error) {
        console.error('Failed to confirm invoice', error)
      }
    },

    async deleteInvoice(id: number) {
      try {
        await $fetch(`http://localhost:8000/api/v1/invoices/${id}`, { 
            method: 'DELETE',
            headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        this.invoices = this.invoices.filter(inv => inv.id !== id)
      } catch (error) {
        console.error('Failed to delete invoice', error)
      }
    },

    async addPayment(invoiceId: number, payment: any) {
      try {
        const response = await $fetch<any>(`http://localhost:8000/api/v1/invoices/${invoiceId}/payments`, {
          method: 'POST',
          body: payment,
          headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        if (this.currentInvoice && this.currentInvoice.id === invoiceId) {
          this.currentInvoice.paid_amount += payment.amount
          this.currentInvoice.remaining_amount -= payment.amount
          // Refresh details to get updated status
          await this.fetchInvoiceById(invoiceId)
        }
        return response.data
      } catch (error) {
        console.error('Failed to add payment', error)
        throw error
      }
    },

    async createReturnInvoice(payload: any) {
      this.loading = true
      try {
        const response = await $fetch<any>('http://localhost:8000/api/v1/invoices/return', {
          method: 'POST',
          body: payload,
          headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
        })
        return response.data
      } catch (error) {
        console.error('Failed to create return invoice', error)
        throw error
      } finally {
        this.loading = false
      }
    }
  }
})
