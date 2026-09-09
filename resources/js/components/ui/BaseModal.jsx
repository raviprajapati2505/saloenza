import React, { Fragment } from 'react'
import { Dialog, Transition } from '@headlessui/react'
import { X } from 'lucide-react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function BaseModal({
  open,
  onClose,
  title,
  size = 'md',
  headerVariant = 'light',
  headerExtra,
  headerClassName,
  bodyClassName,
  children,
  footer,
  className,
}) {
  const sizes = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
    '2xl': 'sm:max-w-5xl',
    '3xl': 'sm:max-w-6xl',
    full: 'sm:max-w-[95vw]',
  }

  const headerStyles = {
    teal: "bg-gradient-to-r from-brand-600 via-brand-600 to-brand-700 border-b border-brand-800/20 text-white shadow-sm",
    dark: "bg-[#0A0F1C] border-b border-slate-800 text-white",
    light: "bg-gradient-to-r from-slate-50 via-brand-50/40 to-slate-50 border-b border-slate-200/90 text-slate-900",
  }

  const isLightTextHeader = headerVariant === 'teal' || headerVariant === 'dark'

  return (
    <Transition.Root show={open} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" />
        </Transition.Child>

        <div className="fixed inset-0 z-10 w-screen overflow-y-auto overscroll-contain">
          <div className="flex min-h-full items-end justify-center sm:items-center sm:p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
              enterTo="opacity-100 translate-y-0 sm:scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 translate-y-0 sm:scale-100"
              leaveTo="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
            >
              <Dialog.Panel
                className={cn(
                  // Mobile: bottom sheet that fills most of the viewport and scrolls inside
                  'relative flex w-full max-h-[94dvh] flex-col overflow-hidden rounded-t-3xl bg-white text-left shadow-2xl border border-slate-200',
                  // Desktop: centered dialog
                  'sm:my-6 sm:max-h-[92vh] sm:rounded-2xl',
                  sizes[size],
                  className,
                )}
              >
                <div className="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-slate-200 sm:hidden" aria-hidden />

                <div className={cn(
                  "flex shrink-0 items-center justify-between gap-3 px-4 py-2.5 sm:px-5 sm:py-3 transition-colors",
                  headerStyles[headerVariant] || headerStyles.light,
                  headerClassName
                )}>
                  <div className="flex items-center gap-3 min-w-0 flex-1">
                    <Dialog.Title as="h3" className={cn("text-base sm:text-lg font-bold leading-tight truncate", isLightTextHeader ? "text-white tracking-wide" : "text-slate-900")}>
                      {title}
                    </Dialog.Title>
                    {headerExtra && <div className="shrink-0">{headerExtra}</div>}
                  </div>

                  <button
                    type="button"
                    className={cn(
                      "flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition-all touch-manipulation focus:outline-none",
                      headerVariant === 'teal'
                        ? "bg-white/20 text-white hover:bg-rose-500 hover:text-white shadow-sm border border-white/25 active:scale-95"
                        : headerVariant === 'dark'
                          ? "bg-slate-800/80 text-slate-300 hover:bg-rose-500 hover:text-white shadow-sm border border-slate-700/60"
                          : "bg-slate-200/70 text-slate-600 hover:bg-rose-500 hover:text-white"
                    )}
                    onClick={onClose}
                    aria-label="Close modal"
                  >
                    <X className="h-6 w-6 stroke-[2.5]" aria-hidden="true" />
                  </button>
                </div>

                <div className={cn("min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-3 sm:px-5 sm:py-4 [-webkit-overflow-scrolling:touch]", bodyClassName)}>
                  {children}
                </div>

                {footer && (
                  <div className="flex shrink-0 flex-col-reverse gap-2 border-t border-slate-200/80 bg-slate-50/90 px-4 py-2.5 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-end sm:gap-3 sm:px-5 sm:py-3 [&>button]:w-full sm:[&>button]:w-auto">
                    {footer}
                  </div>
                )}
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition.Root>
  )
}
