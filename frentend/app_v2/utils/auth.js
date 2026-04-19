const TOKEN_KEY = 'OA_TOKEN'

export function saveToken(token) {
  uni.setStorageSync(TOKEN_KEY, token)
}

export function loadToken() {
  return uni.getStorageSync(TOKEN_KEY) || ''
}

export function clearToken() {
  uni.removeStorageSync(TOKEN_KEY)
}
