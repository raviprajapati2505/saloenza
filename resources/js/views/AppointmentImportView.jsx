import React, { useEffect, useMemo, useState } from 'react'
import { CheckCircle2, Download, FileSpreadsheet, Loader2, Upload } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import { api } from '../lib/api'
import { parseList } from '../lib/apiHelpers'
import { useAuthStore } from '../stores/auth'

const STEPS = ['Salon', 'Upload', 'Review', 'Import']

function apiError(error, fallback) {
  const data = error?.response?.data
  if (data?.errors) {
    const messages = Object.values(data.errors).flat().filter(Boolean)
    if (messages.length > 0) return messages.join(' ')
  }
  return data?.message || fallback
}

export default function AppointmentImportView() {
  const auth = useAuthStore()
  const isSuperAdmin = Boolean(auth.isSystemAdmin || auth.grantsAllPermissions)
  const [step, setStep] = useState(0)
  const [salons, setSalons] = useState([])
  const [salonSearch, setSalonSearch] = useState('')
  const [saloonId, setSaloonId] = useState(isSuperAdmin ? '' : String(auth.tenant?.id || ''))
  const [loadingSalons, setLoadingSalons] = useState(false)
  const [file, setFile] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [preview, setPreview] = useState(null)
  const [progress, setProgress] = useState({ processed: 0, total: 0 })
  const [inserted, setInserted] = useState([])
  const [importErrors, setImportErrors] = useState([])
  const [finished, setFinished] = useState(false)

  const selectedSalon = useMemo(() => {
    if (!isSuperAdmin) {
      return { id: auth.tenant?.id, name: auth.tenant?.name || 'Your salon' }
    }
    return salons.find((salon) => String(salon.id) === String(saloonId)) || null
  }, [auth.tenant?.id, auth.tenant?.name, isSuperAdmin, salons, saloonId])

  useEffect(() => {
    if (!isSuperAdmin) return undefined

    let cancelled = false
    const timer = setTimeout(async () => {
      setLoadingSalons(true)
      try {
        const response = await api.get('/v1/saloons', {
          params: { search: salonSearch, per_page: 50 },
        })
        if (!cancelled) setSalons(parseList(response, 'saloons'))
      } catch (loadError) {
        if (!cancelled) setError(apiError(loadError, 'Could not load salons.'))
      } finally {
        if (!cancelled) setLoadingSalons(false)
      }
    }, 250)

    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [isSuperAdmin, salonSearch])

  const downloadSample = async () => {
    setError('')
    try {
      const response = await api.get('/v1/appointment-imports/sample.csv', { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = 'saloenza-appointment-import-sample.csv'
      link.click()
      URL.revokeObjectURL(url)
    } catch (downloadError) {
      setError(apiError(downloadError, 'Could not download the sample CSV.'))
    }
  }

  const runPreview = async () => {
    if (!file) {
      setError('Choose a CSV file.')
      return
    }
    setBusy(true)
    setError('')
    try {
      const form = new FormData()
      form.append('file', file)
      if (saloonId) form.append('saloon_id', saloonId)
      const response = await api.post('/v1/appointment-imports/preview', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setPreview(response.data?.data || null)
      setStep(2)
    } catch (previewError) {
      setError(apiError(previewError, 'Could not check this file.'))
    } finally {
      setBusy(false)
    }
  }

  const runImport = async () => {
    if (!preview?.token) return
    setStep(3)
    setBusy(true)
    setError('')
    setFinished(false)
    setInserted([])
    setImportErrors([])
    setProgress({ processed: 0, total: preview.valid_count || 0 })

    try {
      let done = false
      let guard = 0
      while (!done && guard < 500) {
        guard += 1
        const response = await api.post('/v1/appointment-imports/commit', {
          token: preview.token,
          saloon_id: saloonId || undefined,
          limit: 20,
        })
        const batch = response.data?.data || {}
        const results = batch.results || []
        setInserted((current) => [
          ...current,
          ...results.filter((row) => row.inserted),
        ])
        setImportErrors((current) => [
          ...current,
          ...results
            .filter((row) => !row.inserted)
            .flatMap((row) => (row.rows || [null]).map((line) => ({
              row: line,
              appointment_code: row.appointment_code,
              message: row.message || 'This visit could not be imported.',
            }))),
        ])
        setProgress({
          processed: batch.processed || 0,
          total: batch.total || preview.valid_count || 0,
        })
        done = Boolean(batch.done)
      }
      setFinished(true)
    } catch (importError) {
      setError(apiError(importError, 'Import stopped before every visit was saved.'))
      setFinished(true)
    } finally {
      setBusy(false)
    }
  }

  const reset = () => {
    setStep(0)
    setFile(null)
    setPreview(null)
    setInserted([])
    setImportErrors([])
    setFinished(false)
    setProgress({ processed: 0, total: 0 })
    setError('')
  }

  const percent = progress.total > 0
    ? Math.min(100, Math.round((progress.processed / progress.total) * 100))
    : 0

  const reviewErrors = preview?.errors || []

  return (
    <div className="mx-auto max-w-5xl">
      <PageHeader
        title="Import appointments"
        subtitle="Bring in visits from the previous system. Branches, staff, and services must already be set up in Saloenza."
        breadcrumbs={[{ label: 'Operations' }, { label: 'Import appointments' }]}
      />

      <ol className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {STEPS.map((label, index) => {
          const complete = index < step || (index === 3 && finished)
          const current = index === step && !finished
          return (
            <li
              key={label}
              className={`rounded-2xl border px-4 py-3 ${
                complete
                  ? 'border-brand-200 bg-brand-50 text-brand-800'
                  : current
                    ? 'border-brand-500 bg-white text-slate-900'
                    : 'border-slate-200 bg-white text-slate-500'
              }`}
            >
              <div className="text-xs font-semibold uppercase tracking-wide">Step {index + 1}</div>
              <div className="mt-1 text-sm font-medium">{label}</div>
            </li>
          )
        })}
      </ol>

      {error && (
        <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      )}

      {step === 0 && (
        <section className="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 className="text-lg font-semibold text-slate-900">Choose the salon</h2>
          <p className="mt-1 text-sm text-slate-500">
            Appointments are imported into one salon. Staff, branches, and the service menu for that salon are used to match each row.
          </p>
          {isSuperAdmin ? (
            <div className="mt-5 space-y-3">
              <label className="block text-sm font-medium text-slate-700" htmlFor="salon-search">
                Search salons
              </label>
              <input
                id="salon-search"
                value={salonSearch}
                onChange={(event) => setSalonSearch(event.target.value)}
                placeholder="Salon name"
                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
              />
              <label className="block text-sm font-medium text-slate-700" htmlFor="salon-select">
                Salon
              </label>
              <select
                id="salon-select"
                value={saloonId}
                onChange={(event) => setSaloonId(event.target.value)}
                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
              >
                <option value="">{loadingSalons ? 'Loading salons…' : 'Select a salon'}</option>
                {salons.map((salon) => (
                  <option key={salon.id} value={salon.id}>{salon.name}</option>
                ))}
              </select>
            </div>
          ) : (
            <div className="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
              Importing into <span className="font-semibold">{selectedSalon?.name}</span>
            </div>
          )}
          <div className="mt-6 flex justify-end">
            <BaseButton
              type="button"
              disabled={isSuperAdmin && !saloonId}
              onClick={() => {
                setError('')
                setStep(1)
              }}
            >
              Continue
            </BaseButton>
          </div>
        </section>
      )}

      {step === 1 && (
        <section className="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 className="text-lg font-semibold text-slate-900">Upload the appointment CSV</h2>
          <p className="mt-1 text-sm text-slate-500">
            One row per service. Rows that share an appointment code become one visit. Customer name and phone on those rows create or match the customer.
          </p>
          <div className="mt-5 flex flex-wrap gap-3">
            <BaseButton type="button" variant="secondary" leftIcon={Download} onClick={downloadSample}>
              Download sample CSV
            </BaseButton>
          </div>
          <label className="mt-5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
            <FileSpreadsheet className="h-8 w-8 text-slate-400" />
            <span className="mt-3 text-sm font-medium text-slate-800">
              {file ? file.name : 'Choose a CSV file'}
            </span>
            <span className="mt-1 text-xs text-slate-500">branch name, staff phone, and service name must already exist</span>
            <input
              type="file"
              accept=".csv,text/csv"
              className="sr-only"
              onChange={(event) => setFile(event.target.files?.[0] || null)}
            />
          </label>
          <div className="mt-6 flex justify-between">
            <BaseButton type="button" variant="ghost" onClick={() => setStep(0)}>Back</BaseButton>
            <BaseButton type="button" leftIcon={Upload} loading={busy} disabled={!file} onClick={runPreview}>
              Check file
            </BaseButton>
          </div>
        </section>
      )}

      {step === 2 && preview && (
        <section className="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 className="text-lg font-semibold text-slate-900">Review before import</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-3">
            <Stat label="Rows" value={preview.total_rows} />
            <Stat label="Ready to import" value={preview.valid_count} />
            <Stat label="Row errors" value={reviewErrors.length} />
          </div>
          <ErrorTable errors={reviewErrors} />
          <div className="mt-6 flex justify-between">
            <BaseButton type="button" variant="ghost" onClick={() => setStep(1)}>Back</BaseButton>
            <BaseButton type="button" disabled={!preview.token || preview.valid_count < 1} onClick={runImport}>
              Import {preview.valid_count} visit{preview.valid_count === 1 ? '' : 's'}
            </BaseButton>
          </div>
        </section>
      )}

      {step === 3 && (
        <section className="rounded-2xl border border-slate-200 bg-white p-6">
          <h2 className="text-lg font-semibold text-slate-900">
            {finished ? 'Import finished' : 'Importing visits'}
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            {progress.processed} of {progress.total} visits processed
            {selectedSalon?.name ? ` for ${selectedSalon.name}` : ''}.
          </p>
          <div className="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
            <div className="h-full rounded-full bg-brand-500 transition-all" style={{ width: `${percent}%` }} />
          </div>
          <div className="mt-2 flex items-center gap-2 text-sm text-slate-600">
            {busy && <Loader2 className="h-4 w-4 animate-spin" />}
            {finished && <CheckCircle2 className="h-4 w-4 text-emerald-600" />}
            <span>{percent}%</span>
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            <Stat label="Inserted" value={inserted.length} />
            <Stat label="Failed during save" value={importErrors.length} />
          </div>
          <ErrorTable errors={[...reviewErrors, ...importErrors]} />
          {finished && (
            <div className="mt-6 flex justify-end">
              <BaseButton type="button" onClick={reset}>Import another file</BaseButton>
            </div>
          )}
        </section>
      )}
    </div>
  )
}

function Stat({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-200 px-4 py-3">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-1 text-2xl font-semibold text-slate-900">{value ?? 0}</div>
    </div>
  )
}

function ErrorTable({ errors }) {
  if (!errors?.length) {
    return (
      <p className="mt-4 text-sm text-slate-500">No row errors.</p>
    )
  }

  return (
    <div className="mt-4 max-h-80 overflow-auto rounded-xl border border-slate-200">
      <table className="min-w-full text-left text-sm">
        <thead className="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th className="px-3 py-2 font-medium">Row</th>
            <th className="px-3 py-2 font-medium">Appointment</th>
            <th className="px-3 py-2 font-medium">Message</th>
          </tr>
        </thead>
        <tbody>
          {errors.map((item, index) => (
            <tr key={`${item.row}-${item.appointment_code}-${index}`} className="border-t border-slate-100">
              <td className="px-3 py-2 text-slate-700">{item.row || '—'}</td>
              <td className="px-3 py-2 text-slate-700">{item.appointment_code || '—'}</td>
              <td className="px-3 py-2 text-rose-700">{item.message}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
