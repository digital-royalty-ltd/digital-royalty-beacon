import { useEffect, useState, useCallback } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Bug, Clock, Trash2, Activity, Calendar, Loader2,
  RefreshCw, RotateCcw, AlertTriangle, ChevronLeft, ChevronRight,
} from 'lucide-react'
import { api } from '@/lib/api'
import { HeartbeatDiagnostics } from '@/components/beacon/HeartbeatDiagnostics'

// ── Types ─────────────────────────────────────────────────────────────────────

type LogScope = 'reports' | 'api' | 'system' | 'admin' | 'webhook' | 'all'

interface LogEntry {
  id:         string
  created_at: string
  scope:      string
  event:      string
  message:    string
  level:      string
  context:    string | null
}

interface DebugInfo {
  scheduler: {
    next_run:       string | null
    last_heartbeat: string | null
  }
  report_status: string
}

interface DeferredRow {
  id:              string
  request_key:     string
  status:          string
  poll_path:       string
  external_id:     string | null
  attempts:        string
  next_attempt_at: string
  last_error:      string | null
  updated_at:      string
}

interface SchedulerRow {
  id:       string
  hook:     string
  status:   string
  next_run: string
  attempts: string
  args:     string
}

interface PagedResult<T> {
  rows:     T[]
  total:    number
  per_page: number
  page:     number
}

// ── Constants ─────────────────────────────────────────────────────────────────

const PER_PAGE = 25

const scopeColors: Record<string, string> = {
  reports: 'bg-[#390d58]/10 text-[#390d58] border-[#390d58]/20',
  api:     'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
  system:  'bg-amber-500/10 text-amber-600 border-amber-500/20',
  admin:   'bg-blue-500/10 text-blue-600 border-blue-500/20',
  webhook: 'bg-orange-500/10 text-orange-600 border-orange-500/20',
}

const deferredStatusColors: Record<string, string> = {
  pending:   'bg-amber-500/10 text-amber-600 border-amber-500/20',
  completed: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
  failed:    'bg-red-500/10 text-red-600 border-red-500/20',
}

const schedulerStatusColors: Record<string, string> = {
  pending:   'bg-amber-500/10 text-amber-600 border-amber-500/20',
  complete:  'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
  failed:    'bg-red-500/10 text-red-600 border-red-500/20',
  running:   'bg-blue-500/10 text-blue-600 border-blue-500/20',
  canceled:  'bg-muted text-muted-foreground',
}

// ── Shared pagination control ─────────────────────────────────────────────────

function TablePagination({ page, total, onPage }: {
  page:   number
  total:  number
  onPage: (p: number) => void
}) {
  const totalPages = Math.max(1, Math.ceil(total / PER_PAGE))
  if (totalPages <= 1) return null

  const from = Math.min((page - 1) * PER_PAGE + 1, total)
  const to   = Math.min(page * PER_PAGE, total)

  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-[#390d58]/10 bg-[#390d58]/[0.01]">
      <p className="text-xs text-muted-foreground">
        {from}–{to} of {total}
      </p>
      <div className="flex items-center gap-1">
        <Button
          variant="outline" size="sm"
          onClick={() => onPage(page - 1)}
          disabled={page <= 1}
          className="h-7 w-7 p-0 border-[#390d58]/20 text-[#390d58]"
        >
          <ChevronLeft className="h-3.5 w-3.5" />
        </Button>
        <span className="text-xs text-muted-foreground px-2 tabular-nums">
          {page} / {totalPages}
        </span>
        <Button
          variant="outline" size="sm"
          onClick={() => onPage(page + 1)}
          disabled={page >= totalPages}
          className="h-7 w-7 p-0 border-[#390d58]/20 text-[#390d58]"
        >
          <ChevronRight className="h-3.5 w-3.5" />
        </Button>
      </div>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────

export function Debug() {
  const [scopeFilter,      setScopeFilter]      = useState<LogScope>('all')
  const [logs,             setLogs]             = useState<LogEntry[]>([])
  const [logsTotal,        setLogsTotal]        = useState(0)
  const [logsPage,         setLogsPage]         = useState(1)
  const [debugInfo,        setDebugInfo]        = useState<DebugInfo | null>(null)
  const [loadingLogs,      setLoadingLogs]      = useState(true)
  const [clearing,         setClearing]         = useState(false)

  const [deferredRows,     setDeferredRows]     = useState<DeferredRow[]>([])
  const [deferredTotal,    setDeferredTotal]    = useState(0)
  const [deferredPage,     setDeferredPage]     = useState(1)
  const [loadingDeferred,  setLoadingDeferred]  = useState(true)

  const [schedulerRows,    setSchedulerRows]    = useState<SchedulerRow[]>([])
  const [schedulerTotal,   setSchedulerTotal]   = useState(0)
  const [schedulerPage,    setSchedulerPage]    = useState(1)
  const [loadingScheduler, setLoadingScheduler] = useState(true)

  const [resetting,     setResetting]     = useState<string | null>(null)
  const [disconnecting, setDisconnecting] = useState(false)

  // ── Fetch functions ──────────────────────────────────────────────────────

  const fetchLogs = useCallback(async (scope: LogScope, page: number) => {
    setLoadingLogs(true)
    try {
      const params = new URLSearchParams({ per_page: String(PER_PAGE), page: String(page) })
      if (scope !== 'all') params.set('scope', scope)
      const result = await api.get<{ rows: LogEntry[]; total: number }>(`/logs?${params}`)
      setLogs(result.rows)
      setLogsTotal(result.total)
    } finally {
      setLoadingLogs(false)
    }
  }, [])

  const fetchDeferred = useCallback(async (page: number) => {
    setLoadingDeferred(true)
    try {
      const result = await api.get<PagedResult<DeferredRow>>(
        `/debug/deferred-requests?per_page=${PER_PAGE}&page=${page}`
      )
      setDeferredRows(result.rows)
      setDeferredTotal(result.total)
    } finally {
      setLoadingDeferred(false)
    }
  }, [])

  const fetchScheduler = useCallback(async (page: number) => {
    setLoadingScheduler(true)
    try {
      const result = await api.get<PagedResult<SchedulerRow>>(
        `/debug/scheduler-actions?per_page=${PER_PAGE}&page=${page}`
      )
      setSchedulerRows(result.rows)
      setSchedulerTotal(result.total)
    } finally {
      setLoadingScheduler(false)
    }
  }, [])

  // ── Effects ──────────────────────────────────────────────────────────────

  useEffect(() => { fetchLogs(scopeFilter, logsPage)    }, [scopeFilter, logsPage,    fetchLogs])
  useEffect(() => { fetchDeferred(deferredPage)          }, [deferredPage,              fetchDeferred])
  useEffect(() => { fetchScheduler(schedulerPage)        }, [schedulerPage,             fetchScheduler])
  useEffect(() => { api.get<DebugInfo>('/debug').then(setDebugInfo).catch(() => null) }, [])

  // Reset to page 1 when scope filter changes
  const handleScopeChange = (val: LogScope) => {
    setScopeFilter(val)
    setLogsPage(1)
  }

  // ── Actions ──────────────────────────────────────────────────────────────

  const handleClearLogs = async () => {
    if (!confirm('Clear all log entries?')) return
    setClearing(true)
    try {
      await api.delete('/logs')
      setLogs([])
      setLogsTotal(0)
      setLogsPage(1)
    } finally {
      setClearing(false)
    }
  }

  const handleReset = async (action: string, label: string) => {
    if (!confirm(`${label} — are you sure? This cannot be undone.`)) return
    setResetting(action)
    try {
      await api.post('/debug/reset', { action })
      setDeferredPage(1)
      setSchedulerPage(1)
      setLogsPage(1)
      fetchDeferred(1)
      fetchScheduler(1)
      fetchLogs(scopeFilter, 1)
      api.get<DebugInfo>('/debug').then(setDebugInfo).catch(() => null)
    } catch {
      alert('Reset failed.')
    } finally {
      setResetting(null)
    }
  }

  const handleDisconnect = async () => {
    if (!confirm('Disconnect Beacon and remove API key? This cannot be undone.')) return
    setDisconnecting(true)
    try {
      await api.delete('/config/api-key')
      if (window.BeaconData) {
        window.BeaconData.hasApiKey   = false
        window.BeaconData.isConnected = false
      }
      window.location.reload()
    } finally {
      setDisconnecting(false)
    }
  }

  // ── Render ────────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">

      {/* Scheduler Info */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center gap-4">
            <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
              <Calendar className="h-5 w-5" />
            </div>
            <div>
              <CardTitle className="text-lg text-[#390d58]">Scheduler</CardTitle>
              <CardDescription>Cron job status and timing information</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="flex items-center gap-4 p-5 rounded-xl bg-[#390d58]/5 border border-[#390d58]/10">
              <div className="rounded-lg bg-[#390d58]/10 p-2.5">
                <Clock className="h-5 w-5 text-[#390d58]" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Next Scheduled Run</p>
                <p className="text-sm font-semibold font-mono mt-0.5">
                  {debugInfo?.scheduler.next_run ?? '—'}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-4 p-5 rounded-xl bg-[#390d58]/5 border border-[#390d58]/10">
              <div className="rounded-lg bg-[#390d58]/10 p-2.5">
                <Activity className="h-5 w-5 text-[#390d58]" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Last Heartbeat</p>
                <p className="text-sm font-semibold font-mono mt-0.5">
                  {debugInfo?.scheduler.last_heartbeat ?? '—'}
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Beacon diagnostics — heartbeat + catalog publish trace (replaces the old quick "Send Heartbeat Now" button) */}
      <HeartbeatDiagnostics />

      {/* Deferred Requests Table */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
                <Activity className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-lg text-[#390d58]">
                  Deferred Requests
                  {deferredTotal > 0 && (
                    <span className="ml-2 text-sm font-normal text-muted-foreground">({deferredTotal})</span>
                  )}
                </CardTitle>
                <CardDescription>Async jobs queued for polling from the Beacon API</CardDescription>
              </div>
            </div>
            <Button variant="outline" size="sm" className="gap-1.5 border-[#390d58]/20 text-[#390d58]"
              onClick={() => fetchDeferred(deferredPage)} disabled={loadingDeferred}>
              {loadingDeferred ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
              Refresh
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                  <TableHead className="w-12 text-[#390d58] font-semibold">ID</TableHead>
                  <TableHead className="w-44 text-[#390d58] font-semibold">Key</TableHead>
                  <TableHead className="w-24 text-[#390d58] font-semibold">Status</TableHead>
                  <TableHead className="text-[#390d58] font-semibold">Poll Path</TableHead>
                  <TableHead className="w-16 text-[#390d58] font-semibold">Tries</TableHead>
                  <TableHead className="w-40 text-[#390d58] font-semibold">Next Attempt</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loadingDeferred ? (
                  <TableRow>
                    <TableCell colSpan={6} className="h-24 text-center">
                      <Loader2 className="h-5 w-5 animate-spin text-[#390d58] mx-auto" />
                    </TableCell>
                  </TableRow>
                ) : deferredRows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                      No deferred requests
                    </TableCell>
                  </TableRow>
                ) : (
                  deferredRows.map((row, i) => (
                    <TableRow key={row.id}
                      className={`font-mono text-sm ${i % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                      <TableCell className="text-muted-foreground">{row.id}</TableCell>
                      <TableCell className="text-[#390d58] font-medium">{row.request_key}</TableCell>
                      <TableCell>
                        <Badge variant="outline"
                          className={`text-xs ${deferredStatusColors[row.status] ?? 'bg-muted text-muted-foreground'}`}>
                          {row.status}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-foreground/70 text-xs truncate max-w-xs">{row.poll_path}</TableCell>
                      <TableCell className="text-muted-foreground">{row.attempts}</TableCell>
                      <TableCell className="text-muted-foreground text-xs">{row.next_attempt_at}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
            <TablePagination
              page={deferredPage}
              total={deferredTotal}
              onPage={p => setDeferredPage(p)}
            />
          </div>
        </CardContent>
      </Card>

      {/* Action Scheduler Table */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
                <Clock className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-lg text-[#390d58]">
                  Action Scheduler
                  {schedulerTotal > 0 && (
                    <span className="ml-2 text-sm font-normal text-muted-foreground">({schedulerTotal})</span>
                  )}
                </CardTitle>
                <CardDescription>All dr-beacon group actions in Action Scheduler</CardDescription>
              </div>
            </div>
            <Button variant="outline" size="sm" className="gap-1.5 border-[#390d58]/20 text-[#390d58]"
              onClick={() => fetchScheduler(schedulerPage)} disabled={loadingScheduler}>
              {loadingScheduler ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
              Refresh
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                  <TableHead className="w-16 text-[#390d58] font-semibold">ID</TableHead>
                  <TableHead className="text-[#390d58] font-semibold">Hook</TableHead>
                  <TableHead className="w-24 text-[#390d58] font-semibold">Status</TableHead>
                  <TableHead className="w-40 text-[#390d58] font-semibold">Next Run</TableHead>
                  <TableHead className="w-16 text-[#390d58] font-semibold">Tries</TableHead>
                  <TableHead className="text-[#390d58] font-semibold">Args</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loadingScheduler ? (
                  <TableRow>
                    <TableCell colSpan={6} className="h-24 text-center">
                      <Loader2 className="h-5 w-5 animate-spin text-[#390d58] mx-auto" />
                    </TableCell>
                  </TableRow>
                ) : schedulerRows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                      No scheduled actions found for dr-beacon group
                    </TableCell>
                  </TableRow>
                ) : (
                  schedulerRows.map((row, i) => (
                    <TableRow key={row.id}
                      className={`font-mono text-sm ${i % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                      <TableCell className="text-muted-foreground">{row.id}</TableCell>
                      <TableCell className="text-[#390d58] font-medium text-xs">{row.hook}</TableCell>
                      <TableCell>
                        <Badge variant="outline"
                          className={`text-xs ${schedulerStatusColors[row.status] ?? 'bg-muted text-muted-foreground'}`}>
                          {row.status}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground text-xs">{row.next_run}</TableCell>
                      <TableCell className="text-muted-foreground">{row.attempts}</TableCell>
                      <TableCell className="text-foreground/70 text-xs truncate max-w-xs">{row.args}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
            <TablePagination
              page={schedulerPage}
              total={schedulerTotal}
              onPage={p => setSchedulerPage(p)}
            />
          </div>
        </CardContent>
      </Card>

      {/* Log Viewer */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
                <Bug className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-lg text-[#390d58]">
                  Log Viewer
                  {logsTotal > 0 && (
                    <span className="ml-2 text-sm font-normal text-muted-foreground">({logsTotal})</span>
                  )}
                </CardTitle>
                <CardDescription>System events and diagnostic information</CardDescription>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <Select value={scopeFilter} onValueChange={val => handleScopeChange(val as LogScope)}>
                <SelectTrigger className="w-36 border-[#390d58]/20">
                  <SelectValue placeholder="Filter" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Scopes</SelectItem>
                  <SelectItem value="reports">Reports</SelectItem>
                  <SelectItem value="api">API</SelectItem>
                  <SelectItem value="webhook">Webhooks</SelectItem>
                  <SelectItem value="system">System</SelectItem>
                  <SelectItem value="admin">Admin</SelectItem>
                </SelectContent>
              </Select>
              <Button variant="outline" size="sm" className="gap-1.5 border-[#390d58]/20 text-[#390d58]"
                onClick={() => fetchLogs(scopeFilter, logsPage)} disabled={loadingLogs}>
                {loadingLogs ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                Refresh
              </Button>
              <Button
                variant="outline" size="sm"
                className="gap-1.5 text-destructive hover:bg-destructive hover:text-white hover:border-destructive"
                onClick={handleClearLogs}
                disabled={clearing}
              >
                {clearing
                  ? <Loader2 className="h-4 w-4 animate-spin" />
                  : <Trash2 className="h-4 w-4" />}
                Clear Logs
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                  <TableHead className="w-44 text-[#390d58] font-semibold">Timestamp</TableHead>
                  <TableHead className="w-28 text-[#390d58] font-semibold">Scope</TableHead>
                  <TableHead className="w-40 text-[#390d58] font-semibold">Event</TableHead>
                  <TableHead className="text-[#390d58] font-semibold">Message</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loadingLogs ? (
                  <TableRow>
                    <TableCell colSpan={4} className="h-32 text-center">
                      <Loader2 className="h-5 w-5 animate-spin text-[#390d58] mx-auto" />
                    </TableCell>
                  </TableRow>
                ) : logs.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="h-32 text-center text-muted-foreground">
                      No log entries found
                    </TableCell>
                  </TableRow>
                ) : (
                  logs.map((log, index) => {
                    const ctx = log.context ? (() => { try { return JSON.parse(log.context) } catch { return null } })() : null
                    return (
                      <TableRow
                        key={log.id}
                        className={`font-mono text-sm ${index % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}
                      >
                        <TableCell className="text-muted-foreground">{log.created_at}</TableCell>
                        <TableCell>
                          <Badge variant="outline"
                            className={`text-xs uppercase ${scopeColors[log.scope] ?? 'bg-muted text-muted-foreground'}`}>
                            {log.scope}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-[#390d58] font-medium">{log.event}</TableCell>
                        <TableCell className="text-foreground/80">
                          <div>{log.message}</div>
                          {ctx && (
                            <pre className="mt-1 max-h-24 overflow-auto rounded bg-muted/50 px-2 py-1 text-[11px] text-muted-foreground">
                              {JSON.stringify(ctx, null, 2)}
                            </pre>
                          )}
                        </TableCell>
                      </TableRow>
                    )
                  })
                )}
              </TableBody>
            </Table>
            <TablePagination
              page={logsPage}
              total={logsTotal}
              onPage={p => setLogsPage(p)}
            />
          </div>
        </CardContent>
      </Card>

      {/* Reset Actions */}
      <Card className="border-red-200 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-red-500 to-red-700" />
        <CardHeader>
          <div className="flex items-center gap-4">
            <div className="rounded-xl bg-red-600 p-3 text-white shadow-md shadow-red-500/20">
              <AlertTriangle className="h-5 w-5" />
            </div>
            <div>
              <CardTitle className="text-lg text-red-700">Reset Actions</CardTitle>
              <CardDescription>Destructive operations for development and troubleshooting.</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {[
              { action: 'clear-reports',        label: 'Clear Reports',          desc: 'Delete all report snapshots and content-area maps' },
              { action: 'clear-deferred',       label: 'Clear Deferred Queue',   desc: 'Delete all deferred request rows' },
              { action: 'clear-action-history', label: 'Clear Action History',   desc: 'Purge completed Action Scheduler records' },
              { action: 'unschedule',           label: 'Unschedule Jobs',        desc: 'Cancel pending actions and clear history' },
              { action: 'full-reset',           label: 'Full Reset',             desc: 'Clear everything: reports, logs, API keys, deferred, scheduler' },
            ].map(({ action, label, desc }) => (
              <button
                key={action}
                onClick={() => handleReset(action, label)}
                disabled={resetting !== null || disconnecting}
                className="text-left rounded-xl border border-red-200 px-4 py-3 hover:border-red-400 hover:bg-red-50 transition-all disabled:opacity-50"
              >
                <div className="flex items-center gap-2 mb-1">
                  {resetting === action
                    ? <Loader2 className="h-3.5 w-3.5 animate-spin text-red-600" />
                    : <RotateCcw className="h-3.5 w-3.5 text-red-600" />}
                  <p className="text-sm font-medium text-red-700">{label}</p>
                </div>
                <p className="text-xs text-muted-foreground">{desc}</p>
              </button>
            ))}
          </div>

          {(window.BeaconData?.hasApiKey ?? false) && (
            <div className="mt-4 pt-4 border-t border-red-200">
              <div className="flex items-center justify-between p-4 rounded-xl bg-red-50 border border-red-200">
                <div>
                  <p className="text-sm font-medium text-red-700">Disconnect &amp; Clear Data</p>
                  <p className="text-xs text-muted-foreground mt-0.5">Remove API key, cancel all jobs, and reload</p>
                </div>
                <Button
                  variant="destructive"
                  className="gap-2 shadow-md shadow-red-500/20"
                  onClick={handleDisconnect}
                  disabled={disconnecting || resetting !== null}
                >
                  {disconnecting
                    ? <Loader2 className="h-4 w-4 animate-spin" />
                    : <Trash2 className="h-4 w-4" />}
                  Disconnect
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

    </div>
  )
}
