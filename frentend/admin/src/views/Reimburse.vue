<template>
  <el-card>
    <div class="toolbar">
      <el-select v-model="status" placeholder="全部状态" clearable @change="fetchList">
        <el-option label="全部" value=""></el-option>
        <el-option label="审批中" value="pending"></el-option>
        <el-option label="已通过" value="approved"></el-option>
        <el-option label="已驳回" value="rejected"></el-option>
      </el-select>
      <el-button type="primary" @click="fetchList">刷新</el-button>
    </div>
    <el-table :data="list" border stripe>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="user_name" label="申请人" />
      <el-table-column prop="type" label="类型" />
      <el-table-column prop="amount" label="金额">
        <template #default="{ row }">
          <span>¥{{ row.amount.toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态">
        <template #default="{ row }">
          <el-tag :type="statusType(row.status)">{{ statusText(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="提交时间" width="180" />
      <el-table-column label="票据" min-width="200">
        <template #default="{ row }">
          <div v-if="row.receipts && row.receipts.length > 0" class="receipt-list">
            <a 
              v-for="(receipt, idx) in row.receipts" 
              :key="idx"
              :href="absoluteUrl(receipt.url)" 
              target="_blank"
              class="receipt-link"
            >
              <el-icon v-if="isImageFile(receipt.file_name)" class="receipt-icon"><Picture /></el-icon>
              <el-icon v-else class="receipt-icon"><Document /></el-icon>
              <span class="receipt-name">{{ receipt.file_name }}</span>
            </a>
          </div>
          <a v-else-if="row.receipt_url" :href="absoluteUrl(row.receipt_url)" target="_blank">
            <el-icon><Document /></el-icon>
            <span>{{ row.receipt_name || '查看' }}</span>
          </a>
          <span v-else>—</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200">
        <template #default="{ row }">
          <el-button v-if="row.status === 'pending'" size="small" type="success" @click="updateStatus(row, 'approved')">通过</el-button>
          <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="openReject(row)">驳回</el-button>
          <span v-else>—</span>
        </template>
      </el-table-column>
    </el-table>
  </el-card>
  <el-dialog v-model="rejectDialog.visible" title="驳回原因" width="400px">
    <el-input v-model="rejectDialog.remark" type="textarea" placeholder="请输入驳回说明" />
    <template #footer>
      <el-button @click="rejectDialog.visible = false">取消</el-button>
      <el-button type="primary" @click="confirmReject">确认</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api, ASSET_BASE_URL } from '../api'
import { Picture, Document } from '@element-plus/icons-vue'

const list = ref([])
const status = ref('')
const rejectDialog = reactive({
  visible: false,
  targetId: null,
  remark: ''
})

const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp']

const isImageFile = (fileName) => {
  if (!fileName) return false
  const ext = fileName.split('.').pop().toLowerCase()
  return IMAGE_EXTENSIONS.includes(ext)
}

const fetchList = async () => {
  const { data } = await api.reimburseList({ status: status.value || undefined })
  list.value = data.data.items || []
}

const statusText = (value) => {
  if (value === 'approved') return '已通过'
  if (value === 'rejected') return '已驳回'
  return '审批中'
}

const statusType = (value) => {
  if (value === 'approved') return 'success'
  if (value === 'rejected') return 'danger'
  return 'warning'
}

const absoluteUrl = (url) => {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url
  return `${ASSET_BASE_URL}${url}`
}

const updateStatus = async (row, nextStatus, remark = '') => {
  await api.updateReimburseStatus(row.id, { status: nextStatus, remark })
  fetchList()
}

const openReject = (row) => {
  rejectDialog.targetId = row.id
  rejectDialog.remark = ''
  rejectDialog.visible = true
}

const confirmReject = async () => {
  if (!rejectDialog.targetId) return
  await api.updateReimburseStatus(rejectDialog.targetId, { status: 'rejected', remark: rejectDialog.remark })
  rejectDialog.visible = false
  fetchList()
}

onMounted(fetchList)
</script>

<style scoped>
.toolbar {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
}
.toolbar .el-select {
  width: 200px;
}

.receipt-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.receipt-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #409eff;
  text-decoration: none;
  font-size: 14px;
}

.receipt-link:hover {
  color: #66b1ff;
  text-decoration: underline;
}

.receipt-icon {
  font-size: 16px;
}

.receipt-name {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
