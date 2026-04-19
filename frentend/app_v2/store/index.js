import { reactive, computed } from 'vue'
import { saveToken, clearToken } from '../utils/auth'

const createMessageSummary = () => ({
  total: 0,
  notifications: {
    personal: 0,
    announcements: 0
  },
  chats: {
    total: 0,
    direct: 0,
    group: 0
  }
})

const state = reactive({
  token: '',
  profile: null,
  pendingTasks: [],
  notifications: 0,
  messageSummary: createMessageSummary()
})

export default {
  state,
  setToken(token) {
    state.token = token
    saveToken(token)
  },
  clearToken() {
    state.token = ''
    clearToken()
  },
  setProfile(profile) {
    state.profile = profile
  },
  setPendingTasks(tasks) {
    state.pendingTasks = tasks
  },
  setNotificationCount(count) {
    state.notifications = count
    state.messageSummary.total = count
  },
  setMessageSummary(summary = {}) {
    const base = createMessageSummary()
    const notificationSummary = summary.notifications || {}
    const chatSummary = summary.chats || {}
    const merged = {
      total: typeof summary.total === 'number' ? summary.total : base.total,
      notifications: {
        personal: typeof notificationSummary.personal === 'number' ? notificationSummary.personal : base.notifications.personal,
        announcements: typeof notificationSummary.announcements === 'number' ? notificationSummary.announcements : base.notifications.announcements
      },
      chats: {
        total: typeof chatSummary.total === 'number' ? chatSummary.total : base.chats.total,
        direct: typeof chatSummary.direct === 'number' ? chatSummary.direct : base.chats.direct,
        group: typeof chatSummary.group === 'number' ? chatSummary.group : base.chats.group
      }
    }
    state.messageSummary = merged
    state.notifications = merged.total
  },
  isLoggedIn: computed(() => !!state.token)
}
