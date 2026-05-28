<template>
  <el-card>
    <div class="toolbar">
      <span class="toolbar-title">请假审批规则</span>
      <div class="toolbar-right">
        <el-tag v-if="hasDefaultRule" type="success" size="large">已配置全局默认规则</el-tag>
        <el-tag v-else type="danger" size="large">未配置全局默认规则（未匹配规则的请假将无法提交）</el-tag>
        <el-button type="primary" @click="openCreate">新增规则</el-button>
      </div>
    </div>

    <el-table :data="rules" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="规则名称" width="160">
        <template #default="{ row }">
          {{ row.name }}
          <el-tag v-if="row.is_global_default" size="small" type="success" style="margin-left: 4px">全局默认</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="适用部门" width="120">
        <template #default="{ row }">{{ row.dept_name || '全部部门' }}</template>
      </el-table-column>
      <el-table-column label="适用职级" width="120">
        <template #default="{ row }">
          <span v-if="row.level_min != null || row.level_max != null">
            {{ row.level_min ?? '不限' }} ~ {{ row.level_max ?? '不限' }}
          </span>
          <span v-else>不限</span>
        </template>
      </el-table-column>
      <el-table-column label="请假类型" width="100">
        <template #default="{ row }">{{ leaveTypeLabel(row.leave_type) }}</template>
      </el-table-column>
      <el-table-column prop="priority" label="优先级" width="80" />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status ? 'success' : 'info'" size="small">{{ row.status ? '启用' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="审批步骤">
        <template #default="{ row }">
          <div v-if="row.steps && row.steps.length">
            <div v-for="step in row.steps" :key="step.step_order" class="step-item">
              <span class="step-order">{{ step.step_order }}.</span>
              <span class="step-name">{{ step.step_name }}</span>
              <span class="step-approver">（{{ approverTypeLabel(step.approver_type) }}<template v-if="step.approver_name">：{{ step.approver_name }}</template>）</span>
              <el-tag v-if="step.auto_approve" size="small" type="warning">自动通过</el-tag>
              <el-tag v-if="step.migrated" size="small" type="info" :title="step.migration_note">已迁移</el-tag>
            </div>
          </div>
          <span v-else class="no-steps">未配置</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="openEdit(row)">编辑</el-button>
          <el-button type="danger" size="small" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑审批规则' : '新增审批规则'" width="720px" destroy-on-close>
      <el-form :model="form" label-width="100px">
        <el-form-item label="规则名称" required>
          <el-input v-model="form.name" placeholder="如：销售部请假审批" />
        </el-form-item>
        <el-form-item label="适用部门">
          <el-select v-model="form.dept_id" placeholder="全部部门" clearable style="width: 100%">
            <el-option v-for="dept in departments" :key="dept.id" :label="dept.name" :value="dept.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="最低职级">
              <el-input-number v-model="form.level_min" :min="0" placeholder="不限" controls-position="right" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最高职级">
              <el-input-number v-model="form.level_max" :min="0" placeholder="不限" controls-position="right" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="请假类型">
          <el-select v-model="form.leave_type" placeholder="全部类型" clearable style="width: 100%">
            <el-option label="年假" value="annual" />
            <el-option label="病假" value="sick" />
            <el-option label="事假" value="personal" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-input-number v-model="form.priority" :min="0" controls-position="right" />
          <span class="form-hint">数值越大优先级越高</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
        </el-form-item>

        <el-divider>审批步骤配置</el-divider>

        <div v-for="(step, idx) in form.steps" :key="idx" class="step-form-item">
          <el-row :gutter="12" align="middle">
            <el-col :span="2">
              <span class="step-form-order">{{ idx + 1 }}</span>
            </el-col>
            <el-col :span="6">
              <el-input v-model="step.step_name" placeholder="步骤名称" />
            </el-col>
            <el-col :span="6">
              <el-select v-model="step.approver_type" placeholder="审批人类型" @change="onApproverTypeChange(step)">
                <el-option label="部门主管" value="dept_leader" />
                <el-option label="直属上级" value="level_up" />
                <el-option label="指定用户" value="specific_user" />
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-select v-if="step.approver_type === 'specific_user'" v-model="step.approver_user_id" placeholder="选择用户" filterable style="width: 100%">
                <el-option v-for="user in staffList" :key="user.id" :label="user.name || user.nickname" :value="user.id" />
              </el-select>
              <span v-else class="approver-type-hint">{{ approverTypeLabel(step.approver_type) }}</span>
            </el-col>
            <el-col :span="2">
              <el-checkbox v-model="step.auto_approve" :true-value="1" :false-value="0" size="small">自动</el-checkbox>
            </el-col>
            <el-col :span="2">
              <el-button type="danger" size="small" circle @click="removeStep(idx)">
                <span style="font-size: 16px">×</span>
              </el-button>
            </el-col>
          </el-row>
        </div>

        <el-button type="primary" plain @click="addStep" style="margin-top: 8px">+ 添加步骤</el-button>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { api } from '../api'

const rules = ref([])
const hasDefaultRule = ref(false)
const departments = ref([])
const staffList = ref([])
const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const saving = ref(false)

const form = ref({
  name: '',
  dept_id: null,
  level_min: null,
  level_max: null,
  leave_type: null,
  priority: 0,
  status: 1,
  steps: []
})

const leaveTypeLabel = (type) => {
  const map = { annual: '年假', sick: '病假', personal: '事假', other: '其他' }
  return type ? (map[type] || type) : '全部'
}

const approverTypeLabel = (type) => {
  const map = { dept_leader: '部门主管', specific_user: '指定用户', level_up: '直属上级' }
  return map[type] || type
}

const fetchRules = async () => {
  const { data } = await api.approvalRules()
  rules.value = data.data.items || []
  hasDefaultRule.value = data.data.has_default_rule || false
}

const fetchDepartments = async () => {
  const { data } = await api.adminDepartments()
  departments.value = data.data.items || []
}

const fetchStaff = async () => {
  const { data } = await api.lookupStaff()
  staffList.value = data.data || []
}

const addStep = () => {
  form.value.steps.push({
    step_order: form.value.steps.length + 1,
    step_name: '',
    approver_type: 'dept_leader',
    approver_user_id: null,
    auto_approve: 0
  })
}

const removeStep = (idx) => {
  form.value.steps.splice(idx, 1)
  form.value.steps.forEach((s, i) => {
    s.step_order = i + 1
  })
}

const onApproverTypeChange = (step) => {
  step.approver_user_id = null
}

const openCreate = () => {
  isEdit.value = false
  editingId.value = null
  form.value = {
    name: '',
    dept_id: null,
    level_min: null,
    level_max: null,
    leave_type: null,
    priority: 0,
    status: 1,
    steps: [
      { step_order: 1, step_name: '部门主管审批', approver_type: 'dept_leader', approver_user_id: null, auto_approve: 0 }
    ]
  }
  dialogVisible.value = true
}

const openEdit = (row) => {
  isEdit.value = true
  editingId.value = row.id
  form.value = {
    name: row.name,
    dept_id: row.dept_id,
    level_min: row.level_min,
    level_max: row.level_max,
    leave_type: row.leave_type,
    priority: row.priority,
    status: row.status,
    steps: (row.steps || []).map(s => ({
      step_order: s.step_order,
      step_name: s.step_name,
      approver_type: s.approver_type,
      approver_user_id: s.approver_user_id,
      auto_approve: s.auto_approve
    }))
  }
  dialogVisible.value = true
}

const handleSave = async () => {
  if (!form.value.name) {
    ElMessage.warning('请输入规则名称')
    return
  }
  if (form.value.steps.length === 0) {
    ElMessage.warning('请至少配置一个审批步骤')
    return
  }
  for (const step of form.value.steps) {
    if (!step.step_name) {
      ElMessage.warning('步骤名称不能为空')
      return
    }
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await api.updateApprovalRule(editingId.value, form.value)
      ElMessage.success('规则已更新')
    } else {
      await api.createApprovalRule(form.value)
      ElMessage.success('规则已创建')
    }
    dialogVisible.value = false
    fetchRules()
  } catch (err) {
    ElMessage.error(err?.response?.data?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除规则「${row.name}」吗？`, '确认删除', { type: 'warning' })
    await api.deleteApprovalRule(row.id)
    ElMessage.success('规则已删除')
    fetchRules()
  } catch {}
}

onMounted(() => {
  fetchRules()
  fetchDepartments()
  fetchStaff()
})
</script>

<style scoped>
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.toolbar-title {
  font-size: 16px;
  font-weight: 600;
}
.toolbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.step-item {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 4px;
  font-size: 13px;
}
.step-item:last-child {
  margin-bottom: 0;
}
.step-order {
  color: #999;
  font-size: 12px;
}
.step-name {
  font-weight: 500;
}
.step-approver {
  color: #666;
}
.no-steps {
  color: #999;
}
.step-form-item {
  margin-bottom: 12px;
  padding: 8px;
  background: #f7f8fa;
  border-radius: 4px;
}
.step-form-order {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: #1677ff;
  color: #fff;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 600;
}
.form-hint {
  margin-left: 8px;
  color: #999;
  font-size: 12px;
}
.approver-type-hint {
  color: #666;
  font-size: 13px;
  line-height: 32px;
}
</style>
