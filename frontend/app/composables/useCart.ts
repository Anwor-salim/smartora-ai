import { useState } from '#app'
import { computed } from 'vue'

export interface CartItem {
  productId: string
  name: string
  unitPrice: number
  quantity: number
  stock: number // reference so we don't over-order
  image?: string
  companyId: string | number
}

export const useCart = () => {
  const cartItems = useState<CartItem[]>('pharmacy-cart', () => [])

  const addToCart = (product: any, quantity: number = 1) => {
    if (cartItems.value.length > 0) {
      if (cartItems.value[0].companyId !== product.companyId) {
        alert('لا يمكن إضافة منتجات من شركات مختلفة في نفس الطلب. تم إفراغ السلة للشركة السابقة.')
        clearCart()
      }
    }

    const existing = cartItems.value.find(item => item.productId === product.id)
    if (existing) {
      if (existing.quantity + quantity <= product.stock) {
        existing.quantity += quantity
      }
    } else {
      cartItems.value.push({
        productId: product.id,
        name: product.name,
        unitPrice: product.price,
        quantity: quantity,
        stock: product.stock,
        image: product.image,
        companyId: product.companyId
      })
    }
  }

  const removeFromCart = (productId: string) => {
    const idx = cartItems.value.findIndex(item => item.productId === productId)
    if (idx !== -1) cartItems.value.splice(idx, 1)
  }
  
  const clearCart = () => {
    cartItems.value = []
  }

  const updateQuantity = (productId: string, quantity: number) => {
    const existing = cartItems.value.find(item => item.productId === productId)
    if (existing && quantity > 0 && quantity <= existing.stock) {
      existing.quantity = quantity
    }
  }

  const cartTotal = computed(() => {
    return cartItems.value.reduce((acc, item) => acc + (item.unitPrice * item.quantity), 0)
  })
  
  const taxTotal = computed(() => {
    return cartTotal.value * 0.15
  })
  
  const finalTotal = computed(() => {
    return cartTotal.value + taxTotal.value
  })

  const cartCount = computed(() => {
    return cartItems.value.reduce((acc, item) => acc + item.quantity, 0)
  })

  return {
    cartItems,
    addToCart,
    removeFromCart,
    clearCart,
    updateQuantity,
    cartTotal,
    taxTotal,
    finalTotal,
    cartCount
  }
}
