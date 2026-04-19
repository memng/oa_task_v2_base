<template>
  <div class="page">
    <el-card>
      <el-form inline :model="query" class="filter-form">
        <el-form-item label="关键字">
          <el-input v-model="query.keyword" placeholder="客户/PI/任务" clearable />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="query.status" placeholder="全部" clearable>
            <el-option label="全部" value="" />
            <el-option label="进行中" value="in_progress" />
            <el-option label="已完成" value="completed" />
            <el-option label="草稿" value="draft" />
          </el-select>
        </el-form-item>
        <el-form-item label="业务员">
          <el-input v-model="query.sales_keyword" placeholder="姓名/昵称" clearable />
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            @change="onDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetch">查询</el-button>
          <el-button @click="reset">重置</el-button>
        </el-form-item>
      </el-form>
      <el-table :data="list" stripe style="width: 100%">
        <el-table-column label="PI号" min-width="180">
          <template #default="{ row }">
            <div class="pi-cell">
              <div class="pi-main">{{ (row.pi_numbers && row.pi_numbers.length ? row.pi_numbers[0] : row.pi_number) || '-' }}</div>
              <div v-if="row.pi_numbers && row.pi_numbers.length > 1" class="pi-extra">
                其余：{{ row.pi_numbers.slice(1).join('，') }}
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="customer_name" label="客户" min-width="160" />
        <el-table-column prop="sales_owner_name" label="业务员" width="140" />
        <el-table-column prop="initiator_name" label="发起人" width="140" />
        <el-table-column prop="expected_delivery_at" label="交期" width="140" />
        <el-table-column label="总价" width="160">
          <template #default="{ row }">
            <span v-if="row.grand_total != null">{{ row.grand_total }} {{ row.currency || '' }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="viewDetail(row)">查看详情</el-button>
            <el-button link @click="editOrder(row)">编辑</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const list = ref([])
const query = reactive({ keyword: '', status: '', sales_keyword: '', start_date: '', end_date: '' })
const dateRange = ref([])
const router = useRouter()
const statusMap = {
  draft: '草稿',
  in_progress: '进行中',
  completed: '已完成',
  cancelled: '已取消'
}

const fetch = async () => {
  const { data } = await api.orders(query)
  list.value = data.data.items || []
}

const onDateChange = (val) => {
  if (val && val.length === 2) {
    query.start_date = val[0]
    query.end_date = val[1]
  } else {
    query.start_date = ''
    query.end_date = ''
  }
}

const reset = () => {
  query.keyword = ''
  query.status = ''
  query.sales_keyword = ''
  query.start_date = ''
  query.end_date = ''
  dateRange.value = []
  fetch()
}

const viewDetail = (row) => {
  router.push(`/orders/${row.id}`)
}

const editOrder = (row) => {
  router.push(`/orders/${row.id}/edit`)
}

const statusLabel = (status) => statusMap[status] || status
const statusTag = (status) => {
  if (status === 'completed') return 'success'
  if (status === 'cancelled') return 'info'
  return 'warning'
}

onMounted(fetch)
</script>

<style scoped>
.page {
  padding: 24px;
}
.filter-form {
  margin-bottom: 16px;
}
.pi-cell {
  display: flex;
  flex-direction: column;
}
.pi-extra {
  color: #909399;
  font-size: 12px;
}
.muted {
  color: #909399;
}
</style>
