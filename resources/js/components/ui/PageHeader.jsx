import React from 'react'
import { Link } from 'react-router-dom'
import { ChevronRight } from 'lucide-react'

export default function PageHeader({
  title,
  subtitle,
  breadcrumbs = [],
  actions,
  compact = false,
}) {
  return (
    <div className={compact ? 'md:flex md:items-center md:justify-between' : 'mb-8 md:flex md:items-center md:justify-between'}>
      <div className="min-w-0 flex-1">
        {breadcrumbs.length > 0 && (
          <nav className={`flex ${compact ? 'mb-1' : 'mb-2'}`} aria-label="Breadcrumb">
            <ol role="list" className="flex items-center space-x-1">
              {breadcrumbs.map((crumb, index) => (
                <li key={crumb.label}>
                  <div className="flex items-center">
                    {index > 0 && (
                      <ChevronRight className="h-4 w-4 flex-shrink-0 text-slate-400 mr-1" aria-hidden="true" />
                    )}
                    {crumb.to ? (
                      <Link
                        to={crumb.to}
                        className="text-xs font-medium text-slate-500 hover:text-slate-700 transition-colors"
                      >
                        {crumb.label}
                      </Link>
                    ) : (
                      <span className="text-xs font-medium text-slate-400 cursor-default">
                        {crumb.label}
                      </span>
                    )}
                  </div>
                </li>
              ))}
            </ol>
          </nav>
        )}

        <h2 className={compact
          ? 'text-xl font-bold leading-6 text-slate-900 sm:truncate sm:text-2xl sm:tracking-tight'
          : 'text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight'}
        >
          {title}
        </h2>
        {subtitle && (
          <p className={compact ? 'mt-0.5 text-xs text-slate-500 line-clamp-1' : 'mt-1.5 text-sm text-slate-500'}>
            {subtitle}
          </p>
        )}
      </div>

      {actions && (
        <div className={`flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center md:ml-4 md:w-auto ${compact ? 'mt-2 md:mt-0' : 'mt-4 md:mt-0'}`}>
          {actions}
        </div>
      )}
    </div>
  )
}
