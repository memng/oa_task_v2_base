<template>
  <scroll-view scroll-y class="page" v-if="detail">
    <view class="action-bar" v-if="canCreateTask">
      <button class="primary" size="mini" @click="openCreateTask">新建关联任务</button>
      <button class="outline" size="mini" @click="goEdit" v-if="canEditOrder">
        {{ isDraft ? '编辑草稿' : '编辑订单' }}
      </button>
    </view>
    <overview-card title="订单信息">
      <view class="row"><text>PI号</text><text>{{ piDisplay }}</text></view>
      <view class="row"><text>客户</text><text>{{ detail.order.customer_name }}</text></view>
      <view class="row"><text>状态</text><text>{{ orderStatusLabel(detail.order.status) }}</text></view>
      <view class="row"><text>币种</text><text>{{ detail.order.currency || '未填' }}</text></view>
      <view class="row"><text>业务员</text><text>{{ detail.order.sales_owner_name || '未填' }}</text></view>
      <view class="row"><text>发起人</text><text>{{ detail.order.initiator_name || '未填' }}</text></view>
      <view class="row"><text>交期</text><text>{{ detail.order.expected_delivery_at || '待定' }}</text></view>
      <view class="row"><text>交货期</text><text>{{ detail.order.delivery_period_days ? `${detail.order.delivery_period_days} 天` : '未设置' }}</text></view>
      <view class="row"><text>海运费</text><text>{{ amountLabel(detail.order.sea_freight) }}</text></view>
      <view class="row"><text>折扣</text><text>{{ amountLabel(detail.order.discount_amount) }}</text></view>
      <view class="row"><text>总价</text><text>{{ amountLabel(detail.order.grand_total) }}</text></view>
      <view class="row"><text>需求备注</text><text>{{ detail.order.requirement_text || '无' }}</text></view>
      <view class="row"><text>订单备注</text><text>{{ detail.order.remark || '无' }}</text></view>
    </overview-card>

    <overview-card title="产品列表">
      <view v-for="item in detail.products" :key="item.id" class="product-card">
        <view class="row">
          <text class="strong">{{ item.product_name }} {{ item.model || '' }}</text>
          <text class="muted">数量 x{{ item.quantity }}</text>
        </view>
        <view class="row"><text>电压</text><text>{{ item.voltage || '未填' }}</text></view>
        <view class="row"><text>功率</text><text>{{ item.power || '未填' }}</text></view>
        <view class="row"><text>加工长度</text><text>{{ item.processing_length || '未填' }}</text></view>
        <view class="row"><text>外形尺寸</text><text>{{ item.dimensions || '未填' }}</text></view>
        <view class="row"><text>要求</text><text>{{ item.requirements || '无' }}</text></view>
        <view class="row"><text>备注</text><text>{{ item.notes || '无' }}</text></view>
        <view class="row"><text>单价</text><text>{{ amountLabel(item.unit_price, item.currency) }}</text></view>
        <view class="row"><text>总价</text><text>{{ amountLabel(item.total_price || item.unit_price * item.quantity, item.currency) }}</text></view>
      </view>
    </overview-card>

    <overview-card title="关联任务">
      <view v-for="task in detail.tasks" :key="task.id" class="task-block">
        <view class="row">
          <text class="strong">{{ task.title }}</text>
          <text class="task-status">{{ task.status_label || task.status }}</text>
        </view>
        <view class="row"><text>类型</text><text>{{ task.type_label || task.type }}</text></view>
        <view class="row"><text>负责人</text><text>{{ task.assignee_name || '待分配' }}</text></view>
        <view class="row"><text>截止</text><text>{{ task.due_at || '待定' }}</text></view>
        <view class="row"><text>描述</text><text>{{ task.description || '无' }}</text></view>
        <view v-if="formDataSummary(task).length" class="submit-block">
          <text class="muted small">提交内容</text>
          <view class="submit-line" v-for="(line, idx) in formDataSummary(task)" :key="idx">{{ line }}</view>
        </view>
        <view v-if="taskAttachments(task).length" class="attach-block">
          <text class="muted small">提交附件 ({{ taskAttachments(task).length }})</text>
          <view class="attach-grid">
            <block v-for="file in taskAttachments(task)" :key="file.media_id">
              <image
                v-if="(file.file_type || '').startsWith('image')"
                :src="file.url"
                class="attach-thumb"
                mode="aspectFill"
                @click="previewImage(file.url)"
              />
              <video
                v-else
                :src="file.url"
                class="attach-video"
                controls
              />
              <view class="attach-tag" v-if="file.category || file.field_key">
                {{ file.category ? `#${file.category}` : `#${file.field_key}` }}
              </view>
            </block>
          </view>
        </view>
      </view>
    </overview-card>

    <overview-card v-if="requirementDocs.length" title="附件">
      <view class="attach-block" v-if="imageDocs.length">
        <text class="muted">图片</text>
        <view class="attach-grid">
          <image
            v-for="doc in imageDocs"
            :key="doc.id"
            class="attach-thumb"
            :src="doc.url"
            mode="aspectFill"
            @click="previewImage(doc.url)"
          />
        </view>
      </view>
      <view class="attach-block" v-if="videoDocs.length">
        <text class="muted">视频</text>
        <view class="attach-grid">
          <video
            v-for="doc in videoDocs"
            :key="doc.id"
            class="attach-video"
            :src="doc.url"
            controls
          />
        </view>
      </view>
      <view class="attach-block" v-if="fileDocs.length">
        <text class="muted">文件</text>
        <view class="row" v-for="doc in fileDocs" :key="doc.id">
          <text>{{ doc.doc_type || '附件' }}</text>
          <navigator v-if="doc.url" :url="doc.url" class="link">
            {{ doc.file_name || doc.url }}
          </navigator>
          <text v-else class="muted">{{ doc.file_name || '附件' }}</text>
        </view>
      </view>
    </overview-card>
  </scroll-view>
  <view class="empty" v-else-if="!loading">暂无订单信息</view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { api, resolveAssetUrl } from '../../utils/request'
import OverviewCard from '../../components/OverviewCard.vue'
import store from '../../store'

const detail = ref(null)
const progress = ref(0)
const loading = ref(true)
const currentOrderId = ref(null)
const profile = computed(() => store.state.profile || {})
const canCreateTask = computed(() => {
  if (!detail.value?.order || !profile.value?.id) return false
  if (detail.value.order.status !== 'in_progress') return false
  const deptType = profile.value.dept && profile.value.dept.type
  if (deptType === 'operation' || deptType === 'finance') return true
  return (
    detail.value.order.initiator_id === profile.value.id ||
    detail.value.order.sales_owner_id === profile.value.id
  )
})
const isDraft = computed(() => detail.value?.order?.status === 'draft')
const canEditOrder = computed(() => {
  if (!detail.value?.order || !profile.value?.id) return false
  const deptType = profile.value.dept && profile.value.dept.type
  if (deptType === 'operation' || deptType === 'finance') return true
  return (
    detail.value.order.initiator_id === profile.value.id ||
    detail.value.order.sales_owner_id === profile.value.id
  )
})
const statusMap = {
  draft: '草稿',
  in_progress: '进行中',
  completed: '已完成',
  cancelled: '已取消'
}
const orderStatusLabel = (status) => statusMap[status] || status || '-'
const piDisplay = computed(() => {
  const nums = detail.value?.order?.pi_numbers
  if (nums && nums.length) {
    return nums.join(' / ')
  }
  return detail.value?.order?.pi_number || '-'
})
const requirementDocs = computed(() => {
  const docs = detail.value?.documents || []
  return docs.map((doc) => ({
    ...doc,
    url: resolveAssetUrl(doc.url || doc.storage_path || '')
  }))
})
const imageDocs = computed(() => requirementDocs.value.filter((doc) => (doc.file_type || '').startsWith('image')))
const videoDocs = computed(() => requirementDocs.value.filter((doc) => (doc.file_type || '').startsWith('video')))
const fileDocs = computed(() =>
  requirementDocs.value.filter((doc) => !(doc.file_type || '').startsWith('image') && !(doc.file_type || '').startsWith('video'))
)
const amountLabel = (value, currency) => {
  if (value === undefined || value === null || value === '') return '-'
  const num = Number(value)
  const formatted = Number.isNaN(num) ? value : num.toFixed(2).replace(/\.00$/, '')
  const cur = currency || detail.value?.order?.currency || ''
  return `${formatted}${cur ? ` ${cur}` : ''}`
}

const previewImage = (url) => {
  const imgs = imageDocs.value.map((d) => d.url).filter(Boolean)
  if (!imgs.length) return
  const current = url || imgs[0]
  uni.previewImage({ urls: imgs, current })
}

const taskAttachments = (task) => {
  if (!task || !task.attachments) return []
  return (task.attachments || []).map((file) => ({
    ...file,
    url: resolveAssetUrl(file.url || '')
  }))
}

const formDataSummary = (task) => {
  const summary = []
  const fd = task.form_data || {}
  if (fd.procurement) {
    const p = fd.procurement
    const parts = []
    if (p.purchase_status) parts.push(`状态: ${p.purchase_status}`)
    if (p.ordered_at) parts.push(`下单: ${p.ordered_at}`)
    if (p.purchase_date) parts.push(`下单日: ${p.purchase_date}`)
    if (p.delivery_date) parts.push(`交期: ${p.delivery_date}`)
    if (p.purchase_price) parts.push(`含税运总价: ${p.purchase_price}${p.currency ? ` ${p.currency}` : ''}`)
    if (p.source_location) parts.push(`货源地: ${p.source_location}`)
    if (p.inventory?.item_id) parts.push(`库存#${p.inventory.item_id} 数量:${p.inventory.quantity || '-'}`)
    if (parts.length) summary.push(parts.join('，'))
    if (p.product_name) summary.push(`产品: ${p.product_name} ${p.model || ''} ${p.voltage || ''}`)
    if (p.requirements) summary.push(`机器要求: ${p.requirements}`)
  }
  if (fd.modules) {
    Object.keys(fd.modules).forEach((key) => {
      const item = fd.modules[key]
      const label = item.label || key
      summary.push(`${label}: ${item.value || '-'}`)
    })
  }
  return summary
}

const normalizeId = (value) => {
  if (value === undefined || value === null) return ''
  const id = String(value).trim()
  return id
}

const loadDetail = async (rawId) => {
  const id = normalizeId(rawId)
  if (!id) {
    detail.value = null
    progress.value = 0
    loading.value = false
    return
  }
  loading.value = true
  try {
    const res = await api.orderDetail(id)
    if (!res || !res.order) {
      throw new Error('订单不存在')
    }
    detail.value = {
      order: res.order,
      products: res.products || [],
      tasks: res.tasks || [],
      costs: res.costs || [],
      documents: res.documents || []
    }
    try {
      const progRes = await api.orderProgress(id)
      progress.value = progRes?.progress || 0
    } catch (progressError) {
      progress.value = 0
      console.warn('加载订单进度失败', progressError)
    }
  } catch (error) {
    detail.value = null
    progress.value = 0
    uni.showToast({ title: (error && error.message) || '获取订单失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

onLoad((query) => {
  const id = normalizeId(query.id || query.orderId || '')
  currentOrderId.value = id || null
  loadDetail(currentOrderId.value)
})

onShow(() => {
  if (currentOrderId.value) {
    loadDetail(currentOrderId.value)
  }
})

const openCreateTask = () => {
  if (!detail.value?.order) return
  uni.navigateTo({
    url: `/pages/tasks/create?orderId=${encodeURIComponent(
      String(detail.value.order.id)
    )}&pi=${(detail.value.order.pi_numbers && detail.value.order.pi_numbers[0]) || detail.value.order.pi_number}`
  })
}

const goEdit = () => {
  if (!detail.value?.order?.id) return
  uni.navigateTo({
    url: `/pages/order/create?orderId=${encodeURIComponent(String(detail.value.order.id))}&mode=edit&status=${detail.value.order.status}`
  })
}
</script>

<style scoped lang="scss">
.page {
  padding: 24rpx;
}
.action-bar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 20rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
  border-radius: 30rpx;
  padding: 12rpx 26rpx;
}
.row {
  display: flex;
  justify-content: space-between;
  padding: 12rpx 0;
  font-size: 26rpx;
}
.strong {
  font-weight: 600;
}
.muted {
  color: #999;
  font-size: 24rpx;
}
.product-card {
  padding: 12rpx 0;
  border-bottom: 1px solid #f0f0f0;
}
.product-card:last-child {
  border-bottom: none;
}
.attach-block {
  margin: 12rpx 0;
}
.attach-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12rpx;
}
.submit-block {
  margin-top: 8rpx;
}
.submit-line {
  font-size: 26rpx;
  color: #333;
  margin: 4rpx 0;
  line-height: 1.5;
}
.task-row {
  align-items: center;
}
.task-info {
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}
.task-status {
  font-weight: 600;
}
.task-attachments {
  margin: 6rpx 0 12rpx;
}
.small {
  font-size: 24rpx;
}
.attach-thumb {
  width: 180rpx;
  height: 180rpx;
  border-radius: 16rpx;
  background: #f5f5f5;
  position: relative;
}
.attach-video {
  width: 100%;
  height: 180rpx;
  border-radius: 16rpx;
  background: #000;
}
.attach-tag {
  position: absolute;
  bottom: 6rpx;
  right: 6rpx;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  padding: 4rpx 8rpx;
  border-radius: 10rpx;
  font-size: 20rpx;
}
.task-block {
  padding: 12rpx 0;
  border-bottom: 1px solid #f0f0f0;
}
.task-block:last-child {
  border-bottom: none;
}
.task-status {
  font-weight: 600;
  color: #1677ff;
}
.link {
  color: #1677ff;
}
.progress {
  font-size: 32rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.empty {
  padding: 120rpx 0;
  text-align: center;
  color: #999;
}
</style>
