<template>
  <scroll-view scroll-y class="page">
    <view class="section">
      <view class="section-title">基本信息</view>
      <view class="card">
        <view class="field avatar-field" @click="changeAvatar">
          <view class="label">头像</view>
          <view class="value avatar-value">
            <image
              class="avatar"
              :class="{ uploading: updatingAvatar }"
              :src="resolveUrl(profile?.avatar_url) || defaultAvatar"
              mode="aspectFill"
            />
            <text class="arrow">›</text>
          </view>
        </view>
        <view class="field">
          <view class="label">姓名</view>
          <view class="value">{{ profile?.name || '--' }}</view>
        </view>
        <view class="field" @click="openChangeMobile">
          <view class="label">手机号</view>
          <view class="value">
            <text>{{ maskMobile(profile?.mobile) }}</text>
            <text class="change-btn">换绑</text>
          </view>
        </view>
        <view class="field">
          <view class="label">部门</view>
          <view class="value">{{ profile?.dept?.name || '未分配' }}</view>
        </view>
        <view class="field editable">
          <view class="label">昵称</view>
          <input
            class="value input-value"
            v-model="editForm.nickname"
            placeholder="未设置"
            @blur="saveField('nickname')"
          />
        </view>
        <view class="field editable">
          <view class="label">邮箱</view>
          <input
            class="value input-value"
            v-model="editForm.email"
            placeholder="未设置"
            @blur="saveField('email')"
          />
        </view>
      </view>
    </view>

    <view class="section">
      <view class="section-title">
        紧急联系人
        <text class="add-btn" @click="openContactModal">+ 添加</text>
      </view>
      <view class="card">
        <view
          v-if="!contacts || contacts.length === 0"
          class="empty"
        >暂无紧急联系人</view>
        <view
          v-for="contact in contacts"
          :key="contact.id"
          class="contact-item"
        >
          <view class="contact-main">
            <view class="contact-name">
              {{ contact.name }}
              <text v-if="contact.is_primary" class="primary-tag">主要</text>
            </view>
            <view class="contact-mobile">{{ contact.mobile }}</view>
            <view v-if="contact.relationship" class="contact-relation">
              关系：{{ contact.relationship }}
            </view>
          </view>
          <view class="contact-actions">
            <text
              class="action-btn"
              @click="setPrimaryContact(contact)"
            >设为主要</text>
            <text
              class="action-btn"
              @click="editContact(contact)"
            >编辑</text>
            <text
              class="action-btn danger"
              @click="confirmDeleteContact(contact)"
            >删除</text>
          </view>
        </view>
      </view>
    </view>

    <view class="section">
      <view class="section-title">银行信息</view>
      <view class="card">
        <view class="field">
          <view class="label">开户人</view>
          <view class="value">{{ profile?.bank_account_name || '--' }}</view>
        </view>
        <view class="field">
          <view class="label">开户银行</view>
          <view class="value">{{ profile?.bank_name || '--' }}</view>
        </view>
        <view class="field">
          <view class="label">银行账号</view>
          <view class="value">{{ profile?.bank_card_no || '--' }}</view>
        </view>
      </view>
    </view>

    <view v-if="showMobileModal" class="modal-mask" @click="closeMobileModal">
      <view class="modal" @click.stop>
        <view class="modal-title">换绑手机号</view>
        <view class="modal-body">
          <input
            class="modal-input"
            v-model="mobileForm.newMobile"
            placeholder="请输入新手机号"
            maxlength="11"
            type="number"
          />
          <view class="code-row">
            <input
              class="modal-input code-input"
              v-model="mobileForm.code"
              placeholder="请输入验证码"
              maxlength="6"
              type="number"
            />
            <button
              class="code-btn"
              :disabled="sendingCode || countdown > 0"
              @click="sendCode"
            >
              {{ countdown > 0 ? `${countdown}s后重发` : '获取验证码' }}
            </button>
          </view>
        </view>
        <view class="modal-footer">
          <button class="modal-btn cancel" @click="closeMobileModal">取消</button>
          <button
            class="modal-btn confirm"
            :loading="submittingMobile"
            @click="submitChangeMobile"
          >确认</button>
        </view>
      </view>
    </view>

    <view v-if="showContactModal" class="modal-mask" @click="closeContactModal">
      <view class="modal" @click.stop>
        <view class="modal-title">
          {{ editingContact ? '编辑紧急联系人' : '添加紧急联系人' }}
        </view>
        <view class="modal-body">
          <input
            class="modal-input"
            v-model="contactForm.name"
            placeholder="请输入姓名"
          />
          <input
            class="modal-input"
            v-model="contactForm.mobile"
            placeholder="请输入手机号"
            maxlength="11"
            type="number"
          />
          <input
            class="modal-input"
            v-model="contactForm.relationship"
            placeholder="与本人关系（如：父母、配偶、子女、朋友等）"
          />
          <view class="checkbox-row">
            <checkbox
              :checked="contactForm.is_primary"
              @change="contactForm.is_primary = $event.detail.value[0] || false"
            />
            <text class="checkbox-label">设为主要联系人</text>
          </view>
        </view>
        <view class="modal-footer">
          <button class="modal-btn cancel" @click="closeContactModal">取消</button>
          <button
            class="modal-btn confirm"
            :loading="submittingContact"
            @click="submitContact"
          >保存</button>
        </view>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import store from '../../store'
import { api, uploadFile, resolveAssetUrl } from '../../utils/request'

const profile = ref(null)
const contacts = ref([])
const defaultAvatar = '/static/icons/avatar.png'
const updatingAvatar = ref(false)

const editForm = reactive({
  nickname: '',
  email: ''
})

const showMobileModal = ref(false)
const mobileForm = reactive({ newMobile: '', code: '' })
const sendingCode = ref(false)
const submittingMobile = ref(false)
const countdown = ref(0)
let countdownTimer = null

const showContactModal = ref(false)
const editingContact = ref(null)
const contactForm = reactive({
  name: '',
  mobile: '',
  relationship: '',
  is_primary: false
})
const submittingContact = ref(false)

const resolveUrl = (url) => {
  return resolveAssetUrl(url)
}

const maskMobile = (mobile) => {
  if (!mobile) return '--'
  if (mobile.length < 11) return mobile
  return mobile.slice(0, 3) + '****' + mobile.slice(7)
}

const fetchProfile = async () => {
  const res = await api.profile()
  profile.value = res.profile
  contacts.value = res.profile.emergency_contacts || []
  editForm.nickname = res.profile.nickname || ''
  editForm.email = res.profile.email || ''
  store.setProfile(res.profile)
}

const saveField = async (field) => {
  const currentValue = profile.value[field] || ''
  const newValue = editForm[field] || ''
  if (currentValue === newValue) return
  try {
    const payload = {}
    payload[field] = newValue || null
    const res = await api.updateProfile(payload)
    if (res && res.profile) {
      profile.value = res.profile
      store.setProfile(res.profile)
    }
  } catch (error) {
    console.warn('save field failed', field, error)
  }
}

const changeAvatar = () => {
  if (updatingAvatar.value) return
  uni.chooseImage({
    count: 1,
    sizeType: ['compressed'],
    sourceType: ['album', 'camera'],
    success(res) {
      const [filePath] = res.tempFilePaths || []
      if (!filePath) return
      uni.navigateTo({
        url: `/pages/mine/avatar-crop?src=${encodeURIComponent(filePath)}`
      })
    },
    fail: (error) => {
      console.warn('choose avatar cancelled', error)
    }
  })
}

const openChangeMobile = () => {
  mobileForm.newMobile = ''
  mobileForm.code = ''
  countdown.value = 0
  showMobileModal.value = true
}

const closeMobileModal = () => {
  showMobileModal.value = false
}

const startCountdown = () => {
  countdown.value = 60
  if (countdownTimer) clearInterval(countdownTimer)
  countdownTimer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      clearInterval(countdownTimer)
      countdownTimer = null
    }
  }, 1000)
}

const sendCode = async () => {
  if (!/^1\d{10}$/.test(mobileForm.newMobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  sendingCode.value = true
  try {
    const res = await api.sendChangeMobileCode({ mobile: mobileForm.newMobile })
    startCountdown()
    uni.showToast({ title: '验证码已发送', icon: 'success' })
    if (res && res.debug_code) {
      console.log('debug code:', res.debug_code)
    }
  } catch (error) {
    console.warn('send code failed', error)
  } finally {
    sendingCode.value = false
  }
}

const submitChangeMobile = async () => {
  if (!/^1\d{10}$/.test(mobileForm.newMobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  if (mobileForm.code.length !== 6) {
    uni.showToast({ title: '请输入6位验证码', icon: 'none' })
    return
  }
  submittingMobile.value = true
  try {
    const res = await api.changeMobile({
      mobile: mobileForm.newMobile,
      code: mobileForm.code
    })
    if (res && res.profile) {
      profile.value = res.profile
      store.setProfile(res.profile)
    }
    uni.showToast({ title: '手机号已更新', icon: 'success' })
    closeMobileModal()
  } catch (error) {
    console.warn('change mobile failed', error)
  } finally {
    submittingMobile.value = false
  }
}

const openContactModal = () => {
  editingContact.value = null
  contactForm.name = ''
  contactForm.mobile = ''
  contactForm.relationship = ''
  contactForm.is_primary = contacts.value.length === 0
  showContactModal.value = true
}

const editContact = (contact) => {
  editingContact.value = contact
  contactForm.name = contact.name || ''
  contactForm.mobile = contact.mobile || ''
  contactForm.relationship = contact.relationship || ''
  contactForm.is_primary = !!contact.is_primary
  showContactModal.value = true
}

const closeContactModal = () => {
  showContactModal.value = false
  editingContact.value = null
}

const submitContact = async () => {
  if (!contactForm.name.trim()) {
    uni.showToast({ title: '请输入联系人姓名', icon: 'none' })
    return
  }
  if (!/^1\d{10}$/.test(contactForm.mobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  submittingContact.value = true
  try {
    let res
    const payload = {
      name: contactForm.name.trim(),
      mobile: contactForm.mobile,
      relationship: contactForm.relationship.trim() || null,
      is_primary: contactForm.is_primary
    }
    if (editingContact.value) {
      res = await api.updateEmergencyContact(editingContact.value.id, payload)
    } else {
      res = await api.addEmergencyContact(payload)
    }
    await fetchProfile()
    uni.showToast({ title: '保存成功', icon: 'success' })
    closeContactModal()
  } catch (error) {
    console.warn('submit contact failed', error)
  } finally {
    submittingContact.value = false
  }
}

const setPrimaryContact = async (contact) => {
  if (contact.is_primary) return
  try {
    await api.updateEmergencyContact(contact.id, { is_primary: true })
    await fetchProfile()
    uni.showToast({ title: '已设为主要联系人', icon: 'success' })
  } catch (error) {
    console.warn('set primary failed', error)
  }
}

const confirmDeleteContact = (contact) => {
  uni.showModal({
    title: '确认删除',
    content: `确定要删除紧急联系人「${contact.name}」吗？`,
    success: async (res) => {
      if (res.confirm) {
        try {
          await api.deleteEmergencyContact(contact.id)
          await fetchProfile()
          uni.showToast({ title: '已删除', icon: 'success' })
        } catch (error) {
          console.warn('delete contact failed', error)
        }
      }
    }
  })
}

onMounted(() => {
  if (store.state.token) {
    fetchProfile()
  }
})

onUnmounted(() => {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  min-height: 100vh;
  box-sizing: border-box;
}
.section {
  margin-bottom: 32rpx;
}
.section-title {
  font-size: 28rpx;
  color: #666;
  margin-bottom: 16rpx;
  padding: 0 8rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.add-btn {
  color: #1677ff;
  font-size: 26rpx;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 0 24rpx;
}
.field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 28rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}
.field:last-child {
  border-bottom: none;
}
.avatar-field {
  cursor: pointer;
}
.label {
  font-size: 28rpx;
  color: #333;
}
.value {
  font-size: 28rpx;
  color: #666;
  display: flex;
  align-items: center;
}
.avatar-value {
  gap: 12rpx;
}
.avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 36rpx;
}
.avatar.uploading {
  opacity: 0.6;
}
.arrow {
  color: #ccc;
  font-size: 32rpx;
}
.change-btn {
  color: #1677ff;
  margin-left: 16rpx;
  font-size: 26rpx;
}
.input-value {
  text-align: right;
  background: transparent;
  padding: 0;
  font-size: 28rpx;
}
.empty {
  padding: 40rpx 0;
  text-align: center;
  color: #999;
  font-size: 28rpx;
}
.contact-item {
  padding: 24rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}
.contact-item:last-child {
  border-bottom: none;
}
.contact-main {
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}
.contact-name {
  font-size: 30rpx;
  font-weight: 500;
  color: #333;
  display: flex;
  align-items: center;
  gap: 12rpx;
}
.primary-tag {
  font-size: 22rpx;
  color: #1677ff;
  background: #e6f4ff;
  padding: 4rpx 12rpx;
  border-radius: 8rpx;
}
.contact-mobile {
  font-size: 28rpx;
  color: #666;
}
.contact-relation {
  font-size: 26rpx;
  color: #999;
}
.contact-actions {
  display: flex;
  gap: 24rpx;
  margin-top: 16rpx;
}
.action-btn {
  font-size: 26rpx;
  color: #1677ff;
}
.action-btn.danger {
  color: #ff4d4f;
}
.modal-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
}
.modal {
  width: 620rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 40rpx;
  box-sizing: border-box;
}
.modal-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  margin-bottom: 32rpx;
}
.modal-body {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}
.modal-input {
  background: #f5f5f5;
  border-radius: 16rpx;
  padding: 24rpx;
  font-size: 28rpx;
}
.code-row {
  display: flex;
  gap: 16rpx;
  align-items: center;
}
.code-input {
  flex: 1;
}
.code-btn {
  width: 240rpx;
  height: 88rpx;
  background: #1677ff;
  color: #fff;
  border-radius: 16rpx;
  font-size: 26rpx;
  line-height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}
.code-btn[disabled] {
  background: #bfbfbf !important;
  color: #fff !important;
}
.checkbox-row {
  display: flex;
  align-items: center;
  gap: 12rpx;
  padding: 8rpx 0;
}
.checkbox-label {
  font-size: 28rpx;
  color: #333;
}
.modal-footer {
  display: flex;
  gap: 24rpx;
  margin-top: 40rpx;
}
.modal-btn {
  flex: 1;
  height: 88rpx;
  border-radius: 16rpx;
  font-size: 30rpx;
}
.modal-btn.cancel {
  background: #f5f5f5;
  color: #666;
}
.modal-btn.confirm {
  background: #1677ff;
  color: #fff;
}
</style>
