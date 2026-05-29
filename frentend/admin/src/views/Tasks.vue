<template>
  <div class="page">
    <el-card>
      <el-form :model="query" inline class="filters">
        <el-form-item label="关键字">
          <el-input v-model="query.keyword" placeholder="任务/订单/客户" clearable />
        </el-form-item>
        <el-form-item label="任务类型">
          <el-select v-model="query.type" placeholder="全部" clearable>
            <el-option v-for="item in typeOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="query.status" placeholder="全部" clearable>
            <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="发起人ID">
          <el-input v-model="query.created_by" placeholder="输入用户ID" clearable />
        </el-form-item>
        <el-form-item label="负责人ID">
          <el-input v-model="query.assigned_to" placeholder="输入用户ID" clearable />
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            start-placeholder="开始时间"
            end-placeholder="结束时间"
            value-format="YYYY-MM-DD"
            @change="onDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="loading" @click="fetch">查询</el-button>
          <el-button @click="reset">重置</el-button>
        </el-form-item>
      </el-form>

      <div class="scope-tabs">
        <el-radio-group v-model="currentScope" @change="fetch" size="small">
          <el-radio-button v-for="item in scopeOptions" :key="item.value" :label="item.value">{{ item.label }}</el-radio-button>
        </el-radio-group>
      </div>

      <div class="batch-actions" v-if="selectedIds.length > 0">
        <span class="selected-count">已选择 {{ selectedIds.length }} 项</span>
        <el-button type="primary" size="small" @click="openAssignDialog">批量指派</el-button>
        <el-button type="warning" size="small" @click="handleBatchUrge">批量催办</el-button>
        <el-button size="small" @click="clearSelection">取消选择</el-button>
      </div>

      <el-table
        ref="tableRef"
        :data="list"
        stripe
        :loading="loading"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="title" label="任务" min-width="200" />
        <el-table-column prop="type_label" label="类型" width="140" />
        <el-table-column label="订单" width="160">
          <template #default="{ row }">
            <span v-if="row.pi_number">{{ row.pi_number }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="assignee_name" label="负责人" width="140" />
        <el-table-column prop="creator_name" label="发起人" width="140" />
        <el-table-column prop="due_at" label="截止时间" width="170" />
        <el-table-column label="状态" width="140">
          <template #default="{ row }">
            <el-tag :type="taskStatusTag(row.status)">{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="采购信息" min-width="200">
          <template #default="{ row }">
            <div v-if="row.type === 'procurement'">
              <template v-if="row.procurement">
                <div>供应商：{{ row.procurement.supplier_name || '-' }}</div>
                <div>采购价：{{ row.procurement.purchase_price || '-' }} {{ row.procurement.currency || '' }}</div>
              </template>
              <span v-else-if="row.procurement_hidden" class="muted">仅管理员可见</span>
              <span v-else>-</span>
            </div>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="viewTask(row.id)">查看</el-button>
            <el-button v-if="row.order_id" type="info" link @click="goOrder(row.order_id)">订单详情</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-drawer v-model="detailDrawer" title="任务详情" size="30%">
      <div v-if="taskDetail">
        <div class="drawer-actions" v-if="taskDetail.task?.order?.id">
          <el-button type="primary" plain size="small" @click="goOrder(taskDetail.task.order.id)">订单详情</el-button>
        </div>
        <el-descriptions :column="1" border>
          <el-descriptions-item label="任务">{{ taskDetail.task.title }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ taskDetail.task.type_label }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ taskDetail.task.status_label }}</el-descriptions-item>
          <el-descriptions-item label="负责人">{{ taskDetail.task.assignee_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="截止时间">{{ taskDetail.task.due_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="描述">{{ taskDetail.task.description || '-' }}</el-descriptions-item>
        </el-descriptions>
        <h4 class="logs-title">操作记录</h4>
        <el-timeline>
          <el-timeline-item v-for="log in taskDetail.logs" :key="log.id" :timestamp="log.created_at">
            <p>{{ log.action }} - {{ log.message || '无备注' }}</p>
          </el-timeline-item>
        </el-timeline>
      </div>
      <div v-else class="muted">请选择任务查看详情</div>
    </el-drawer>

    <el-dialog v-model="assignDialog" title="批量指派任务" width="500px">
      <el-form :model="assignForm" label-width="100px">
        <el-form-item label="负责人" required>
          <el-select v-model="assignForm.assigned_to" placeholder="请选择负责人" filterable remote :remote-method="searchUsers" :loading="userLoading">
            <el-option v-for="user in userOptions" :key="user.id" :label="`${user.name} (${user.id})`" :value="user.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="开始时间">
          <el-date-picker v-model="assignForm.start_at" type="datetime" placeholder="选择开始时间" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="assignDialog = false">取消</el-button>
        <el-button type="primary" :loading="batchLoading" @click="handleBatchAssign">确定指派</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { ElMessage, ElMessageBox } from 'element-plus'

const router = useRouter()
const tableRef = ref(null)
const list = ref([])
const loading = ref(false)
const batchLoading = ref(false)
const detailDrawer = ref(false)
const taskDetail = ref(null)
const dateRange = ref([])
const query = reactive({
  keyword: '',
  type: '',
  status: '',
  created_by: '',
  assigned_to: '',
  created_from: '',
  created_to: ''
})
const currentScope = ref('')
const scopeOptions = [
  { label: '全部任务', value: '' },
  { label: '我发起', value: 'initiated' },
  { label: '我负责', value: 'assigned' },
  { label: '待审核', value: 'review' },
  { label: '订单任务', value: 'order' }
]
const typeOptions = [
  { label: '全部', value: '' },
  { label: '采购任务', value: 'procurement' },
  { label: '铭牌制作', value: 'nameplate' },
  { label: '机器数据', value: 'machine_data' },
  { label: '机器验收', value: 'acceptance' },
  { label: '打包唛头', value: 'packaging' },
  { label: '装柜发货', value: 'shipment' },
  { label: '工厂订单', value: 'factory_order' },
  { label: '临时任务', value: 'temporary' }
]
const statusOptions = [
  { label: '全部', value: '' },
  { label: '待开始', value: 'pending' },
  { label: '进行中', value: 'in_progress' },
  { label: '待审核', value: 'waiting_audit' },
  { label: '已完成', value: 'completed' },
  { label: '已驳回', value: 'rejected' }
]

const selectedIds = ref([])
const assignDialog = ref(false)
const assignForm = reactive({
  assigned_to: null,
  start_at: ''
})
const userOptions = ref([])
const userLoading = ref(false)

const handleSelectionChange = (selection) => {
  selectedIds.value = selection.map(item => item.id)
}

const clearSelection = () => {
  selectedIds.value = []
  tableRef.value?.clearSelection()
}

const openAssignDialog = () => {
  assignForm.assigned_to = null
  assignForm.start_at = ''
  userOptions.value = []
  assignDialog.value = true
}

const searchUsers = async (keyword) => {
  if (!keyword) {
    userOptions.value = []
    return
  }
  userLoading.value = true
  try {
    const { data } = await api.lookupStaff({ keyword })
    userOptions.value = data.data || []
  } finally {
    userLoading.value = false
  }
}

const showBatchResult = (title, result) => {
  const { success_count, failed_count, failed_tasks } = result
  const total = success_count + failed_count

  let html = `
    <div style="padding: 8px 0;">
      <div style="display: flex; gap: 24px; margin-bottom: 16px; padding: 16px; background: #f5f7fb; border-radius: 8px;">
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 12px; color: #909399; margin-bottom: 4px;">操作总数</div>
          <div style="font-size: 24px; font-weight: 600; color: #333;">${total}</div>
        </div>
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 12px; color: #909399; margin-bottom: 4px;">成功</div>
          <div style="font-size: 24px; font-weight: 600; color: #52c41a;">${success_count}</div>
        </div>
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 12px; color: #909399; margin-bottom: 4px;">失败</div>
          <div style="font-size: 24px; font-weight: 600; color: #ff4d4f;">${failed_count}</div>
        </div>
      </div>
  `

  if (failed_count > 0 && failed_tasks && failed_tasks.length > 0) {
    const reasonGroups = {}
    failed_tasks.forEach(item => {
      const key = item.reason || '未知原因'
      if (!reasonGroups[key]) {
        reasonGroups[key] = []
      }
      reasonGroups[key].push(item)
    })

    const groupEntries = Object.entries(reasonGroups)
    const hasMultipleGroups = groupEntries.length > 1

    html += `
      <div style="margin-top: 16px;">
        <div style="font-size: 14px; font-weight: 600; color: #ff4d4f; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
          <span>失败原因汇总${hasMultipleGroups ? `（${groupEntries.length} 类）` : ''}</span>
          <span style="font-size: 12px; font-weight: 400; color: #909399;">共 ${failed_count} 项</span>
        </div>
    `

    groupEntries.forEach(([reason, items], groupIndex) => {
      const collapsed = items.length > 3

      html += `
        <div style="margin-bottom: 12px; border-radius: 8px; overflow: hidden; border: 1px solid #ffccc7;">
          <div style="padding: 10px 12px; background: #fff1f0; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #ff4d4f; flex-shrink: 0;"></span>
              <span style="font-size: 13px; color: #333; font-weight: 500;">${reason}</span>
            </div>
            <span style="font-size: 12px; color: #999; background: #fff; padding: 2px 8px; border-radius: 10px;">${items.length} 项</span>
          </div>
          <div style="padding: 4px 12px 8px; background: #fff;" id="fail-group-${groupIndex}">
      `

      const visibleItems = collapsed ? items.slice(0, 3) : items
      visibleItems.forEach((item) => {
        html += `
          <div style="padding: 6px 0; font-size: 12px; color: #666; border-bottom: 1px dashed #f0f0f0; display: flex; justify-content: space-between;">
            <span>任务ID: ${item.id}</span>
          </div>
        `
      })

      if (collapsed) {
        html += `
            <div style="padding: 8px 0; text-align: center;">
              <a onclick="
                var el = document.getElementById('fail-group-${groupIndex}-more');
                var toggle = document.getElementById('fail-group-${groupIndex}-toggle');
                if (el.style.display === 'none') {
                  el.style.display = 'block';
                  toggle.textContent = '收起';
                } else {
                  el.style.display = 'none';
                  toggle.textContent = '展开全部 ${items.length} 项';
                }
                return false;
              " id="fail-group-${groupIndex}-toggle" style="font-size: 12px; color: #1677ff; cursor: pointer; text-decoration: none;">展开全部 ${items.length} 项</a>
            </div>
            <div id="fail-group-${groupIndex}-more" style="display: none;">
        `
        items.slice(3).forEach((item) => {
          html += `
            <div style="padding: 6px 0; font-size: 12px; color: #666; border-bottom: 1px dashed #f0f0f0; display: flex; justify-content: space-between;">
              <span>任务ID: ${item.id}</span>
            </div>
          `
        })
        html += `</div>`
      }

      html += `
          </div>
        </div>
      `
    })

    html += `</div>`
  }

  html += '</div>'

  const msgType = failed_count > 0 ? 'warning' : 'success'

  ElMessageBox({
    title,
    message: html,
    dangerouslyUseHTMLString: true,
    showCancelButton: false,
    confirmButtonText: '知道了',
    type: msgType,
    customClass: 'batch-result-dialog'
  })
}

const handleBatchAssign = async () => {
  if (!assignForm.assigned_to) {
    ElMessage.warning('请选择负责人')
    return
  }
  try {
    batchLoading.value = true
    const { data } = await api.batchAssignTasks({
      task_ids: selectedIds.value,
      assigned_to: assignForm.assigned_to,
      start_at: assignForm.start_at
    })
    assignDialog.value = false
    clearSelection()
    await fetch()
    showBatchResult('批量指派结果', data.data, 'assign')
  } catch (err) {
    ElMessage.error(err.response?.data?.message || '批量指派失败')
  } finally {
    batchLoading.value = false
  }
}

const handleBatchUrge = async () => {
  try {
    await ElMessageBox.confirm(`确定要催办选中的 ${selectedIds.value.length} 个任务吗？`, '批量催办', {
      type: 'warning'
    })
    batchLoading.value = true
    const { data } = await api.batchUrgeTasks({
      task_ids: selectedIds.value
    })
    clearSelection()
    await fetch()
    showBatchResult('批量催办结果', data.data, 'urge')
  } catch (err) {
    if (err !== 'cancel') {
      ElMessage.error(err.response?.data?.message || '批量催办失败')
    }
  } finally {
    batchLoading.value = false
  }
}

const fetch = async () => {
  loading.value = true
  try {
    const params = { ...query }
    if (currentScope.value === 'order') {
      params.category = 'order'
      params.scope = ''
    } else {
      params.category = ''
      params.scope = currentScope.value
    }
    const { data } = await api.tasks(params)
    list.value = data.data.items || []
    selectedIds.value = []
  } finally {
    loading.value = false
  }
}

const reset = () => {
  query.keyword = ''
  query.type = ''
  query.status = ''
  query.created_by = ''
  query.assigned_to = ''
  query.created_from = ''
  query.created_to = ''
  dateRange.value = []
  currentScope.value = ''
  clearSelection()
  fetch()
}

const onDateChange = (val) => {
  if (val && val.length === 2) {
    query.created_from = val[0]
    query.created_to = val[1]
  } else {
    query.created_from = ''
    query.created_to = ''
  }
}

const taskStatusTag = (status) => {
  if (status === 'completed') return 'success'
  if (status === 'waiting_audit') return 'warning'
  if (status === 'rejected') return 'danger'
  return 'info'
}

const viewTask = async (id) => {
  detailDrawer.value = true
  const { data } = await api.taskDetail(id)
  taskDetail.value = data.data
}

const goOrder = (orderId) => {
  if (!orderId) return
  router.push(`/orders/${orderId}`)
}

onMounted(fetch)
</script>

<style scoped>
.page {
  padding: 24px;
}
.filters {
  margin-bottom: 16px;
}
.scope-tabs {
  margin-bottom: 16px;
  display: flex;
  justify-content: flex-start;
}
.batch-actions {
  margin-bottom: 16px;
  padding: 12px 16px;
  background: #f0f5ff;
  border-radius: 4px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.selected-count {
  color: #1677ff;
  font-weight: 500;
}
.muted {
  color: #909399;
}
.logs-title {
  margin-top: 16px;
}
.drawer-actions {
  margin-bottom: 12px;
  text-align: right;
}
</style>
