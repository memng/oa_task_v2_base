<template>
  <div class="page" v-loading="loading">
    <el-page-header @back="goBack" content="订单详情" class="header">
      <template #title>
        <span>{{ detail?.order?.pi_number || '订单详情' }}</span>
      </template>
      <template #extra>
        <el-button type="primary" @click="openTaskForm">新建关联任务</el-button>
        <el-button @click="goEdit" plain>编辑订单</el-button>
      </template>
    </el-page-header>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>订单信息</span>
          <el-tag :type="statusTag(detail?.order?.status)">
            {{ statusLabel(detail?.order?.status) }}
          </el-tag>
        </div>
      </template>
      <el-descriptions :column="3" border v-if="detail?.order">
        <el-descriptions-item label="PI号">{{ piDisplay }}</el-descriptions-item>
        <el-descriptions-item label="客户">{{ detail.order.customer_name }}</el-descriptions-item>
        <el-descriptions-item label="币种">{{ detail.order.currency || '-' }}</el-descriptions-item>
        <el-descriptions-item label="交期">{{ detail.order.expected_delivery_at || '待定' }}</el-descriptions-item>
        <el-descriptions-item label="交货期(天)">{{ detail.order.delivery_period_days || '-' }}</el-descriptions-item>
        <el-descriptions-item label="业务员">{{ detail.order.sales_owner_name || '未知' }}</el-descriptions-item>
        <el-descriptions-item label="海运费">{{ formatAmount(detail.order.sea_freight) }}</el-descriptions-item>
        <el-descriptions-item label="折扣">{{ formatAmount(detail.order.discount_amount) }}</el-descriptions-item>
        <el-descriptions-item label="总价">{{ formatAmount(detail.order.grand_total) }}</el-descriptions-item>
        <el-descriptions-item label="发起人">{{ detail.order.initiator_name || '未知' }}</el-descriptions-item>
        <el-descriptions-item label="订单备注">{{ detail.order.remark || '-' }}</el-descriptions-item>
        <el-descriptions-item label="附件">
          <div v-if="documentList.length">
            <div v-for="doc in documentList" :key="doc.id" class="doc-row">
              <el-tag size="small" class="doc-tag">{{ doc.doc_type || '附件' }}</el-tag>
              <el-link v-if="doc.url" :href="assetUrl(doc.url)" target="_blank" type="primary">
                {{ doc.file_name || doc.url }}
              </el-link>
              <span v-else>{{ doc.file_name || '文件' }}</span>
            </div>
          </div>
          <span v-else class="muted">-</span>
        </el-descriptions-item>
      </el-descriptions>
      <div class="progress-box">
        <span>任务完成度</span>
        <el-progress :percentage="progressPercent" :stroke-width="16" />
      </div>
    </el-card>

    <el-card class="products-card">
      <template #header>
        <div class="card-header">
          <span>产品信息</span>
          <span class="muted">产品总价：{{ formatAmount(detail?.order?.products_total) }}</span>
        </div>
      </template>
      <el-table :data="detail?.products || []" size="small" stripe>
        <el-table-column prop="product_name" label="产品" min-width="160" />
        <el-table-column prop="model" label="型号" width="140" />
        <el-table-column prop="voltage" label="电压" width="140" />
        <el-table-column prop="power" label="机器功率" width="120" />
        <el-table-column prop="processing_length" label="加工长度" width="140" />
        <el-table-column prop="dimensions" label="外形尺寸" min-width="160" />
        <el-table-column prop="quantity" label="数量" width="100" />
        <el-table-column label="单价" width="140">
          <template #default="{ row }">
            {{ formatAmount(row.unit_price, row.currency || detail?.order?.currency) }}
          </template>
        </el-table-column>
        <el-table-column label="总价" width="160">
          <template #default="{ row }">
            {{ formatAmount(row.total_price, row.currency || detail?.order?.currency) }}
          </template>
        </el-table-column>
        <el-table-column prop="notes" label="备注" min-width="160" />
      </el-table>
    </el-card>

    <el-card class="tasks-card">
      <template #header>
        <div class="card-header">
          <span>关联任务</span>
        </div>
      </template>
      <el-table :data="detail?.tasks || []" stripe>
        <el-table-column prop="title" label="任务" min-width="180" />
        <el-table-column prop="type_label" label="类型" width="140" />
        <el-table-column prop="assignee_name" label="负责人" width="140" />
        <el-table-column prop="due_at" label="截止时间" width="160" />
        <el-table-column label="采购信息" min-width="220">
          <template #default="{ row }">
            <div v-if="row.type === 'procurement'">
              <div v-if="row.procurement">
                <div>供应商：{{ row.procurement.supplier_name || '-' }}</div>
                <div>采购价：{{ row.procurement.purchase_price || '-' }} {{ row.procurement.currency || '' }}</div>
              </div>
              <div v-else-if="row.procurement_hidden" class="muted">供应商与采购价仅管理员可见</div>
              <div v-else class="muted">-</div>
            </div>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="提交内容" min-width="200">
          <template #default="{ row }">
            <div v-if="formDataSummary(row).length" class="submit-lines">
              <div v-for="(line, idx) in formDataSummary(row)" :key="idx" class="submit-line">{{ line }}</div>
            </div>
            <div v-if="row.attachments && row.attachments.length" class="doc-row">
              <div v-for="file in row.attachments" :key="file.media_id" class="doc-row">
                <el-link v-if="file.url" :href="assetUrl(file.url)" target="_blank" type="primary">
                  {{ file.file_name || file.url }}
                </el-link>
                <span v-else>{{ file.file_name || '附件' }}</span>
                <el-tag size="small" class="doc-tag">{{ file.file_type || '文件' }}</el-tag>
                <el-tag v-if="file.category" size="small" type="info" class="doc-tag">#{{ file.category }}</el-tag>
              </div>
            </div>
            <span v-if="!formDataSummary(row).length && !(row.attachments && row.attachments.length)" class="muted">暂无</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="140">
          <template #default="{ row }">
            <el-tag :type="taskStatusTag(row.status)">{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-drawer v-model="taskDrawer" title="新建关联任务" size="30%">
      <el-form :model="taskForm" label-width="90px">
        <el-form-item label="任务类型">
          <el-select v-model="taskForm.type" placeholder="请选择任务类型">
            <el-option v-for="item in taskTypes" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="任务标题">
          <el-input v-model="taskForm.title" placeholder="请输入标题" />
        </el-form-item>
        <el-form-item label="负责人ID">
          <el-input v-model="taskForm.assigned_to" placeholder="请输入负责人ID" />
        </el-form-item>
        <el-form-item label="截止时间">
          <el-date-picker v-model="taskForm.due_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="请选择时间" />
        </el-form-item>
        <el-form-item label="需要审核">
          <el-switch v-model="taskForm.need_audit" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="任务描述">
          <el-input type="textarea" v-model="taskForm.description" placeholder="请输入要求" rows="4" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div class="drawer-footer">
          <el-button @click="taskDrawer = false">取消</el-button>
          <el-button type="primary" @click="submitTask" :loading="taskSubmitting">提交</el-button>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ASSET_BASE_URL, api } from '../api'

const route = useRoute()
const router = useRouter()
const detail = ref(null)
const loading = ref(false)
const taskDrawer = ref(false)
const taskSubmitting = ref(false)
const statusMap = {
  draft: '草稿',
  in_progress: '进行中',
  completed: '已完成',
  cancelled: '已取消'
}
const taskTypes = [
  { label: '采购任务', value: 'procurement' },
  { label: '铭牌制作', value: 'nameplate' },
  { label: '机器数据', value: 'machine_data' },
  { label: '机器验收', value: 'acceptance' },
  { label: '打包唛头', value: 'packaging' },
  { label: '装柜发货', value: 'shipment' },
  { label: '工厂订单', value: 'factory_order' },
  { label: '临时任务', value: 'temporary' }
]

const taskForm = reactive({
  order_id: null,
  type: 'procurement',
  title: '',
  assigned_to: '',
  due_at: '',
  need_audit: 0,
  description: ''
})

const piDisplay = computed(() => {
  const nums = detail.value?.order?.pi_numbers
  if (nums && nums.length) {
    return nums.join(' / ')
  }
  return detail.value?.order?.pi_number || '-'
})
const documentList = computed(() => detail.value?.documents || [])

const fetchDetail = async () => {
  loading.value = true
  try {
    const { data } = await api.orderDetail(route.params.id)
    detail.value = data.data
    taskForm.order_id = data.data?.order?.id || null
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.push('/orders')
}

const statusLabel = (status) => statusMap[status] || status || '-'
const statusTag = (status) => {
  if (status === 'completed') return 'success'
  if (status === 'cancelled') return 'info'
  return 'warning'
}
const taskStatusTag = (status) => {
  if (status === 'completed') return 'success'
  if (status === 'waiting_audit') return 'warning'
  if (status === 'rejected') return 'danger'
  return 'info'
}

const assetUrl = (url) => {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url
  return `${ASSET_BASE_URL}${url}`
}

const formatAmount = (value, currency = '') => {
  if (value === undefined || value === null || value === '') {
    return '-'
  }
  const num = Number(value)
  const amount = Number.isFinite(num) ? num.toFixed(2).replace(/\.00$/, '') : value
  const cur = currency || detail.value?.order?.currency || ''
  return `${amount}${cur ? ` ${cur}` : ''}`
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
    if (p.inventory?.item_id) {
      parts.push(`库存#${p.inventory.item_id} 数量:${p.inventory.quantity || '-'}`)
    }
    if (parts.length) {
      summary.push(parts.join('，'))
    }
    if (p.product_name) {
      summary.push(`产品: ${p.product_name} ${p.model || ''} ${p.voltage || ''}`)
    }
    if (p.requirements) {
      summary.push(`机器要求: ${p.requirements}`)
    }
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

const progressPercent = computed(() => {
  if (!detail.value?.tasks?.length) return 0
  const total = detail.value.tasks.length
  const done = detail.value.tasks.filter((task) => task.status === 'completed').length
  return Math.round((done / total) * 100)
})

const openTaskForm = () => {
  taskDrawer.value = true
}

const submitTask = async () => {
  if (!taskForm.title) {
    ElMessage.warning('请填写任务标题')
    return
  }
  taskSubmitting.value = true
  try {
    await api.createTask({
      order_id: taskForm.order_id,
      type: taskForm.type,
      title: taskForm.title,
      assigned_to: taskForm.assigned_to ? Number(taskForm.assigned_to) : null,
      due_at: taskForm.due_at,
      need_audit: taskForm.need_audit,
      description: taskForm.description
    })
    ElMessage.success('任务创建成功')
    taskDrawer.value = false
    taskForm.title = ''
    taskForm.assigned_to = ''
    taskForm.due_at = ''
    taskForm.need_audit = 0
    taskForm.description = ''
    await fetchDetail()
  } catch (error) {
    console.error(error)
  } finally {
    taskSubmitting.value = false
  }
}

const goEdit = () => {
  if (detail.value?.order?.id) {
    router.push(`/orders/${detail.value.order.id}/edit`)
  }
}

onMounted(fetchDetail)
</script>

<style scoped>
.page {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.header {
  margin-bottom: 8px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.progress-box {
  margin-top: 24px;
}
.tasks-card {
  margin-top: 8px;
}
.products-card {
  margin-top: 8px;
}
.muted {
  color: #909399;
}
.doc-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}
.submit-lines {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 6px;
}
.submit-line {
  line-height: 1.4;
}
.doc-tag {
  margin-right: 4px;
}
.drawer-footer {
  text-align: right;
}
</style>
