import { useEffect, useRef, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
  Key, Plus, Trash2, Copy, Eye, EyeOff, ToggleLeft, ToggleRight,
  Loader2, CheckCircle2, AlertCircle, RefreshCw, Plug, ExternalLink,
  ChevronDown, ChevronUp, Gauge, ScrollText, ChevronLeft, ChevronRight,
} from 'lucide-react'
import { api } from '@/lib/api'

// ─── Types ─────────────────────────────────────────────────────────────────

interface ApiKey {
  id:             number
  name:           string
  key_prefix:     string
  is_active:      boolean | 0 | 1
  max_concurrent: number
  hourly_limit:   number
  daily_limit:    number
  last_used_at:   string | null
  created_at:     string
}

interface NewKey extends ApiKey {
  key: string
}

interface EndpointParam {
  name:        string
  type:        string
  required:    boolean
  description: string
}

interface Endpoint {
  key:              string
  group:            string
  method:           'GET' | 'POST' | 'PATCH' | 'DELETE'
  path:             string
  title:            string
  description:      string
  parameters:       EndpointParam[]
  response_example: string
  enabled:          boolean
}

// ─── Helpers ───────────────────────────────────────────────────────────────

function dateLabel(d: string | null): string {
  if (!d) return 'Never'
  const dt = new Date(d.endsWith('Z') ? d : d + 'Z')
  return dt.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}

function isActive(k: ApiKey): boolean {
  return k.is_active === true || k.is_active === 1
}

const METHOD_STYLES: Record<string, string> = {
  GET:    'bg-blue-50 text-blue-700 border-blue-200',
  POST:   'bg-emerald-50 text-emerald-700 border-emerald-200',
  PATCH:  'bg-amber-50 text-amber-700 border-amber-200',
  DELETE: 'bg-red-50 text-red-700 border-red-200',
}

// ─── New key reveal banner ──────────────────────────────────────────────────

function NewKeyBanner({ apiKey, onDismiss }: { apiKey: NewKey; onDismiss: () => void }) {
  const [copied,  setCopied]  = useState(false)
  const [visible, setVisible] = useState(false)

  const handleCopy = () => {
    navigator.clipboard.writeText(apiKey.key)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className="rounded-xl border border-emerald-500/30 bg-emerald-50 p-5 mb-6">
      <div className="flex items-start gap-3">
        <CheckCircle2 className="h-5 w-5 text-emerald-600 mt-0.5 shrink-0" />
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-emerald-800 mb-1">API key created — copy it now</p>
          <p className="text-xs text-emerald-700 mb-3">
            This is the only time the full key will be shown. Store it somewhere safe.
          </p>
          <div className="flex items-center gap-2">
            <code className="flex-1 bg-white border border-emerald-200 rounded-lg px-3 py-2 text-sm font-mono text-emerald-900 truncate">
              {visible ? apiKey.key : apiKey.key.replace(/./g, '•')}
            </code>
            <Button size="sm" variant="ghost" className="text-emerald-700 hover:text-emerald-900 shrink-0"
              onClick={() => setVisible(v => !v)}>
              {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </Button>
            <Button size="sm" variant="outline" className="border-emerald-300 text-emerald-700 shrink-0"
              onClick={handleCopy}>
              {copied ? <CheckCircle2 className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              {copied ? 'Copied' : 'Copy'}
            </Button>
          </div>
        </div>
        <button onClick={onDismiss}
          className="text-emerald-500 hover:text-emerald-700 text-lg leading-none shrink-0"
          aria-label="Dismiss">×</button>
      </div>
    </div>
  )
}

// ─── Rate limit fields ──────────────────────────────────────────────────────

interface RateLimits {
  max_concurrent: number
  hourly_limit:   number
  daily_limit:    number
}

function RateLimitFields({
  value,
  onChange,
  disabled,
}: {
  value:    RateLimits
  onChange: (v: RateLimits) => void
  disabled?: boolean
}) {
  const field = (
    label:   string,
    hint:    string,
    key:     keyof RateLimits,
    min = 1,
  ) => (
    <div className="flex-1 min-w-0">
      <label className="block text-xs font-semibold text-[#390d58] mb-1">{label}</label>
      <Input
        type="number"
        min={min}
        value={value[key]}
        onChange={e => onChange({ ...value, [key]: Math.max(min, parseInt(e.target.value) || min) })}
        className="h-8 text-sm border-[#390d58]/20 focus-visible:ring-[#390d58]/30"
        disabled={disabled}
      />
      <p className="text-[10px] text-muted-foreground mt-1">{hint}</p>
    </div>
  )

  return (
    <div className="flex gap-3">
      {field('Max concurrent', 'Simultaneous requests', 'max_concurrent')}
      {field('Hourly limit', 'Requests per hour', 'hourly_limit')}
      {field('Daily limit', 'Requests per day', 'daily_limit')}
    </div>
  )
}

// ─── Create key form ────────────────────────────────────────────────────────

const DEFAULT_LIMITS: RateLimits = { max_concurrent: 1, hourly_limit: 60, daily_limit: 500 }

function CreateKeyForm({ onCreated }: { onCreated: (k: NewKey) => void }) {
  const [open,    setOpen]    = useState(false)
  const [name,    setName]    = useState('')
  const [limits,  setLimits]  = useState<RateLimits>(DEFAULT_LIMITS)
  const [saving,  setSaving]  = useState(false)
  const [error,   setError]   = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const handleOpen = () => {
    setOpen(true); setName(''); setLimits(DEFAULT_LIMITS); setError(null)
    setTimeout(() => inputRef.current?.focus(), 50)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!name.trim()) return
    setSaving(true); setError(null)
    try {
      const key = await api.post<NewKey>('/api-keys', { name: name.trim(), ...limits })
      onCreated(key); setOpen(false); setName(''); setLimits(DEFAULT_LIMITS)
    } catch {
      setError('Failed to create key. Please try again.')
    } finally {
      setSaving(false)
    }
  }

  if (!open) {
    return (
      <Button size="sm" className="gap-1.5 bg-[#390d58] hover:bg-[#4a1170] text-white" onClick={handleOpen}>
        <Plus className="h-4 w-4" /> New API Key
      </Button>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="border border-[#390d58]/20 rounded-xl p-5 mb-6 bg-[#390d58]/[0.02]">
      <p className="text-sm font-semibold text-[#390d58] mb-4">New API Key</p>
      <div className="space-y-4">
        <div>
          <label className="block text-xs font-semibold text-[#390d58] mb-1">Key name</label>
          <Input
            ref={inputRef}
            placeholder="e.g. My App, Staging, Zapier"
            value={name}
            onChange={e => setName(e.target.value)}
            className="border-[#390d58]/20 focus-visible:ring-[#390d58]/30"
            disabled={saving}
          />
        </div>

        <div>
          <div className="flex items-center gap-1.5 mb-3">
            <Gauge className="h-3.5 w-3.5 text-[#390d58]" />
            <span className="text-xs font-semibold text-[#390d58]">Rate limits</span>
          </div>
          <RateLimitFields value={limits} onChange={setLimits} disabled={saving} />
        </div>

        {error && <p className="text-xs text-red-600">{error}</p>}

        <div className="flex gap-2 pt-1">
          <Button type="submit" size="sm" className="bg-[#390d58] hover:bg-[#4a1170] text-white"
            disabled={saving || !name.trim()}>
            {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1" /> : null}
            Create key
          </Button>
          <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)} disabled={saving}>
            Cancel
          </Button>
        </div>
      </div>
    </form>
  )
}

// ─── Limits summary pill ────────────────────────────────────────────────────

function LimitsSummary({ apiKey }: { apiKey: ApiKey }) {
  return (
    <div className="flex items-center gap-2 text-[10px] text-muted-foreground font-mono">
      <span className="flex items-center gap-1">
        <Gauge className="h-3 w-3" />
        {apiKey.max_concurrent} concurrent
      </span>
      <span>·</span>
      <span>{apiKey.hourly_limit}/hr</span>
      <span>·</span>
      <span>{apiKey.daily_limit}/day</span>
    </div>
  )
}

// ─── Logs panel ────────────────────────────────────────────────────────────

interface ApiLog {
  id:               number
  api_key_id:       number
  endpoint_key:     string | null
  method:           string
  path:             string
  status_code:      number
  response_time_ms: number | null
  ip_address:       string | null
  created_at:       string
}

interface LogsPage {
  rows:  ApiLog[]
  total: number
}

function statusBadgeClass(code: number): string {
  if (code >= 200 && code < 300) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (code >= 400 && code < 500) return 'bg-amber-50 text-amber-700 border-amber-200'
  return 'bg-red-50 text-red-700 border-red-200'
}

function LogsPanel({ keyId }: { keyId: number }) {
  const PER_PAGE = 25

  const [data,    setData]    = useState<LogsPage | null>(null)
  const [page,    setPage]    = useState(1)
  const [loading, setLoading] = useState(false)
  const [error,   setError]   = useState<string | null>(null)

  const load = (p: number) => {
    setLoading(true); setError(null)
    api.get<LogsPage>(`/api-keys/${keyId}/logs?page=${p}&per_page=${PER_PAGE}`)
      .then(d => { setData(d); setPage(p) })
      .catch(() => setError('Could not load logs.'))
      .finally(() => setLoading(false))
  }

  // Load on first render
  useEffect(() => { load(1) }, [keyId])

  const totalPages = data ? Math.ceil(data.total / PER_PAGE) : 1
  const from       = data ? (page - 1) * PER_PAGE + 1 : 0
  const to         = data ? Math.min(page * PER_PAGE, data.total) : 0

  return (
    <div className="px-5 pb-5 border-t border-[#390d58]/10 bg-[#390d58]/[0.02]">
      <div className="pt-4">
        {/* Header row */}
        <div className="flex items-center justify-between mb-3">
          <span className="text-xs font-semibold text-[#390d58]">
            {data ? `${data.total.toLocaleString()} request${data.total !== 1 ? 's' : ''}` : 'Requests'}
          </span>
          <Button size="sm" variant="ghost" className="h-7 px-2 text-[#390d58]"
            onClick={() => load(page)} disabled={loading}>
            {loading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
          </Button>
        </div>

        {error ? (
          <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">{error}</p>
        ) : loading && !data ? (
          <div className="flex items-center justify-center h-16 gap-2 text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin text-[#390d58]" />
            <span className="text-xs">Loading…</span>
          </div>
        ) : data?.rows.length === 0 ? (
          <div className="text-center py-8 text-muted-foreground">
            <ScrollText className="h-6 w-6 mx-auto mb-2 opacity-30" />
            <p className="text-xs">No requests logged yet.</p>
          </div>
        ) : data ? (
          <>
            <div className="rounded-lg border border-[#390d58]/10 overflow-hidden">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-[#390d58]/[0.04] border-b border-[#390d58]/10">
                    <th className="px-3 py-2 text-left font-semibold text-[#390d58] w-36">Time</th>
                    <th className="px-3 py-2 text-left font-semibold text-[#390d58] w-14">Method</th>
                    <th className="px-3 py-2 text-left font-semibold text-[#390d58]">Path</th>
                    <th className="px-3 py-2 text-left font-semibold text-[#390d58] w-16">Status</th>
                    <th className="px-3 py-2 text-right font-semibold text-[#390d58] w-16">Time (ms)</th>
                    <th className="px-3 py-2 text-left font-semibold text-[#390d58] w-28">IP</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#390d58]/10">
                  {data.rows.map(log => (
                    <tr key={log.id} className="hover:bg-[#390d58]/[0.02] transition-colors">
                      <td className="px-3 py-2 font-mono text-muted-foreground whitespace-nowrap">
                        {dateLabel(log.created_at)}
                      </td>
                      <td className="px-3 py-2">
                        <span className={`font-mono font-bold px-1.5 py-0.5 rounded border text-[10px] ${METHOD_STYLES[log.method] ?? 'bg-slate-50 text-slate-600 border-slate-200'}`}>
                          {log.method}
                        </span>
                      </td>
                      <td className="px-3 py-2 font-mono text-muted-foreground truncate max-w-[200px]" title={log.path}>
                        {log.path}
                      </td>
                      <td className="px-3 py-2">
                        <span className={`font-mono font-bold px-1.5 py-0.5 rounded border text-[10px] ${statusBadgeClass(log.status_code)}`}>
                          {log.status_code}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-right font-mono text-muted-foreground">
                        {log.response_time_ms != null ? log.response_time_ms : '—'}
                      </td>
                      <td className="px-3 py-2 font-mono text-muted-foreground">
                        {log.ip_address ?? '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-between mt-3">
                <span className="text-[10px] text-muted-foreground">
                  {from}–{to} of {data.total.toLocaleString()}
                </span>
                <div className="flex items-center gap-1">
                  <Button size="sm" variant="outline"
                    className="h-7 w-7 p-0 border-[#390d58]/20 text-[#390d58]"
                    onClick={() => load(page - 1)} disabled={page <= 1 || loading}>
                    <ChevronLeft className="h-3.5 w-3.5" />
                  </Button>
                  <span className="text-[10px] text-muted-foreground px-1">{page} / {totalPages}</span>
                  <Button size="sm" variant="outline"
                    className="h-7 w-7 p-0 border-[#390d58]/20 text-[#390d58]"
                    onClick={() => load(page + 1)} disabled={page >= totalPages || loading}>
                    <ChevronRight className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            )}
          </>
        ) : null}
      </div>
    </div>
  )
}

// ─── Single key row ─────────────────────────────────────────────────────────

function ApiKeyRow({ apiKey, onToggle, onDelete, onUpdateLimits }: {
  apiKey:         ApiKey
  onToggle:       (id: number, active: boolean) => Promise<void>
  onDelete:       (id: number) => Promise<void>
  onUpdateLimits: (id: number, limits: RateLimits) => Promise<void>
}) {
  const [toggling,   setToggling]   = useState(false)
  const [deleting,   setDeleting]   = useState(false)
  const [editLimits, setEditLimits] = useState(false)
  const [showLogs,   setShowLogs]   = useState(false)
  const [savingLimits, setSavingLimits] = useState(false)
  const [limitsError,  setLimitsError]  = useState<string | null>(null)
  const [limits, setLimits] = useState<RateLimits>({
    max_concurrent: apiKey.max_concurrent ?? 1,
    hourly_limit:   apiKey.hourly_limit   ?? 60,
    daily_limit:    apiKey.daily_limit    ?? 500,
  })

  const active = isActive(apiKey)

  const handleToggle = async () => {
    setToggling(true)
    await onToggle(apiKey.id, !active)
    setToggling(false)
  }

  const handleDelete = async () => {
    if (!window.confirm(`Revoke "${apiKey.name}"? This cannot be undone.`)) return
    setDeleting(true)
    await onDelete(apiKey.id)
    setDeleting(false)
  }

  const handleSaveLimits = async () => {
    setSavingLimits(true); setLimitsError(null)
    try {
      await onUpdateLimits(apiKey.id, limits)
      setEditLimits(false)
    } catch {
      setLimitsError('Failed to save. Please try again.')
    } finally {
      setSavingLimits(false)
    }
  }

  const handleCancelLimits = () => {
    setLimits({
      max_concurrent: apiKey.max_concurrent ?? 1,
      hourly_limit:   apiKey.hourly_limit   ?? 60,
      daily_limit:    apiKey.daily_limit    ?? 500,
    })
    setLimitsError(null)
    setEditLimits(false)
  }

  return (
    <div>
      {/* Main row */}
      <div className="flex items-center gap-4 px-5 py-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-3 mb-1">
            <span className="text-sm font-semibold text-[#390d58]">{apiKey.name}</span>
            <Badge variant="outline" className={active
              ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-xs gap-1'
              : 'bg-slate-100 text-slate-500 border-slate-200 text-xs gap-1'}>
              {active ? <CheckCircle2 className="h-3 w-3" /> : <AlertCircle className="h-3 w-3" />}
              {active ? 'Active' : 'Inactive'}
            </Badge>
          </div>
          <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground font-mono mb-1">
            <span>{apiKey.key_prefix}…</span>
            <span>Created {dateLabel(apiKey.created_at)}</span>
            <span>Last used: {dateLabel(apiKey.last_used_at)}</span>
          </div>
          <LimitsSummary apiKey={apiKey} />
        </div>
        <div className="flex items-center gap-1 shrink-0">
          <Button
            size="sm" variant="ghost"
            className={`gap-1.5 h-8 px-3 ${showLogs ? 'text-[#390d58] bg-[#390d58]/10' : 'text-[#390d58]'}`}
            onClick={() => { setShowLogs(v => !v); setEditLimits(false) }}
            title="View request logs"
          >
            <ScrollText className="h-4 w-4" />
            Logs
            {showLogs ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
          </Button>
          <Button
            size="sm" variant="ghost"
            className={`gap-1.5 h-8 px-3 ${editLimits ? 'text-[#390d58] bg-[#390d58]/10' : 'text-[#390d58]'}`}
            onClick={() => { setEditLimits(v => !v); setShowLogs(false) }}
            title="Edit rate limits"
          >
            <Gauge className="h-4 w-4" />
            Limits
            {editLimits ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
          </Button>
          <Button size="sm" variant="ghost" className="gap-1.5 text-[#390d58] h-8 px-3"
            onClick={handleToggle} disabled={toggling}>
            {toggling
              ? <Loader2 className="h-4 w-4 animate-spin" />
              : active ? <ToggleRight className="h-4 w-4" /> : <ToggleLeft className="h-4 w-4" />}
            {active ? 'Disable' : 'Enable'}
          </Button>
          <Button size="sm" variant="ghost"
            className="gap-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 h-8 px-3"
            onClick={handleDelete} disabled={deleting}>
            {deleting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
            Revoke
          </Button>
        </div>
      </div>

      {/* Expandable logs */}
      {showLogs && <LogsPanel keyId={apiKey.id} />}

      {/* Expandable limits editor */}
      {editLimits && (
        <div className="px-5 pb-5 border-t border-[#390d58]/10 bg-[#390d58]/[0.02]">
          <div className="pt-4 space-y-4">
            <RateLimitFields value={limits} onChange={setLimits} disabled={savingLimits} />
            {limitsError && <p className="text-xs text-red-600">{limitsError}</p>}
            <div className="flex gap-2">
              <Button size="sm" className="bg-[#390d58] hover:bg-[#4a1170] text-white"
                onClick={handleSaveLimits} disabled={savingLimits}>
                {savingLimits ? <Loader2 className="h-4 w-4 animate-spin mr-1" /> : null}
                Save limits
              </Button>
              <Button size="sm" variant="ghost" onClick={handleCancelLimits} disabled={savingLimits}>
                Cancel
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── Endpoint row ───────────────────────────────────────────────────────────

function EndpointRow({ endpoint, onToggle }: {
  endpoint: Endpoint
  onToggle: (key: string, enabled: boolean) => Promise<void>
}) {
  const [saving, setSaving] = useState(false)

  const handleToggle = async (checked: boolean) => {
    setSaving(true)
    await onToggle(endpoint.key, checked)
    setSaving(false)
  }

  return (
    <div className="flex items-center gap-4 px-5 py-3.5">
      <span className={`text-[10px] font-bold px-2 py-0.5 rounded border font-mono shrink-0 w-14 text-center ${METHOD_STYLES[endpoint.method] ?? ''}`}>
        {endpoint.method}
      </span>
      <code className="text-xs font-mono text-slate-700 shrink-0 w-48 truncate">
        /beacon/public/v1{endpoint.path}
      </code>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-[#390d58] truncate">{endpoint.title}</p>
        <p className="text-xs text-muted-foreground truncate">{endpoint.description}</p>
      </div>
      <div className="shrink-0 flex items-center gap-2">
        {saving && <Loader2 className="h-3.5 w-3.5 animate-spin text-[#390d58]" />}
        <Switch checked={endpoint.enabled} onCheckedChange={handleToggle} disabled={saving} />
      </div>
    </div>
  )
}

// ─── Main page ──────────────────────────────────────────────────────────────

export function ApiPage() {
  const [keys,      setKeys]      = useState<ApiKey[]>([])
  const [endpoints, setEndpoints] = useState<Endpoint[]>([])
  const [loadingKeys, setLoadingKeys] = useState(true)
  const [loadingEps,  setLoadingEps]  = useState(true)
  const [keysError,   setKeysError]   = useState<string | null>(null)
  const [epsError,    setEpsError]    = useState<string | null>(null)
  const [newKey, setNewKey] = useState<NewKey | null>(null)

  const loadKeys = () => {
    setLoadingKeys(true); setKeysError(null)
    api.get<ApiKey[]>('/api-keys')
      .then(setKeys)
      .catch(() => setKeysError('Could not load API keys.'))
      .finally(() => setLoadingKeys(false))
  }

  const loadEndpoints = () => {
    setLoadingEps(true); setEpsError(null)
    api.get<Endpoint[]>('/api-endpoints')
      .then(setEndpoints)
      .catch(() => setEpsError('Could not load endpoints.'))
      .finally(() => setLoadingEps(false))
  }

  useEffect(() => { loadKeys(); loadEndpoints() }, [])

  const handleCreated = (k: NewKey) => {
    setNewKey(k)
    setKeys(prev => [{ ...k } as ApiKey, ...prev])
  }

  const handleToggleKey = async (id: number, active: boolean) => {
    await api.patch(`/api-keys/${id}`, { is_active: active })
    setKeys(prev => prev.map(k => k.id === id ? { ...k, is_active: active } : k))
  }

  const handleDeleteKey = async (id: number) => {
    await api.delete(`/api-keys/${id}`)
    setKeys(prev => prev.filter(k => k.id !== id))
    if (newKey?.id === id) setNewKey(null)
  }

  const handleUpdateLimits = async (id: number, limits: RateLimits) => {
    await api.patch(`/api-keys/${id}`, limits)
    setKeys(prev => prev.map(k => k.id === id ? { ...k, ...limits } : k))
  }

  const handleToggleEndpoint = async (key: string, enabled: boolean) => {
    await api.patch(`/api-endpoints/${key}`, { enabled })
    setEndpoints(prev => prev.map(ep => ep.key === key ? { ...ep, enabled } : ep))
  }

  const groups = endpoints.reduce<Record<string, Endpoint[]>>((acc, ep) => {
    if (!acc[ep.group]) acc[ep.group] = []
    acc[ep.group].push(ep)
    return acc
  }, {})

  const enabledCount = endpoints.filter(e => e.enabled).length
  const docsUrl      = `${window.BeaconData?.siteUrl ?? ''}/?beacon_docs=1`

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[#390d58]">API</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Manage API keys and control which endpoints are available to external integrations.
          </p>
        </div>
        <a href={docsUrl} target="_blank" rel="noreferrer">
          <Button variant="outline" size="sm" className="gap-1.5 border-[#390d58]/20 text-[#390d58]">
            <ExternalLink className="h-4 w-4" />
            View docs page
          </Button>
        </a>
      </div>

      {/* ── API Keys ── */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
                <Key className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-lg text-[#390d58]">API Keys</CardTitle>
                <CardDescription>Issue keys to allow external services to authenticate. Rate limits are enforced per key.</CardDescription>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" className="gap-1.5 border-[#390d58]/20 text-[#390d58]"
                onClick={loadKeys} disabled={loadingKeys}>
                {loadingKeys ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                Refresh
              </Button>
              <CreateKeyForm onCreated={handleCreated} />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {newKey && <NewKeyBanner apiKey={newKey} onDismiss={() => setNewKey(null)} />}

          {loadingKeys ? (
            <div className="flex items-center justify-center h-24 text-muted-foreground gap-2">
              <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" />
              <span className="text-sm">Loading…</span>
            </div>
          ) : keysError ? (
            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{keysError}</p>
          ) : keys.length === 0 ? (
            <div className="rounded-xl border border-[#390d58]/10 p-8 text-center text-muted-foreground">
              <Key className="h-8 w-8 mx-auto mb-3 opacity-30" />
              <p className="text-sm">No API keys yet.</p>
              <p className="text-xs mt-1">Click "New API Key" to create your first key.</p>
            </div>
          ) : !loadingKeys && (
            <div className="rounded-xl border border-[#390d58]/10 overflow-hidden divide-y divide-[#390d58]/10">
              {keys.map(k => (
                <ApiKeyRow
                  key={k.id}
                  apiKey={k}
                  onToggle={handleToggleKey}
                  onDelete={handleDeleteKey}
                  onUpdateLimits={handleUpdateLimits}
                />
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* ── Endpoints ── */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
                <Plug className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-lg text-[#390d58]">Endpoints</CardTitle>
                <CardDescription>
                  Toggle which endpoints are available. Disabled endpoints return 403 and appear in the docs as disabled.
                  {!loadingEps && ` ${enabledCount} of ${endpoints.length} enabled.`}
                </CardDescription>
              </div>
            </div>
            <Button variant="outline" size="sm" className="gap-1.5 border-[#390d58]/20 text-[#390d58]"
              onClick={loadEndpoints} disabled={loadingEps}>
              {loadingEps ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
              Refresh
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {loadingEps ? (
            <div className="flex items-center justify-center h-24 text-muted-foreground gap-2">
              <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" />
              <span className="text-sm">Loading…</span>
            </div>
          ) : epsError ? (
            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{epsError}</p>
          ) : (
            <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
              {Object.entries(groups).map(([group, eps], gi) => (
                <div key={group}>
                  <div className={`px-5 py-2 bg-[#390d58]/[0.03] border-b border-[#390d58]/10 ${gi > 0 ? 'border-t' : ''}`}>
                    <span className="text-xs font-bold text-[#390d58] uppercase tracking-wide">{group}</span>
                  </div>
                  <div className="divide-y divide-[#390d58]/10">
                    {eps.map(ep => (
                      <EndpointRow key={ep.key} endpoint={ep} onToggle={handleToggleEndpoint} />
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* ── Authentication docs ── */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <CardTitle className="text-base text-[#390d58]">Authentication</CardTitle>
          <CardDescription>Include your API key as a Bearer token in every request.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">Request header</p>
            <pre className="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-mono text-slate-800 overflow-x-auto">
              Authorization: Bearer drb_your_key_here
            </pre>
          </div>
          <div>
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">Base URL</p>
            <pre className="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-mono text-slate-800 overflow-x-auto">
              {`${window.BeaconData?.siteUrl ?? ''}/wp-json/beacon/public/v1`}
            </pre>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
