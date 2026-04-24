<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="section-title">任务信息</view>
      <view class="form-item">
        <text>任务类型</text>
        <picker :range="types" range-key="label" @change="onTypeChange">
          <view class="picker">{{ currentType.label }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>任务标题</text>
        <input v-model="form.title" placeholder="请输入任务标题" />
      </view>
      <view class="form-item">
        <text>
          关联订单
          <text v-if="isOrderOptional" class="optional-label">（选填）</text>
        </text>
        <view
          v-if="orderInfo"
          class="order-pill selectable"
          @click="openOrderSelector"
        >
          <view>PI: {{ orderInfo.pi || '未设置PI' }}</view>
          <view class="order-sub">{{ orderInfo.customer || '客户未填写' }} · ID: {{ form.order_id }}</view>
        </view>
        <view
          v-else
          class="picker placeholder"
          @click="openOrderSelector"
        >
          请选择关联订单
        </view>
      </view>
    </view>
    <view class="card">
      <view class="section-title">执行信息</view>
      <view class="form-item">
        <text>负责人</text>
        <view class="picker" :class="{ placeholder: !assigneeLabel }" @click="openAssigneeSelector">
          {{ assigneeLabel || '请选择执行人' }}
        </view>
      </view>
      <view class="form-item">
        <text>截止时间</text>
        <uni-datetime-picker
          type="datetime"
          return-type="string"
          :clear-icon="false"
          :border="false"
          v-model="form.due_at"
          @change="onDueDateChange"
        >
          <view class="picker" :class="{ placeholder: !dueDateLabel }">
            {{ dueDateLabel || '请选择截止时间' }}
          </view>
        </uni-datetime-picker>
      </view>
      <view class="form-item switch-item">
        <text>需要审核</text>
        <switch :checked="form.need_audit === 1" @change="onNeedAuditChange" />
      </view>
    </view>
    <view class="card">
      <view class="section-title">任务要求</view>
      <textarea v-model="form.description" placeholder="请输入任务要求"></textarea>
    </view>
    <button class="primary" :loading="submitting" :disabled="submitting" @click="submit">创建任务</button>
  </scroll-view>
  <view v-if="orderDialogVisible" class="assign-mask">
    <view class="assign-dialog large">
      <view class="dialog-title">选择关联订单</view>
      <view class="dialog-section">
        <input class="dialog-search" v-model.trim="orderKeyword" placeholder="搜索PI或客户名称" />
        <scroll-view scroll-y class="dialog-scroll">
          <view v-if="orderLoading" class="loading">订单加载中...</view>
          <view v-else>
            <view
              v-for="item in filteredOrders"
              :key="item.id"
              class="list-row"
              :class="{ active: isSelectedOrder(item) }"
              @click="selectOrder(item)"
            >
              <view class="row-title">{{ item.pi || `订单ID ${item.id}` }}</view>
              <view class="row-desc">{{ item.customer || '未填写客户' }}</view>
            </view>
            <view v-if="!filteredOrders.length" class="empty">暂无匹配订单</view>
          </view>
        </scroll-view>
      </view>
      <view class="dialog-actions">
        <button class="outline" @click="clearOrderSelection">不关联订单</button>
        <button class="outline" @click="closeOrderSelector">取消</button>
        <button class="primary" @click="confirmOrder">确认</button>
      </view>
    </view>
  </view>
  <view v-if="assigneeDialogVisible" class="assign-mask">
    <view class="assign-dialog">
      <view class="dialog-title">选择负责人</view>
      <view class="dialog-section">
        <scroll-view scroll-y class="staff-scroll">
          <view v-if="staffLoading" class="loading">员工列表加载中...</view>
          <view v-else>
            <view v-for="group in staffGroups" :key="group.name" class="staff-group">
              <view class="group-title">{{ group.name }}</view>
              <view class="staff-list">
                <view
                  v-for="user in group.users"
                  :key="user.id"
                  class="staff-item"
                  :class="{ active: isSelectedAssignee(user) }"
                  @click="selectAssignee(user)"
                >
                  {{ user.name }}
                </view>
              </view>
            </view>
            <view v-if="!staffGroups.length" class="empty">暂无员工</view>
          </view>
        </scroll-view>
      </view>
      <view class="dialog-actions">
        <button class="outline" @click="closeAssigneeSelector">取消</button>
        <button class="primary" @click="confirmAssignee">确认</button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'
import UniDatetimePicker from '../../uni_modules/uni-datetime-picker/components/uni-datetime-picker/uni-datetime-picker.vue'

const types = [
  { label: '采购任务', value: 'procurement' },
  { label: '铭牌制作', value: 'nameplate' },
  { label: '机器数据', value: 'machine_data' },
  { label: '验收任务', value: 'acceptance' },
  { label: '打包唛头', value: 'packaging' },
  { label: '装柜发货', value: 'shipment' },
  { label: '工厂订单', value: 'factory_order' },
  { label: '临时任务', value: 'temporary' }
]
const currentType = ref(types[0])
const form = reactive({
  type: 'procurement',
  title: '',
  order_id: null,
  assigned_to: '',
  due_at: '',
  need_audit: 0,
  description: ''
})
const orderInfo = ref(null)
const submitting = ref(false)
const optionalOrderTypes = ['temporary', 'factory_order']
const isOrderOptional = computed(() => optionalOrderTypes.includes(form.type))

const orderList = ref([])
const orderLoading = ref(false)
const orderLoaded = ref(false)
const orderDialogVisible = ref(false)
const orderKeyword = ref('')
const selectedOrderId = ref('')
const selectedOrderInfo = ref(null)

const filteredOrders = computed(() => {
  if (!orderKeyword.value) {
    return orderList.value
  }
  const keyword = orderKeyword.value.toLowerCase()
  return orderList.value.filter((item) => {
    const pi = item.pi ? item.pi.toLowerCase() : ''
    const customer = item.customer ? item.customer.toLowerCase() : ''
    return pi.includes(keyword) || customer.includes(keyword) || String(item.id).includes(keyword)
  })
})

const staffList = ref([])
const staffLoading = ref(false)
const staffLoaded = ref(false)
const assigneeDialogVisible = ref(false)
const assigneeName = ref('')
const assigneeOriginal = reactive({ id: '', name: '' })
const selectedAssigneeId = ref('')
const selectedAssigneeName = ref('')

const staffGroups = computed(() => {
  if (!staffList.value.length) {
    return []
  }
  const groups = staffList.value.reduce((acc, user) => {
    const dept = user.dept_name || '未分组'
    if (!acc[dept]) {
      acc[dept] = []
    }
    acc[dept].push(user)
    return acc
  }, {})
  return Object.keys(groups).map((dept) => ({
    name: dept,
    users: groups[dept]
  }))
})

const assigneeLabel = computed(() => {
  if (assigneeName.value) {
    return assigneeName.value
  }
  if (!form.assigned_to) {
    return ''
  }
  const target = staffList.value.find((user) => String(user.id) === String(form.assigned_to))
  return target ? target.name : ''
})

const dueDateLabel = computed(() => {
  if (!form.due_at) {
    return ''
  }
  const value = form.due_at.replace(/-/g, '/')
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return form.due_at
  }
  const pad = (num) => String(num).padStart(2, '0')
  return `${date.getFullYear()}年${pad(date.getMonth() + 1)}月${pad(date.getDate())}日 ${pad(date.getHours())}:${pad(date.getMinutes())}`
})

const onTypeChange = (e) => {
  currentType.value = types[e.detail.value]
  form.type = currentType.value.value
}

const onNeedAuditChange = (event) => {
  form.need_audit = event.detail.value ? 1 : 0
}

const fetchOrders = async () => {
  if (orderLoading.value) {
    return
  }
  orderLoading.value = true
  try {
    const res = await api.orderList({ page_size: 100, status: 'in_progress' })
    const items = res.items || []
    orderList.value = items.map((item) => ({
      id: item.id != null ? String(item.id) : '',
      pi: (item.pi_numbers && item.pi_numbers.length ? item.pi_numbers.join(' / ') : item.pi_number) || '',
      customer: item.customer_name || '',
      status: item.status || ''
    }))
    orderLoaded.value = true
  } catch (error) {
    console.error(error)
  } finally {
    orderLoading.value = false
  }
}

const ensureOrderLoaded = () => {
  if (!orderLoaded.value && !orderLoading.value) {
    fetchOrders()
  }
}

const openOrderSelector = () => {
  selectedOrderId.value = form.order_id ? String(form.order_id) : ''
  selectedOrderInfo.value = orderInfo.value ? { ...orderInfo.value } : null
  orderDialogVisible.value = true
  ensureOrderLoaded()
}

const closeOrderSelector = () => {
  selectedOrderId.value = form.order_id ? String(form.order_id) : ''
  selectedOrderInfo.value = orderInfo.value ? { ...orderInfo.value } : null
  orderDialogVisible.value = false
}

const selectOrder = (order) => {
  selectedOrderId.value = order.id
  selectedOrderInfo.value = { pi: order.pi, customer: order.customer }
}

const isSelectedOrder = (order) => String(order.id) === String(selectedOrderId.value || '')

const clearOrderSelection = () => {
  selectedOrderId.value = ''
  selectedOrderInfo.value = null
}

const confirmOrder = () => {
  form.order_id = selectedOrderId.value ? String(selectedOrderId.value) : null
  orderInfo.value = selectedOrderInfo.value
  orderDialogVisible.value = false
}

const fetchStaff = async () => {
  if (staffLoading.value) {
    return
  }
  staffLoading.value = true
  try {
    const res = await api.lookupStaff()
    staffList.value = res.items || []
    staffLoaded.value = true
  } catch (error) {
    console.error(error)
  } finally {
    staffLoading.value = false
  }
}

const ensureStaffLoaded = () => {
  if (!staffLoaded.value && !staffLoading.value) {
    fetchStaff()
  }
}

const openAssigneeSelector = () => {
  assigneeOriginal.id = form.assigned_to ? String(form.assigned_to) : ''
  assigneeOriginal.name = assigneeLabel.value || ''
  selectedAssigneeId.value = assigneeOriginal.id
  selectedAssigneeName.value = assigneeOriginal.name
  assigneeDialogVisible.value = true
  ensureStaffLoaded()
}

const closeAssigneeSelector = () => {
  selectedAssigneeId.value = assigneeOriginal.id
  selectedAssigneeName.value = assigneeOriginal.name
  assigneeDialogVisible.value = false
}

const selectAssignee = (staff) => {
  selectedAssigneeId.value = String(staff.id)
  selectedAssigneeName.value = staff.name
}

const isSelectedAssignee = (staff) => String(staff.id) === String(selectedAssigneeId.value || '')

const confirmAssignee = () => {
  form.assigned_to = selectedAssigneeId.value ? String(selectedAssigneeId.value) : ''
  assigneeName.value = selectedAssigneeName.value || ''
  assigneeDialogVisible.value = false
}

const normalizeDatetime = (value) => {
  if (!value) {
    return ''
  }
  if (typeof value === 'string' && value.length === 16) {
    return `${value}:00`
  }
  return value
}

const onDueDateChange = (value) => {
  form.due_at = normalizeDatetime(value)
}

const submit = async () => {
  if (!form.title) {
    uni.showToast({ title: '请填写任务标题', icon: 'none' })
    return
  }
  if (!isOrderOptional.value && !form.order_id) {
    uni.showToast({ title: '请选择关联订单', icon: 'none' })
    return
  }
  submitting.value = true
  try {
    await api.createTask({
      order_id: form.order_id,
      type: form.type,
      title: form.title,
      assigned_to: form.assigned_to ? Number(form.assigned_to) : null,
      due_at: form.due_at,
      need_audit: form.need_audit,
      description: form.description
    })
    uni.showToast({ title: '任务已创建', icon: 'success' })
    setTimeout(() => {
      uni.navigateBack()
    }, 500)
  } catch (error) {
    console.error(error)
  } finally {
    submitting.value = false
  }
}

onLoad((query) => {
  if (query && query.orderId) {
    form.order_id = String(query.orderId)
    orderInfo.value = { pi: query.pi }
  }
  fetchOrders()
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
uni-datetime-picker {
  width: 100%;
  display: block;
}
.switch-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.order-pill {
  padding: 12rpx 16rpx;
  border-radius: 16rpx;
  background: #f0f5ff;
  color: #1677ff;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}
.order-pill.selectable {
  border: 2rpx dashed #6aa1ff;
}
.order-sub {
  font-size: 24rpx;
  color: #7f8c8d;
}
.primary {
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
.optional-label {
  font-size: 24rpx;
  color: #999;
  margin-left: 8rpx;
}
.assign-mask {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 999;
  padding: 0 24rpx;
}
.assign-dialog {
  width: 90%;
  max-height: 80vh;
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  flex-direction: column;
}
.assign-dialog.large {
  width: 92%;
}
.dialog-title {
  font-size: 32rpx;
  font-weight: 600;
  margin-bottom: 24rpx;
}
.dialog-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}
.dialog-scroll {
  flex: 1;
  max-height: 520rpx;
}
.staff-scroll {
  flex: 1;
  max-height: 520rpx;
}
.staff-group {
  margin-bottom: 16rpx;
}
.group-title {
  font-size: 28rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.staff-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
}
.staff-item {
  padding: 12rpx 20rpx;
  border-radius: 40rpx;
  background: #f1f2f5;
  color: #333;
  font-size: 26rpx;
}
.staff-item.active {
  background: #1677ff;
  color: #fff;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-top: 24rpx;
}
.dialog-search {
  background: #f5f6fa;
  border-radius: 16rpx;
  padding: 16rpx;
  font-size: 26rpx;
}
.list-row {
  padding: 20rpx 16rpx;
  border-radius: 16rpx;
  background: #f6f7fb;
  margin-bottom: 16rpx;
}
.list-row.active {
  border: 2rpx solid #1677ff;
  background: #e8f1ff;
}
.row-title {
  font-size: 28rpx;
  font-weight: 600;
}
.row-desc {
  font-size: 24rpx;
  color: #818c99;
  margin-top: 8rpx;
}
.loading {
  text-align: center;
  padding: 40rpx 0;
  color: #666;
}
.empty {
  text-align: center;
  color: #999;
  padding: 40rpx 0;
}
</style>
