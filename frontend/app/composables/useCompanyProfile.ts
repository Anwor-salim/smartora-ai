import { useState } from '#app'

export interface CompanyProfile {
  name: string
  location: string
  taxNumber: string
  logoBase64: string | null
}

export const useCompanyProfile = () => {
  const profile = useState<CompanyProfile>('company-profile', () => ({
    name: 'رابط فارما للتوزيع',
    location: 'المملكة العربية السعودية، الرياض',
    taxNumber: '300012345600003',
    logoBase64: null
  }))

  const updateProfile = (data: Partial<CompanyProfile>) => {
    profile.value = { ...profile.value, ...data }
  }

  return {
    profile,
    updateProfile
  }
}
