// src/api/dashboard.js
import apiClient from './client'

export function getOrganiserDashboardApi() {
  return apiClient.get('/dashboard/organiser')
}