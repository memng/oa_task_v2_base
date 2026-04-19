<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="title">基础身份信息</view>
      <view class="form-item" v-for="field in basicFields" :key="field.key">
        <text>{{ field.label }}</text>
        <input v-model="form[field.key]" :placeholder="field.placeholder" />
      </view>
    </view>
    <view class="card">
      <view class="title">银行卡信息</view>
      <view class="form-item" v-for="field in bankFields" :key="field.key">
        <text>{{ field.label }}</text>
        <input v-model="form[field.key]" :placeholder="field.placeholder" />
      </view>
    </view>
    <view class="card">
      <view class="title">隐私授权</view>
      <view class="tips">
        <view>本人本人承诺所有信息真实有效，如有信息虚假将承担法律责任</view>
      </view>
    </view>
    <button class="submit" :loading="submitting" :disabled="submitting" @click="submit">
      保存资料
    </button>
  </scroll-view>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import store from '../../store'
import { api } from '../../utils/request'

const form = reactive({
  name: '',
  id_card: '',
  address: '',
  bank_card_no: '',
  bank_account_name: '',
  bank_name: ''
})

const submitting = ref(false)

const basicFields = [
  { key: 'name', label: '真实姓名', placeholder: '请输入真实姓名' },
  { key: 'id_card', label: '身份证号', placeholder: '请输入18位身份证号码' },
  { key: 'address', label: '联系地址', placeholder: '请输入居住地' }
]

const bankFields = [
  { key: 'bank_card_no', label: '银行卡号', placeholder: '请输入工资卡卡号' },
  { key: 'bank_account_name', label: '持卡人姓名', placeholder: '请输入持卡人姓名' },
  { key: 'bank_name', label: '开户银行', placeholder: '请输入开户银行' }
]

const fieldKeys = [...new Set([...basicFields, ...bankFields].map((field) => field.key))]

const fillForm = (profile = {}) => {
  fieldKeys.forEach((key) => {
    const value = profile[key]
    form[key] = typeof value === 'string' ? value : value || ''
  })
}

const fetchProfile = async () => {
  try {
    const res = await api.profile()
    if (res && res.profile) {
      store.setProfile(res.profile)
      fillForm(res.profile)
    }
  } catch (error) {
    console.error(error)
  }
}

const submit = async () => {
  if (submitting.value) return
  submitting.value = true
  const payload = fieldKeys.reduce((acc, key) => {
    const value = form[key]
    acc[key] = typeof value === 'string' ? value.trim() : value
    return acc
  }, {})
  try {
    const res = await api.updateProfile(payload)
    if (res && res.profile) {
      store.setProfile(res.profile)
      fillForm(res.profile)
    }
    uni.showToast({ title: '资料已更新', icon: 'success' })
  } catch (error) {
    console.error(error)
  } finally {
    submitting.value = false
  }
}

watch(
  () => store.state.profile,
  (value) => {
    if (value) {
      fillForm(value)
    }
  },
  { immediate: true }
)

onShow(() => {
  fetchProfile()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.form-item {
  margin-bottom: 16rpx;
}
.form-item text {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
}
.form-item input {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.tips {
  color: #999;
  font-size: 24rpx;
}
.submit {
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
</style>
