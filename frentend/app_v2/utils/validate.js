export const validateMobile = (mobile) => {
  const mobileReg = /^1[3-9]\d{9}$/
  return mobileReg.test(mobile)
}

export const validatePassword = (password) => {
  const passwordReg = /^[a-zA-Z0-9]+$/
  return passwordReg.test(password)
}

export const validateRequiredFields = (formData, fields) => {
  for (const field of fields) {
    if (!formData[field.key]) {
      const message = field.message || `请填写${field.label}`
      uni.showToast({ title: message, icon: 'none' })
      return false
    }
  }
  return true
}
