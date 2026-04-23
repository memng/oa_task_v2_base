<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">推送通知</div>
      </template>
      <el-form :model="notifyForm" label-width="80px">
        <el-form-item label="标题">
          <el-input v-model="notifyForm.title" />
        </el-form-item>
        <el-form-item label="内容">
          <el-input type="textarea" rows="3" v-model="notifyForm.content" />
        </el-form-item>
        <el-form-item label="范围">
          <el-select v-model="notifyForm.target_type">
            <el-option label="全部人员" value="all" />
            <el-option label="按部门" value="department" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="notifyForm.target_type === 'department'" label="选择部门">
          <el-select v-model="notifyForm.dept_id" placeholder="请选择部门">
            <el-option v-for="dept in departments" :key="dept.id" :label="dept.name" :value="dept.id" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="sendNotification">发送通知</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card style="margin-top: 16px;">
      <template #header>
        <div class="card-header">发布公告</div>
      </template>
      <el-form :model="form" label-width="80px">
        <el-form-item label="标题">
          <el-input v-model="form.title" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category">
            <el-option label="系统" value="system" />
            <el-option label="任务" value="task" />
            <el-option label="通用" value="general" />
          </el-select>
        </el-form-item>
        <el-form-item label="投放范围">
          <el-select v-model="form.target_type" @change="handleTargetTypeChange">
            <el-option label="全员" value="all" />
            <el-option label="指定部门" value="department" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.target_type === 'department'" label="选择部门">
          <el-select
            v-model="form.dept_ids"
            multiple
            placeholder="请选择部门（可多选）"
            style="width: 100%"
          >
            <el-option v-for="dept in departments" :key="dept.id" :label="dept.name" :value="dept.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="内容">
          <el-input type="textarea" rows="4" v-model="form.content" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="publish">发布公告</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card style="margin-top: 16px;">
      <el-table :data="list" stripe>
        <el-table-column prop="title" label="标题" />
        <el-table-column prop="category" label="分类" />
        <el-table-column prop="publish_status" label="状态" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { api } from '../api'
import { sanitizeObject } from '../utils/security'

const list = ref([])
const form = reactive({ title: '', category: 'general', target_type: 'all', dept_ids: [], content: '' })
const notifyForm = reactive({ title: '', content: '', target_type: 'all', dept_id: null })
const departments = ref([])

const fetchAnnouncements = async () => {
  const { data } = await api.announcements()
  list.value = data.data.items || []
}

const fetchDepartments = async () => {
  const { data } = await api.adminDepartments()
  departments.value = data.data.items || []
}

const handleTargetTypeChange = (val) => {
  if (val === 'all') {
    form.dept_ids = []
  }
}

const publish = async () => {
  if (!form.title || !form.content) {
    return ElMessage.error('请填写完整')
  }
  if (form.target_type === 'department' && form.dept_ids.length === 0) {
    return ElMessage.error('请至少选择一个部门')
  }
  const payload = sanitizeObject({
    title: form.title,
    category: form.category,
    content: form.content,
    publish_status: 'published'
  }, ['title', 'content'])
  if (form.target_type === 'department' && form.dept_ids.length > 0) {
    payload.dept_ids = form.dept_ids
  }
  await api.publishAnnouncement(payload)
  ElMessage.success('公告已发布')
  Object.assign(form, { title: '', category: 'general', target_type: 'all', dept_ids: [], content: '' })
  fetchAnnouncements()
}

const sendNotification = async () => {
  if (!notifyForm.title || !notifyForm.content) {
    return ElMessage.error('请填写通知标题和内容')
  }
  if (notifyForm.target_type === 'department' && !notifyForm.dept_id) {
    return ElMessage.error('请选择部门')
  }
  const payload = sanitizeObject({
    title: notifyForm.title,
    content: notifyForm.content,
    target_type: notifyForm.target_type
  }, ['title', 'content'])
  if (notifyForm.target_type === 'department') {
    payload.dept_id = notifyForm.dept_id
  }
  await api.sendNotification(payload)
  ElMessage.success('通知已发送')
  Object.assign(notifyForm, { title: '', content: '', target_type: 'all', dept_id: null })
}

onMounted(() => {
  fetchAnnouncements()
  fetchDepartments()
})
</script>

<style scoped>
.page {
  padding: 24px;
}
.card-header {
  font-weight: 600;
}
</style>
