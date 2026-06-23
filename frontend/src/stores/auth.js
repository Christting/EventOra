import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { loginApi, registerApi } from '@/api/auth'
import { updateProfileApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('eventora_token') || null)
  const user = ref(JSON.parse(localStorage.getItem('eventora_user') || 'null'))

  const isLoggedIn = computed(() => !!token.value)
  const role = computed(() => user.value?.role || null)
  const isAdmin = computed(() => role.value === 'faculty_admin')
  const isOrganiser = computed(() => role.value === 'organiser')

  // Persists the token + user object from a successful login/register
  // response into both reactive state (for the current session) and
  // localStorage (so a page refresh doesn't log the user out).
  function setSession(data) {
    token.value = data.token
    user.value = data.user

    localStorage.setItem('eventora_token', data.token)
    localStorage.setItem('eventora_user', JSON.stringify(data.user))
  }

  // Now async and hits the real backend. Role is NOT passed in anymore -
  // the backend looks up the account and returns whatever role it
  // actually has; the frontend just displays it.
  async function login(email, password) {
    try {
      const response = await loginApi(email, password)
      setSession(response.data.data)
      return { success: true }
    } catch (error) {
      // error.response exists for any HTTP error status (401, 422, etc.) -
      // .error.message is the human-readable string our AuthController
      // always includes per the project's error response convention.
      const message = error.response?.data?.error?.message || 'Login failed. Please try again.'
      return { success: false, message }
    }
  }

  // payload must contain: name, email, password, role ('attendee' or
  // 'organiser' only - the backend rejects anything else server-side).
  async function register(payload) {
    try {
      const response = await registerApi(payload)
      setSession(response.data.data)
      return { success: true }
    } catch (error) {
      const message = error.response?.data?.error?.message || 'Registration failed. Please try again.'
      return { success: false, message }
    }
  }

  function updateProfile(updatedUser) {
  user.value = {
    ...user.value,
    ...updatedUser
  }

  localStorage.setItem('eventora_user', JSON.stringify(user.value))
}

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('eventora_token')
    localStorage.removeItem('eventora_user')
  }

  return {
    token, user, isLoggedIn, role, isAdmin, isOrganiser,
    login, register, logout, updateProfile
  }
})