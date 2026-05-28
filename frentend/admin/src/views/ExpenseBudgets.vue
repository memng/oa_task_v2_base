<template>
  <el-card>
    <div class="toolbar">
      <div class="toolbar-left">
        <el-select v-model="filterPeriod" placeholder="全部周期" clearable @change="fetchList" style="width: 160px">
          <el-option v-for="p in periodOptions" :key="p" :label="p" :value="p" />
        </el-select>
        <el-select v-model="filterDept" placeholder="全部部门" clearable @change="fetchList" style="width: 200px">
          <el-option v-for="d in departments" :key="d.id" :label="d.name" :value="d.id" />
        </el-select>
        <el-button type="primary" @click="fetchList">刷新</el-button>
      </div>
      <el-button type="success" @click="openDialog()">新增预算</el-button>
    </div>
    <el-table :data="list" border stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="dept_name" label="部门" width="140" />
      <el-table-column prop="type_label" label="报销类型" width="120" />
      <el-table-column prop="period" label="预算周期" width="120" />
      <el-table-column prop="budget_amount" label="预算金额" width="120">
        <template #default="{ row }">
          <span>¥{{ row.budget_amount.toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="used_amount" label="已用金额" width="120">
        <template #default="{ row }">
          <span>¥{{ row.used_amount.toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="remain_amount" label="剩余金额" width="120">
        <template #default="{ row }">
          <span>¥{{ row.remain_amount.toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="usage_percent" label="使用率" width="100">
        <template #default="{ row }">
          <el-progress :percentage="Math.min(row.usage_percent, 100)" :color="progressColor(row.usage_percent)" :stroke-width="12" :text-inside="true" />
        </template>
      </el-table-column>
      <el-table-column label="操作" width="150">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑预算' : '新增预算'" width="500px">
      <el-form :model="dialog.form" label-width="100px">
        <el-form-item label="部门">
          <el-select v-model="dialog.form.dept_id" placeholder="全局预算" clearable style="width: 100%">
            <el-option v-for="d in departments" :key="d.id" :label="d.name" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="报销类型">
          <el-select v-model="dialog.form.type" placeholder="所有类型" clearable style="width: 100%">
            <el-option label="所有类型" value="" />
            <el-option label="差旅费" value="travel" />
            <el-option label="采购费用" value="purchase" />
            <el-option label="餐费" value="meal" />
            <el-option label="交通费" value="transport" />
            <el-option label="办公用品" value="office" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="预算周期">
          <el-date-picker v-model="dialog.form.period" type="month" placeholder="选择月份" format="YYYY-MM" value-format="YYYY-MM" style="width: 100%" />
        </el-form-item>
        <el-form-item label="预算金额">
          <el-input-number v-model="dialog.form.budget_amount" :min="0.01" :precision="2" :step="100" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确认</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../api'

const list = ref([])
const departments = ref([])
const filterPeriod = ref('')
const filterDept = ref('')

const periodOptions = (() => {
  const now = new Date()
  const opts = []
  for (let i = -3; i <= 6; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() + i, 1)
    opts.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
  }
  return opts
})()

const dialog = reactive({
  visible: false,
  isEdit: false,
  editId: null,
  form: {
    dept_id: null,
    type: '',
    period: '',
    budget_amount: 1000
  }
})

const progressColor = (percent) => {
  if (percent >= 90) return '#ff4d4f'
  if (percent >= 70) return '#fa8c16'
  return '#52c41a'
}

const fetchList = async () => {
  const params = {}
  if (filterPeriod.value) params.period = filterPeriod.value
  if (filterDept.value) params.dept_id = filterDept.value
  const { data } = await api.expenseBudgets(params)
  list.value = data.data.items || []
}

const fetchDepartments = async () => {
  try {
    const { data } = await api.adminDepartments()
    departments.value = data.data || []
  } catch (e) {
    departments.value = []
  }
}

const openDialog = (row = null) => {
  if (row) {
    dialog.isEdit = true
    dialog.editId = row.id
    dialog.form.dept_id = row.dept_id
    dialog.form.type = row.type
    dialog.form.period = row.period
    dialog.form.budget_amount = row.budget_amount
  } else {
    dialog.isEdit = false
    dialog.editId = null
    dialog.form.dept_id = null
    dialog.form.type = ''
    dialog.form.period = ''
    dialog.form.budget_amount = 1000
  }
  dialog.visible = true
}

const handleSubmit = async () => {
  if (!dialog.form.period) {
    ElMessage.warning('请选择预算周期')
    return
  }
  if (dialog.form.budget_amount <= 0) {
    ElMessage.warning('预算金额必须大于0')
    return
  }

  const payload = {
    dept_id: dialog.form.dept_id || '',
    type: dialog.form.type || '',
    period: dialog.form.period,
    budget_amount: dialog.form.budget_amount
  }

  if (dialog.isEdit) {
    await api.updateExpenseBudget(dialog.editId, payload)
  } else {
    await api.createExpenseBudget(payload)
  }

  dialog.visible = false
  fetchList()
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除 ${row.dept_name} ${row.type_label} ${row.period} 的预算吗？`, '确认删除', { type: 'warning' })
    await api.deleteExpenseBudget(row.id)
    fetchList()
  } catch (e) {
    // cancelled
  }
}

onMounted(() => {
  fetchDepartments()
  fetchList()
})
</script>

<style scoped>
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.toolbar-left {
  display: flex;
  gap: 12px;
  align-items: center;
}
</style>
