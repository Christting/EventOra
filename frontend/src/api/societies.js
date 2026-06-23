// src/api/societies.js
import apiClient from './client'

export function getMySocietiesApi() {
  return apiClient.get('/societies/mine')
}