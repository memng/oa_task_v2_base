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
    </el-card>

    <el-card class="stage-card">
      <template #header>
        <div class="card-header">
          <span>阶段进度</span>
          <span class="muted" v-if="stageProgress">
            整体完成 {{ stageProgress.overall_progress }}%
          </span>
        </div>
      </template>
      <div v-if="stageProgress" class="stage-stepper">
        <div
          v-for="(stage, idx) in stageProgress.stages"
          :key="stage.stage"
          class="stage-step"
          :class="{
            'is-completed': stage.status === 'completed',
            'is-current': stage.stage === stageProgress.current_stage,
            'is-overdue': stage.status === 'overdue',
            'is-pending': stage.status === 'pending',
            'is-progress': stage.status === 'in_progress',
          }"
        >
          <div class="stage-node" @click="handleStageClick(stage)">
            <div class="stage-icon">
              <el-icon v-if="stage.status === 'completed'" :size="20"><Check /></el-icon>
              <el-icon v-else-if="stage.status === 'overdue'" :size="20"><Warning /></el-icon>
              <span v-else class="stage-order">{{ stage.order }}</span>
            </div>
            <div class="stage-label">{{ stage.label }}</div>
            <div class="stage-meta" v-if="stage.total_tasks > 0">
              {{ stage.completed_tasks }}/{{ stage.total_tasks }}
            </div>
            <el-tag v-if="stage.status === 'overdue'" type="danger" size="small" class="overdue-tag">超期</el-tag>
            <el-tag v-if="stage.has_delay_reason" type="warning" size="small" class="overdue-tag">已说明</el-tag>
          </div>
          <div v-if="idx < stageProgress.stages.length - 1" class="stage-connector" :class="{ 'is-done': stage.status === 'completed' }" />
        </div>
      </div>
      <div class="progress-bar-wrap" v-if="stageProgress">
        <el-progress :percentage="stageProgress.overall_progress" :stroke-width="14" :color="progressColor" />
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
        <el-table-column label="延期" width="100">
          <template #default="{ row }">
            <el-button v-if="isTaskOverdue(row) && !row.delay_reason" type="danger" size="small" link @click="openDelayDialog(row)">填写原因</el-button>
            <el-tag v-else-if="row.delay_reason" type="warning" size="small">已说明</el-tag>
            <span v-else class="muted">-</span>
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

    <el-dialog v-model="delayDialog" title="填写延期原因" width="480px">
      <el-form label-width="80px">
        <el-form-item label="延期原因">
          <el-input type="textarea" v-model="delayForm.reason" :rows="4" placeholder="请说明延期原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="delayDialog = false">取消</el-button>
        <el-button type="primary" @click="submitDelayReason" :loading="delaySubmitting">提交</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="stageTransitionDialog" title="推进阶段" width="480px">
      <el-form label-width="80px">
        <el-form-item label="目标阶段">
          <el-select v-model="stageTransitionForm.to_stage" placeholder="请选择目标阶段">
            <el-option
              v-for="s in availableNextStages"
              :key="s.value"
              :label="s.label"
              :value="s.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item v-if="currentStageIsOverdue" label="延期原因">
          <el-input type="textarea" v-model="stageTransitionForm.delay_reason" :rows="3" placeholder="当前阶段超期，请说明延期原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="stageTransitionDialog = false">取消</el-button>
        <el-button type="primary" @click="submitStageTransition" :loading="stageTransitionSubmitting">确认推进</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Check, Warning } from '@element-plus/icons-vue'
import { ASSET_BASE_URL, api } from '../api'

const route = useRoute()
const router = useRouter()
const detail = ref(null)
const loading = ref(false)
const taskDrawer = ref(false)
const taskSubmitting = ref(false)
const delayDialog = ref(false)
const delaySubmitting = ref(false)
const stageTransitionDialog = ref(false)
const stageTransitionSubmitting = ref(false)
const delayForm = reactive({ taskId: null, reason: '' })
const stageTransitionForm = reactive({ to_stage: '', delay_reason: '' })
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

const stageProgress = computed(() => detail.value?.stage_progress || null)

const progressColor = computed(() => {
  if (!stageProgress.value) return '#409eff'
  const p = stageProgress.value.overall_progress
  if (p >= 100) return '#67c23a'
  if (p >= 60) return '#409eff'
  if (p >= 30) return '#e6a23c'
  return '#f56c6c'
})

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

const isTaskOverdue = (task) => {
  if (task.status === 'completed' || task.status === 'cancelled') return false
  if (!task.due_at) return false
  return new Date(task.due_at) < new Date()
}

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

const openDelayDialog = (task) => {
  delayForm.taskId = task.id
  delayForm.reason = ''
  delayDialog.value = true
}

const submitDelayReason = async () => {
  if (!delayForm.reason.trim()) {
    ElMessage.warning('请填写延期原因')
    return
  }
  delaySubmitting.value = true
  try {
    await api.orderTaskDelayReason(taskForm.order_id, delayForm.taskId, { delay_reason: delayForm.reason })
    ElMessage.success('延期原因已记录')
    delayDialog.value = false
    await fetchDetail()
  } catch (error) {
    console.error(error)
  } finally {
    delaySubmitting.value = false
  }
}

const handleStageClick = (stage) => {
  if (stage.stage === stageProgress.value?.current_stage && stage.status !== 'completed') {
    stageTransitionForm.to_stage = ''
    stageTransitionForm.delay_reason = ''
    stageTransitionDialog.value = true
  }
}

const submitStageTransition = async () => {
  if (!stageTransitionForm.to_stage) {
    ElMessage.warning('请选择目标阶段')
    return
  }
  stageTransitionSubmitting.value = true
  try {
    await api.orderStageTransition(route.params.id, {
      to_stage: stageTransitionForm.to_stage,
      delay_reason: stageTransitionForm.delay_reason || null
    })
    ElMessage.success('阶段推进成功')
    stageTransitionDialog.value = false
    await fetchDetail()
  } catch (error) {
    console.error(error)
  } finally {
    stageTransitionSubmitting.value = false
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
.stage-card {
  margin-top: 8px;
}
.stage-stepper {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 8px 0;
}
.stage-step {
  display: flex;
  align-items: center;
  flex: 1;
}
.stage-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  min-width: 80px;
}
.stage-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  border: 2px solid #dcdfe6;
  background: #fff;
  color: #909399;
  transition: all 0.3s;
}
.stage-step.is-completed .stage-icon {
  background: #67c23a;
  border-color: #67c23a;
  color: #fff;
}
.stage-step.is-current .stage-icon {
  background: #409eff;
  border-color: #409eff;
  color: #fff;
}
.stage-step.is-overdue .stage-icon {
  background: #f56c6c;
  border-color: #f56c6c;
  color: #fff;
}
.stage-step.is-progress .stage-icon {
  border-color: #409eff;
  color: #409eff;
}
.stage-order {
  font-size: 14px;
}
.stage-label {
  margin-top: 6px;
  font-size: 13px;
  color: #606266;
  text-align: center;
  white-space: nowrap;
}
.stage-step.is-completed .stage-label {
  color: #67c23a;
}
.stage-step.is-current .stage-label {
  color: #409eff;
  font-weight: 600;
}
.stage-step.is-overdue .stage-label {
  color: #f56c6c;
}
.stage-meta {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}
.overdue-tag {
  margin-top: 4px;
}
.stage-connector {
  flex: 1;
  height: 2px;
  background: #dcdfe6;
  margin: 18px 4px 0;
  min-width: 20px;
}
.stage-connector.is-done {
  background: #67c23a;
}
.progress-bar-wrap {
  margin-top: 16px;
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
