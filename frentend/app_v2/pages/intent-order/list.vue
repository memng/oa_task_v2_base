<template>
  <scroll-view scroll-y class="page">
    <view class="header">
      <view class="tabs">
        <view
          v-for="item in tabs"
          :key="item.value"
          :class="['tab', { active: currentTab === item.value }]"
          @click="switchTab(item.value)"
        >
          <view class="count">{{ summary[item.value] || 0 }}</view>
          <view>{{ item.label }}</view>
        </view>
      </view>
      <picker :range="statusFilters" range-key="label" @change="onStatusChange">
        <view class="filter">{{ currentStatus.label }}</view>
      </picker>
    </view>

    <view class="card" v-for="item in list" :key="item.id">
      <view class="status-badge" :class="getStatusClass(item.status)">
        {{ getStatusLabel(item.status) }}
      </view>
      <view class="title">{{ item.product_name }}</view>
      <view class="meta">
        <text>型号：{{ item.model || '-' }}</text>
        <text>电压：{{ item.voltage || '-' }}</text>
        <text>数量：{{ item.quantity }}台</text>
      </view>
      <view class="customer">{{ item.customer_name }}</view>
      <view class="requirement">
        <view>客户要求</view>
        <view class="detail">{{ item.customer_requirements || '待补充' }}</view>
      </view>
      <view class="actions">
        <button size="mini" @click="edit(item)">编辑</button>
        <button class="primary" size="mini" @click="changeStatus(item)" :disabled="isFinalStatus(item.status)">
          变更状态
        </button>
        <button size="mini" @click="openDetail(item)">详情</button>
      </view>
    </view>
    <view v-if="!list.length" class="empty">暂无意向订单</view>

    <button class="fab" @click="showForm = !showForm">+</button>
    <view v-if="showForm" class="form">
      <input v-model="form.customer_name" placeholder="客户名称" />
      <input v-model="form.product_name" placeholder="产品名称" />
      <input v-model="form.model" placeholder="型号" />
      <button class="primary" size="mini" @click="create">保存</button>
    </view>

    <view v-if="showTransitionModal" class="modal-overlay" @click="closeTransitionModal">
      <view class="modal-content" @click.stop>
        <view class="modal-title">阶段流转</view>
        <view class="modal-body">
          <view class="current-status">
            当前阶段：<span class="status-text">{{ getStatusLabel(currentItem?.status) }}</span>
          </view>
          <view class="transition-options">
            <view
              v-for="option in availableTransitions"
              :key="option.status"
              :class="['transition-option', { selected: selectedTransition === option.status }]"
              @click="selectTransition(option)"
            >
              <view class="option-label">{{ option.label }}</view>
              <view class="option-type" :class="option.type">
                {{ getTransitionTypeLabel(option.type) }}
              </view>
            </view>
          </view>
          <view v-if="selectedTransitionNeedReason" class="reason-input">
            <textarea
              v-model="transitionReason"
              placeholder="请填写原因（必填）"
              class="textarea"
            ></textarea>
          </view>
        </view>
        <view class="modal-footer">
          <button class="btn-cancel" @click="closeTransitionModal">取消</button>
          <button class="btn-confirm primary" @click="confirmTransition" :disabled="!canConfirmTransition">
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
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow, onUnload } from '@dcloudio/uni-app'
import { api, request } from '../../utils/request'

const list = ref([])

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

const tabs = [
  { label: '进行中', value: 'in_progress' },
  { label: '已成交', value: 'won' },
  { label: '已失败', value: 'lost' }
]

const statusFilters = [
  { label: '全部', value: '' },
  { label: '新建', value: 'new' },
  { label: '初评', value: 'initial_review' },
  { label: '需求确认', value: 'requirement_confirm' },
  { label: '方案', value: 'proposal' },
  { label: '商务谈判', value: 'business_negotiation' },
  { label: '合同评审', value: 'contract_review' },
  { label: '已成交', value: 'won' },
  { label: '已失败', value: 'lost' }
]

const currentTab = ref('in_progress')
const currentStatus = ref(statusFilters[0])
const summary = ref({
  in_progress: 0,
  won: 0,
  lost: 0,
  new: 0,
  initial_review: 0,
  requirement_confirm: 0,
  proposal: 0,
  business_negotiation: 0,
  contract_review: 0
})

const showForm = ref(false)
const form = ref({ customer_name: '', product_name: '', model: '', quantity: 1 })

const showTransitionModal = ref(false)
const currentItem = ref(null)
const availableTransitions = ref([])
const selectedTransition = ref('')
const selectedTransitionNeedReason = ref(false)
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
const currentEditItem = ref(null)

const canConfirmTransition = computed(() => {
  if (!selectedTransition.value) return false
  if (selectedTransitionNeedReason.value && !transitionReason.value.trim()) return false
  return true
})

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

const isFinalStatus = (status) => {
  const normalized = normalizeStatus(status)
  return normalized === 'won' || normalized === 'lost'
}

const getTransitionTypeLabel = (type) => {
  const labels = {
    forward: '推进',
    backward: '退回',
    lost: '关闭'
  }
  return labels[type] || type
}

const fetchList = async () => {
  const params = {}
  const tabStatus = currentStatus.value.value || currentTab.value
  
  if (tabStatus === 'in_progress') {
  } else if (tabStatus) {
    params.status = tabStatus
  }
  
  const res = await api.intentOrders(params)
  let items = res.items || []
  
  if (currentTab.value === 'in_progress' && !currentStatus.value.value) {
    items = items.filter(item => !isFinalStatus(item.status))
  }
  
  list.value = items
  
  const remoteSummary = res.summary || {}
  summary.value = {
    in_progress: 
      (remoteSummary.new || 0) +
      (remoteSummary.initial_review || 0) +
      (remoteSummary.requirement_confirm || 0) +
      (remoteSummary.proposal || 0) +
      (remoteSummary.business_negotiation || 0) +
      (remoteSummary.contract_review || 0),
    won: remoteSummary.won || 0,
    lost: remoteSummary.lost || 0,
    new: remoteSummary.new || 0,
    initial_review: remoteSummary.initial_review || 0,
    requirement_confirm: remoteSummary.requirement_confirm || 0,
    proposal: remoteSummary.proposal || 0,
    business_negotiation: remoteSummary.business_negotiation || 0,
    contract_review: remoteSummary.contract_review || 0
  }
}

const switchTab = (value) => {
  currentTab.value = value
  currentStatus.value = statusFilters[0]
  fetchList()
}

const onStatusChange = (e) => {
  currentStatus.value = statusFilters[e.detail.value]
  fetchList()
}

const create = async () => {
  if (!form.value.customer_name || !form.value.product_name) {
    uni.showToast({ title: '请填写完整', icon: 'none' })
    return
  }
  await request({ url: '/intent-orders', method: 'POST', data: form.value })
  showForm.value = false
  form.value = { customer_name: '', product_name: '', model: '', quantity: 1 }
  fetchList()
}

const edit = (item) => {
  currentEditItem.value = item
  editForm.value = {
    customer_name: item.customer_name || '',
    product_name: item.product_name || '',
    model: item.model || '',
    voltage: item.voltage || '',
    quantity: item.quantity || 1,
    expected_close_date: item.expected_close_date || '',
    customer_requirements: item.customer_requirements || ''
  }
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  isSubmitting.value = false
  currentEditItem.value = null
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
  if (editForm.value.expected_close_date && editForm.value.expected_close_date.trim()) {
    const dateStr = editForm.value.expected_close_date.trim()
    if (!isValidDate(dateStr)) {
      uni.showToast({ title: '预计成交日期格式不正确', icon: 'none' })
      return false
    }
  }
  return true
}

const isValidDate = (dateStr) => {
  if (!dateStr) return true
  
  const datePattern1 = /^\d{4}-\d{2}-\d{2}$/
  const datePattern2 = /^\d{4}\/\d{2}\/\d{2}$/
  const dateTimePattern = /^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/
  
  if (!(datePattern1.test(dateStr) || datePattern2.test(dateStr) || dateTimePattern.test(dateStr))) {
    return false
  }
  
  const normalizedDate = dateStr.replace(/\//g, '-').split(' ')[0]
  const parts = normalizedDate.split('-')
  const year = parseInt(parts[0], 10)
  const month = parseInt(parts[1], 10)
  const day = parseInt(parts[2], 10)
  
  if (month < 1 || month > 12) {
    return false
  }
  
  const daysInMonth = getDaysInMonth(year, month)
  if (day < 1 || day > daysInMonth) {
    return false
  }
  
  return true
}

const getDaysInMonth = (year, month) => {
  const daysPerMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  
  if (month === 2) {
    if ((year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0)) {
      return 29
    }
  }
  
  return daysPerMonth[month - 1]
}

const submitEdit = async () => {
  if (!validateEditForm()) return
  if (!currentEditItem.value) return

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

    await api.updateIntentOrder(currentEditItem.value.id, payload)
    uni.showToast({ title: '保存成功', icon: 'success' })
    closeEditModal()
    fetchList()
  } catch (e) {
    console.error(e)
  } finally {
    isSubmitting.value = false
  }
}

const changeStatus = async (item) => {
  if (isFinalStatus(item.status)) {
    uni.showToast({ title: '终态不可再流转', icon: 'none' })
    return
  }
  
  currentItem.value = item
  
  try {
    const res = await api.intentOrderAvailableTransitions(item.id)
    availableTransitions.value = res.available_transitions || []
    showTransitionModal.value = true
    selectedTransition.value = ''
    selectedTransitionNeedReason.value = false
    transitionReason.value = ''
  } catch (e) {
    console.error(e)
  }
}

const selectTransition = (option) => {
  selectedTransition.value = option.status
  selectedTransitionNeedReason.value = option.need_reason
}

const closeTransitionModal = () => {
  showTransitionModal.value = false
  currentItem.value = null
  availableTransitions.value = []
  selectedTransition.value = ''
  selectedTransitionNeedReason.value = false
  transitionReason.value = ''
}

const confirmTransition = async () => {
  if (!currentItem.value || !selectedTransition.value) return
  
  try {
    const payload = {
      to_status: selectedTransition.value
    }
    if (selectedTransitionNeedReason.value) {
      payload.reason = transitionReason.value.trim()
    }
    
    await api.intentOrderTransition(currentItem.value.id, payload)
    uni.showToast({ title: '流转成功', icon: 'success' })
    closeTransitionModal()
    fetchList()
  } catch (e) {
    console.error(e)
  }
}

const openDetail = (item) => {
  uni.navigateTo({ url: `/pages/intent-order/detail?id=${item.id}` })
}

onShow(() => {
  fetchList()
})

onUnload(() => {
  uni.$off('intentOrderUpdated')
})

uni.$on('intentOrderUpdated', () => {
  fetchList()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  position: relative;
  height: 100vh;
}
.header {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.tabs {
  display: flex;
  justify-content: space-between;
}
.tab {
  flex: 1;
  text-align: center;
  border-right: 1rpx solid #f0f0f0;
  color: #666;
}
.tab:last-child {
  border-right: none;
}
.tab.active {
  color: #1677ff;
}
.count {
  font-size: 36rpx;
  font-weight: 600;
}
.filter {
  margin-top: 16rpx;
  background: #f7f8fa;
  padding: 16rpx;
  border-radius: 16rpx;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  position: relative;
}
.status-badge {
  position: absolute;
  top: 24rpx;
  right: 24rpx;
  padding: 4rpx 16rpx;
  border-radius: 8rpx;
  font-size: 24rpx;
  color: #fff;
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

.title {
  font-size: 30rpx;
  font-weight: 600;
  margin: 8rpx 0;
  padding-right: 120rpx;
}
.meta {
  display: flex;
  justify-content: space-between;
  color: #666;
  font-size: 24rpx;
  flex-wrap: wrap;
  gap: 8rpx;
}
.customer {
  margin: 12rpx 0;
  color: #fa541c;
}
.requirement {
  font-size: 24rpx;
  color: #666;
}
.detail {
  margin-top: 4rpx;
  color: #333;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-top: 16rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
}
.empty {
  text-align: center;
  color: #999;
  padding: 60rpx 0;
}
.fab {
  position: fixed;
  right: 48rpx;
  bottom: 200rpx;
  width: 88rpx;
  height: 88rpx;
  border-radius: 44rpx;
  background: #1677ff;
  color: #fff;
  border: none;
  z-index: 100;
}
.form {
  position: fixed;
  left: 32rpx;
  right: 32rpx;
  bottom: 40rpx;
  background: #fff;
  padding: 24rpx;
  border-radius: 24rpx;
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 12rpx;
  z-index: 200;
}
.form input {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
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
  width: 80%;
  max-width: 600rpx;
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
  padding: 24rpx;
}
.current-status {
  padding: 16rpx;
  background: #f7f8fa;
  border-radius: 12rpx;
  margin-bottom: 24rpx;
  font-size: 28rpx;
}
.status-text {
  color: #1677ff;
  font-weight: 600;
}
.transition-options {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}
.transition-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx;
  border: 2rpx solid #e8e8e8;
  border-radius: 16rpx;
}
.transition-option.selected {
  border-color: #1677ff;
  background: #e6f4ff;
}
.option-label {
  font-size: 30rpx;
  font-weight: 500;
}
.option-type {
  font-size: 24rpx;
  padding: 4rpx 12rpx;
  border-radius: 8rpx;
}
.option-type.forward {
  background: #f6ffed;
  color: #52c41a;
}
.option-type.backward {
  background: #fff7e6;
  color: #faad14;
}
.option-type.lost {
  background: #fff1f0;
  color: #ff4d4f;
}
.reason-input {
  margin-top: 24rpx;
}
.textarea {
  width: 100%;
  min-height: 160rpx;
  padding: 16rpx;
  background: #f7f8fa;
  border-radius: 12rpx;
  font-size: 28rpx;
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
.required {
  color: #ff4d4f;
}
.form-input {
  width: 100%;
  height: 88rpx;
  line-height: 88rpx;
  padding: 0 20rpx;
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
