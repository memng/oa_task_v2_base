<template>
  <scroll-view scroll-y class="page" v-if="detail">
    <view class="card">
      <view class="status-header">
        <view class="status-badge" :class="getStatusClass(detail.status)">
          {{ getStatusLabel(detail.status) }}
        </view>
        <view class="progress-text">{{ progress }}% 完成</view>
      </view>
      <view class="progress-bar">
        <view class="progress-fill" :style="{ width: progress + '%' }"></view>
      </view>
      <view class="title">{{ detail.customer_name }}</view>
      <view class="subtitle">{{ detail.product_name }} · {{ detail.model || '型号待定' }}</view>
      <view class="meta-row">
        <text>数量：{{ detail.quantity }}</text>
        <text>电压：{{ detail.voltage || '-' }}</text>
      </view>
      <view class="meta-row">
        <text>预计成交：{{ detail.expected_close_date || '待定' }}</text>
      </view>
    </view>

    <view class="card">
      <view class="section-title">客户需求</view>
      <view class="content">{{ detail.customer_requirements || '暂无描述' }}</view>
    </view>

    <view class="card" v-if="transitions.length > 0">
      <view class="section-title">流转历史</view>
      <view class="timeline">
        <view
          v-for="(item, index) in transitions"
          :key="item.id"
          :class="['timeline-item', { last: index === transitions.length - 1 }]"
        >
          <view class="timeline-dot" :class="getTransitionDotClass(item.transition_type)"></view>
          <view class="timeline-content">
            <view class="timeline-header">
              <text class="timeline-status">{{ getStatusLabel(item.to_status) }}</text>
              <text class="timeline-type" :class="item.transition_type">
                {{ getTransitionTypeLabel(item.transition_type) }}
              </text>
            </view>
            <view class="timeline-meta">
              <text>操作人：{{ item.operator_name || '-' }}</text>
              <text>{{ item.created_at }}</text>
            </view>
            <view v-if="item.reason" class="timeline-reason">
              原因：{{ item.reason }}
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="card">
      <view class="section-title">操作</view>
      <view class="action-buttons">
        <button class="action-btn edit-btn" @click="openEditModal">编辑</button>
      </view>
    </view>

    <view class="card" v-if="availableTransitions.length > 0">
      <view class="section-title">可执行操作</view>
      <view class="transition-options">
        <view
          v-for="option in availableTransitions"
          :key="option.status"
          class="transition-option"
          @click="showTransitionModal = true; selectedOption = option"
        >
          <view class="option-label">{{ option.label }}</view>
          <view class="option-type" :class="option.type">
            {{ getTransitionTypeLabel(option.type) }}
            <text v-if="option.need_reason">（需填原因）</text>
          </view>
          <view class="option-arrow">›</view>
        </view>
      </view>
    </view>

    <view v-if="showTransitionModal" class="modal-overlay" @click="closeTransitionModal">
      <view class="modal-content" @click.stop>
        <view class="modal-title">确认流转</view>
        <view class="modal-body">
          <view class="confirm-info">
            <view class="info-row">
              <text class="info-label">当前阶段：</text>
              <text class="info-value">{{ getStatusLabel(detail.status) }}</text>
            </view>
            <view class="info-row">
              <text class="info-label">目标阶段：</text>
              <text class="info-value highlight">{{ selectedOption?.label }}</text>
            </view>
            <view class="info-row">
              <text class="info-label">操作类型：</text>
              <text class="info-value">{{ getTransitionTypeLabel(selectedOption?.type) }}</text>
            </view>
          </view>
          <view v-if="selectedOption?.need_reason" class="reason-section">
            <text class="reason-label">流转原因 <span class="required">*</span></text>
            <textarea
              v-model="transitionReason"
              placeholder="请填写流转原因"
              class="textarea"
            ></textarea>
          </view>
        </view>
        <view class="modal-footer">
          <button class="btn-cancel" @click="closeTransitionModal">取消</button>
          <button class="btn-confirm primary" @click="confirmTransition" :disabled="!canConfirm">
            确认
          </button>
        </view>
      </view>
    </view>

    <view v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
      <view class="modal-content edit-modal" @click.stop>
        <view class="modal-title">编辑意向订单</view>
        <view class="modal-body">
          <view class="form-item">
            <text class="form-label">客户名称 <span class="required">*</span></text>
            <input
              v-model="editForm.customer_name"
              placeholder="请输入客户名称"
              class="form-input"
            />
          </view>
          <view class="form-item">
            <text class="form-label">产品名称 <span class="required">*</span></text>
            <input
              v-model="editForm.product_name"
              placeholder="请输入产品名称"
              class="form-input"
            />
          </view>
          <view class="form-item">
            <text class="form-label">型号 <span class="required">*</span></text>
            <input
              v-model="editForm.model"
              placeholder="请输入型号"
              class="form-input"
            />
          </view>
          <view class="form-item">
            <text class="form-label">电压</text>
            <input
              v-model="editForm.voltage"
              placeholder="请输入电压"
              class="form-input"
            />
          </view>
          <view class="form-item">
            <text class="form-label">数量</text>
            <input
              v-model="editForm.quantity"
              type="number"
              placeholder="请输入数量"
              class="form-input"
            />
          </view>
          <view class="form-item">
            <text class="form-label">预计成交日期</text>
            <input
              v-model="editForm.expected_close_date"
              placeholder="请输入预计成交日期"
              class="form-input"
            />
          </view>
          <view class="form-item">
            <text class="form-label">客户需求</text>
            <textarea
              v-model="editForm.customer_requirements"
              placeholder="请输入客户需求"
              class="form-textarea"
            ></textarea>
          </view>
        </view>
        <view class="modal-footer">
          <button class="btn-cancel" @click="closeEditModal">取消</button>
          <button class="btn-confirm primary" @click="submitEdit" :disabled="isSubmitting">
            {{ isSubmitting ? '提交中...' : '保存' }}
          </button>
        </view>
      </view>
    </view>
  </scroll-view>
  <view class="loading" v-else-if="loading">
    <text>加载中...</text>
  </view>
  <view class="empty" v-else>
    <text>暂无意向订单信息</text>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const detail = ref(null)
const transitions = ref([])
const availableTransitions = ref([])
const progress = ref(0)
const loading = ref(true)

const showTransitionModal = ref(false)
const selectedOption = ref(null)
const transitionReason = ref('')

const showEditModal = ref(false)
const isSubmitting = ref(false)
const editForm = ref({
  customer_name: '',
  product_name: '',
  model: '',
  voltage: '',
  quantity: 1,
  expected_close_date: '',
  customer_requirements: ''
})

const canConfirm = computed(() => {
  if (!selectedOption.value) return false
  if (selectedOption.value.need_reason && !transitionReason.value.trim()) return false
  return true
})

const legacyStatusMap = {
  pending: 'new',
  done: 'won'
}

const normalizeStatus = (status) => {
  if (!status) return status
  return legacyStatusMap[status] || status
}

const stageLabels = {
  new: '新建',
  initial_review: '初评',
  requirement_confirm: '需求确认',
  proposal: '方案',
  business_negotiation: '商务谈判',
  contract_review: '合同评审',
  won: '成交',
  lost: '失败关闭',
  pending: '新建',
  done: '成交'
}

const getStatusLabel = (status) => {
  return stageLabels[status] || status
}

const getStatusClass = (status) => {
  const normalized = normalizeStatus(status)
  const classMap = {
    new: 'status-new',
    initial_review: 'status-review',
    requirement_confirm: 'status-confirm',
    proposal: 'status-proposal',
    business_negotiation: 'status-negotiation',
    contract_review: 'status-contract',
    won: 'status-won',
    lost: 'status-lost'
  }
  return classMap[normalized] || 'status-default'
}

const getTransitionTypeLabel = (type) => {
  const labels = {
    forward: '推进',
    backward: '退回',
    lost: '关闭'
  }
  return labels[type] || type
}

const getTransitionDotClass = (type) => {
  const classMap = {
    forward: 'dot-forward',
    backward: 'dot-backward',
    lost: 'dot-lost'
  }
  return classMap[type] || 'dot-default'
}

const fetchDetail = async (id) => {
  loading.value = true
  try {
    const res = await api.intentOrderDetail(id)
    if (!res || !res.item) {
      throw new Error('意向订单不存在')
    }
    detail.value = res.item
    transitions.value = res.transitions || []
    availableTransitions.value = res.available_transitions || []
    progress.value = res.progress || 0
  } catch (error) {
    detail.value = null
    transitions.value = []
    availableTransitions.value = []
    progress.value = 0
    uni.showToast({ title: (error && error.message) || '获取意向订单失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

const closeTransitionModal = () => {
  showTransitionModal.value = false
  selectedOption.value = null
  transitionReason.value = ''
}

const confirmTransition = async () => {
  if (!detail.value || !selectedOption.value) return
  
  const payload = {
    to_status: selectedOption.value.status
  }
  if (selectedOption.value.need_reason) {
    payload.reason = transitionReason.value.trim()
  }
  
  try {
    await api.intentOrderTransition(detail.value.id, payload)
    uni.showToast({ title: '流转成功', icon: 'success' })
    closeTransitionModal()
    fetchDetail(detail.value.id)
  } catch (e) {
    console.error(e)
  }
}

const openEditModal = () => {
  if (!detail.value) return
  editForm.value = {
    customer_name: detail.value.customer_name || '',
    product_name: detail.value.product_name || '',
    model: detail.value.model || '',
    voltage: detail.value.voltage || '',
    quantity: detail.value.quantity || 1,
    expected_close_date: detail.value.expected_close_date || '',
    customer_requirements: detail.value.customer_requirements || ''
  }
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  isSubmitting.value = false
}

const validateEditForm = () => {
  if (!editForm.value.customer_name || !editForm.value.customer_name.trim()) {
    uni.showToast({ title: '请输入客户名称', icon: 'none' })
    return false
  }
  if (!editForm.value.product_name || !editForm.value.product_name.trim()) {
    uni.showToast({ title: '请输入产品名称', icon: 'none' })
    return false
  }
  if (!editForm.value.model || !editForm.value.model.trim()) {
    uni.showToast({ title: '请输入型号', icon: 'none' })
    return false
  }
  return true
}

const submitEdit = async () => {
  if (!validateEditForm()) return
  if (!detail.value) return

  isSubmitting.value = true
  try {
    const payload = {
      customer_name: editForm.value.customer_name.trim(),
      product_name: editForm.value.product_name.trim(),
      model: editForm.value.model.trim(),
      voltage: editForm.value.voltage || null,
      quantity: parseInt(editForm.value.quantity) || 1,
      expected_close_date: editForm.value.expected_close_date || null,
      customer_requirements: editForm.value.customer_requirements || null
    }

    await api.updateIntentOrder(detail.value.id, payload)
    uni.showToast({ title: '保存成功', icon: 'success' })
    closeEditModal()
    fetchDetail(detail.value.id)
    uni.$emit('intentOrderUpdated')
  } catch (e) {
    console.error(e)
  } finally {
    isSubmitting.value = false
  }
}

onLoad(async (query) => {
  if (query.id) {
    fetchDetail(query.id)
  }
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  min-height: 100vh;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.04);
}

.status-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16rpx;
}
.status-badge {
  padding: 8rpx 24rpx;
  border-radius: 24rpx;
  font-size: 26rpx;
  color: #fff;
  font-weight: 500;
}
.status-new { background: #1890ff; }
.status-review { background: #faad14; }
.status-confirm { background: #13c2c2; }
.status-proposal { background: #52c41a; }
.status-negotiation { background: #722ed1; }
.status-contract { background: #eb2f96; }
.status-won { background: #52c41a; }
.status-lost { background: #ff4d4f; }
.status-default { background: #999; }

.progress-text {
  font-size: 28rpx;
  color: #666;
}
.progress-bar {
  height: 12rpx;
  background: #f0f0f0;
  border-radius: 6rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #1890ff, #52c41a);
  border-radius: 6rpx;
  transition: width 0.3s ease;
}

.title {
  font-size: 34rpx;
  font-weight: 600;
}
.subtitle {
  margin-top: 8rpx;
  color: #8c8c8c;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  margin-top: 12rpx;
  color: #666;
  font-size: 28rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.content {
  color: #666;
  line-height: 1.6;
  font-size: 28rpx;
}

.timeline {
  position: relative;
}
.timeline-item {
  display: flex;
  position: relative;
  padding-bottom: 32rpx;
}
.timeline-item.last {
  padding-bottom: 0;
}
.timeline-dot {
  width: 20rpx;
  height: 20rpx;
  border-radius: 50%;
  margin-top: 8rpx;
  margin-right: 20rpx;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}
.dot-forward { background: #52c41a; }
.dot-backward { background: #faad14; }
.dot-lost { background: #ff4d4f; }
.dot-default { background: #999; }

.timeline-item::before {
  content: '';
  position: absolute;
  left: 9rpx;
  top: 36rpx;
  bottom: 0;
  width: 2rpx;
  background: #e8e8e8;
}
.timeline-item.last::before {
  display: none;
}

.timeline-content {
  flex: 1;
  padding-bottom: 8rpx;
}
.timeline-header {
  display: flex;
  align-items: center;
  gap: 16rpx;
}
.timeline-status {
  font-size: 28rpx;
  font-weight: 500;
  color: #333;
}
.timeline-type {
  font-size: 22rpx;
  padding: 2rpx 12rpx;
  border-radius: 8rpx;
}
.timeline-type.forward {
  background: #f6ffed;
  color: #52c41a;
}
.timeline-type.backward {
  background: #fff7e6;
  color: #faad14;
}
.timeline-type.lost {
  background: #fff1f0;
  color: #ff4d4f;
}

.timeline-meta {
  display: flex;
  justify-content: space-between;
  margin-top: 8rpx;
  font-size: 24rpx;
  color: #999;
}
.timeline-reason {
  margin-top: 12rpx;
  padding: 16rpx;
  background: #f7f8fa;
  border-radius: 12rpx;
  font-size: 26rpx;
  color: #666;
}

.transition-options {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}
.transition-option {
  display: flex;
  align-items: center;
  padding: 24rpx;
  background: #f7f8fa;
  border-radius: 16rpx;
  position: relative;
}
.option-label {
  font-size: 30rpx;
  font-weight: 500;
  color: #333;
  flex: 1;
}
.option-type {
  font-size: 24rpx;
  color: #666;
  margin-right: 16rpx;
}
.option-type.forward { color: #52c41a; }
.option-type.backward { color: #faad14; }
.option-type.lost { color: #ff4d4f; }
.option-arrow {
  font-size: 36rpx;
  color: #ccc;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-content {
  width: 85%;
  max-width: 640rpx;
  background: #fff;
  border-radius: 24rpx;
  overflow: hidden;
}
.modal-title {
  padding: 32rpx;
  text-align: center;
  font-size: 32rpx;
  font-weight: 600;
  border-bottom: 1rpx solid #f0f0f0;
}
.modal-body {
  padding: 24rpx 32rpx;
}
.confirm-info {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 24rpx;
}
.info-row {
  display: flex;
  margin-bottom: 16rpx;
  font-size: 28rpx;
}
.info-row:last-child {
  margin-bottom: 0;
}
.info-label {
  color: #999;
  width: 160rpx;
}
.info-value {
  color: #333;
  flex: 1;
}
.info-value.highlight {
  color: #1677ff;
  font-weight: 500;
}
.reason-section {
  margin-top: 24rpx;
}
.reason-label {
  font-size: 28rpx;
  color: #333;
  display: block;
  margin-bottom: 12rpx;
}
.required {
  color: #ff4d4f;
}
.textarea {
  width: 100%;
  min-height: 180rpx;
  padding: 16rpx;
  background: #fff;
  border: 2rpx solid #e8e8e8;
  border-radius: 12rpx;
  font-size: 28rpx;
  box-sizing: border-box;
}
.modal-footer {
  display: flex;
  border-top: 1rpx solid #f0f0f0;
}
.modal-footer button {
  flex: 1;
  height: 96rpx;
  line-height: 96rpx;
  border-radius: 0;
  border: none;
  background: #fff;
  font-size: 30rpx;
}
.btn-cancel {
  color: #666;
  border-right: 1rpx solid #f0f0f0;
}
.btn-confirm {
  color: #1677ff;
}
.btn-confirm.primary {
  background: #1677ff;
  color: #fff;
}
.btn-confirm[disabled] {
  opacity: 0.5;
}
.loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 120rpx 0;
  color: #999;
  font-size: 28rpx;
}
.empty {
  padding: 120rpx 0;
  text-align: center;
  color: #999;
  font-size: 28rpx;
}
.action-buttons {
  display: flex;
  gap: 16rpx;
}
.action-btn {
  flex: 1;
  padding: 20rpx;
  border-radius: 12rpx;
  font-size: 28rpx;
  border: none;
}
.edit-btn {
  background: #1677ff;
  color: #fff;
}
.edit-modal {
  max-height: 85vh;
  overflow: hidden;
}
.edit-modal .modal-body {
  max-height: 60vh;
  overflow-y: auto;
}
.form-item {
  margin-bottom: 24rpx;
}
.form-item:last-child {
  margin-bottom: 0;
}
.form-label {
  display: block;
  font-size: 28rpx;
  color: #333;
  margin-bottom: 12rpx;
}
.form-input {
  width: 100%;
  padding: 20rpx 16rpx;
  background: #f7f8fa;
  border: 2rpx solid #e8e8e8;
  border-radius: 12rpx;
  font-size: 28rpx;
  box-sizing: border-box;
}
.form-textarea {
  width: 100%;
  min-height: 180rpx;
  padding: 16rpx;
  background: #f7f8fa;
  border: 2rpx solid #e8e8e8;
  border-radius: 12rpx;
  font-size: 28rpx;
  box-sizing: border-box;
}
</style>
