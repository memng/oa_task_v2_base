<template>
  <view class="app-root">
    <slot />
  </view>
</template>

<script setup>
import { onLaunch } from '@dcloudio/uni-app'
import { loadToken } from './utils/auth'
import store from './store'
import { startMessagePolling } from './utils/message-center'

onLaunch(() => {
  const token = loadToken()
  if (token) {
    store.setToken(token)
    startMessagePolling()
  }
})
</script>

<style lang="scss">
.app-root,
page {
  background-color: #f5f6f8;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Segoe UI', sans-serif;
}

scroll-view.page,
view.page,
.page {
  width: 100%;
  box-sizing: border-box;
  padding-right: 10rpx;
}
</style>
