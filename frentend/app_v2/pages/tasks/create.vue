<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="section-title">
        任务信息
        <text class="section-action" @click="openTemplatePicker">使用模板</text>
      </view>
      <view v-if="currentAppliedTemplate" class="template-badge">
        已应用模板：{{ currentAppliedTemplate.name }}
        <text v-if="appliedTemplateStack.length > 1" class="template-stack-count">({{ appliedTemplateStack.length }})</text>
        <text class="template-clear" @click.stop="onClearTemplateClick">清除</text>
      </view>
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
      <view class="form-item">
        <text>优先级</text>
        <view class="priority-options">
          <view
            v-for="p in priorityOptions"
            :key="p.value"
            class="priority-option"
            :class="{ active: form.priority === p.value }"
            :style="form.priority === p.value ? { color: p.color, borderColor: p.color, background: p.color + '15' } : {}"
            @click="selectPriority(p.value)"
          >
            <text class="priority-label">{{ p.label }}</text>
            <text class="priority-name">{{ p.name }}</text>
          </view>
        </view>
      </view>
      <view class="form-item">
        <text>标签</text>
        <view class="tag-options">
          <view
            v-for="tag in tagOptions"
            :key="tag.value"
            class="tag-option"
            :class="{ active: form.tags.includes(tag.value) }"
            :style="form.tags.includes(tag.value) ? { color: tag.color, borderColor: tag.color, background: tag.color + '15' } : {}"
            @click="toggleTag(tag.value)"
          >
            {{ tag.label }}
          </view>
        </view>
      </view>
    </view>
    <view class="card">
      <view class="section-title">任务要求</view>
      <textarea v-model="form.description" placeholder="请输入任务要求"></textarea>
    </view>
    <view class="card template-action-card" @click="openSaveTemplateDialog">
      <view class="template-action">
        <text class="template-action-icon">⭐</text>
        <text class="template-action-text">另存为任务模板</text>
        <text class="template-action-arrow">›</text>
      </view>
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
  <view v-if="templateDialogVisible" class="assign-mask">
    <view class="assign-dialog large">
      <view class="dialog-title">选择任务模板</view>
      <view class="dialog-section">
        <input class="dialog-search" v-model.trim="templateKeyword" placeholder="搜索模板名称" />
        <scroll-view scroll-y class="dialog-scroll">
          <view v-if="templateLoading" class="loading">模板加载中...</view>
          <view v-else-if="!templates.length" class="empty">暂无任务模板</view>
          <view v-else>
            <view
              v-for="item in filteredTemplates"
              :key="item.id"
              class="template-item"
              @click="selectTemplate(item)"
            >
              <view class="template-item-title">{{ item.name }}</view>
              <view class="template-item-sub">{{ item.title }} · {{ formatType(item.type) }}</view>
            </view>
          </view>
        </scroll-view>
      </view>
      <view class="dialog-actions">
        <button class="outline" @click="closeTemplatePicker">取消</button>
      </view>
    </view>
  </view>
  <view v-if="saveTemplateDialogVisible" class="assign-mask">
    <view class="assign-dialog">
      <view class="dialog-title">另存为任务模板</view>
      <view class="dialog-section">
        <view class="form-item">
          <text>模板名称</text>
          <input v-model="saveTemplateName" placeholder="请输入模板名称，例如：采购-进口轴承" />
        </view>
      </view>
      <view class="dialog-actions">
        <button class="outline" @click="closeSaveTemplateDialog">取消</button>
        <button class="primary" :loading="savingTemplate" @click="submitSaveTemplate">保存</button>
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
const priorityOptions = [
  { value: 0, label: 'P0', name: '最高', color: '#ff4d4f' },
  { value: 1, label: 'P1', name: '高', color: '#fa8c16' },
  { value: 2, label: 'P2', name: '中', color: '#faad14' },
  { value: 3, label: 'P3', name: '低', color: '#52c41a' }
]
const tagOptions = ref([
  { value: 'urgent', label: '紧急', color: '#ff4d4f' },
  { value: 'customer', label: '客户', color: '#1677ff' },
  { value: 'internal', label: '内部', color: '#722ed1' }
])
const tagOptionsLoading = ref(false)
const fetchTagOptions = async () => {
  tagOptionsLoading.value = true
  try {
    const res = await api.tagOptions()
    if (res && res.items && res.items.length > 0) {
      tagOptions.value = res.items
    }
  } catch (error) {
    console.error('Failed to fetch tag options:', error)
  } finally {
    tagOptionsLoading.value = false
  }
}
const currentType = ref(types[0])
const form = reactive({
  type: 'procurement',
  title: '',
  order_id: null,
  assigned_to: '',
  due_at: '',
  need_audit: 0,
  priority: 3,
  tags: [],
  description: ''
})
const orderInfo = ref(null)
const submitting = ref(false)
const optionalOrderTypes = ['temporary', 'factory_order']
const isOrderOptional = computed(() => optionalOrderTypes.includes(form.type))

const templates = ref([])
const templateLoading = ref(false)
const templateDialogVisible = ref(false)
const templateKeyword = ref('')
const templateSnapshotStack = ref([])
const appliedTemplateStack = ref([])

const currentAppliedTemplate = computed(() => {
  const stack = appliedTemplateStack.value
  return stack.length ? stack[stack.length - 1] : null
})

const filteredTemplates = computed(() => {
  if (!templateKeyword.value) {
    return templates.value
  }
  const keyword = templateKeyword.value.toLowerCase()
  return templates.value.filter((item) => {
    const name = item.name ? item.name.toLowerCase() : ''
    const title = item.title ? item.title.toLowerCase() : ''
    return name.includes(keyword) || title.includes(keyword)
  })
})

const formatType = (type) => {
  const target = types.find((t) => t.value === type)
  return target ? target.label : type
}

const fetchTemplates = async () => {
  if (templateLoading.value) {
    return
  }
  templateLoading.value = true
  try {
    const res = await api.taskTemplates()
    templates.value = (res && res.items) || []
  } catch (error) {
    console.error(error)
    uni.showToast({ title: '模板加载失败', icon: 'none' })
  } finally {
    templateLoading.value = false
  }
}

const openTemplatePicker = () => {
  templateDialogVisible.value = true
  fetchTemplates()
}

const closeTemplatePicker = () => {
  templateDialogVisible.value = false
}

const selectTemplate = (item) => {
  templateSnapshotStack.value.push({
    type: form.type,
    currentTypeValue: currentType.value.value,
    title: form.title,
    description: form.description,
    need_audit: form.need_audit,
    assigned_to: form.assigned_to,
    assigneeName: assigneeName.value,
    priority: form.priority,
    tags: [...form.tags]
  })
  form.type = item.type || form.type
  const typeTarget = types.find((t) => t.value === form.type)
  if (typeTarget) {
    currentType.value = typeTarget
  }
  form.title = item.title || ''
  form.description = item.description || ''
  form.need_audit = Number(item.need_audit || 0)
  form.assigned_to = item.assigned_to ? String(item.assigned_to) : ''
  form.priority = item.priority != null ? Number(item.priority) : 3
  form.tags = Array.isArray(item.tags) ? [...item.tags] : []
  assigneeName.value = ''
  appliedTemplateStack.value.push({ id: item.id, name: item.name })
  templateDialogVisible.value = false
  uni.showToast({ title: '已应用模板', icon: 'success' })
}

const clearAppliedTemplate = () => {
  const snapshot = templateSnapshotStack.value.pop()
  appliedTemplateStack.value.pop()
  if (snapshot) {
    form.type = snapshot.type
    const typeTarget = types.find((t) => t.value === snapshot.currentTypeValue)
    if (typeTarget) {
      currentType.value = typeTarget
    }
    form.title = snapshot.title
    form.description = snapshot.description
    form.need_audit = snapshot.need_audit
    form.assigned_to = snapshot.assigned_to
    assigneeName.value = snapshot.assigneeName
    form.priority = snapshot.priority
    form.tags = snapshot.tags || []
  }
}

const onClearTemplateClick = () => {
  const stackDepth = appliedTemplateStack.value.length
  if (stackDepth <= 0) {
    return
  }
  const tip = stackDepth > 1
    ? `将回滚到上一层模板状态，当前已叠加 ${stackDepth} 层模板`
    : '将回滚到应用模板前的状态'
  uni.showModal({
    title: '清除模板',
    content: tip + '，是否继续？',
    confirmText: '回滚',
    cancelText: '取消',
    success: (res) => {
      if (res.confirm) {
        clearAppliedTemplate()
        const remain = appliedTemplateStack.value.length
        if (remain > 0) {
          uni.showToast({ title: `已回滚，剩余 ${remain} 层模板`, icon: 'none' })
        } else {
          uni.showToast({ title: '已回滚到初始状态', icon: 'none' })
        }
      }
    }
  })
}

const saveTemplateDialogVisible = ref(false)
const saveTemplateName = ref('')
const savingTemplate = ref(false)

const openSaveTemplateDialog = () => {
  if (!form.title) {
    uni.showToast({ title: '请先填写任务标题', icon: 'none' })
    return
  }
  saveTemplateName.value = form.title
  saveTemplateDialogVisible.value = true
}

const closeSaveTemplateDialog = () => {
  saveTemplateDialogVisible.value = false
}

const submitSaveTemplate = async () => {
  if (!saveTemplateName.value) {
    uni.showToast({ title: '请输入模板名称', icon: 'none' })
    return
  }
  savingTemplate.value = true
  try {
    await api.createTaskTemplate({
      name: saveTemplateName.value,
      type: form.type,
      title: form.title,
      description: form.description,
      assigned_to: form.assigned_to ? Number(form.assigned_to) : null,
      need_audit: form.need_audit,
      priority: form.priority,
      tags: form.tags.length > 0 ? form.tags : null
    })
    saveTemplateDialogVisible.value = false
    uni.showToast({ title: '模板已保存', icon: 'success' })
    fetchTemplates()
  } catch (error) {
    console.error(error)
    uni.showToast({ title: '模板保存失败', icon: 'none' })
  } finally {
    savingTemplate.value = false
  }
}

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

const selectPriority = (value) => {
  form.priority = value
}

const toggleTag = (value) => {
  const index = form.tags.indexOf(value)
  if (index > -1) {
    form.tags.splice(index, 1)
  } else {
    form.tags.push(value)
  }
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
      priority: form.priority,
      tags: form.tags.length > 0 ? form.tags : null,
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
  fetchTagOptions()
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
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.section-action {
  font-size: 26rpx;
  color: #1677ff;
  font-weight: 400;
}
.template-badge {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12rpx 16rpx;
  border-radius: 16rpx;
  background: #fff7e6;
  color: #fa8c16;
  font-size: 26rpx;
  margin-bottom: 16rpx;
}
.template-clear {
  color: #ff4d4f;
  font-size: 24rpx;
}
.template-stack-count {
  color: #999;
  font-size: 22rpx;
  margin-left: 8rpx;
}
.template-action-card {
  padding: 0;
  overflow: hidden;
}
.template-action {
  display: flex;
  align-items: center;
  padding: 24rpx;
}
.template-action-icon {
  font-size: 32rpx;
  margin-right: 16rpx;
}
.template-action-text {
  flex: 1;
  font-size: 28rpx;
  color: #333;
}
.template-action-arrow {
  color: #ccc;
  font-size: 36rpx;
}
.template-item {
  padding: 20rpx 16rpx;
  border-radius: 16rpx;
  background: #f6f7fb;
  margin-bottom: 16rpx;
}
.template-item:active {
  background: #e8f1ff;
}
.template-item-title {
  font-size: 28rpx;
  font-weight: 600;
  color: #222;
}
.template-item-sub {
  font-size: 24rpx;
  color: #818c99;
  margin-top: 8rpx;
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
.priority-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
}
.priority-option {
  flex: 1;
  min-width: 140rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4rpx;
  padding: 16rpx 12rpx;
  border: 2rpx solid #e5e6eb;
  border-radius: 12rpx;
  background: #f7f8fa;
}
.priority-option.active {
  border-width: 2rpx;
  border-style: solid;
}
.priority-label {
  font-size: 28rpx;
  font-weight: 600;
}
.priority-name {
  font-size: 22rpx;
  opacity: 0.8;
}
.tag-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
}
.tag-option {
  padding: 12rpx 24rpx;
  border: 2rpx solid #e5e6eb;
  border-radius: 40rpx;
  background: #f7f8fa;
  font-size: 26rpx;
}
.tag-option.active {
  border-width: 2rpx;
  border-style: solid;
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
