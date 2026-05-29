import store from '../store'

const BASE_URL = uni.getStorageSync('API_BASE') || 'http://localhost:80/api'
const ASSET_BASE = BASE_URL.replace(/\/api\/?$/, '')

export function resolveAssetUrl(url) {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url
  const prefix = ASSET_BASE || BASE_URL
  if (!prefix) return url
  if (url.startsWith('/')) {
    return `${prefix}${url}`
  }
  return `${prefix}/${url}`
}

export function setBaseUrl(url) {
  uni.setStorageSync('API_BASE', url)
}

export function request(options) {
  const token = store.state.token
  const silent = options.silentError === true
  return new Promise((resolve, reject) => {
    const headers = options.header || {}
    if (!headers['Content-Type']) {
      headers['Content-Type'] = 'application/json'
    }
    if (token) {
      headers.Authorization = `Bearer ${token}`
    }
    uni.request({
      url: `${BASE_URL}${options.url}`,
      method: options.method || 'GET',
      data: options.data || {},
      header: headers,
      success: (res) => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          resolve(res.data.data)
        } else {
          if (!silent) {
            uni.showToast({ title: res.data.message || '请求失败', icon: 'none' })
          }
          reject(res.data || {})
        }
      },
      fail: (err) => {
        if (!silent) {
          uni.showToast({ title: '网络异常', icon: 'none' })
        }
        reject(err || {})
      }
    })
  })
}

export function uploadFile(filePath, formData = {}) {
  const token = store.state.token
  return new Promise((resolve, reject) => {
    uni.uploadFile({
      url: `${BASE_URL}/upload`,
      filePath,
      name: 'file',
      formData,
      header: {
        Authorization: token ? `Bearer ${token}` : ''
      },
      success: (res) => {
        try {
          const data = JSON.parse(res.data || '{}')
          if (res.statusCode >= 200 && res.statusCode < 300) {
            const payload = data.data || {}
            if (payload.url) {
              payload.url = resolveAssetUrl(payload.url)
            }
            resolve(payload)
          } else {
            uni.showToast({ title: data.message || '上传失败', icon: 'none' })
            reject(data)
          }
        } catch (error) {
          reject(error)
        }
      },
      fail: (err) => {
        uni.showToast({ title: '上传失败', icon: 'none' })
        reject(err)
      }
    })
  })
}

export function uploadReceipt(filePath, formData = {}) {
  const token = store.state.token
  return new Promise((resolve, reject) => {
    uni.uploadFile({
      url: `${BASE_URL}/upload/receipt`,
      filePath,
      name: 'file',
      formData,
      header: {
        Authorization: token ? `Bearer ${token}` : ''
      },
      success: (res) => {
        try {
          const data = JSON.parse(res.data || '{}')
          if (res.statusCode >= 200 && res.statusCode < 300) {
            const payload = data.data || {}
            if (payload.url) {
              payload.url = resolveAssetUrl(payload.url)
            }
            resolve(payload)
          } else {
            uni.showToast({ title: data.message || '上传失败', icon: 'none' })
            reject(data)
          }
        } catch (error) {
          reject(error)
        }
      },
      fail: (err) => {
        uni.showToast({ title: '上传失败', icon: 'none' })
        reject(err)
      }
    })
  })
}

export const api = {
  login(payload) {
    return request({ url: '/auth/login', method: 'POST', data: payload })
  },
  register(payload) {
    return request({ url: '/auth/register', method: 'POST', data: payload })
  },
  profile() {
    return request({ url: '/auth/profile' })
  },
  updateProfile(payload = {}) {
    return request({ url: '/auth/profile', method: 'PUT', data: payload })
  },
  logout() {
    return request({ url: '/auth/logout', method: 'POST' })
  },
  departments() {
    return request({ url: '/departments' })
  },
  lookupStaff(params = {}) {
    return request({ url: '/lookups/staff', data: params })
  },
  currencies() {
    return request({ url: '/currencies' })
  },
  voltages() {
    return request({ url: '/voltages' })
  },
  reimburseList(params = {}) {
    return request({ url: '/reimburse', data: params })
  },
  reimburseDetail(id) {
    return request({ url: `/reimburse/${id}` })
  },
  createReimburse(payload) {
    return request({ url: '/reimburse', method: 'POST', data: payload })
  },
  reimbursePreCheck(payload) {
    return request({ url: '/reimburse/pre-check', method: 'POST', data: payload })
  },
  reimburseBudgetStatus(params = {}) {
    return request({ url: '/reimburse/budget-status', data: params })
  },
  leaveList(params = {}) {
    return request({ url: '/leave', data: params })
  },
  leaveDetail(id) {
    return request({ url: `/leave/${id}` })
  },
  createLeave(payload) {
    return request({ url: '/leave', method: 'POST', data: payload })
  },
  cancelLeave(id, payload = {}) {
    return request({ url: `/leave/${id}/cancel`, method: 'POST', data: payload })
  },
  approveLeave(id, payload = {}) {
    return request({ url: `/leave/${id}/approve`, method: 'POST', data: payload })
  },
  leavePendingApprovals() {
    return request({ url: '/leave/pending-approvals' })
  },
  lookups() {
    return request({ url: '/lookups' })
  },
  suppliers(params = {}) {
    return request({ url: '/suppliers', data: params })
  },
  inventory(params = {}) {
    return request({ url: '/inventory', data: params })
  },
  consumeInventory(id, payload = {}) {
    return request({ url: `/inventory/${id}/consume`, method: 'POST', data: payload })
  },
  summary() {
    return request({ url: '/dashboard/summary' })
  },
  factoryBoard() {
    return request({ url: '/dashboard/factory-board' })
  },
  todos(params = {}) {
    return request({ url: '/dashboard/todos', data: params })
  },
  createFactoryOrder(payload) {
    return request({ url: '/dashboard/factory-board', method: 'POST', data: payload })
  },
  orderList(params = {}) {
    return request({ url: '/orders', data: params })
  },
  orderDetail(id) {
    return request({ url: `/orders/${id}` })
  },
  updateOrder(id, payload) {
    return request({ url: `/orders/${id}`, method: 'PUT', data: payload })
  },
  orderProgress(id) {
    return request({ url: `/orders/${id}/progress` })
  },
  orderStages() {
    return request({ url: '/orders/stages' })
  },
  orderStageTransition(id, payload) {
    return request({ url: `/orders/${id}/stage-transition`, method: 'POST', data: payload })
  },
  orderTaskDelayReason(orderId, taskId, payload) {
    return request({ url: `/orders/${orderId}/tasks/${taskId}/delay-reason`, method: 'POST', data: payload })
  },
  orderStageHistory(id) {
    return request({ url: `/orders/${id}/stage-history` })
  },
  cancelOrder(id) {
    return request({ url: `/orders/${id}/cancel`, method: 'POST' })
  },
  createOrder(payload) {
    return request({ url: '/orders', method: 'POST', data: payload })
  },
  addOrderDocument(id, payload) {
    return request({ url: `/orders/${id}/documents`, method: 'POST', data: payload })
  },
  taskList(params = {}) {
    return request({ url: '/tasks', data: params })
  },
  taskDetail(id) {
    return request({ url: `/tasks/${id}` })
  },
  createTask(payload) {
    return request({ url: '/tasks', method: 'POST', data: payload })
  },
  assignTask(id, payload) {
    return request({ url: `/tasks/${id}/assign`, method: 'POST', data: payload })
  },
  updateTaskStatus(id, payload) {
    return request({ url: `/tasks/${id}/status`, method: 'POST', data: payload })
  },
  updateProcurementTask(id, payload) {
    return request({ url: `/tasks/${id}/procurement`, method: 'POST', data: payload })
  },
  urgeTask(id) {
    return request({ url: `/tasks/${id}/urge`, method: 'POST' })
  },
  batchAssignTasks(payload) {
    return request({ url: '/tasks/batch/assign', method: 'POST', data: payload })
  },
  batchUrgeTasks(payload) {
    return request({ url: '/tasks/batch/urge', method: 'POST', data: payload })
  },
  copyTask(id, options = {}) {
    return request({ url: `/tasks/${id}/copy`, method: 'POST', silentError: options.silentError })
  },
  followTask(id) {
    return request({ url: `/tasks/${id}/follow`, method: 'POST', silentError: true })
  },
  unfollowTask(id) {
    return request({ url: `/tasks/${id}/unfollow`, method: 'POST', silentError: true })
  },
  taskFollowStatus(id) {
    return request({ url: `/tasks/${id}/follow-status` })
  },
  followedTasks(params = {}) {
    return request({ url: '/tasks/followed', data: params })
  },
  taskComments(taskId, params = {}) {
    return request({ url: `/tasks/${taskId}/comments`, data: params })
  },
  createTaskComment(taskId, payload) {
    return request({ url: `/tasks/${taskId}/comments`, method: 'POST', data: payload })
  },
  deleteTaskComment(commentId) {
    return request({ url: `/tasks/comments/${commentId}`, method: 'DELETE' })
  },
  taskTemplates(params = {}) {
    return request({ url: '/task-templates', data: params })
  },
  taskTemplateDetail(id) {
    return request({ url: `/task-templates/${id}` })
  },
  createTaskTemplate(payload) {
    return request({ url: '/task-templates', method: 'POST', data: payload })
  },
  updateTaskTemplate(id, payload) {
    return request({ url: `/task-templates/${id}`, method: 'PUT', data: payload })
  },
  deleteTaskTemplate(id) {
    return request({ url: `/task-templates/${id}`, method: 'DELETE' })
  },
  announcements(params = {}) {
    return request({ url: '/announcements', data: params })
  },
  announcementDetail(id) {
    return request({ url: `/announcements/${id}` })
  },
  announcementMarkRead(id) {
    return request({ url: `/announcements/${id}/read`, method: 'POST' })
  },
  notifications(params = {}) {
    return request({ url: '/notifications', data: params })
  },
  notificationMarkRead(id) {
    return request({ url: `/notifications/${id}/read`, method: 'POST' })
  },
  notificationReadAll() {
    return request({ url: '/notifications/read-all', method: 'POST' })
  },
  notificationReadGroup(payload = {}) {
    return request({ url: '/notifications/read-group', method: 'POST', data: payload })
  },
  notificationReadSummary() {
    return request({ url: '/notifications/read-summary' })
  },
  messageUnread() {
    return request({ url: '/messages/unread-count' })
  },
  chatConversations(params = {}) {
    return request({ url: '/chat/conversations', data: params })
  },
  chatCreate(payload) {
    return request({ url: '/chat/rooms', method: 'POST', data: payload })
  },
  chatMessages(id, params = {}) {
    return request({ url: `/chat/rooms/${id}/messages`, data: params })
  },
  chatSend(id, payload) {
    return request({ url: `/chat/rooms/${id}/messages`, method: 'POST', data: payload })
  },
  chatMarkRead(id, payload = {}) {
    return request({ url: `/chat/rooms/${id}/read`, method: 'POST', data: payload })
  },
  chatMessageReaders(roomId, messageId) {
    return request({ url: `/chat/rooms/${roomId}/messages/${messageId}/readers` })
  },
  chatPin(roomId) {
    return request({ url: `/chat/rooms/${roomId}/pin`, method: 'POST' })
  },
  chatMute(roomId, payload = {}) {
    return request({ url: `/chat/rooms/${roomId}/mute`, method: 'POST', data: payload })
  },
  chatClearUnread(roomId) {
    return request({ url: `/chat/rooms/${roomId}/clear-unread`, method: 'POST' })
  },
  intentOrders(params = {}) {
    return request({ url: '/intent-orders', data: params })
  },
  intentOrderDetail(id) {
    return request({ url: `/intent-orders/${id}` })
  },
  intentOrderStages() {
    return request({ url: '/intent-orders/stages' })
  },
  intentOrderTransition(id, payload) {
    return request({ url: `/intent-orders/${id}/transition`, method: 'POST', data: payload })
  },
  intentOrderAvailableTransitions(id) {
    return request({ url: `/intent-orders/${id}/available-transitions` })
  },
  updateIntentOrder(id, payload) {
    return request({ url: `/intent-orders/${id}`, method: 'PUT', data: payload })
  }
}
