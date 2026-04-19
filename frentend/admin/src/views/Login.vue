<template>
  <div class="login-page">
    <el-card class="login-card">
      <div class="title">OA管理后台</div>
      <el-form :model="form" label-width="0">
        <el-form-item>
          <el-input v-model="form.username" placeholder="请输入账号" />
        </el-form-item>
        <el-form-item>
          <el-input v-model="form.password" placeholder="请输入密码" show-password />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="submit" :loading="loading" @click="submit">登录</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ADMIN_TOKEN_KEY, api } from '../api'

const router = useRouter()
const form = reactive({
  username: 'admin',
  password: '123456'
})
const loading = ref(false)

const submit = async () => {
  if (!form.username || !form.password) {
    ElMessage.error('请填写账号和密码')
    return
  }
  loading.value = true
  try {
    const { data } = await api.login({
      username: form.username,
      password: form.password
    })
    const payload = data.data
    localStorage.setItem(ADMIN_TOKEN_KEY, payload.token)
    localStorage.setItem('OA_ADMIN_PROFILE', JSON.stringify(payload.profile || {}))
    ElMessage.success('登录成功')
    router.push('/dashboard')
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f2f5;
}
.login-card {
  width: 360px;
}
.title {
  font-size: 22px;
  text-align: center;
  margin-bottom: 16px;
  font-weight: 600;
}
.submit {
  width: 100%;
}
</style>

