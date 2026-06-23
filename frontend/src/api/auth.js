// src/api/auth.js
import apiClient from './client'

export function loginApi(email, password) {
  return apiClient.post('/auth/login', { email, password })
}

export function registerApi(payload) {
  return apiClient.post('/auth/register', payload)
}