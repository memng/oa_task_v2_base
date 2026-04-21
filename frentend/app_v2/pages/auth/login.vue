<template>
  <view class="page">
    <view class="logo">OA任务助手</view>

    <view class="tabs">
      <view
        v-for="tab in tabs"
        :key="tab.key"
        class="tab"
        :class="{ active: tab.key === activeTab }"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </view>
    </view>

    <view v-if="activeTab === 'account'" class="card form-card">
      <view class="form">
        <input v-model="form.mobile" placeholder="请输入11位中国大陆手机号" maxlength="11" type="number" />
        <input v-model="form.password" placeholder="请输入密码（数字和英文字符）" maxlength="20" type="password" />
        <button class="primary" :loading="loading" :disabled="loading" @click="loginByPassword">
          账号密码登录
        </button>
        <view class="info">新注册账号需后台审核通过后方可登录</view>
      </view>
    </view>

    <view v-else class="card wechat-card">
      <view class="wechat-tip">授权微信后可直接登录系统</view>
      <button class="wechat" type="default" :loading="loading" :disabled="loading" @click="loginWithWeChat">
        微信授权登录
      </button>
    </view>

    <button class="register-link" @click="goRegister">还没有账号？立即注册</button>
  </view>
</template>

<script setup>
import { reactive, ref } from 'vue'
import store from '../../store'
import { api } from '../../utils/request'
import { startMessagePolling } from '../../utils/message-center'
import { validateMobile, validatePassword } from '../../utils/validate'

const tabs = [
  { key: 'account', label: '账号登录' },
  { key: 'wechat', label: '微信登录' }
]
const activeTab = ref('account')
const form = reactive({ mobile: '', password: '' })
const loading = ref(false)

const handleLoginSuccess = (payload) => {
  store.setToken(payload.token)
  store.setProfile(payload.profile)
  startMessagePolling()
  uni.switchTab({ url: '/pages/home/index' })
}

const goRegister = () => {
  uni.navigateTo({ url: '/pages/auth/register' })
}

const loginByPassword = async () => {
  if (!form.mobile) {
    uni.showToast({ title: '请输入手机号', icon: 'none' })
    return
  }
  
  if (!validateMobile(form.mobile)) {
    uni.showToast({ title: '请输入正确的中国大陆手机号', icon: 'none' })
    return
  }
  
  if (!form.password) {
    uni.showToast({ title: '请输入密码', icon: 'none' })
    return
  }
  
  if (!validatePassword(form.password)) {
    uni.showToast({ title: '密码仅能包含数字和英文字符', icon: 'none' })
    return
  }
  loading.value = true
  try {
    const res = await api.login({
      login_type: 'password',
      mobile: form.mobile,
      password: form.password
    })
    handleLoginSuccess(res)
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const loginWithWeChat = () => {
  if (loading.value) return
  loading.value = true
  uni.login({
    provider: 'weixin',
    success: (loginRes) => {
      if (!loginRes.code) {
        uni.showToast({ title: '微信授权失败', icon: 'none' })
        loading.value = false
        return
      }
      if (typeof uni.getUserProfile === 'function') {
        uni.getUserProfile({
          desc: '用于完善会员资料',
          success: (profileRes) => {
            sendWeChatLogin(loginRes.code, profileRes.userInfo || {})
          },
          fail: () => {
            sendWeChatLogin(loginRes.code, {})
          }
        })
      } else if (typeof uni.getUserInfo === 'function') {
        uni.getUserInfo({
          success: (profileRes) => {
            sendWeChatLogin(loginRes.code, profileRes.userInfo || {})
          },
          fail: () => {
            sendWeChatLogin(loginRes.code, {})
          }
        })
      } else {
        sendWeChatLogin(loginRes.code, {})
      }
    },
    fail: () => {
      uni.showToast({ title: '微信登录失败', icon: 'none' })
      loading.value = false
    }
  })
}

const sendWeChatLogin = async (code, userInfo) => {
  try {
    const res = await api.login({
      login_type: 'wechat',
      code,
      nickname: userInfo.nickName,
      avatar_url: userInfo.avatarUrl
    })
    handleLoginSuccess(res)
  } catch (error) {
    uni.showToast({ title: '微信登录失败', icon: 'none' })
    console.error(error)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.page {
  padding: 80rpx 40rpx;
  min-height: 100vh;
  background: #f6f7fb;
  box-sizing: border-box;
}
.logo {
  font-size: 44rpx;
  font-weight: 600;
  margin-bottom: 40rpx;
  text-align: center;
}
.tabs {
  display: flex;
  background: #fff;
  border-radius: 24rpx;
  margin-bottom: 32rpx;
  overflow: hidden;
}
.tab {
  flex: 1;
  text-align: center;
  padding: 24rpx 0;
  font-size: 30rpx;
  color: #999;
}
.tab.active {
  color: #1677ff;
  font-weight: 600;
  box-shadow: inset 0 -6rpx 0 #1677ff;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 40rpx 32rpx;
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.04);
}
.form {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}
.form input {
  background: #f5f5f5;
  border-radius: 16rpx;
  padding: 28rpx 24rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
  border: none;
}
.wechat {
  background: #07c160;
  color: #fff;
  border: none;
  margin-top: 24rpx;
}
.wechat-tip {
  text-align: center;
  color: #666;
  font-size: 26rpx;
}
.info {
  font-size: 22rpx;
  color: #999;
  text-align: center;
  margin-top: 8rpx;
}
.register-link {
  margin-top: 40rpx;
  background: transparent;
  border: none;
  color: #1677ff;
  text-decoration: underline;
}
</style>
