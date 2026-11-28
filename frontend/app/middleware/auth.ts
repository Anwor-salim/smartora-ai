export default defineNuxtRouteMiddleware(async (to) => {
  const { hydrateToken, fetchMe, isAuthenticated } = useAuth()
  hydrateToken()

  const user = await fetchMe()
  if (!user || !isAuthenticated.value) {
    return navigateTo(`/login?redirect=${encodeURIComponent(to.fullPath)}`)
  }
})
