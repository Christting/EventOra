import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/', name: 'home', component: () => import('@/views/HomeView.vue') },
  { path: '/login', name: 'login', component: () => import('@/views/auth/LoginView.vue') },
  { path: '/register', name: 'register', component: () => import('@/views/auth/RegisterView.vue') },
  {
    path: '/profile',
    name: 'profile',
    component: () => import('@/views/ProfileView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/notifications',
    name: 'notifications',
    component: () => import('@/views/NotificationView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin',
    name: 'admin-dashboard',
    component: () => import('@/views/admin/AdminDashboardView.vue'),
    meta: { requiresAuth: true, role: 'faculty_admin' },
  },
  {
    path: '/admin/approval-queue',
    name: 'approval-queue',
    component: () => import('@/views/admin/ApprovalQueueView.vue'),
    meta: { requiresAuth: true, role: 'faculty_admin' },
  },
  {
    path: '/admin/approval-detail/:id',
    name: 'approval-detail',
    component: () => import('@/views/admin/ApprovalDetailView.vue'),
    meta: { requiresAuth: true, role: 'faculty_admin' },
  },
  {
    path: '/organiser/dashboard',
    name: 'organiser-dashboard',
    component: () => import('@/views/organiser/OrganiserDashboardView.vue'),
    meta: { requiresAuth: true, role: 'organiser' },
  },
  {
    path: '/organiser/create-event',
    name: 'create-event',
    component: () => import('@/views/organiser/CreateEventView.vue'),
    meta: { requiresAuth: true, role: 'organiser' },
  },
  {
    path: '/organiser/event-detail/:id',
    name: 'organiser-event-detail',
    component: () => import('@/views/organiser/OrganiserEventDetailView.vue'),
    meta: { requiresAuth: true, role: 'organiser' },
  },
  {
    path: '/event/:id',
    name: 'event-detail',
    component: () => import('@/views/EventDetailView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  linkActiveClass: 'active',
  linkExactActiveClass: 'active',
})

router.beforeEach((to) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    return '/login'
  }

  if (to.meta.role && authStore.role !== to.meta.role) {
    return '/'
  }
})

export default router