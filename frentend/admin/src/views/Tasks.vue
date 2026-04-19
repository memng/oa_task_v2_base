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

      <el-table :data="list" stripe :loading="loading">
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
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const router = useRouter()
const list = ref([])
const loading = ref(false)
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
