export interface ToastItem {
  id: number
  type: 'success' | 'error' | 'warning' | 'info'
  title: string
  message?: string
}

let nextToastId = 1

export const useToast = () => {
  const toasts = useState<ToastItem[]>('ui_toasts', () => [])

  const remove = (id: number) => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  const push = (type: ToastItem['type'], title: string, message?: string, duration = 4200) => {
    const id = nextToastId++
    toasts.value = [...toasts.value, { id, type, title, message }]
    if (process.client && duration > 0) {
      setTimeout(() => remove(id), duration)
    }
  }

  return {
    toasts,
    remove,
    success: (title: string, message?: string) => push('success', title, message),
    error: (title: string, message?: string) => push('error', title, message, 6000),
    warning: (title: string, message?: string) => push('warning', title, message, 5000),
    info: (title: string, message?: string) => push('info', title, message),
  }
}
