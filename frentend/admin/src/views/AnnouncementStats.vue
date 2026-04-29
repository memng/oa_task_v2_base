<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">公告已读统计</div>
      </template>
      
      <div class="stats-cards">
        <div class="stat-card">
          <div class="stat-label">总用户数</div>
          <div class="stat-value">{{ summary.total_users || 0 }}</div>
        </div>
        <div class="stat-card read">
          <div class="stat-label">已读人数</div>
          <div class="stat-value">{{ summary.read_users || 0 }}</div>
        </div>
        <div class="stat-card unread">
          <div class="stat-label">未读人数</div>
          <div class="stat-value">{{ summary.unread_users || 0 }}</div>
        </div>
        <div class="stat-card rate">
          <div class="stat-label">已读率</div>
          <div class="stat-value">{{ summary.read_rate || 0 }}%</div>
        </div>
      </div>
    </el-card>

    <el-card style="margin-top: 16px;">
      <template #header>
        <div class="card-header">按部门分布</div>
      </template>
      <el-table :data="summary.by_department || []" stripe>
        <el-table-column prop="dept_name" label="部门" />
        <el-table-column prop="total_users" label="总人数" align="center" />
        <el-table-column prop="read_users" label="已读人数" align="center">
          <template #default="{ row }">
            <span style="color: #67c23a;">{{ row.read_users }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="unread_users" label="未读人数" align="center">
          <template #default="{ row }">
            <span style="color: #f56c6c;">{{ row.unread_users }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="read_rate" label="已读率" align="center">
          <template #default="{ row }">
            <el-progress 
              :percentage="row.read_rate" 
              :stroke-width="18"
              :color="getProgressColor(row.read_rate)"
            />
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card style="margin-top: 16px;">
      <template #header>
        <div class="card-header">
          <span>公告列表</span>
          <span style="margin-left: 8px; color: #999; font-weight: normal; font-size: 12px;">
            （点击查看单条公告的详细统计）
          </span>
        </div>
      </template>
      <el-table :data="announcementList" stripe>
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="title" label="公告标题" min-width="200">
          <template #default="{ row }">
            <el-button type="text" @click="viewDetail(row)">
              {{ row.title }}
            </el-button>
          </template>
        </el-table-column>
        <el-table-column prop="category" label="分类" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ getCategoryLabel(row.category) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="publish_status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.publish_status)" size="small">
              {{ getStatusLabel(row.publish_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="read_count" label="已读/总人数" width="120" align="center">
          <template #default="{ row }">
            <span style="color: #67c23a;">{{ row.read_count || 0 }}</span>
            <span style="color: #999;">/</span>
            <span>{{ row.total_users || summary.total_users || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="read_rate" label="已读率" width="180">
          <template #default="{ row }">
            <el-progress 
              :percentage="row.read_rate || 0" 
              :stroke-width="14"
              :color="getProgressColor(row.read_rate)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="published_at" label="发布时间" width="170" />
      </el-table>
    </el-card>

    <el-dialog
      v-model="detailVisible"
      title="公告详细统计"
      width="800px"
      :close-on-click-modal="false"
    >
      <template v-if="currentDetail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="公告标题">
            {{ currentDetail.announcement?.title }}
          </el-descriptions-item>
          <el-descriptions-item label="发布状态">
            <el-tag :type="getStatusType(currentDetail.announcement?.publish_status)" size="small">
              {{ getStatusLabel(currentDetail.announcement?.publish_status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="分类">
            {{ getCategoryLabel(currentDetail.announcement?.category) }}
          </el-descriptions-item>
          <el-descriptions-item label="发布时间">
            {{ currentDetail.announcement?.published_at }}
          </el-descriptions-item>
        </el-descriptions>

        <div style="margin-top: 16px;">
          <el-divider>统计概览</el-divider>
          <div class="stats-cards">
            <div class="stat-card">
              <div class="stat-label">总用户数</div>
              <div class="stat-value">{{ currentDetail.summary?.total_users || 0 }}</div>
            </div>
            <div class="stat-card read">
              <div class="stat-label">已读人数</div>
              <div class="stat-value">{{ currentDetail.summary?.read_users || 0 }}</div>
            </div>
            <div class="stat-card unread">
              <div class="stat-label">未读人数</div>
              <div class="stat-value">{{ currentDetail.summary?.unread_users || 0 }}</div>
            </div>
            <div class="stat-card rate">
              <div class="stat-label">已读率</div>
              <div class="stat-value">{{ currentDetail.summary?.read_rate || 0 }}%</div>
            </div>
          </div>
        </div>

        <div style="margin-top: 16px;">
          <el-divider>部门分布</el-divider>
          <el-table :data="currentDetail.by_department || []" stripe size="small">
            <el-table-column prop="dept_name" label="部门" />
            <el-table-column prop="total_users" label="总人数" align="center" width="80" />
            <el-table-column prop="read_users" label="已读" align="center" width="60">
              <template #default="{ row }">
                <span style="color: #67c23a;">{{ row.read_users }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="unread_users" label="未读" align="center" width="60">
              <template #default="{ row }">
                <span style="color: #f56c6c;">{{ row.unread_users }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="read_rate" label="已读率" width="150">
              <template #default="{ row }">
                <el-progress 
                  :percentage="row.read_rate" 
                  :stroke-width="12"
                  :color="getProgressColor(row.read_rate)"
                />
              </template>
            </el-table-column>
          </el-table>
        </div>

        <el-tabs v-model="activeTab" style="margin-top: 16px;">
          <el-tab-pane label="已读用户" name="read">
            <el-table :data="currentDetail.read_users || []" stripe size="small" max-height="300">
              <el-table-column prop="user_id" label="ID" width="80" align="center" />
              <el-table-column prop="user_name" label="姓名" />
              <el-table-column prop="dept_name" label="部门" />
              <el-table-column prop="read_at" label="阅读时间" width="180" />
            </el-table>
          </el-tab-pane>
          <el-tab-pane label="未读用户" name="unread">
            <el-table :data="currentDetail.unread_users || []" stripe size="small" max-height="300">
              <el-table-column prop="user_id" label="ID" width="80" align="center" />
              <el-table-column prop="user_name" label="姓名" />
              <el-table-column prop="dept_name" label="部门" />
            </el-table>
          </el-tab-pane>
        </el-tabs>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api'
import { ElMessage } from 'element-plus'

const summary = ref({})
const announcementList = ref([])
const detailVisible = ref(false)
const currentDetail = ref(null)
const activeTab = ref('read')

const getProgressColor = (percentage) => {
  if (percentage >= 80) return '#67c23a'
  if (percentage >= 50) return '#e6a23c'
  return '#f56c6c'
}

const getCategoryLabel = (category) => {
  const map = {
    factory: '工厂',
    sales: '销售',
    general: '通用',
    system: '系统',
    task: '任务'
  }
  return map[category] || category
}

const getStatusLabel = (status) => {
  const map = {
    draft: '草稿',
    published: '已发布',
    archived: '已归档'
  }
  return map[status] || status
}

const getStatusType = (status) => {
  const map = {
    draft: 'info',
    published: 'success',
    archived: 'warning'
  }
  return map[status] || 'info'
}

const fetchSummary = async () => {
  try {
    const { data } = await api.announcementStatsSummary()
    summary.value = data.data || {}
  } catch (e) {
    console.error('获取统计数据失败:', e)
    ElMessage.error('获取统计数据失败')
  }
}

const fetchAnnouncementList = async () => {
  try {
    const { data } = await api.announcementStatsList()
    announcementList.value = data.data?.items || []
  } catch (e) {
    console.error('获取公告列表失败:', e)
    ElMessage.error('获取公告列表失败')
  }
}

const viewDetail = async (row) => {
  try {
    const { data } = await api.announcementStatsDetail(row.id)
    currentDetail.value = data.data
    activeTab.value = 'read'
    detailVisible.value = true
  } catch (e) {
    console.error('获取公告详情失败:', e)
    ElMessage.error('获取公告详情失败')
  }
}

onMounted(() => {
  fetchSummary()
  fetchAnnouncementList()
})
</script>

<style scoped>
.page {
  padding: 24px;
}
.card-header {
  font-weight: 600;
}
.stats-cards {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.stat-card {
  flex: 1;
  min-width: 150px;
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
  text-align: center;
}
.stat-card.read {
  background: #f0f9eb;
}
.stat-card.unread {
  background: #fef0f0;
}
.stat-card.rate {
  background: #ecf5ff;
}
.stat-label {
  color: #909399;
  font-size: 14px;
  margin-bottom: 8px;
}
.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: #303133;
}
.stat-card.read .stat-value {
  color: #67c23a;
}
.stat-card.unread .stat-value {
  color: #f56c6c;
}
.stat-card.rate .stat-value {
  color: #409eff;
}
</style>
