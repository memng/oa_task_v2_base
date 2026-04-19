<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="title">账号信息</view>
      <view class="form-item">
        <text>真实姓名</text>
        <input v-model="form.name" placeholder="请输入真实姓名" />
      </view>
      <view class="form-item">
        <text>手机号</text>
        <input v-model="form.mobile" placeholder="请输入11位中国大陆手机号" type="number" maxlength="11" />
      </view>
      <view class="form-item">
        <text>登录密码</text>
        <input v-model="form.password" placeholder="请输入至少8位（数字和英文字符）" type="password" />
      </view>
      <view class="form-item">
        <text>确认密码</text>
        <input v-model="form.confirm_password" placeholder="请再次输入密码" type="password" />
      </view>
      <view class="form-item">
        <text>所属部门</text>
        <picker mode="selector" :range="departments" range-key="name" @change="onDeptChange">
          <view class="picker-value" :class="{ placeholder: !form.dept_id }">{{ deptName }}</view>
        </picker>
      </view>
    </view>

    <view class="card">
      <view class="title">资料信息</view>
      <view class="form-item">
        <text>身份证号</text>
        <input v-model="form.id_card" placeholder="请输入身份证号码" maxlength="18" />
      </view>
      <view class="form-item">
        <text>联系地址</text>
        <input v-model="form.address" placeholder="请输入联系地址" />
      </view>
      <view class="form-item">
        <text>银行卡号</text>
        <input v-model="form.bank_card_no" placeholder="请输入工资卡卡号" type="number" />
      </view>
      <view class="form-item">
        <text>持卡人姓名</text>
        <input v-model="form.bank_account_name" placeholder="请输入持卡人姓名" />
      </view>
      <view class="form-item">
        <text>开户银行</text>
        <input v-model="form.bank_name" placeholder="请输入开户行信息" />
      </view>
    </view>

    <view class="card">
      <view class="title">微信绑定</view>
      <view class="wechat-row">
        <view>
          <view class="wechat-status">{{ wechatStatus }}</view>
          <view class="wechat-desc">绑定后可一键登录</view>
        </view>
        <button size="mini" type="primary" :loading="binding" @click="bindWechat">
          {{ wechatInfo.code ? '重新绑定' : '立即绑定' }}
        </button>
      </view>
    </view>

    <button class="submit" :loading="submitting" :disabled="submitting" @click="submit">
      提交注册
    </button>
  </scroll-view>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const form = reactive({
  name: '',
  mobile: '',
  password: '',
  confirm_password: '',
  dept_id: '',
  id_card: '',
  address: '',
  bank_account_name: '',
  bank_name: '',
  bank_card_no: ''
})

const departments = ref([])
const binding = ref(false)
const submitting = ref(false)
const wechatInfo = reactive({
  code: '',
  nickname: '',
  avatar_url: ''
})

const deptName = computed(() => {
  if (!form.dept_id) {
    return '请选择部门'
  }
  const match = departments.value.find((item) => item.id === form.dept_id)
  return match ? match.name : '请选择部门'
})

const wechatStatus = computed(() => {
  if (!wechatInfo.code) {
    return '未绑定微信'
  }
  return `已绑定${wechatInfo.nickname || '微信账号'}`
})

const fetchDepartments = async () => {
  try {
    const res = await api.departments()
    departments.value = res.departments || []
  } catch (error) {
    console.error(error)
  }
}

const onDeptChange = (event) => {
  const index = Number(event.detail.value)
  const target = departments.value[index]
  if (target) {
    form.dept_id = target.id
  }
}

const bindWechat = () => {
  if (binding.value) return
  binding.value = true
  uni.login({
    provider: 'weixin',
    success: (loginRes) => {
      if (!loginRes.code) {
        uni.showToast({ title: '授权失败', icon: 'none' })
        binding.value = false
        return
      }
      const onProfileSuccess = (profileRes) => {
        wechatInfo.code = loginRes.code
        wechatInfo.nickname = profileRes?.userInfo?.nickName || ''
        wechatInfo.avatar_url = profileRes?.userInfo?.avatarUrl || ''
        binding.value = false
        uni.showToast({ title: '微信绑定成功', icon: 'success' })
      }
      const onProfileFail = () => {
        wechatInfo.code = loginRes.code
        wechatInfo.nickname = ''
        wechatInfo.avatar_url = ''
        binding.value = false
        uni.showToast({ title: '微信绑定成功', icon: 'success' })
      }
      if (typeof uni.getUserProfile === 'function') {
        uni.getUserProfile({
          desc: '用于完善会员资料',
          success: onProfileSuccess,
          fail: onProfileFail
        })
      } else if (typeof uni.getUserInfo === 'function') {
        uni.getUserInfo({
          success: onProfileSuccess,
          fail: onProfileFail
        })
      } else {
        onProfileFail()
      }
    },
    fail: () => {
      uni.showToast({ title: '微信授权失败', icon: 'none' })
      binding.value = false
    }
  })
}

const validateMobile = (mobile) => {
  const mobileReg = /^1[3-9]\d{9}$/
  return mobileReg.test(mobile)
}

const validatePassword = (password) => {
  const passwordReg = /^[a-zA-Z0-9]{8,}$/
  return passwordReg.test(password)
}

const validateForm = () => {
  const requiredFields = [
    { key: 'name', label: '真实姓名' },
    { key: 'mobile', label: '手机号' },
    { key: 'password', label: '密码' },
    { key: 'confirm_password', label: '确认密码' },
    { key: 'dept_id', label: '所属部门' },
    { key: 'id_card', label: '身份证号' },
    { key: 'address', label: '联系地址' },
    { key: 'bank_card_no', label: '银行卡号' },
    { key: 'bank_account_name', label: '持卡人姓名' },
    { key: 'bank_name', label: '开户银行' }
  ]
  for (const field of requiredFields) {
    if (!form[field.key]) {
      uni.showToast({ title: `请填写${field.label}`, icon: 'none' })
      return false
    }
  }
  
  if (!validateMobile(form.mobile)) {
    uni.showToast({ title: '请输入正确的中国大陆手机号', icon: 'none' })
    return false
  }
  
  if (!validatePassword(form.password)) {
    uni.showToast({ title: '密码至少8位，仅包含数字和英文字符', icon: 'none' })
    return false
  }
  
  if (form.password !== form.confirm_password) {
    uni.showToast({ title: '两次密码不一致', icon: 'none' })
    return false
  }
  if (!wechatInfo.code) {
    uni.showToast({ title: '请先绑定微信', icon: 'none' })
    return false
  }
  return true
}

const submit = async () => {
  if (!validateForm() || submitting.value) {
    return
  }
  submitting.value = true
  try {
    await api.register({
      ...form,
      code: wechatInfo.code,
      nickname: wechatInfo.nickname,
      avatar_url: wechatInfo.avatar_url
    })
    uni.showToast({ title: '提交成功，待审批', icon: 'none' })
    setTimeout(() => {
      uni.redirectTo({ url: '/pages/auth/login' })
    }, 1200)
  } catch (error) {
    console.error(error)
  } finally {
    submitting.value = false
  }
}

onLoad(() => {
  fetchDepartments()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  min-height: 100vh;
  box-sizing: border-box;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx;
  margin-bottom: 24rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 24rpx;
}
.form-item {
  margin-bottom: 20rpx;
}
.form-item text {
  display: block;
  color: #666;
  margin-bottom: 10rpx;
}
.form-item input {
  background: #f5f5f5;
  border-radius: 16rpx;
  padding: 24rpx;
}
.picker-value {
  background: #f5f5f5;
  border-radius: 16rpx;
  padding: 24rpx;
}
.picker-value.placeholder {
  color: #999;
}
.wechat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.wechat-status {
  font-size: 28rpx;
  font-weight: 500;
}
.wechat-desc {
  font-size: 24rpx;
  color: #999;
}
.submit {
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
</style>
