<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="section-title">报销信息</view>
      <view class="form-item">
        <text>报销类型</text>
        <picker :range="types" range-key="label" @change="onTypeChange">
          <view class="picker">{{ currentType.label }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>金额</text>
        <input v-model="form.amount" type="number" placeholder="请输入金额" />
      </view>
      <view class="form-item">
        <text>说明</text>
        <textarea v-model="form.remark" placeholder="请输入报销说明" />
      </view>
    </view>
    <view class="card">
      <view class="section-title">票据上传</view>
      <view class="upload" @click="upload" :class="{ disabled: uploading }">
        <template v-if="receiptUrl">
          <image class="receipt-preview" :src="receiptUrl" mode="aspectFit" />
          <text class="tip">点击可重新上传</text>
        </template>
        <view v-else class="placeholder">
          <text>点击上传票据</text>
        </view>
      </view>
      <view v-if="receiptName" class="file-name">{{ receiptName }}</view>
    </view>
    <button class="primary" :loading="submitting" @click="submit">提交报销</button>
  </scroll-view>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { api, uploadFile } from '../../utils/request'

const types = [
  { label: '差旅费用', value: 'travel' },
  { label: '采购费用', value: 'purchase' }
]
const currentType = ref(types[0])
const form = reactive({
  type: 'travel',
  amount: '',
  remark: '',
  receipt_media_id: null
})
const receiptName = ref('')
const receiptUrl = ref('')
const uploading = ref(false)
const submitting = ref(false)

const onTypeChange = (e) => {
  currentType.value = types[e.detail.value]
  form.type = currentType.value.value
}

const chooseFile = () =>
  new Promise((resolve, reject) => {
    if (typeof uni.chooseMedia === 'function') {
      uni.chooseMedia({
        count: 1,
        mediaType: ['image'],
        sourceType: ['album', 'camera'],
        success: (res) => {
          const file = res.tempFiles?.[0]
          resolve(file?.tempFilePath || file?.path || '')
        },
        fail: reject
      })
      return
    }
    uni.chooseImage({
      count: 1,
      sourceType: ['album', 'camera'],
      success: (res) => {
        const path = res.tempFilePaths?.[0] || ''
        resolve(path)
      },
      fail: reject
    })
  })

const upload = async () => {
  if (uploading.value) return
  try {
    uploading.value = true
    const path = await chooseFile()
    if (!path) return
    const result = await uploadFile(path)
    form.receipt_media_id = result.media_id
    receiptName.value = result.file_name || '票据附件'
    receiptUrl.value = result.url || ''
    uni.showToast({ title: '上传成功', icon: 'success' })
  } catch (error) {
    if (error?.errMsg && error.errMsg.includes('cancel')) {
      return
    }
    uni.showToast({ title: '上传失败', icon: 'none' })
  } finally {
    uploading.value = false
  }
}

const submit = async () => {
  if (submitting.value) return
  if (!form.amount || Number(form.amount) <= 0) {
    uni.showToast({ title: '请输入正确金额', icon: 'none' })
    return
  }
  submitting.value = true
  try {
    await api.createReimburse({
      type: form.type,
      amount: Number(form.amount),
      remark: form.remark,
      receipt_media_id: form.receipt_media_id
    })
    uni.showToast({ title: '报销已提交', icon: 'success' })
    form.amount = ''
    form.remark = ''
    form.receipt_media_id = null
    receiptName.value = ''
    receiptUrl.value = ''
  } catch (error) {
    uni.showToast({ title: '提交失败', icon: 'none' })
  } finally {
    submitting.value = false
  }
}
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
.section-title {
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
.picker,
input,
textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.upload {
  border: 1rpx dashed #1677ff;
  border-radius: 16rpx;
  text-align: center;
  padding: 20rpx;
  color: #1677ff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.upload.disabled {
  opacity: 0.6;
}
.placeholder {
  padding: 20rpx 0;
}
.receipt-preview {
  width: 200rpx;
  height: 200rpx;
  border-radius: 8rpx;
  background: #f7f8fa;
  margin-bottom: 8rpx;
}
.tip {
  font-size: 22rpx;
  color: #999;
}
.file-name {
  margin-top: 8rpx;
  font-size: 24rpx;
  color: #333;
}
.primary {
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
</style>
