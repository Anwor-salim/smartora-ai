<template>
  <div class="flex min-h-screen items-center justify-center bg-[#F6F7F9]">
    <div class="flex flex-col items-center gap-3 text-center">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F5B700] text-[#181A1F] shadow-sm">
        <Zap class="h-6 w-6 fill-current" />
      </div>
      <p class="text-sm font-bold text-slate-500">جاري التحقق من الجلسة...</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Zap } from 'lucide-vue-next'

const { hydrateToken, fetchMe, isAuthenticated } = useAuth()

onMounted(async () => {
  hydrateToken()
  const user = await fetchMe()
  if (user && isAuthenticated.value) {
    await navigateTo('/company', { replace: true })
  } else {
    await navigateTo('/login', { replace: true })
  }
})
</script>
