<template>
  <scroll-view scroll-y class="page" v-if="task">
    <view class="card">
      <view class="heading">
        <view>
          <view class="task-title">{{ task.title }}</view>
          <view class="task-sub">{{ task.type_label }} · {{ task.order?.pi_number || '无订单' }}</view>
        </view>
        <view class="heading-actions">
          <button v-if="task.order?.id" class="outline small-btn" @click="openOrderDetail">订单详情</button>
          <view class="flag" :class="task.status">{{ task.status_label }}</view>
        </view>
      </view>
      <view class="chips">
        <text class="chip">负责人：{{ task.assignee_name || '待分配' }}</text>
        <text v-if="task.order?.customer_name" class="chip">客户：{{ task.order.customer_name }}</text>
      </view>
      <view class="requirement">
        <view class="label">任务要求</view>
        <view class="content">{{ task.description || '请按要求执行' }}</view>
        <view v-if="task.payload && task.payload.modules && task.payload.modules.length" class="modules">
          <view class="caption">需要填写</view>
          <view class="tags">
            <text v-for="item in task.payload.modules" :key="item" class="tag">{{ item }}</text>
          </view>
        </view>
      </view>
      <view class="timeline">
        <view>
          <view class="caption">截止时间</view>
          <view class="value">{{ task.due_at || '-' }}</view>
        </view>
        <view>
          <view class="caption">创建人</view>
          <view class="value">{{ task.creator_name || '管理员' }}</view>
        </view>
      </view>
      <view class="timeline">
        <view>
          <view class="caption">创建时间</view>
          <view class="value">{{ task.created_at || '-' }}</view>
        </view>
        <view>
          <view class="caption">需要审核</view>
          <view class="value">{{ task.need_audit ? '是' : '否' }}</view>
        </view>
      </view>
      <view v-if="task.type === 'procurement'" class="timeline column">
        <view>
          <view class="caption">供应商</view>
          <view class="value">
            {{ task.procurement?.supplier_name || (task.procurement_hidden ? '管理员可见' : '未填写') }}
          </view>
        </view>
        <view>
          <view class="caption">采购价格</view>
          <view class="value">
            <text v-if="task.procurement">
              {{ task.procurement.purchase_price || '-' }} {{ task.procurement.currency || '' }}
            </text>
            <text v-else-if="task.procurement_hidden">管理员可见</text>
            <text v-else>-</text>
          </view>
        </view>
      </view>
      <view class="actions">
        <button class="outline" @click="openChat">沟通</button>
        <button v-if="canUrgeTask" class="primary" :loading="urgingTask" @click="urgeTask">催办一次</button>
      </view>
    </view>

    <view class="card" v-if="showAuditPanel">
      <view class="section-title">管理员审核</view>
      <view class="form-item">
        <text>审核意见</text>
        <textarea v-model="auditComment" placeholder="请输入审核意见" rows="3"></textarea>
      </view>
      <view class="audit-actions">
        <button class="outline" :loading="auditProcessing" @click="submitAudit('reject')">驳回</button>
        <button class="primary" :loading="auditProcessing" @click="submitAudit('approve')">通过审核</button>
      </view>
    </view>

    <view class="card" v-if="canProcessTask">
      <view class="section-title">任务处理</view>
      <view class="form-item">
        <text>任务状态</text>
        <picker :range="availableTaskStatusOptions" range-key="label" :value="processingStatusIndex" @change="onProcessingStatusChange">
          <view class="picker">{{ processingStatusLabel }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>开始时间</text>
        <view class="datetime-row">
          <picker mode="date" :value="processingDate" @change="onProcessingDateChange">
            <view class="picker">{{ processingDate || '选择日期' }}</view>
          </picker>
          <picker mode="time" :value="processingTime" @change="onProcessingTimeChange">
            <view class="picker">{{ processingTime || '选择时间' }}</view>
          </picker>
        </view>
      </view>
      <view class="form-item">
        <text>处理备注</text>
        <textarea v-model="processingForm.comment" placeholder="请填写处理说明" rows="3"></textarea>
      </view>
      <view class="upload-row">
        <button class="outline" size="mini" @click="chooseProcessingAttachment" :loading="uploadingAttachment">
          上传图片
        </button>
      </view>
      <view class="pending-attachments" v-if="pendingAttachments.length">
        <view class="pending-item" v-for="(item, index) in pendingAttachments" :key="item.media_id">
          <image v-if="item.file_type === 'image'" :src="item.url" mode="aspectFill" />
          <video v-else :src="item.url" controls></video>
          <text class="remove" @click="removePendingAttachment(index)">删除</text>
        </view>
      </view>
      <button class="primary" :loading="processingTask" @click="submitTaskProcessing">提交处理</button>
    </view>

    <view class="card" v-if="canProcessTask && moduleSchema.length">
      <view class="section-title">提交内容</view>
      <view v-for="module in moduleSchema" :key="module.key" class="module-block">
        <view class="form-item">
          <text>{{ module.label }}</text>
          <textarea v-model="moduleForm[module.key].value" :placeholder="`请输入${module.label}`" rows="3"></textarea>
        </view>
        <view class="upload-row">
          <button class="outline" size="mini" @click="chooseModuleAttachment(module.key)" :loading="uploadingAttachment">
            上传图片/视频
          </button>
        </view>
        <view class="pending-attachments" v-if="moduleForm[module.key]?.attachments?.length">
          <view
            class="pending-item"
            v-for="(item, idx) in moduleForm[module.key].attachments"
            :key="`${module.key}-${item.media_id}-${idx}`"
          >
            <image v-if="item.file_type === 'image'" :src="item.url" mode="aspectFill" />
            <video v-else :src="item.url" controls></video>
            <text v-if="item.isNew" class="remove" @click="removeModuleAttachment(module.key, idx)">删除</text>
          </view>
        </view>
      </view>
      <button class="primary" :loading="processingTask" @click="submitModuleOnly">提交内容</button>
    </view>

    <view class="card" v-if="isProcurement">
      <view class="section-title">采购处理</view>
      <view class="form-item">
        <text>采购状态</text>
        <picker :range="purchaseStatusOptions" range-key="label" :value="purchaseStatusIndex" @change="onPurchaseStatusChange">
          <view class="picker">{{ purchaseStatusLabel }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>下单时间</text>
        <view class="picker" @click="setOrderedNow">{{ orderedAtLabel }}</view>
        <view class="small-tip">点击可更新为当前时间</view>
      </view>
      <view class="form-item">
        <text>预计交货期</text>
        <picker mode="date" :value="procurementForm.delivery_date" @change="onDeliveryChange">
          <view class="picker">{{ procurementForm.delivery_date || '请选择预计到货日期' }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>货源地</text>
        <input v-model="procurementForm.source_location" placeholder="请输入货源地" />
      </view>
      <view class="form-item">
        <text>供应商</text>
        <picker
          :range="supplierOptions"
          range-key="name"
          :value="supplierIndex >= 0 ? supplierIndex : 0"
          @change="onSupplierChange"
        >
          <view class="picker">{{ supplierLabel }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>含税运总价</text>
        <input type="digit" v-model="procurementForm.purchase_price" placeholder="请输入含税运总价" />
      </view>
      <view class="form-item">
        <text>币种</text>
        <input v-model="procurementForm.currency" placeholder="例如 CNY" />
      </view>
      <view class="form-item">
        <text>产品名称</text>
        <input v-model="procurementForm.product_name" placeholder="自动带入/可编辑" />
      </view>
      <view class="form-item">
        <text>型号</text>
        <input v-model="procurementForm.model" placeholder="型号" />
      </view>
      <view class="form-item">
        <text>电压</text>
        <input v-model="procurementForm.voltage" placeholder="电压" />
      </view>
      <view class="form-item">
        <text>机器要求</text>
        <textarea v-model="procurementForm.requirements" placeholder="请输入机器要求" rows="3"></textarea>
      </view>
      <view class="form-item">
        <text>库存引入</text>
        <view class="inventory-box">
          <view class="inventory-selected" v-if="selectedInventory">
            <view class="row-title">{{ selectedInventory.product_name }} {{ selectedInventory.model || '' }}</view>
            <view class="row-desc">电压：{{ selectedInventory.voltage || '-' }} · 库存：{{ selectedInventory.quantity }}</view>
            <view class="row-desc" v-if="selectedInventory.requirements">要求：{{ selectedInventory.requirements }}</view>
          </view>
          <view v-else class="muted">还未选择库存产品</view>
          <view class="inventory-actions">
            <button class="outline" size="mini" @click="openInventoryDialog">引入库存</button>
            <view class="qty-box">
              <text>使用数量</text>
              <input type="number" v-model="procurementForm.inventory_quantity" placeholder="数量" />
            </view>
          </view>
        </view>
      </view>
      <view class="form-item">
        <text>采购合同</text>
        <view class="upload-row">
          <button class="outline" size="mini" @click="chooseContractAttachment" :loading="uploadingAttachment">上传合同</button>
        </view>
        <view class="pending-attachments" v-if="contractAttachments.length">
          <view class="pending-item" v-for="(item, idx) in contractAttachments" :key="`contract-${item.media_id}-${idx}`">
            <image :src="item.url" mode="aspectFill" />
            <text v-if="item.isNew" class="remove" @click="removeContractAttachment(idx)">删除</text>
          </view>
        </view>
      </view>
      <view class="form-item">
        <text>任务状态</text>
        <picker :range="availableTaskStatusOptions" range-key="label" :value="taskStatusIndex" @change="onTaskStatusChange">
          <view class="picker">{{ taskStatusLabel }}</view>
        </picker>
      </view>
      <button class="primary" :loading="processing" @click="submitProcurement">提交处理</button>
    </view>

    <view class="card" v-if="existingAttachments.length">
      <view class="section-title">已上传文件</view>
      <view class="attachment-grid">
        <view class="attachment-item" v-for="item in existingAttachments" :key="item.id">
          <image v-if="item.file_type === 'image'" :src="item.url" mode="aspectFill" />
          <video v-else :src="item.url" controls></video>
          <view class="attachment-name">
            {{ item.file_name || '附件' }}
            <text v-if="item.category" class="tag">#{{ item.category }}</text>
            <text v-else-if="item.field_key" class="tag">#{{ item.field_key }}</text>
          </view>
        </view>
      </view>
    </view>

    <view class="card">
      <view class="section-title">操作记录</view>
      <view v-if="logs.length" class="logs">
        <view v-for="log in logs" :key="log.id" class="log-item">
          <view class="log-row">
            <text class="log-action">{{ log.action_label }}</text>
            <text class="log-time">{{ log.created_at }}</text>
          </view>
          <view class="log-message">
            <text space="nbsp">{{ log.message_display }}</text>
          </view>
        </view>
      </view>
      <view v-else class="empty">暂无记录</view>
    </view>
  </scroll-view>
  <view v-else class="empty">暂无任务数据</view>

  <view v-if="inventoryDialogVisible" class="assign-mask">
    <view class="assign-dialog large">
      <view class="dialog-title">选择库存</view>
      <view class="dialog-section">
        <input
          class="dialog-search"
          v-model.trim="inventoryKeyword"
          placeholder="搜索产品/型号/供应商"
          @confirm="fetchInventory"
        />
        <scroll-view scroll-y class="dialog-scroll">
          <view v-if="inventoryLoading" class="loading">库存加载中...</view>
          <view v-else>
            <view
              v-for="item in inventoryList"
              :key="item.id"
              class="list-row"
              :class="{ active: isSelectedInventory(item) }"
              @click="selectInventory(item)"
            >
              <view class="row-title">{{ item.product_name }} {{ item.model || '' }}</view>
              <view class="row-desc">供应商：{{ item.supplier_name || '未设置' }}</view>
              <view class="row-desc">电压：{{ item.voltage || '-' }} · 库存：{{ item.quantity }}</view>
              <view class="row-desc" v-if="item.requirements">要求：{{ item.requirements }}</view>
            </view>
            <view v-if="!inventoryList.length" class="empty">暂无库存</view>
          </view>
        </scroll-view>
        <view class="form-item qty-inline">
          <text>使用数量</text>
          <input type="number" v-model="procurementForm.inventory_quantity" placeholder="数量" />
        </view>
      </view>
      <view class="dialog-actions">
        <button class="outline" @click="inventoryDialogVisible = false">取消</button>
        <button class="primary" @click="confirmInventorySelection">确认</button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import store from '../../store'
import { api, uploadFile, resolveAssetUrl } from '../../utils/request'

const taskId = ref(null)
const task = ref(null)
const rawLogs = ref([])
const logs = ref([])
const existingAttachments = ref([])
const pendingAttachments = ref([])
const supplierOptions = ref([])
const supplierIndex = ref(-1)
const purchaseStatusIndex = ref(0)
const taskStatusIndex = ref(0)
const processing = ref(false)
const processingTask = ref(false)
const uploadingAttachment = ref(false)
const staffMap = ref({})
const auditComment = ref('')
const auditProcessing = ref(false)
const urgingTask = ref(false)
const profile = computed(() => store.state.profile || {})
const isAdminDept = computed(() => {
  const type = profile.value?.dept?.type
  return type === 'operation' || type === 'finance'
})

const logActionMap = {
  created: '创建',
  updated: '更新',
  assign: '分配',
  comment: '备注',
  status: '状态',
  procurement: '采购更新',
  urged: '催办'
}
const logFieldMap = {
  assigned_to: '负责人',
  start_at: '开始时间',
  due_at: '截止时间',
  status: '状态',
  need_audit: '需要审核',
  description: '描述',
  updated_at: '更新时间',
  completed_at: '完成时间'
}
const logStatusMap = {
  pending: '待开始',
  in_progress: '进行中',
  waiting_audit: '待审核',
  completed: '已完成',
  rejected: '已驳回',
  cancelled: '已取消'
}

const purchaseStatusOptions = [
  { label: '未下单', value: 'not_ordered' },
  { label: '已下单', value: 'ordered' },
  { label: '已到货', value: 'arrived' }
]
const taskStatusOptions = [
  { label: '待开始', value: 'pending' },
  { label: '进行中', value: 'in_progress' },
  { label: '待审核', value: 'waiting_audit' },
  { label: '已完成', value: 'completed' }
]
const availableTaskStatusOptions = computed(() => {
  if (task.value?.need_audit && !isAdminDept.value) {
    return taskStatusOptions.filter((item) => item.value !== 'completed')
  }
  return taskStatusOptions
})

const processingStatusIndex = ref(0)
const processingDate = ref('')
const processingTime = ref('')
const processingForm = reactive({
  status: 'pending',
  start_at: '',
  comment: ''
})

const procurementForm = reactive({
  supplier_id: '',
  purchase_price: '',
  currency: 'CNY',
  delivery_date: '',
  purchase_status: 'not_ordered',
  purchase_date: '',
  ordered_at: '',
  source_location: '',
  inventory_item_id: '',
  inventory_quantity: 1,
  product_name: '',
  model: '',
  voltage: '',
  requirements: '',
  status: ''
})
const contractAttachments = ref([])
const inventoryDialogVisible = ref(false)
const inventoryList = ref([])
const inventoryLoading = ref(false)
const inventoryKeyword = ref('')
const selectedInventory = ref(null)
const moduleForm = reactive({})

const isProcurement = computed(() => task.value?.type === 'procurement')
const canProcessTask = computed(() => {
  if (!task.value || !profile.value?.id) return false
  const assigneeId = task.value.assigned_to ? Number(task.value.assigned_to) : null
  return (assigneeId && assigneeId === Number(profile.value.id)) || isAdminDept.value
})
const showAuditPanel = computed(
  () => isAdminDept.value && task.value?.need_audit && task.value?.status === 'waiting_audit'
)
const canUrgeTask = computed(() => {
  if (!task.value || !profile.value?.id) return false
  const assigneeId = task.value.assigned_to ? Number(task.value.assigned_to) : null
  const userId = Number(profile.value.id)
  
  if (assigneeId === userId) return false
  if (task.value.status === 'completed' || task.value.status === 'cancelled') return false
  if (!assigneeId) return false
  
  if (isAdminDept.value) return true
  if (Number(task.value.created_by) === userId) return true
  
  return false
})
const normalizeModuleKey = (label = '', index = 0) => {
  const key = String(label || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
  return key || `field_${index}`
}
const moduleSchema = computed(() => {
  const schema = []
  const current = task.value
  if (!current) return schema
  if (current.type === 'nameplate') {
    schema.push({ key: 'nameplate', label: '铭牌制作提交' })
  } else if (current.type === 'acceptance') {
    schema.push({ key: 'acceptance', label: '机器验收记录' })
  } else if (current.type === 'packaging') {
    schema.push({ key: 'packaging', label: '打包与贴唛头' })
  } else if (current.type === 'shipment') {
    schema.push({ key: 'shipment', label: '发货记录' })
  } else if (current.type === 'fee') {
    ;(current.payload?.modules || []).forEach((label, index) => {
      schema.push({ key: normalizeModuleKey(label, index), label })
    })
  } else if (current.type === 'document') {
    ;(current.payload?.required_docs || []).forEach((label, index) => {
      schema.push({ key: normalizeModuleKey(label, index), label })
    })
  }
  return schema
})
watch(
  moduleSchema,
  (schema) => {
    schema.forEach((item) => {
      if (!moduleForm[item.key]) {
        moduleForm[item.key] = { value: '', attachments: [] }
      }
    })
  },
  { immediate: true }
)
const processingStatusLabel = computed(
  () => availableTaskStatusOptions.value[processingStatusIndex.value]?.label || '请选择状态'
)

const supplierLabel = computed(() => {
  if (supplierIndex.value >= 0 && supplierOptions.value[supplierIndex.value]) {
    return supplierOptions.value[supplierIndex.value].name
  }
  if (procurementForm.supplier_id) {
    const target = supplierOptions.value.find((item) => String(item.id) === String(procurementForm.supplier_id))
    if (target) {
      return target.name
    }
  }
  return '请选择供应商'
})
const purchaseStatusLabel = computed(() => purchaseStatusOptions[purchaseStatusIndex.value]?.label || '请选择状态')
const taskStatusLabel = computed(
  () => availableTaskStatusOptions.value[taskStatusIndex.value]?.label || '请选择状态'
)
const orderedAtLabel = computed(() => procurementForm.ordered_at || '切换为已下单后自动生成')

const syncSupplierIndex = () => {
  if (!procurementForm.supplier_id) {
    supplierIndex.value = -1
    return
  }
  supplierIndex.value = supplierOptions.value.findIndex(
    (item) => String(item.id) === String(procurementForm.supplier_id)
  )
}

const syncStatusIndexes = () => {
  const psIndex = purchaseStatusOptions.findIndex((item) => item.value === procurementForm.purchase_status)
  purchaseStatusIndex.value = psIndex >= 0 ? psIndex : 0
  const tsIndex = availableTaskStatusOptions.value.findIndex((item) => item.value === procurementForm.status)
  taskStatusIndex.value = tsIndex >= 0 ? tsIndex : 0
}

const syncProcessingStatusIndex = () => {
  const idx = availableTaskStatusOptions.value.findIndex((item) => item.value === processingForm.status)
  processingStatusIndex.value = idx >= 0 ? idx : 0
}

const syncProcessingStartFields = () => {
  if (processingForm.start_at) {
    const [datePart, timePart] = processingForm.start_at.split(' ')
    processingDate.value = datePart || ''
    processingTime.value = timePart ? timePart.slice(0, 5) : ''
  } else {
    processingDate.value = ''
    processingTime.value = ''
  }
}

const syncProcessingForm = () => {
  processingForm.status = task.value?.status || 'pending'
  processingForm.start_at = task.value?.start_at || ''
  processingForm.comment = ''
  syncProcessingStatusIndex()
  syncProcessingStartFields()
}

const syncProcurementForm = () => {
  const info = task.value?.procurement || {}
  const saved = task.value?.form_data?.procurement || {}
  procurementForm.supplier_id = info.supplier_id ? String(info.supplier_id) : ''
  procurementForm.purchase_price = info.purchase_price
    ? String(info.purchase_price)
    : saved.purchase_price || ''
  procurementForm.currency = info.currency || saved.currency || 'CNY'
  procurementForm.delivery_date = info.delivery_date || saved.delivery_date || ''
  procurementForm.purchase_status = info.purchase_status || saved.purchase_status || 'not_ordered'
  procurementForm.purchase_date = saved.purchase_date || info.purchase_date || ''
  procurementForm.ordered_at = saved.ordered_at || info.ordered_at || ''
  procurementForm.source_location = saved.source_location || info.source_location || ''
  procurementForm.inventory_item_id = saved.inventory?.item_id ? String(saved.inventory.item_id) : ''
  procurementForm.inventory_quantity = saved.inventory?.quantity || 1
  procurementForm.product_name =
    saved.inventory?.product_name || info.product_name || task.value?.payload?.product_name || ''
  procurementForm.model = saved.inventory?.model || info.model || task.value?.payload?.model || ''
  procurementForm.voltage = saved.inventory?.voltage || ''
  procurementForm.requirements = saved.inventory?.requirements || task.value?.description || ''
  procurementForm.status = task.value?.status
  if (saved.inventory) {
    selectedInventory.value = { ...saved.inventory }
  }
}

const attachmentsByField = (fieldKey, savedIds = []) => {
  const savedSet = new Set((savedIds || []).map((id) => String(id)))
  return (existingAttachments.value || [])
    .filter(
      (item) =>
        item.field_key === fieldKey ||
        savedSet.has(String(item.media_id)) ||
        savedSet.has(String(item.id))
    )
    .map((item) => ({ ...item, isNew: false }))
}

const initModuleForm = (taskData) => {
  const savedModules = taskData?.form_data?.modules || {}
  const schemaKeys = moduleSchema.value.map((item) => item.key)
  Object.keys(moduleForm).forEach((key) => {
    if (!schemaKeys.includes(key)) {
      delete moduleForm[key]
    }
  })
  moduleSchema.value.forEach((item, index) => {
    const saved = savedModules[item.key] || {}
    moduleForm[item.key] = {
      value: saved.value || '',
      attachments: attachmentsByField(item.key, saved.attachments || [])
    }
  })
}

const buildModulePayload = () => {
  const payload = {}
  const attachments = []
  moduleSchema.value.forEach((item, index) => {
    const entry = moduleForm[item.key] || {}
    const moduleAttachments = entry.attachments || []
    payload[item.key] = {
      label: item.label,
      value: entry.value || '',
      attachments: moduleAttachments.map((att) => att.media_id)
    }
    moduleAttachments.forEach((att) => {
      if (att.isNew) {
        attachments.push({
          media_id: att.media_id,
          category: 'module',
          field_key: item.key
        })
      }
    })
  })
  return { payload, attachments }
}

const updateProcessingStartAt = () => {
  if (!processingDate.value) {
    processingForm.start_at = ''
    return
  }
  const timePart = processingTime.value ? `${processingTime.value}:00` : '00:00:00'
  processingForm.start_at = `${processingDate.value} ${timePart}`
}

const onProcessingStatusChange = (event) => {
  const index = Number(event.detail.value)
  processingStatusIndex.value = index
  processingForm.status = availableTaskStatusOptions.value[index]?.value || processingForm.status
}

const onProcessingDateChange = (event) => {
  processingDate.value = event.detail.value
  updateProcessingStartAt()
}

const onProcessingTimeChange = (event) => {
  processingTime.value = event.detail.value
  updateProcessingStartAt()
}

const fetchSuppliers = async () => {
  try {
    const res = await api.suppliers()
    supplierOptions.value = res.items || []
    syncSupplierIndex()
  } catch (error) {
    console.error(error)
  }
}

const fetchInventory = async () => {
  inventoryLoading.value = true
  try {
    const params = { available_only: 1 }
    if (inventoryKeyword.value) {
      params.keyword = inventoryKeyword.value
    }
    const res = await api.inventory(params)
    inventoryList.value = res.items || []
  } catch (error) {
    console.error(error)
  } finally {
    inventoryLoading.value = false
  }
}

const openInventoryDialog = () => {
  inventoryDialogVisible.value = true
  fetchInventory()
}

const selectInventory = (item) => {
  selectedInventory.value = item
  procurementForm.inventory_item_id = item?.id ? String(item.id) : ''
  procurementForm.product_name = item?.product_name || procurementForm.product_name
  procurementForm.model = item?.model || procurementForm.model
  procurementForm.voltage = item?.voltage || procurementForm.voltage
  procurementForm.requirements = item?.requirements || procurementForm.requirements
  if (!procurementForm.inventory_quantity || procurementForm.inventory_quantity > (item?.quantity || 0)) {
    procurementForm.inventory_quantity = Math.max(1, item?.quantity || 1)
  }
}

const isSelectedInventory = (item) =>
  String(item.id) === String(procurementForm.inventory_item_id || '')

const confirmInventorySelection = () => {
  inventoryDialogVisible.value = false
}

const fetchStaff = async () => {
  try {
    const res = await api.lookupStaff()
    const map = {}
    ;(res.items || []).forEach((user) => {
      map[user.id] = user.name
    })
    staffMap.value = map
    updateFormattedLogs()
  } catch (error) {
    console.error(error)
  }
}

const fetchTaskDetail = async (id) => {
  try {
    const res = await api.taskDetail(id)
    const taskData = res.task || res.data?.task || null
    if (!taskData) {
      task.value = null
      uni.showToast({ title: '未找到任务', icon: 'none' })
      return
    }
    task.value = taskData
    rawLogs.value = res.logs || []
    updateFormattedLogs()
    existingAttachments.value = (res.attachments || []).map((item) => ({
      id: item.id,
      media_id: item.media_id,
      file_name: item.file_name,
      file_type: item.file_type || 'image',
      url: resolveAssetUrl(item.url),
      category: item.category || null,
      field_key: item.field_key || null,
      isNew: false
    }))
    contractAttachments.value = existingAttachments.value.filter((item) => item.category === 'contract')
    if (task.value?.type === 'procurement') {
      syncProcurementForm()
      syncSupplierIndex()
      syncStatusIndexes()
    }
    initModuleForm(task.value)
    syncProcessingForm()
  } catch (error) {
    console.error(error)
    task.value = null
    uni.showToast({ title: '加载任务失败', icon: 'none' })
  }
}

const formatLogs = (items) =>
  (items || []).map((log) => {
    const readableMessage = formatLogMessage(log.message)
    return {
      ...log,
      action_label: logActionMap[log.action] || log.action || '记录',
      message_display: readableMessage
    }
  })

const formatLogMessage = (message) => {
  if (!message) {
    return '无内容'
  }
  const trimmed = String(message).trim()
  if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
    try {
      const parsed = JSON.parse(trimmed)
      if (Array.isArray(parsed)) {
        return parsed.map(formatLogObject).join('\n')
      }
      if (parsed && typeof parsed === 'object') {
        return formatLogObject(parsed)
      }
    } catch (error) {
      return message
    }
  }
  return message
}

const formatLogObject = (obj = {}) => {
  const lines = []
  Object.keys(obj).forEach((key) => {
    if (key === 'assigned_to_name') {
      return
    }
    const label = logFieldMap[key] || key
    lines.push(`${label}: ${formatLogValue(key, obj[key], obj)}`)
  })
  return lines.join('\n')
}

const formatLogValue = (key, value, context = {}) => {
  if (value === null || value === undefined || value === '') {
    return '无'
  }
  if (typeof value === 'object') {
    if (key === 'payload') {
      return formatPayloadSummary(value)
    }
    if (key === 'form_data') {
      return formatFormDataSummary(value)
    }
    if (Array.isArray(value)) {
      return value.map((item) => formatLogValue(key, item, context)).join(' / ')
    }
    return JSON.stringify(value)
  }
  if (key === 'assigned_to') {
    if (context.assigned_to_name) {
      return context.assigned_to_name
    }
    const numericId = Number(value)
    const mappedName =
      staffMap.value[numericId] ||
      staffMap.value[String(numericId)] ||
      staffMap.value[value]
    return mappedName || value
  }
  if (key === 'status') {
    return logStatusMap[value] || value
  }
  if (key === 'need_audit') {
    return Number(value) ? '是' : '否'
  }
  return value
}

const formatPayloadSummary = (payload = {}) => {
  if (payload.inventory_usage) {
    const usage = payload.inventory_usage
    return `使用库存#${usage.item_id || '-'}，数量:${usage.quantity || '-'}，剩余:${usage.remaining_quantity ?? '-'}`
  }
  const keys = Object.keys(payload || {})
  if (!keys.length) return '无'
  return keys
    .map((k) => {
      const val = payload[k]
      if (typeof val === 'object') {
        return `${k}:${JSON.stringify(val)}`
      }
      return `${k}:${val}`
    })
    .join('，')
}

const formatFormDataSummary = (formData = {}) => {
  const parts = []
  if (formData.procurement) {
    const p = formData.procurement
    parts.push(
      [
        p.purchase_status ? `状态:${p.purchase_status}` : '',
        p.ordered_at ? `下单:${p.ordered_at}` : '',
        p.purchase_date ? `下单日:${p.purchase_date}` : '',
        p.delivery_date ? `交期:${p.delivery_date}` : '',
        p.purchase_price ? `含税运总价:${p.purchase_price}${p.currency ? ` ${p.currency}` : ''}` : '',
        p.source_location ? `货源地:${p.source_location}` : ''
      ]
        .filter(Boolean)
        .join('，')
    )
    if (p.inventory?.item_id) {
      parts.push(`库存:#${p.inventory.item_id}，数量:${p.inventory.quantity || '-'}`)
    }
    if (p.product_name) {
      parts.push(`产品:${p.product_name} ${p.model || ''} ${p.voltage || ''}`.trim())
    }
    if (p.requirements) {
      parts.push(`机器要求:${p.requirements}`)
    }
  }
  if (formData.modules) {
    Object.keys(formData.modules).forEach((k) => {
      const item = formData.modules[k]
      const label = item.label || k
      parts.push(`${label}:${item.value || '未填写'}`)
    })
  }
  return parts.join('，') || '无'
}

const updateFormattedLogs = () => {
  logs.value = formatLogs(rawLogs.value || [])
}

const onSupplierChange = (event) => {
  const index = Number(event.detail.value)
  supplierIndex.value = index
  const target = supplierOptions.value[index]
  procurementForm.supplier_id = target ? String(target.id) : ''
}

const onDeliveryChange = (event) => {
  procurementForm.delivery_date = event.detail.value
}

const onPurchaseStatusChange = (event) => {
  const index = Number(event.detail.value)
  purchaseStatusIndex.value = index
  procurementForm.purchase_status = purchaseStatusOptions[index]?.value || 'not_ordered'
  if (procurementForm.purchase_status === 'ordered' && !procurementForm.ordered_at) {
    const now = new Date()
    const pad = (num) => String(num).padStart(2, '0')
    procurementForm.ordered_at = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(
      now.getDate()
    )} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`
    procurementForm.purchase_date = procurementForm.purchase_date || procurementForm.ordered_at.slice(0, 10)
  }
}

const setOrderedNow = () => {
  const now = new Date()
  const pad = (num) => String(num).padStart(2, '0')
  procurementForm.ordered_at = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(
    now.getHours()
  )}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`
  procurementForm.purchase_date = procurementForm.ordered_at.slice(0, 10)
}

const onTaskStatusChange = (event) => {
  const index = Number(event.detail.value)
  taskStatusIndex.value = index
  procurementForm.status = availableTaskStatusOptions.value[index]?.value || task.value?.status || 'pending'
}

const chooseModuleAttachment = (fieldKey) => {
  if (uploadingAttachment.value) return
  uni.chooseMedia({
    count: 6,
    mediaType: ['image', 'video'],
    success: async (res) => {
      if (!res.tempFiles?.length) return
      uploadingAttachment.value = true
      try {
        if (!moduleForm[fieldKey]) {
          moduleForm[fieldKey] = { value: '', attachments: [] }
        }
        for (const file of res.tempFiles) {
          const result = await uploadFile(file.tempFilePath)
          moduleForm[fieldKey].attachments.push({
            media_id: result.media_id,
            url: resolveAssetUrl(result.url),
            file_type: file.fileType === 'video' ? 'video' : 'image',
            category: 'module',
            field_key: fieldKey,
            isNew: true
          })
        }
      } catch (error) {
        console.error(error)
      } finally {
        uploadingAttachment.value = false
      }
    }
  })
}

const removeModuleAttachment = (fieldKey, index) => {
  if (!moduleForm[fieldKey]?.attachments) return
  moduleForm[fieldKey].attachments.splice(index, 1)
}

const chooseContractAttachment = () => {
  if (uploadingAttachment.value) return
  uni.chooseMedia({
    count: 4,
    mediaType: ['image'],
    success: async (res) => {
      if (!res.tempFiles?.length) return
      uploadingAttachment.value = true
      try {
        for (const file of res.tempFiles) {
          const result = await uploadFile(file.tempFilePath)
          contractAttachments.value.push({
            media_id: result.media_id,
            url: resolveAssetUrl(result.url),
            file_type: 'image',
            category: 'contract',
            field_key: 'contract',
            isNew: true
          })
        }
      } catch (error) {
        console.error(error)
      } finally {
        uploadingAttachment.value = false
      }
    }
  })
}

const removeContractAttachment = (index) => {
  contractAttachments.value.splice(index, 1)
}

const chooseProcessingAttachment = () => {
  if (uploadingAttachment.value) return
  uni.chooseMedia({
    count: 6,
    mediaType: ['image', 'video'],
    success: async (res) => {
      if (!res.tempFiles?.length) return
      uploadingAttachment.value = true
      try {
        for (const file of res.tempFiles) {
          const result = await uploadFile(file.tempFilePath)
          pendingAttachments.value.push({
            media_id: result.media_id,
            url: resolveAssetUrl(result.url),
            file_type: file.fileType === 'video' ? 'video' : 'image',
            category: null,
            field_key: null,
            isNew: true
          })
        }
      } catch (error) {
        console.error(error)
      } finally {
        uploadingAttachment.value = false
      }
    }
  })
}

const removePendingAttachment = (index) => {
  pendingAttachments.value.splice(index, 1)
}

const submitAudit = async (action) => {
  if (!task.value?.id) return
  const status = action === 'approve' ? 'completed' : 'rejected'
  auditProcessing.value = true
  try {
    await api.updateTaskStatus(task.value.id, {
      status,
      comment: auditComment.value || ''
    })
    uni.showToast({ title: action === 'approve' ? '审核通过' : '已驳回', icon: 'success' })
    auditComment.value = ''
    await fetchTaskDetail(task.value.id)
  } catch (error) {
    console.error(error)
  } finally {
    auditProcessing.value = false
  }
}

const submitTaskProcessing = async () => {
  if (!task.value?.id) return
  if (!processingForm.status) {
    uni.showToast({ title: '请选择状态', icon: 'none' })
    return
  }
  let submitStatus = processingForm.status
  if (task.value?.need_audit && !isAdminDept.value && submitStatus === 'completed') {
    submitStatus = 'waiting_audit'
    processingForm.status = submitStatus
    syncProcessingStatusIndex()
  }
  const { payload: modulePayload, attachments: moduleAttachments } = buildModulePayload()
  const formDataPayload = {}
  if (Object.keys(modulePayload).length > 0) {
    formDataPayload.modules = modulePayload
  }
  const attachmentPayload = [
    ...pendingAttachments.value.map((item) => ({
      media_id: item.media_id,
      category: item.category || null,
      field_key: item.field_key || null
    })),
    ...moduleAttachments
  ]
  processingTask.value = true
  try {
    await api.updateTaskStatus(task.value.id, {
      status: submitStatus,
      start_at: processingForm.start_at || null,
      comment: processingForm.comment || '',
      attachments: attachmentPayload,
      form_data: Object.keys(formDataPayload).length ? formDataPayload : undefined
    })
    uni.showToast({ title: '任务已更新', icon: 'success' })
    processingForm.comment = ''
    pendingAttachments.value = []
    moduleSchema.value.forEach((item) => {
      if (moduleForm[item.key]) {
        moduleForm[item.key].attachments = []
      }
    })
    await fetchTaskDetail(task.value.id)
  } catch (error) {
    console.error(error)
  } finally {
    processingTask.value = false
  }
}

const submitModuleOnly = async () => {
  if (!task.value?.id) return
  const { payload: modulePayload, attachments: moduleAttachments } = buildModulePayload()
  const formDataPayload = {}
  if (Object.keys(modulePayload).length > 0) {
    formDataPayload.modules = modulePayload
  }
  const attachmentPayload = [
    ...moduleAttachments,
    ...pendingAttachments.value.map((item) => ({
      media_id: item.media_id,
      category: item.category || null,
      field_key: item.field_key || null
    }))
  ]
  if (!Object.keys(formDataPayload).length && !attachmentPayload.length) {
    uni.showToast({ title: '请填写内容或上传附件', icon: 'none' })
    return
  }
  processingTask.value = true
  try {
    await api.updateTaskStatus(task.value.id, {
      status: task.value.status || 'in_progress',
      attachments: attachmentPayload,
      form_data: Object.keys(formDataPayload).length ? formDataPayload : undefined
    })
    uni.showToast({ title: '提交成功', icon: 'success' })
    pendingAttachments.value = []
    moduleSchema.value.forEach((item) => {
      if (moduleForm[item.key]) {
        moduleForm[item.key].attachments = []
      }
    })
    await fetchTaskDetail(task.value.id)
  } catch (error) {
    console.error(error)
  } finally {
    processingTask.value = false
  }
}

const submitProcurement = async () => {
  if (!procurementForm.supplier_id) {
    uni.showToast({ title: '请选择供应商', icon: 'none' })
    return
  }
  if (procurementForm.inventory_item_id && selectedInventory.value) {
    const available = selectedInventory.value.quantity || 0
    const useQty = Number(procurementForm.inventory_quantity || 0)
    if (useQty <= 0) {
      uni.showToast({ title: '请输入使用数量', icon: 'none' })
      return
    }
    if (useQty > available) {
      uni.showToast({ title: '数量超过库存可用数量', icon: 'none' })
      return
    }
  }
  const { payload: modulePayload, attachments: moduleAttachments } = buildModulePayload()
  const formDataPayload = {
    procurement: {
      purchase_status: procurementForm.purchase_status,
      ordered_at: procurementForm.ordered_at,
      purchase_date: procurementForm.purchase_date,
      delivery_date: procurementForm.delivery_date,
      source_location: procurementForm.source_location,
      purchase_price: procurementForm.purchase_price,
      currency: procurementForm.currency,
      inventory: selectedInventory.value
        ? {
            ...selectedInventory.value,
            quantity: procurementForm.inventory_quantity
          }
        : null,
      product_name: procurementForm.product_name,
      model: procurementForm.model,
      voltage: procurementForm.voltage,
      requirements: procurementForm.requirements
    }
  }
  if (Object.keys(modulePayload).length) {
    formDataPayload.modules = modulePayload
  }
  const contractAttachmentPayload = contractAttachments.value
    .filter((item) => item.isNew)
    .map((item) => ({
      media_id: item.media_id,
      category: 'contract',
      field_key: 'contract'
    }))
  const attachmentPayload = [...contractAttachmentPayload, ...moduleAttachments]
  processing.value = true
  try {
    let submitStatus = procurementForm.status
    if (task.value?.need_audit && !isAdminDept.value && submitStatus === 'completed') {
      submitStatus = 'waiting_audit'
      procurementForm.status = submitStatus
      syncStatusIndexes()
    }
    const payload = {
      supplier_id: Number(procurementForm.supplier_id),
      purchase_price: procurementForm.purchase_price !== '' ? Number(procurementForm.purchase_price) : null,
      currency: procurementForm.currency,
      delivery_date: procurementForm.delivery_date,
      purchase_status: procurementForm.purchase_status,
      purchase_date: procurementForm.purchase_date,
      ordered_at: procurementForm.ordered_at,
      source_location: procurementForm.source_location,
      inventory_item_id: procurementForm.inventory_item_id ? Number(procurementForm.inventory_item_id) : null,
      inventory_quantity: procurementForm.inventory_item_id ? Number(procurementForm.inventory_quantity || 0) : null,
      status: submitStatus
    }
    await api.updateProcurementTask(taskId.value, {
      ...payload,
      attachments: attachmentPayload,
      form_data: formDataPayload
    })
    uni.showToast({ title: '已更新', icon: 'success' })
    await fetchTaskDetail(taskId.value)
  } catch (error) {
    console.error(error)
  } finally {
    processing.value = false
  }
}

watch(task, (val) => {
  if (val) {
    syncProcessingForm()
  }
})

watch(availableTaskStatusOptions, () => {
  syncProcessingStatusIndex()
  syncStatusIndexes()
})

onLoad(async (query) => {
  if (!query?.id) return
  taskId.value = Number(query.id)
  await Promise.all([fetchTaskDetail(taskId.value), fetchSuppliers(), fetchStaff()])
})

const openChat = () => {
  uni.navigateTo({ url: '/pages/messages/group' })
}

const openOrderDetail = () => {
  if (!task.value?.order?.id) return
  uni.navigateTo({ url: `/pages/order/detail?id=${task.value.order.id}` })
}

const urgeTask = async () => {
  if (!task.value?.id || urgingTask.value) return
  urgingTask.value = true
  try {
    await api.urgeTask(task.value.id)
    uni.showToast({ title: '催办成功', icon: 'success' })
    await fetchTaskDetail(taskId.value)
  } catch (error) {
    console.error(error)
  } finally {
    urgingTask.value = false
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
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.04);
}
.heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10rpx;
}
.heading-actions {
  display: flex;
  align-items: center;
  gap: 12rpx;
}
.small-btn {
  padding: 6rpx 16rpx;
  border-radius: 14rpx;
  font-size: 22rpx;
}
.task-title {
  font-size: 34rpx;
  font-weight: 600;
}
.task-sub {
  color: #8c8c8c;
  margin-top: 6rpx;
}
.flag {
  padding: 6rpx 20rpx;
  border-radius: 20rpx;
  background: #e6f4ff;
  color: #1677ff;
}
.flag.completed {
  background: #f6ffed;
  color: #52c41a;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
  margin: 16rpx 0;
}
.chip {
  padding: 6rpx 16rpx;
  border-radius: 16rpx;
  background: #f0f5ff;
  color: #1677ff;
  font-size: 22rpx;
}
.requirement .label {
  font-size: 26rpx;
  color: #666;
  margin-bottom: 8rpx;
}
.requirement .content {
  font-size: 26rpx;
  color: #333;
  line-height: 1.6;
}
.requirement .modules {
  margin-top: 10rpx;
}
.requirement .tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8rpx;
  margin-top: 6rpx;
}
.requirement .tag {
  padding: 6rpx 12rpx;
  background: #eef4ff;
  color: #1677ff;
  border-radius: 8rpx;
  font-size: 24rpx;
}
.timeline {
  display: flex;
  justify-content: space-between;
  margin-top: 20rpx;
  gap: 20rpx;
}
.timeline.column {
  flex-direction: column;
}
.caption {
  color: #999;
  font-size: 24rpx;
}
.value {
  font-size: 26rpx;
  margin-top: 8rpx;
}
.actions {
  display: flex;
  gap: 16rpx;
  margin-top: 28rpx;
}
.outline {
  border: 1rpx solid #d6e4ff;
  color: #1677ff;
  background: #fff;
  border-radius: 28rpx;
  padding: 0 32rpx;
}
.section-title {
  font-size: 28rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.logs {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}
.log-item {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.log-row {
  display: flex;
  justify-content: space-between;
  font-size: 24rpx;
}
.log-action {
  font-weight: 600;
}
.log-time {
  color: #999;
}
.log-message {
  margin-top: 6rpx;
  color: #666;
  font-size: 26rpx;
}
.empty {
  text-align: center;
  color: #999;
  padding: 20rpx 0;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.form-item {
  margin-bottom: 20rpx;
}
.form-item text {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
  font-size: 24rpx;
}
.form-item input {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
  font-size: 26rpx;
}
.form-item textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
  font-size: 26rpx;
  min-height: 160rpx;
  box-sizing: border-box;
}
.form-item.qty-inline {
  display: flex;
  align-items: center;
  gap: 12rpx;
}
.form-item.qty-inline text {
  margin-bottom: 0;
  min-width: 160rpx;
}
.form-item.qty-inline input {
  margin: 0;
}
.picker {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 18rpx 16rpx;
  color: #333;
}
.datetime-row {
  display: flex;
  gap: 16rpx;
}
.upload-row {
  display: flex;
  justify-content: flex-start;
  margin-bottom: 16rpx;
}
.pending-attachments {
  display: flex;
  gap: 16rpx;
  flex-wrap: wrap;
  margin-bottom: 16rpx;
}
.pending-item {
  position: relative;
  width: 140rpx;
  height: 140rpx;
  border-radius: 16rpx;
  overflow: hidden;
  background: #f0f2f5;
}
.pending-item image,
.pending-item video {
  width: 100%;
  height: 100%;
}
.pending-item .remove {
  position: absolute;
  right: 8rpx;
  top: 8rpx;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  padding: 4rpx 8rpx;
  border-radius: 12rpx;
  font-size: 20rpx;
}
.attachment-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}
.attachment-item {
  width: 200rpx;
  border-radius: 16rpx;
  overflow: hidden;
  background: #f7f8fa;
}
.attachment-item image,
.attachment-item video {
  width: 100%;
  height: 160rpx;
}
.attachment-name {
  text-align: center;
  padding: 8rpx;
  font-size: 24rpx;
  color: #666;
}
.attachment-name .tag {
  margin-left: 8rpx;
}
.audit-actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
}
.module-block {
  border-bottom: 1rpx solid #f0f0f0;
  padding-bottom: 16rpx;
  margin-bottom: 12rpx;
}
.module-block:last-child {
  border-bottom: 0;
  margin-bottom: 0;
}
.inventory-box {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}
.inventory-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12rpx;
}
.inventory-selected .row-title {
  font-weight: 600;
}
.qty-box {
  display: flex;
  align-items: center;
  gap: 12rpx;
}
.small-tip {
  font-size: 22rpx;
  color: #999;
  margin-top: 6rpx;
}
.tag {
  background: #eef4ff;
  color: #1677ff;
  padding: 2rpx 8rpx;
  border-radius: 12rpx;
  font-size: 22rpx;
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
  width: 92%;
  max-height: 80vh;
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  flex-direction: column;
}
.assign-dialog.large {
  width: 94%;
}
.dialog-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}
.dialog-scroll {
  flex: 1;
  max-height: 520rpx;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-top: 16rpx;
}
.dialog-title {
  font-size: 32rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.dialog-search {
  background: #f5f6fa;
  border-radius: 16rpx;
  padding: 16rpx;
  font-size: 26rpx;
}
.list-row {
  padding: 16rpx;
  border-radius: 16rpx;
  background: #f6f7fb;
  margin-bottom: 12rpx;
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
  margin-top: 4rpx;
}
.loading {
  text-align: center;
  padding: 40rpx 0;
  color: #666;
}
.qty-inline {
  flex-direction: row;
  align-items: center;
}
.qty-inline input {
  flex: 1;
}
</style>
