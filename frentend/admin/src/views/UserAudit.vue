<template>
  <div class="page">
    <el-card>
      <div class="toolbar">
        <el-radio-group v-model="status" @change="fetchList">
          <el-radio-button v-for="item in statusOptions" :key="item.value" :label="item.value">{{ item.label }}</el-radio-button>
        </el-radio-group>
      </div>
      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="name" label="姓名" />
        <el-table-column prop="mobile" label="手机号" />
        <el-table-column prop="dept_name" label="部门" />
        <el-table-column prop="created_at" label="注册时间" width="180" />
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="拒绝原因" min-width="200">
          <template #default="{ row }">
            <span v-if="row.reject_reason">{{ row.reject_reason }}</span>
            <span v-else-if="row.status === 'disabled' || row.status === 'rejected'" class="text-muted">无</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="280">
          <template #default="{ row }">
            <el-button size="small" @click="viewDetail(row)">
              详情
            </el-button>
            <el-button v-if="row.status === 'pending'" size="small" type="success" @click="approve(row)">通过</el-button>
            <el-button
              v-if="row.status === 'pending'"
              size="small"
              type="danger"
              @click="reject(row)"
            >
              驳回
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="detailVisible"
      title="注册详情"
      width="650px"
      @close="resetDetailState"
    >
      <div v-if="detailLoading" class="empty-detail">
        <el-empty description="加载中..." />
      </div>
      <div v-else-if="detailItem" class="detail-content">
        <div v-if="detailItem.status === 'rejected' || detailItem.reject_reason" class="reject-section">
          <div class="section-title reject-title">
            <el-icon class="warn-icon"><Warning /></el-icon>
            驳回原因
            <el-button v-if="!isEditingRejectReason" size="small" type="primary" link @click="startEditRejectReason">
              <el-icon><Edit /></el-icon>
              编辑
            </el-button>
            <template v-else>
              <el-button size="small" type="primary" link @click="saveRejectReason">
                <el-icon><Check /></el-icon>
                保存
              </el-button>
              <el-button size="small" type="info" link @click="cancelEditRejectReason">
                取消
              </el-button>
            </template>
          </div>
          <div v-if="!isEditingRejectReason" class="reject-reason-box">
            {{ detailItem.reject_reason }}
          </div>
          <div v-else>
            <el-input
              v-model="editingRejectReason"
              type="textarea"
              :rows="3"
              maxlength="500"
              show-word-limit
              placeholder="请输入驳回原因"
            />
          </div>
          <div class="hint-box">
            <el-icon><InfoFilled /></el-icon>
            <span>用户可通过移动端登录后查看驳回原因，并重新编辑资料提交审核</span>
          </div>
        </div>

        <div class="section-title">基本信息</div>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="姓名">{{ detailItem.name }}</el-descriptions-item>
          <el-descriptions-item label="手机号">{{ detailItem.mobile }}</el-descriptions-item>
          <el-descriptions-item label="身份证号">{{ detailItem.id_card }}</el-descriptions-item>
          <el-descriptions-item label="部门">{{ detailItem.dept?.name }}</el-descriptions-item>
        </el-descriptions>

        <div class="section-title">银行信息</div>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="银行卡号">{{ detailItem.bank_card_no }}</el-descriptions-item>
          <el-descriptions-item label="持卡人">{{ detailItem.bank_account_name }}</el-descriptions-item>
          <el-descriptions-item label="开户银行" :span="2">{{ detailItem.bank_name }}</el-descriptions-item>
        </el-descriptions>

        <div class="section-title">联系信息</div>
        <el-descriptions :column="1" border>
          <el-descriptions-item label="地址">{{ detailItem.address }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-dialog>

    <el-dialog
      v-model="rejectDialogVisible"
      title="驳回注册"
      width="500px"
    >
      <div v-if="rejectingUser" class="reject-form">
        <div class="reject-user-info">
          <span>用户：</span>
          <strong>{{ rejectingUser.name }} ({{ rejectingUser.mobile }})</strong>
        </div>
        <div class="template-section">
          <div class="section-label">快速选择模板（点击即可填入下方）</div>
          <div v-if="templates.length > 0" class="template-list">
            <div
              v-for="tpl in templates"
              :key="tpl.id"
              class="template-item"
              @click="applyTemplate(tpl)"
            >
              {{ tpl.content }}
            </div>
          </div>
          <el-empty v-else description="暂无可用模板" :image-size="60" />
        </div>
        <el-form-item label="驳回原因" required>
          <el-input
            v-model="rejectReason"
            type="textarea"
            :rows="4"
            maxlength="500"
            show-word-limit
            placeholder="请输入或选择驳回原因（最多500个字符）"
          />
        </el-form-item>
      </div>
      <template #footer>
        <el-button @click="rejectDialogVisible = false">取消</el-button>
        <el-button type="danger" @click="confirmReject" :disabled="!rejectReason.trim()">
          确认驳回
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Warning, InfoFilled, Edit, Check } from '@element-plus/icons-vue'
import { api } from '../api'

const status = ref('pending')
const list = ref([])
const loading = ref(false)
const detailVisible = ref(false)
const detailLoading = ref(false)
const detailItem = ref(null)
const isEditingRejectReason = ref(false)
const editingRejectReason = ref('')
const rejectDialogVisible = ref(false)
const rejectingUser = ref(null)
const rejectReason = ref('')
const templates = ref([])

const statusOptions = [
  { label: '待审核', value: 'pending' },
  { label: '已通过', value: 'active' },
  { label: '已驳回', value: 'rejected' },
  { label: '已禁用', value: 'disabled' }
]

const fetchList = async () => {
  loading.value = true
  try {
    const { data } = await api.adminUsers({ status: status.value })
    list.value = data.data.items || []
  } finally {
    loading.value = false
  }
}

const fetchTemplates = async () => {
  try {
    const { data } = await api.activeRejectTemplates()
    templates.value = data.data.items || []
  } catch (e) {
    console.warn('Failed to fetch reject templates:', e)
    templates.value = []
  }
}

const resetDetailState = () => {
  detailItem.value = null
  isEditingRejectReason.value = false
  editingRejectReason.value = ''
}

const viewDetail = async (row) => {
  resetDetailState()
  detailLoading.value = true
  detailVisible.value = true
  try {
    const { data } = await api.adminUserDetail(row.id)
    detailItem.value = data.data.item
    console.log('Detail loaded:', detailItem.value)
  } catch (e) {
    console.error('Failed to load detail:', e)
    ElMessage.error('获取详情失败')
    detailVisible.value = false
  } finally {
    detailLoading.value = false
  }
}

const startEditRejectReason = () => {
  editingRejectReason.value = detailItem.value.reject_reason || ''
  isEditingRejectReason.value = true
}

const cancelEditRejectReason = () => {
  isEditingRejectReason.value = false
  editingRejectReason.value = ''
}

const saveRejectReason = async () => {
  const trimmed = editingRejectReason.value.trim()
  if (!trimmed) {
    ElMessage.warning('驳回原因不能为空')
    return
  }
  if (trimmed.length > 500) {
    ElMessage.warning('驳回原因不能超过500个字符')
    return
  }

  try {
    await api.rejectUser(detailItem.value.id, { reject_reason: trimmed })
    detailItem.value.reject_reason = trimmed
    isEditingRejectReason.value = false
    ElMessage.success('驳回原因已更新')
    fetchList()
  } catch (e) {
    ElMessage.error('更新失败')
  }
}

const approve = async (row) => {
  await api.approveUser(row.id)
  ElMessage.success('已通过审核')
  fetchList()
}

const reject = async (row) => {
  rejectingUser.value = row
  rejectReason.value = ''
  if (templates.value.length === 0) {
    await fetchTemplates()
  }
  rejectDialogVisible.value = true
}

const applyTemplate = (tpl) => {
  rejectReason.value = tpl.content
}

const confirmReject = async () => {
  const trimmed = rejectReason.value.trim()
  if (!trimmed) {
    ElMessage.warning('请填写驳回原因')
    return
  }
  if (trimmed.length > 500) {
    ElMessage.warning('驳回原因不能超过500个字符')
    return
  }

  await api.rejectUser(rejectingUser.value.id, { reject_reason: trimmed })
  ElMessage.success('已驳回该注册')
  rejectDialogVisible.value = false
  fetchList()
}

const statusLabel = (value) => {
  const map = {
    pending: '待审核',
    active: '已通过',
    rejected: '已驳回',
    disabled: '已禁用'
  }
  return map[value] || value
}

const statusType = (value) => {
  const map = {
    pending: 'warning',
    active: 'success',
    rejected: 'danger',
    disabled: 'info'
  }
  return map[value] || 'info'
}

onMounted(() => {
  fetchList()
  fetchTemplates()
})
</script>

<style scoped>
.page {
  padding: 24px;
}
.toolbar {
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
}
.section-title {
  margin: 20px 0 12px;
  font-weight: 600;
  color: #303133;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.section-title:first-of-type {
  margin-top: 0;
}
.reject-title {
  gap: 6px;
  color: #f56c6c;
}
.warn-icon {
  font-size: 18px;
}
.reject-section {
  padding: 16px;
  background: #fef0f0;
  border: 1px solid #fbc4c4;
  border-radius: 8px;
  margin-bottom: 16px;
}
.reject-reason-box {
  padding: 12px 14px;
  background: #fff;
  border-radius: 4px;
  color: #606266;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
}
.hint-box {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin-top: 12px;
  padding: 8px 12px;
  background: #ecf5ff;
  border-radius: 4px;
  color: #409eff;
  font-size: 13px;
}
.hint-box .el-icon {
  font-size: 16px;
  margin-top: 1px;
  flex-shrink: 0;
}
.empty-detail {
  padding: 40px 0;
}
.reject-user-info {
  margin-bottom: 16px;
  color: #606266;
}
.template-section {
  margin-bottom: 16px;
}
.section-label {
  font-size: 14px;
  color: #606266;
  margin-bottom: 8px;
}
.template-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.template-item {
  padding: 10px 14px;
  background: #f5f7fa;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  white-space: normal;
  word-break: break-word;
  line-height: 1.5;
  color: #606266;
}
.template-item:hover {
  background: #ecf5ff;
  border-color: #d9ecff;
  color: #409eff;
}
.text-muted {
  color: #909399;
}
</style>
