<template>
  <scroll-view scroll-y class="page">
    <view class="reject-banner">
      <view class="reject-title">审核未通过</view>
      <view class="reject-reason">{{ rejectReason || '请修改资料后重新提交' }}</view>
    </view>

    <view class="card">
      <view class="title">账号信息</view>
      <view class="form-item">
        <text>真实姓名</text>
        <input v-model="form.name" placeholder="请输入真实姓名" />
      </view>
      <view class="form-item">
        <text>手机号</text>
        <input v-model="form.mobile" placeholder="请输入11位中国大陆手机号" type="number" maxlength="11" disabled />
      </view>
      <view class="form-item">
        <text>登录密码</text>
        <input v-model="form.password" placeholder="留空则不修改" type="password" />
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
      重新提交
    </button>
  </scroll-view>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'
import { validateMobile, validatePassword, validateRequiredFields } from '../../utils/validate'

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
const rejectReason = ref('')
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

const fetchRejectedInfo = async (mobile) => {
  try {
    const res = await api.rejectedInfo({ mobile })
    const profile = res.profile || {}
    rejectReason.value = res.reject_reason || ''
    
    form.name = profile.name || ''
    form.mobile = profile.mobile || mobile
    form.id_card = profile.id_card || ''
    form.address = profile.address || ''
    form.bank_account_name = profile.bank_account_name || ''
    form.bank_name = profile.bank_name || ''
    form.bank_card_no = profile.bank_card_no || ''
    form.dept_id = profile.dept_id || ''
    form.nickname = profile.nickname || ''
    form.avatar_url = profile.avatar_url || ''
    
    wechatInfo.nickname = profile.nickname || ''
    wechatInfo.avatar_url = profile.avatar_url || ''
  } catch (error) {
    console.error(error)
    uni.showToast({ title: '获取信息失败', icon: 'none' })
    setTimeout(() => {
      uni.redirectTo({ url: '/pages/auth/login' })
    }, 1500)
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

const validateForm = () => {
  const requiredFields = [
    { key: 'name', label: '真实姓名' },
    { key: 'mobile', label: '手机号' },
    { key: 'dept_id', label: '所属部门' },
    { key: 'id_card', label: '身份证号' },
    { key: 'address', label: '联系地址' },
    { key: 'bank_card_no', label: '银行卡号' },
    { key: 'bank_account_name', label: '持卡人姓名' },
    { key: 'bank_name', label: '开户银行' }
  ]
  
  if (!validateRequiredFields(form, requiredFields)) {
    return false
  }
  
  if (!validateMobile(form.mobile)) {
    uni.showToast({ title: '请输入正确的中国大陆手机号', icon: 'none' })
    return false
  }
  
  if (form.password) {
    if (!validatePassword(form.password)) {
      uni.showToast({ title: '请输入密码，仅包含数字和英文字符', icon: 'none' })
      return false
    }
    
    if (form.password !== form.confirm_password) {
      uni.showToast({ title: '两次密码不一致', icon: 'none' })
      return false
    }
  }
  
  return true
}

const submit = async () => {
  if (!validateForm() || submitting.value) {
    return
  }
  submitting.value = true
  try {
    await api.resubmitProfile({
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

onLoad((options) => {
  const mobile = options?.mobile
  if (!mobile) {
    uni.showToast({ title: '参数错误', icon: 'none' })
    setTimeout(() => {
      uni.redirectTo({ url: '/pages/auth/login' })
    }, 1500)
    return
  }
  
  fetchDepartments()
  fetchRejectedInfo(mobile)
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  min-height: 100vh;
  box-sizing: border-box;
}
.reject-banner {
  background: linear-gradient(135deg, #f56c6c 0%, #f78989 100%);
  border-radius: 24rpx;
  padding: 32rpx;
  margin-bottom: 24rpx;
  color: #fff;
}
.reject-title {
  font-size: 34rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.reject-reason {
  font-size: 28rpx;
  line-height: 1.6;
  opacity: 0.95;
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
.form-item input[disabled] {
  color: #999;
  background: #f0f0f0;
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
