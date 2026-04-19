<template>
  <scroll-view scroll-y class="page">
    <view v-if="loading" class="loading">加载中...</view>
    <template v-else>
      <view class="banner" v-if="order">
        <view>
          <view class="pi">PI号码：{{ order.pi_number }}</view>
          <view class="product">客户：{{ order.customer_name }}</view>
        </view>
        <view class="deadline">截止 {{ order.expected_delivery_at || '待定' }}</view>
      </view>

      <view class="summary-card">
        <view>已上传 {{ documentSummary.uploaded }}/{{ documentSummary.required_total }} 项</view>
        <view class="tip">{{ documentSummary.status_label || '待上传文件' }}</view>
      </view>

      <view class="card" v-for="section in sections" :key="section.title">
        <view class="section-title">{{ section.title }}</view>
        <view
          class="folder"
          v-for="item in section.items"
          :key="item.value"
          @click="pickFile(item)"
        >
          <view>
            <view class="folder-name">{{ item.label }}</view>
            <view class="tip">
              {{ item.uploaded_at ? `已上传：${item.uploaded_at}` : '支持PDF、WORD、图片格式' }}
            </view>
          </view>
          <view class="folder-right">
            <text :class="['status-text', item.uploaded ? 'done' : 'pending']">
              {{ item.uploaded ? '已上传' : '未上传' }}
            </text>
            <text class="upload-link">
              {{ uploadingType === item.value ? '上传中...' : item.uploaded ? '重新上传' : '上传' }}
            </text>
          </view>
        </view>
      </view>

      <view class="footer">
        <button class="outline" @click="cancel">返回</button>
        <button class="primary" @click="complete">完成</button>
      </view>
    </template>
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api, uploadFile } from '../../../utils/request'

const orderId = ref(null)
const order = ref(null)
const loading = ref(true)
const uploadingType = ref('')
const documentSummary = ref({
  uploaded: 0,
  required_total: 6,
  status_label: '待上传文件'
})

const baseSections = [
  {
    title: '任务发起人需上传文件',
    items: [
      { value: 'pi', label: 'PI文件(合同)' },
      { value: 'commercial_invoice', label: '商业发票' },
      { value: 'customs_declaration', label: '报关单' },
      { value: 'bill_of_lading', label: '提单' },
      { value: 'freight_invoice', label: '海运费用发票' }
    ]
  },
  {
    title: '财务需上传文件',
    items: [{ value: 'payment_receipt', label: '水单' }]
  }
]

const sections = ref(createSections())

function createSections(documents = []) {
  const docMap = {}
  documents.forEach((doc) => {
    docMap[doc.doc_type] = doc
  })
  return baseSections.map((section) => ({
    title: section.title,
    items: section.items.map((item) => {
      const matched = docMap[item.value] || {}
      const uploadedAt = matched.uploaded_at ? matched.uploaded_at.slice(0, 16) : ''
      return {
        ...item,
        uploaded: !!matched.id,
        uploaded_at: uploadedAt
      }
    })
  }))
}

const computeSummary = (documents = [], orderStatus = '') => {
  const required = baseSections.reduce((acc, section) => acc + section.items.length, 0)
  const uploadedTypes = new Set(
    documents.map((doc) => doc.doc_type).filter((type) => typeof type === 'string' && type)
  )
  let statusLabel = '待上传文件'
  if (uploadedTypes.size >= required && required > 0) {
    statusLabel = '待管理员审核'
  } else if (uploadedTypes.size > 0) {
    statusLabel = '继续上传'
  }
  if (orderStatus === 'cancelled') {
    statusLabel = '审核驳回'
  }
  return {
    uploaded: uploadedTypes.size,
    required_total: required,
    status_label: statusLabel
  }
}

const fetchDetail = async (id) => {
  loading.value = true
  try {
    const res = await api.orderDetail(id)
    order.value = res.order || null
    sections.value = createSections(res.documents || [])
    documentSummary.value = res.document_summary || computeSummary(res.documents || [], res.order?.status)
  } catch (error) {
    console.error('fetch order detail failed', error)
    uni.showToast({ title: '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

const chooseAnyFile = () =>
  new Promise((resolve, reject) => {
    const pickFromAlbumOrCamera = () => {
      if (typeof uni.chooseMedia === 'function') {
        uni.chooseMedia({
          count: 1,
          mediaType: ['image', 'video'],
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
    }

    const pickFromFiles = () => {
      if (typeof uni.chooseMessageFile === 'function') {
        uni.chooseMessageFile({
          count: 1,
          type: 'all',
          extension: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'],
          success: (res) => {
            const file = res.tempFiles?.[0]
            resolve(file?.path || file?.tempFilePath || '')
          },
          fail: reject
        })
        return
      }
      pickFromAlbumOrCamera()
    }

    uni.showActionSheet({
      itemList: ['拍摄或从相册选择', '从文件中选择'],
      success: (res) => {
        if (res.tapIndex === 1) {
          pickFromFiles()
        } else {
          pickFromAlbumOrCamera()
        }
      },
      fail: reject
    })
  })

const pickFile = async (item) => {
  if (!orderId.value || uploadingType.value) return
  try {
    uploadingType.value = item.value
    const filePath = await chooseAnyFile()
    if (!filePath) return
    const uploadRes = await uploadFile(filePath)
    if (!uploadRes?.media_id) {
      uni.showToast({ title: '上传失败', icon: 'none' })
      return
    }
    const result = await api.addOrderDocument(orderId.value, {
      doc_type: item.value,
      media_id: uploadRes.media_id
    })
    sections.value = createSections(result.documents || [])
    documentSummary.value = result.document_summary || documentSummary.value
    uni.showToast({ title: '上传成功', icon: 'success' })
  } catch (error) {
    if (error?.errMsg && error.errMsg.includes('cancel')) {
      return
    }
    console.error('upload document failed', error)
    uni.showToast({ title: '上传失败', icon: 'none' })
  } finally {
    uploadingType.value = ''
  }
}

const cancel = () => {
  uni.navigateBack()
}

const complete = () => {
  uni.showToast({ title: '已保存', icon: 'success' })
  setTimeout(() => {
    uni.navigateBack()
  }, 300)
}

onLoad((options) => {
  if (options?.id) {
    orderId.value = Number(options.id)
    fetchDetail(orderId.value)
  } else {
    loading.value = false
  }
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  min-height: 100vh;
}
.loading {
  text-align: center;
  color: #999;
  padding: 80rpx 0;
}
.banner {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  justify-content: space-between;
  margin-bottom: 24rpx;
}
.pi {
  font-size: 24rpx;
  color: #999;
}
.product {
  font-size: 28rpx;
  font-weight: 600;
}
.deadline {
  color: #1677ff;
}
.summary-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.summary-card .tip {
  color: #999;
  font-size: 24rpx;
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
.folder {
  border: 1rpx dashed #d9d9d9;
  border-radius: 16rpx;
  padding: 16rpx;
  margin-bottom: 12rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.folder-name {
  font-size: 26rpx;
}
.tip {
  font-size: 22rpx;
  color: #999;
  margin-top: 8rpx;
}
.folder-right {
  text-align: right;
}
.status-text {
  font-size: 24rpx;
  display: block;
}
.status-text.done {
  color: #52c41a;
}
.status-text.pending {
  color: #fa8c16;
}
.upload-link {
  font-size: 24rpx;
  color: #1677ff;
}
.footer {
  display: flex;
  gap: 16rpx;
  margin-top: 24rpx;
}
.outline,
.primary {
  flex: 1;
  border-radius: 32rpx;
}
.outline {
  border: 1rpx solid #1677ff;
  color: #1677ff;
  background: #fff;
}
.primary {
  background: #1677ff;
  color: #fff;
}
</style>
