import axios from 'axios'

export const ADMIN_TOKEN_KEY = 'OA_ADMIN_TOKEN'
export const API_BASE_URL = import.meta.env.VITE_API_BASE || 'http://localhost:80/api'
export const ASSET_BASE_URL = (import.meta.env.VITE_ASSET_BASE || API_BASE_URL).replace(/\/api\/?$/, '')
const client = axios.create({
  baseURL: API_BASE_URL,
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem(ADMIN_TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem(ADMIN_TOKEN_KEY)
      localStorage.removeItem('OA_ADMIN_PROFILE')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export const api = {
  login(data) {
    return client.post('/admin/auth/login', data)
  },
  summary() {
    return client.get('/dashboard/summary')
  },
  orders(params) {
    return client.get('/orders', { params })
  },
  orderDetail(id) {
    return client.get(`/orders/${id}`)
  },
  updateOrder(id, data) {
    return client.put(`/orders/${id}`, data)
  },
  orderProgress(id) {
    return client.get(`/orders/${id}/progress`)
  },
  tasks(params) {
    return client.get('/tasks', { params })
  },
  taskDetail(id) {
    return client.get(`/tasks/${id}`)
  },
  createTask(data) {
    return client.post('/tasks', data)
  },
  announcements() {
    return client.get('/announcements')
  },
  lookupStaff(params = {}) {
    return client.get('/lookups/staff', { params })
  },
  currencies(params = {}) {
    return client.get('/currencies', { params })
  },
  voltages(params = {}) {
    return client.get('/voltages', { params })
  },
  publishAnnouncement(data) {
    return client.post('/announcements', data)
  },
  sendNotification(data) {
    return client.post('/notifications', data)
  },
  adminDepartments() {
    return client.get('/admin/departments')
  },
  createDepartment(data) {
    return client.post('/admin/departments', data)
  },
  updateDepartment(id, data) {
    return client.put(`/admin/departments/${id}`, data)
  },
  deleteDepartment(id) {
    return client.delete(`/admin/departments/${id}`)
  },
  adminUsers(params = {}) {
    return client.get('/admin/users', { params })
  },
  approveUser(id) {
    return client.post(`/admin/users/${id}/approve`)
  },
  rejectUser(id, data) {
    return client.post(`/admin/users/${id}/reject`, data)
  },
  adminSuppliers(params = {}) {
    return client.get('/admin/suppliers', { params })
  },
  createSupplier(data) {
    return client.post('/admin/suppliers', data)
  },
  updateSupplier(id, data) {
    return client.put(`/admin/suppliers/${id}`, data)
  },
  deleteSupplier(id) {
    return client.delete(`/admin/suppliers/${id}`)
  },
  adminCurrencies(params = {}) {
    return client.get('/admin/currencies', { params })
  },
  createCurrency(data) {
    return client.post('/admin/currencies', data)
  },
  updateCurrency(id, data) {
    return client.put(`/admin/currencies/${id}`, data)
  },
  deleteCurrency(id) {
    return client.delete(`/admin/currencies/${id}`)
  },
  adminVoltages(params = {}) {
    return client.get('/admin/voltages', { params })
  },
  createVoltage(data) {
    return client.post('/admin/voltages', data)
  },
  updateVoltage(id, data) {
    return client.put(`/admin/voltages/${id}`, data)
  },
  deleteVoltage(id) {
    return client.delete(`/admin/voltages/${id}`)
  },
  orderTaskSettings() {
    return client.get('/admin/order-task-settings')
  },
  saveOrderTaskSettings(data) {
    return client.post('/admin/order-task-settings', data)
  },
  reimburseList(params = {}) {
    return client.get('/admin/reimburse', { params })
  },
  updateReimburseStatus(id, data) {
    return client.post(`/admin/reimburse/${id}/status`, data)
  },
  leaveRequests(params = {}) {
    return client.get('/admin/leave', { params })
  },
  updateLeaveStatus(id, data) {
    return client.post(`/admin/leave/${id}/status`, data)
  },
  inventory(params = {}) {
    return client.get('/admin/inventory', { params })
  },
  createInventory(data) {
    return client.post('/admin/inventory', data)
  },
  updateInventory(id, data) {
    return client.put(`/admin/inventory/${id}`, data)
  },
  inventoryPublic(params = {}) {
    return client.get('/inventory', { params })
  },
  consumeInventory(id, data) {
    return client.post(`/inventory/${id}/consume`, data)
  },
  adminShiftSchedules(params = {}) {
    return client.get('/admin/shift-schedules', { params })
  },
  shiftScheduleDetail(id) {
    return client.get(`/admin/shift-schedules/${id}`)
  },
  createShiftSchedule(data) {
    return client.post('/admin/shift-schedules', data)
  },
  updateShiftSchedule(id, data) {
    return client.put(`/admin/shift-schedules/${id}`, data)
  },
  deleteShiftSchedule(id) {
    return client.delete(`/admin/shift-schedules/${id}`)
  }
}
