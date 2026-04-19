<template>
  <div class="page" v-loading="loading">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>订单任务分配设置</span>
          <span class="hint">为不同任务预设负责人，定时任务会自动分配</span>
        </div>
      </template>
      <el-form label-width="140px" class="settings-form">
        <el-form-item label="铭牌制作">
          <el-select v-model="form.nameplate" multiple filterable placeholder="选择铭牌制作人">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="机器验收">
          <el-select v-model="form.acceptance" multiple filterable placeholder="选择验收人">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="打包贴唛头">
          <el-select v-model="form.packaging" multiple filterable placeholder="选择打包人">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="发货">
          <el-select v-model="form.shipment" multiple filterable placeholder="选择发货人">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="文件上传">
          <el-select v-model="form.document" multiple filterable placeholder="选择资料上传人">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
        <el-divider />
        <el-form-item label="费用-业务员">
          <el-select v-model="form.fee_sales" multiple filterable placeholder="选择业务员">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="费用-工厂发货人">
          <el-select v-model="form.fee_factory" multiple filterable placeholder="选择工厂发货人">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="费用-财务">
          <el-select v-model="form.fee_finance" multiple filterable placeholder="选择财务人员">
            <el-option v-for="staff in staffOptions" :key="staff.id" :label="staffLabel(staff)" :value="staff.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <div class="actions">
        <el-button @click="load">重置</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存设置</el-button>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../api'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const saving = ref(false)
const staffOptions = ref([])
const form = reactive({
  nameplate: [],
  acceptance: [],
  packaging: [],
  shipment: [],
  document: [],
  fee_sales: [],
  fee_factory: [],
  fee_finance: []
})

const staffLabel = (staff) => `${staff.name}${staff.dept_name ? `（${staff.dept_name}）` : ''}`

const loadStaff = async () => {
  const { data } = await api.lookupStaff()
  staffOptions.value = data?.data?.items || []
}

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.orderTaskSettings()
    const items = data?.data?.items || {}
    Object.keys(form).forEach((key) => {
      form[key] = (items[key] || []).map((item) => item.id)
    })
  } finally {
    loading.value = false
  }
}

const save = async () => {
  saving.value = true
  try {
    await api.saveOrderTaskSettings({ ...form })
    ElMessage.success('配置已保存')
  } catch (error) {
    console.error(error)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadStaff()
  load()
})
</script>

<style scoped>
.page {
  padding: 24px;
}
.settings-form {
  max-width: 780px;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.hint {
  color: #909399;
  font-size: 13px;
}
</style>
