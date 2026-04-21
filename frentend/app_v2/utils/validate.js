export const validateMobile = (mobile) => {
  const mobileReg = /^1[3-9]\d{9}$/
  return mobileReg.test(mobile)
}

export const validatePassword = (password) => {
  const passwordReg = /^[a-zA-Z0-9]+$/
  return passwordReg.test(password)
}
