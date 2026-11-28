<template>
  <div class="min-h-screen bg-[#F6F7F9] text-[#181A1F]">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <UiToastContainer />
  </div>
</template>

<script setup lang="ts">
const { initLocale } = useLocale()

onMounted(() => {
  initLocale()
})
</script>
