<template>
  <div class="page">
    <el-card>
      <div class="toolbar">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索班次名称"
          clearable
          style="width: 220px; margin-right: 16px"
          @clear="fetchList"
          @keyup.enter="fetchList"
        />
        <el-select
          v-model="searchDeptId"
          placeholder="筛选部门"
          clearable
          style="width: 180px; margin-right: 16px"
          @change="fetchList"
          @clear="fetchList"
        >
          <el-option label="通用班次" :value="null" />
          <el-option v-for="dept in departments" :key="dept.id" :label="dept.name" :value="dept.id" />
        </el-select>
        <el-button type="primary" @click="openDialog()">新增班次</el-button>
      </div>
      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="name" label="班次名称" width="180" />
        <el-table-column label="适用部门" width="140">
          <template #default="{ row }">
            <el-tag v-if="row.dept_id" size="small">{{ row.dept_name || row.dept_id }}</el-tag>
            <el-tag v-else size="small" type="info">通用</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="上班时间" width="100">
          <template #default="{ row }">{{ formatTime(row.start_time) }}</template>
        </el-table-column>
        <el-table-column label="下班时间" width="100">
          <template #default="{ row }">{{ formatTime(row.end_time) }}</template>
        </el-table-column>
        <el-table-column label="休息日" width="160">
          <template #default="{ row }">
            <span v-if="row.saturday_off && row.sunday_off">周六周日</span>
            <span v-else-if="row.saturday_off">周六</span>
            <span v-else-if="row.sunday_off">周日</span>
            <span v-else type="info">无</span>
          </template>
        </el-table-column>
        <el-table-column label="打卡方式" width="100">
          <template #default="{ row }">{{ checkInTypeLabel(row.check_in_type) }}</template>
        </el-table-column>
        <el-table-column label="允许迟到" width="90">
          <template #default="{ row }">{{ row.allow_late_minutes || 0 }}分钟</template>
        </el-table-column>
        <el-table-column label="允许早退" width="90">
          <template #default="{ row }">{{ row.allow_early_minutes || 0 }}分钟</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status ? 'success' : 'info'">{{ row.status ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="openDialog(row)">编辑</el-button>
            <el-popconfirm title="确定删除该班次？" @confirm="remove(row)">
              <template #reference>
                <el-button size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="visible" :title="editingId ? '编辑班次' : '新增班次'" width="640px" :close-on-click-modal="false">
      <el-form :model="form" label-width="140px" :rules="rules" ref="formRef">
        <el-form-item label="班次名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入班次名称，如：标准上班制" />
        </el-form-item>
        <el-form-item label="适用部门" prop="dept_id">
          <el-select v-model="form.dept_id" placeholder="请选择适用部门（不选则为通用班次）" clearable style="width: 100%">
            <el-option label="通用班次（所有部门）" :value="null" />
            <el-option v-for="dept in departments" :key="dept.id" :label="dept.name" :value="dept.id" />
          </el-select>
          <div class="form-tip">
            注意：一个部门只能有一个启用的班次。设置后，该部门成员（user表dept_id字段匹配）将按此班次执行。
          </div>
        </el-form-item>
        <el-divider content-position="left">时间设置</el-divider>
        <el-form-item label="上班时间" prop="start_time">
          <el-time-select
            v-model="form.start_time"
            :picker-options="{ start: '00:00', step: '00:15', end: '23:45' }"
            placeholder="选择上班时间"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="下班时间" prop="end_time">
          <el-time-select
            v-model="form.end_time"
            :picker-options="{ start: '00:00', step: '00:15', end: '23:45' }"
            placeholder="选择下班时间"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="休息日设置">
          <el-checkbox v-model="form.saturday_off" true-label="1" false-label="0">周六休息</el-checkbox>
          <el-checkbox v-model="form.sunday_off" true-label="1" false-label="0" style="margin-left: 24px">周日休息</el-checkbox>
        </el-form-item>
        <el-divider content-position="left">异常阈值设置</el-divider>
        <el-form-item label="允许迟到">
          <el-input-number v-model="form.allow_late_minutes" :min="0" :max="120" />
          <span class="unit">分钟</span>
          <div class="form-tip">此时间内打卡仍算正常</div>
        </el-form-item>
        <el-form-item label="允许早退">
          <el-input-number v-model="form.allow_early_minutes" :min="0" :max="120" />
          <span class="unit">分钟</span>
          <div class="form-tip">此时间内下班仍算正常</div>
        </el-form-item>
        <el-form-item label="旷工阈值">
          <el-input-number v-model="form.absent_after_minutes" :min="30" :max="240" />
          <span class="unit">分钟</span>
          <div class="form-tip">上班后多少分钟未打卡算旷工</div>
        </el-form-item>
        <el-divider content-position="left">打卡方式设置</el-divider>
        <el-form-item label="打卡方式" prop="check_in_type">
          <el-radio-group v-model="form.check_in_type">
            <el-radio value="gps">GPS定位</el-radio>
            <el-radio value="wifi">WiFi</el-radio>
            <el-radio value="both">GPS + WiFi</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="GPS打卡范围" v-if="form.check_in_type !== 'wifi'">
          <el-row :gutter="12">
            <el-col :span="8">
              <el-input v-model="form.gps_lat" placeholder="纬度" @input="trimInput('gps_lat')" />
            </el-col>
            <el-col :span="8">
              <el-input v-model="form.gps_lng" placeholder="经度" @input="trimInput('gps_lng')" />
            </el-col>
            <el-col :span="8">
              <el-input-number v-model="form.gps_radius" :min="50" :max="2000" :controls-position="right" style="width: 100%" />
              <span class="unit">米</span>
            </el-col>
          </el-row>
          <div class="form-tip">设置公司坐标和有效打卡半径（默认200米）</div>
        </el-form-item>
        <el-form-item label="WiFi信息" v-if="form.check_in_type !== 'gps'">
          <el-row :gutter="12">
            <el-col :span="12">
              <el-input v-model="form.wifi_ssid" placeholder="WiFi名称（SSID）" />
            </el-col>
            <el-col :span="12">
              <el-input v-model="form.wifi_bssid" placeholder="WiFi MAC地址（BSSID）" />
            </el-col>
          </el-row>
        </el-form-item>
        <el-divider content-position="left">状态</el-divider>
        <el-form-item label="状态">
          <el-switch v-model="form.status" active-text="启用" inactive-text="停用" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" @click="submit" :loading="submitting">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { api } from '../api'

const list = ref([])
const departments = ref([])
const loading = ref(false)
const visible = ref(false)
const submitting = ref(false)
const editingId = ref(null)
const searchKeyword = ref('')
const searchDeptId = ref(null)
const formRef = ref(null)

const form = reactive({
  name: '',
  dept_id: null,
  start_time: '09:00',
  end_time: '18:00',
  saturday_off: 1,
  sunday_off: 1,
  allow_late_minutes: 10,
  allow_early_minutes: 10,
  absent_after_minutes: 60,
  check_in_type: 'gps',
  gps_lat: '',
  gps_lng: '',
  gps_radius: 200,
  wifi_ssid: '',
  wifi_bssid: '',
  status: 1
})

const rules = {
  name: [{ required: true, message: '请输入班次名称', trigger: 'blur' }],
  start_time: [{ required: true, message: '请选择上班时间', trigger: 'change' }],
  end_time: [{ required: true, message: '请选择下班时间', trigger: 'change' }]
}

const checkInTypeOptions = [
  { label: 'GPS定位', value: 'gps' },
  { label: 'WiFi', value: 'wifi' },
  { label: 'GPS + WiFi', value: 'both' }
]

const checkInTypeLabel = (value) => {
  const target = checkInTypeOptions.find((item) => item.value === value)
  return target ? target.label : value
}

const formatTime = (time) => {
  if (!time) return '-'
  if (typeof time === 'string' && time.length >= 5) {
    return time.substring(0, 5)
  }
  return time
}

const trimInput = (field) => {
  if (form[field]) {
    form[field] = String(form[field]).trim()
  }
}

const fetchDepartments = async () => {
  try {
    const { data } = await api.adminDepartments()
    departments.value = (data.data.items || []).filter((d) => d.status === 1)
  } catch (e) {
    console.error('fetch departments failed', e)
  }
}

const fetchList = async () => {
  loading.value = true
  try {
    const params = {}
    if (searchKeyword.value) {
      params.keyword = searchKeyword.value
    }
    if (searchDeptId.value !== undefined && searchDeptId.value !== '') {
      params.dept_id = searchDeptId.value
    }
    const { data } = await api.adminShiftSchedules(params)
    list.value = data.data.items || []
  } finally {
    loading.value = false
  }
}

const openDialog = (row = null) => {
  if (row) {
    editingId.value = row.id
    form.name = row.name
    form.dept_id = row.dept_id
    form.start_time = formatTime(row.start_time)
    form.end_time = formatTime(row.end_time)
    form.saturday_off = Number(row.saturday_off) || 1
    form.sunday_off = Number(row.sunday_off) || 1
    form.allow_late_minutes = Number(row.allow_late_minutes) || 0
    form.allow_early_minutes = Number(row.allow_early_minutes) || 0
    form.absent_after_minutes = Number(row.absent_after_minutes) || 60
    form.check_in_type = row.check_in_type
    form.gps_lat = row.gps_lat || ''
    form.gps_lng = row.gps_lng || ''
    form.gps_radius = Number(row.gps_radius) || 200
    form.wifi_ssid = row.wifi_ssid || ''
    form.wifi_bssid = row.wifi_bssid || ''
    form.status = Number(row.status)
  } else {
    editingId.value = null
    form.name = ''
    form.dept_id = null
    form.start_time = '09:00'
    form.end_time = '18:00'
    form.saturday_off = 1
    form.sunday_off = 1
    form.allow_late_minutes = 10
    form.allow_early_minutes = 10
    form.absent_after_minutes = 60
    form.check_in_type = 'gps'
    form.gps_lat = ''
    form.gps_lng = ''
    form.gps_radius = 200
    form.wifi_ssid = ''
    form.wifi_bssid = ''
    form.status = 1
  }
  visible.value = true
}

const submit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    if (!form.start_time || !form.end_time) {
      return ElMessage.error('请设置上班时间和下班时间')
    }
    
    submitting.value = true
    const payload = {
      name: form.name,
      dept_id: form.dept_id,
      start_time: form.start_time,
      end_time: form.end_time,
      saturday_off: Number(form.saturday_off),
      sunday_off: Number(form.sunday_off),
      allow_late_minutes: Number(form.allow_late_minutes) || 0,
      allow_early_minutes: Number(form.allow_early_minutes) || 0,
      absent_after_minutes: Number(form.absent_after_minutes) || 60,
      check_in_type: form.check_in_type,
      gps_lat: form.gps_lat || null,
      gps_lng: form.gps_lng || null,
      gps_radius: Number(form.gps_radius) || 200,
      wifi_ssid: form.wifi_ssid || null,
      wifi_bssid: form.wifi_bssid || null,
      status: Number(form.status)
    }
    try {
      if (editingId.value) {
        await api.updateShiftSchedule(editingId.value, payload)
        ElMessage.success('班次已更新')
      } else {
        await api.createShiftSchedule(payload)
        ElMessage.success('班次已创建')
      }
      visible.value = false
      fetchList()
    } finally {
      submitting.value = false
    }
  })
}

const remove = async (row) => {
  await api.deleteShiftSchedule(row.id)
  ElMessage.success('已删除')
  fetchList()
}

onMounted(() => {
  fetchDepartments()
  fetchList()
})
</script>

<style scoped>
.page {
  padding: 24px;
}
.toolbar {
  margin-bottom: 16px;
  display: flex;
  justify-content: flex-end;
  align-items: center;
}
.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}
.unit {
  margin-left: 8px;
  color: #606266;
}
</style>
