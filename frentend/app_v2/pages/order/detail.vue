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

    <overview-card title="阶段进度">
      <view class="stage-progress-info" v-if="stageProgress">
        <view class="progress-header">
          <text class="progress-label">整体完成</text>
          <text class="progress-value">{{ stageProgress.overall_progress }}%</text>
        </view>
        <view class="progress-bar">
          <view class="progress-fill" :style="{ width: stageProgress.overall_progress + '%' }" />
        </view>
      </view>
      <view class="stage-steps" v-if="stageProgress">
        <view
          v-for="(stage, idx) in stageProgress.stages"
          :key="stage.stage"
          class="stage-item"
          :class="{
            'is-completed': stage.status === 'completed',
            'is-current': stage.stage === stageProgress.current_stage,
            'is-overdue': stage.status === 'overdue',
          }"
        >
          <view class="stage-dot-wrap">
            <view class="stage-dot">
              <text v-if="stage.status === 'completed'" class="dot-check">✓</text>
              <text v-else-if="stage.status === 'overdue'" class="dot-warn">!</text>
              <text v-else class="dot-num">{{ stage.order }}</text>
            </view>
            <view v-if="idx < stageProgress.stages.length - 1" class="stage-line" :class="{ 'is-done': stage.status === 'completed' }" />
          </view>
          <view class="stage-content">
            <view class="stage-title-row">
              <text class="stage-name">{{ stage.label }}</text>
              <text v-if="stage.status === 'overdue'" class="overdue-badge">超期</text>
              <text v-if="stage.has_delay_reason" class="delay-badge">已说明</text>
            </view>
            <text v-if="stage.total_tasks > 0" class="stage-meta">
              {{ stage.completed_tasks }}/{{ stage.total_tasks }} 完成
            </text>
            <view v-if="stage.stage === stageProgress.current_stage && stage.status !== 'completed'" class="stage-action">
              <button class="advance-btn" size="mini" @click="openStageTransition">推进阶段</button>
            </view>
          </view>
        </view>
      </view>
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
        <view v-if="isTaskOverdue(task) && !task.delay_reason" class="delay-action-row">
          <text class="overdue-hint">已超期</text>
          <button class="delay-btn" size="mini" @click="openDelayDialog(task)">填写延期原因</button>
        </view>
        <view v-if="task.delay_reason" class="delay-reason-row">
          <text class="muted small">延期原因：</text>
          <text class="delay-reason-text">{{ task.delay_reason }}</text>
        </view>
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

    <view class="delay-dialog-mask" v-if="delayDialogVisible" @click="delayDialogVisible = false">
      <view class="delay-dialog" @click.stop>
        <text class="delay-dialog-title">填写延期原因</text>
        <textarea class="delay-dialog-input" v-model="delayReasonText" placeholder="请说明延期原因" />
        <view class="delay-dialog-actions">
          <button class="outline" size="mini" @click="delayDialogVisible = false">取消</button>
          <button class="primary" size="mini" @click="submitDelayReason">提交</button>
        </view>
      </view>
    </view>

    <view class="delay-dialog-mask" v-if="stageTransitionVisible" @click="stageTransitionVisible = false">
      <view class="delay-dialog" @click.stop>
        <text class="delay-dialog-title">推进阶段</text>
        <view class="stage-picker">
          <view
            v-for="s in availableNextStages"
            :key="s.value"
            class="stage-picker-item"
            :class="{ 'is-selected': stageTransitionTarget === s.value }"
            @click="stageTransitionTarget = s.value"
          >
            <text>{{ s.label }}</text>
          </view>
        </view>
        <textarea
          v-if="currentStageIsOverdue"
          class="delay-dialog-input"
          v-model="stageTransitionDelayReason"
          placeholder="当前阶段超期，请说明延期原因"
        />
        <view class="delay-dialog-actions">
          <button class="outline" size="mini" @click="stageTransitionVisible = false">取消</button>
          <button class="primary" size="mini" @click="submitStageTransition">确认推进</button>
        </view>
      </view>
    </view>
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
const delayDialogVisible = ref(false)
const delayReasonText = ref('')
const delayTaskId = ref(null)
const stageTransitionVisible = ref(false)
const stageTransitionTarget = ref('')
const stageTransitionDelayReason = ref('')
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

const stageProgress = computed(() => detail.value?.stage_progress || null)

const availableNextStages = computed(() => {
  if (!stageProgress.value) return []
  const stages = stageProgress.value.stages || []
  const currentIdx = stages.findIndex(s => s.stage === stageProgress.value.current_stage)
  return stages.filter((s, idx) => idx > currentIdx).map(s => ({ value: s.stage, label: s.label }))
})

const currentStageIsOverdue = computed(() => {
  if (!stageProgress.value) return false
  const current = (stageProgress.value.stages || []).find(s => s.stage === stageProgress.value.current_stage)
  return current?.is_overdue || false
})

const previewImage = (url) => {
  const imgs = imageDocs.value.map((d) => d.url).filter(Boolean)
  if (!imgs.length) return
  const current = url || imgs[0]
  uni.previewImage({ urls: imgs, current })
}

const isTaskOverdue = (task) => {
  if (task.status === 'completed' || task.status === 'cancelled') return false
  if (!task.due_at) return false
  return new Date(task.due_at) < new Date()
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
      documents: res.documents || [],
      stage_progress: res.stage_progress || null
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

const openDelayDialog = (task) => {
  delayTaskId.value = task.id
  delayReasonText.value = ''
  delayDialogVisible.value = true
}

const submitDelayReason = async () => {
  if (!delayReasonText.value.trim()) {
    uni.showToast({ title: '请填写延期原因', icon: 'none' })
    return
  }
  try {
    await api.orderTaskDelayReason(currentOrderId.value, delayTaskId.value, {
      delay_reason: delayReasonText.value.trim()
    })
    uni.showToast({ title: '延期原因已记录', icon: 'success' })
    delayDialogVisible.value = false
    await loadDetail(currentOrderId.value)
  } catch (error) {
    uni.showToast({ title: '提交失败', icon: 'none' })
  }
}

const openStageTransition = () => {
  stageTransitionTarget.value = ''
  stageTransitionDelayReason.value = ''
  stageTransitionVisible.value = true
}

const submitStageTransition = async () => {
  if (!stageTransitionTarget.value) {
    uni.showToast({ title: '请选择目标阶段', icon: 'none' })
    return
  }
  try {
    await api.orderStageTransition(currentOrderId.value, {
      to_stage: stageTransitionTarget.value,
      delay_reason: stageTransitionDelayReason.value || null
    })
    uni.showToast({ title: '阶段推进成功', icon: 'success' })
    stageTransitionVisible.value = false
    await loadDetail(currentOrderId.value)
  } catch (error) {
    uni.showToast({ title: '推进失败', icon: 'none' })
  }
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
.outline {
  border: 1px solid #1677ff;
  color: #1677ff;
  border-radius: 30rpx;
  padding: 12rpx 26rpx;
  background: #fff;
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

.stage-progress-info {
  margin-bottom: 16rpx;
}
.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8rpx;
}
.progress-label {
  font-size: 26rpx;
  color: #333;
}
.progress-value {
  font-size: 28rpx;
  font-weight: 600;
  color: #1677ff;
}
.progress-bar {
  height: 16rpx;
  background: #e8e8e8;
  border-radius: 8rpx;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: #1677ff;
  border-radius: 8rpx;
  transition: width 0.3s;
}

.stage-steps {
  margin-top: 20rpx;
}
.stage-item {
  display: flex;
  align-items: flex-start;
  min-height: 80rpx;
}
.stage-dot-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-right: 20rpx;
}
.stage-dot {
  width: 48rpx;
  height: 48rpx;
  border-radius: 50%;
  border: 3rpx solid #dcdfe6;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24rpx;
  font-weight: 600;
  color: #909399;
  flex-shrink: 0;
}
.stage-item.is-completed .stage-dot {
  background: #67c23a;
  border-color: #67c23a;
  color: #fff;
}
.stage-item.is-current .stage-dot {
  background: #1677ff;
  border-color: #1677ff;
  color: #fff;
}
.stage-item.is-overdue .stage-dot {
  background: #f56c6c;
  border-color: #f56c6c;
  color: #fff;
}
.dot-check {
  color: #fff;
  font-size: 28rpx;
}
.dot-warn {
  color: #fff;
  font-size: 28rpx;
  font-weight: 700;
}
.dot-num {
  font-size: 22rpx;
}
.stage-line {
  width: 3rpx;
  min-height: 32rpx;
  flex: 1;
  background: #dcdfe6;
  margin-top: 4rpx;
}
.stage-line.is-done {
  background: #67c23a;
}
.stage-content {
  flex: 1;
  padding-bottom: 20rpx;
}
.stage-title-row {
  display: flex;
  align-items: center;
  gap: 10rpx;
}
.stage-name {
  font-size: 28rpx;
  color: #333;
  font-weight: 500;
}
.stage-item.is-completed .stage-name {
  color: #67c23a;
}
.stage-item.is-current .stage-name {
  color: #1677ff;
  font-weight: 600;
}
.stage-item.is-overdue .stage-name {
  color: #f56c6c;
}
.overdue-badge {
  font-size: 20rpx;
  color: #fff;
  background: #f56c6c;
  padding: 2rpx 10rpx;
  border-radius: 8rpx;
}
.delay-badge {
  font-size: 20rpx;
  color: #fff;
  background: #e6a23c;
  padding: 2rpx 10rpx;
  border-radius: 8rpx;
}
.stage-meta {
  font-size: 24rpx;
  color: #999;
  margin-top: 4rpx;
}
.stage-action {
  margin-top: 8rpx;
}
.advance-btn {
  font-size: 24rpx;
  background: #1677ff;
  color: #fff;
  border-radius: 20rpx;
  padding: 6rpx 20rpx;
}

.delay-action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8rpx;
}
.overdue-hint {
  font-size: 24rpx;
  color: #f56c6c;
  font-weight: 600;
}
.delay-btn {
  font-size: 24rpx;
  background: #f56c6c;
  color: #fff;
  border-radius: 20rpx;
  padding: 6rpx 20rpx;
}
.delay-reason-row {
  margin-top: 8rpx;
  padding: 8rpx 12rpx;
  background: #fef0e6;
  border-radius: 8rpx;
}
.delay-reason-text {
  font-size: 24rpx;
  color: #e6a23c;
}

.delay-dialog-mask {
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
.delay-dialog {
  width: 600rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx;
}
.delay-dialog-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  margin-bottom: 24rpx;
}
.delay-dialog-input {
  width: 100%;
  min-height: 200rpx;
  border: 1px solid #dcdfe6;
  border-radius: 12rpx;
  padding: 16rpx;
  font-size: 28rpx;
  box-sizing: border-box;
}
.delay-dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-top: 24rpx;
}
.stage-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
  margin-bottom: 20rpx;
}
.stage-picker-item {
  padding: 10rpx 24rpx;
  border: 2rpx solid #dcdfe6;
  border-radius: 20rpx;
  font-size: 26rpx;
  color: #606266;
}
.stage-picker-item.is-selected {
  border-color: #1677ff;
  color: #1677ff;
  background: #ecf5ff;
}
</style>
