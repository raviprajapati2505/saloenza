import React, { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Menu, Transition } from '@headlessui/react'
import { Bell, CheckCheck, Loader2 } from 'lucide-react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { useAuthStore } from '../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'
import {
  fetchAdminNotifications,
  markAdminNotificationRead,
  markAllAdminNotificationsRead,
} from '../../services/notificationService.js'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

function formatRelativeTime(value) {
  if (!value) return ''
  const date = new Date(value)
  const diffMs = Date.now() - date.getTime()
  const minutes = Math.floor(diffMs / 60000)
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

export default function NotificationBell() {
  const auth = useAuthStore()
  const router = useNavigate()
  const [items, setItems] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [loading, setLoading] = useState(false)
  const [markingAll, setMarkingAll] = useState(false)
  const pollRef = useRef(null)

  const canView = Boolean(
    auth.isAuthenticated
    && (
      auth.grantsAllPermissions
      || auth.isSystemAdmin
      || auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES)
      || auth.canPlatform(PLATFORM_PERMISSIONS.UPGRADE_REQUESTS)
      || auth.canPlatform(PLATFORM_PERMISSIONS.ONBOARDING)
    ),
  )

  const load = useCallback(async ({ silent = false } = {}) => {
    if (!canView) return
    if (!silent) setLoading(true)
    try {
      const payload = await fetchAdminNotifications({ per_page: 15 })
      setItems(payload.notifications || [])
      setUnreadCount(Number(payload.unread_count || 0))
    } catch {
      // Keep last known state; bell is non-blocking.
    } finally {
      if (!silent) setLoading(false)
    }
  }, [canView])

  useEffect(() => {
    if (!canView) {
      setItems([])
      setUnreadCount(0)
      return undefined
    }

    void load()
    pollRef.current = window.setInterval(() => {
      void load({ silent: true })
    }, 60000)

    return () => {
      if (pollRef.current) window.clearInterval(pollRef.current)
    }
  }, [canView, load])

  if (!canView) return null

  const openNotification = async (notification) => {
    if (!notification?.id) return

    try {
      if (!notification.read_at) {
        const payload = await markAdminNotificationRead(notification.id)
        setUnreadCount(Number(payload.unread_count || 0))
        setItems((prev) => prev.map((row) => (
          row.id === notification.id
            ? { ...row, read_at: payload.notification?.read_at || new Date().toISOString() }
            : row
        )))
      }
    } catch {
      // Still navigate even if mark-read fails.
    }

    if (notification.action_url) {
      router(notification.action_url)
    }
  }

  const handleMarkAll = async (event) => {
    event.preventDefault()
    event.stopPropagation()
    if (markingAll || unreadCount < 1) return
    setMarkingAll(true)
    try {
      await markAllAdminNotificationsRead()
      setUnreadCount(0)
      setItems((prev) => prev.map((row) => ({
        ...row,
        read_at: row.read_at || new Date().toISOString(),
      })))
    } finally {
      setMarkingAll(false)
    }
  }

  return (
    <Menu as="div" className="relative">
      <Menu.Button
        type="button"
        onClick={() => void load({ silent: true })}
        className="relative p-2 rounded-full text-slate-300 hover:bg-slate-800/80 hover:text-white md:text-slate-500 md:hover:bg-slate-100/80 md:hover:text-slate-900 transition-colors group"
      >
        <Bell className="w-5 h-5 group-hover:animate-wiggle" />
        {unreadCount > 0 ? (
          <span className="absolute -top-0.5 -right-0.5 inline-flex min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        ) : null}
      </Menu.Button>

      <Transition
        enter="transition ease-out duration-100"
        enterFrom="transform opacity-0 scale-95"
        enterTo="transform opacity-100 scale-100"
        leave="transition ease-in duration-75"
        leaveFrom="transform opacity-100 scale-100"
        leaveTo="transform opacity-0 scale-95"
      >
        <Menu.Items className="absolute right-0 mt-2 w-[22rem] origin-top-right overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xl focus:outline-none">
          <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div>
              <p className="text-sm font-semibold text-slate-900">Notifications</p>
              <p className="text-xs text-slate-500">{unreadCount} unread</p>
            </div>
            <button
              type="button"
              onClick={handleMarkAll}
              disabled={markingAll || unreadCount < 1}
              className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand-700 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {markingAll ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <CheckCheck className="h-3.5 w-3.5" />}
              Mark all read
            </button>
          </div>

          <div className="max-h-96 overflow-y-auto">
            {loading && items.length === 0 ? (
              <div className="flex items-center justify-center gap-2 px-4 py-10 text-sm text-slate-500">
                <Loader2 className="h-4 w-4 animate-spin" /> Loading...
              </div>
            ) : null}

            {!loading && items.length === 0 ? (
              <div className="px-4 py-10 text-center text-sm text-slate-500">
                No notifications yet.
              </div>
            ) : null}

            {items.map((notification) => (
              <Menu.Item key={notification.id}>
                {({ active }) => (
                  <button
                    type="button"
                    onClick={() => void openNotification(notification)}
                    className={cn(
                      'flex w-full flex-col gap-1 border-b border-slate-50 px-4 py-3 text-left transition-colors last:border-b-0',
                      active ? 'bg-slate-50' : 'bg-white',
                      !notification.read_at ? 'bg-brand-50/40' : '',
                    )}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <p className={cn('text-sm text-slate-900', !notification.read_at && 'font-semibold')}>
                        {notification.title}
                      </p>
                      {!notification.read_at ? (
                        <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500" />
                      ) : null}
                    </div>
                    <p className="text-xs leading-relaxed text-slate-600">{notification.body}</p>
                    <p className="text-[11px] text-slate-400">{formatRelativeTime(notification.created_at)}</p>
                  </button>
                )}
              </Menu.Item>
            ))}
          </div>

          <div className="border-t border-slate-100 px-4 py-2.5">
            <Link
              to="/admin/onboarding"
              className="text-xs font-medium text-slate-600 hover:text-brand-700"
            >
              Open platform console
            </Link>
          </div>
        </Menu.Items>
      </Transition>
    </Menu>
  )
}
