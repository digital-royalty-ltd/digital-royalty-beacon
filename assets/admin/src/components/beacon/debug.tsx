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
import { Bug, Clock, Trash2, Activity, Calendar, Loader2, RefreshCw, RotateCcw, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'

type LogScope = 'reports' | 'api' | 'system' | 'admin' | 'all'

interface LogEntry {
  id:         string
  created_at: string
  scope:      string
  event:      string
  message:    string
  level:      string
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

const scopeColors: Record<string, string> = {
  reports: 'bg-[#390d58]/10 text-[#390d58] border-[#390d58]/20',
  api:     'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
  system:  'bg-amber-500/10 text-amber-600 border-amber-500/20',
  admin:   'bg-blue-500/10 text-blue-600 border-blue-500/20',
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

export function Debug() {
  const [scopeFilter,      setScopeFilter]      = useState<LogScope>('all')
  const [logs,             setLogs]             = useState<LogEntry[]>([])
  const [debugInfo,        setDebugInfo]        = useState<DebugInfo | null>(null)
  const [loadingLogs,      setLoadingLogs]      = useState(true)
  const [clearing,         setClearing]         = useState(false)

  const [deferredRows,     setDeferredRows]     = useState<DeferredRow[]>([])
  const [deferredTotal,    setDeferredTotal]    = useState(0)
  const [loadingDeferred,  setLoadingDeferred]  = useState(true)

  const [schedulerRows,    setSchedulerRows]    = useState<SchedulerRow[]>([])
  const [schedulerTotal,   setSchedulerTotal]   = useState(0)
  const [loadingScheduler, setLoadingScheduler] = useState(true)

  const [resetting,        setResetting]        = useState<string | null>(null)

  const fetchLogs = useCallback(async (scope: LogScope) => {
    setLoadingLogs(true)
    try {
      const params = scope !== 'all' ? `?scope=${scope}&per_page=200` : '?per_page=200'
      const result = await api.get<{ rows: LogEntry[]; total: number }>(`/logs${params}`)
      setLogs(result.rows)
    } finally {
      setLoadingLogs(false)
    }
  }, [])

  const fetchDeferred = useCallback(async () => {
    setLoadingDeferred(true)
    try {
      const result = await api.get<PagedResult<DeferredRow>>('/debug/deferred-requests?per_page=50')
      setDeferredRows(result.rows)
      setDeferredTotal(result.total)
    } finally {
      setLoadingDeferred(false)
    }
  }, [])

  const fetchScheduler = useCallback(async () => {
    setLoadingScheduler(true)
    try {
      const result = await api.get<PagedResult<SchedulerRow>>('/debug/scheduler-actions?per_page=50')
      setSchedulerRows(result.rows)
      setSchedulerTotal(result.total)
    } finally {
      setLoadingScheduler(false)
    }
  }, [])

  useEffect(() => { fetchLogs(scopeFilter) }, [scopeFilter, fetchLogs])
  useEffect(() => { api.get<DebugInfo>('/debug').then(setDebugInfo).catch(() => null) }, [])
  useEffect(() => { fetchDeferred() }, [fetchDeferred])
  useEffect(() => { fetchScheduler() }, [fetchScheduler])

  const handleClearLogs = async () => {
    if (!confirm('Clear all log entries?')) return
    setClearing(true)
    try {
      await api.delete('/logs')
      setLogs([])
    } finally {
      setClearing(false)
    }
  }

  const handleReset = async (action: string, label: string) => {
    if (!confirm(`${label} — are you sure? This cannot be undone.`)) return
    setResetting(action)
    try {
      await api.post('/debug/reset', { action })
      // Refresh all data after reset
      fetchDeferred()
      fetchScheduler()
      api.get<DebugInfo>('/debug').then(setDebugInfo).catch(() => null)
    } catch {
      alert('Reset failed.')
    } finally {
      setResetting(null)
    }
  }

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
              onClick={fetchDeferred} disabled={loadingDeferred}>
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
              onClick={fetchScheduler} disabled={loadingScheduler}>
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
                <CardTitle className="text-lg text-[#390d58]">Log Viewer</CardTitle>
                <CardDescription>System events and diagnostic information</CardDescription>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <Select value={scopeFilter} onValueChange={val => setScopeFilter(val as LogScope)}>
                <SelectTrigger className="w-36 border-[#390d58]/20">
                  <SelectValue placeholder="Filter" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Scopes</SelectItem>
                  <SelectItem value="reports">Reports</SelectItem>
                  <SelectItem value="api">API</SelectItem>
                  <SelectItem value="system">System</SelectItem>
                  <SelectItem value="admin">Admin</SelectItem>
                </SelectContent>
              </Select>
              <Button
                variant="outline"
                size="sm"
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
                  logs.map((log, index) => (
                    <TableRow
                      key={log.id}
                      className={`font-mono text-sm ${index % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}
                    >
                      <TableCell className="text-muted-foreground">
                        {log.created_at}
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant="outline"
                          className={`text-xs uppercase ${scopeColors[log.scope] ?? 'bg-muted text-muted-foreground'}`}
                        >
                          {log.scope}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-[#390d58] font-medium">
                        {log.event}
                      </TableCell>
                      <TableCell className="text-foreground/80">
                        {log.message}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
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
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              { action: 'clear-reports',  label: 'Clear Reports',          desc: 'Delete all report snapshots and content-area maps' },
              { action: 'clear-deferred', label: 'Clear Deferred Queue',   desc: 'Delete all deferred request rows' },
              { action: 'unschedule',     label: 'Unschedule Jobs',        desc: 'Cancel all pending Action Scheduler actions' },
              { action: 'full-reset',     label: 'Full Reset',             desc: 'Clear reports, deferred, scheduler, and onboarding state' },
            ].map(({ action, label, desc }) => (
              <button
                key={action}
                onClick={() => handleReset(action, label)}
                disabled={resetting !== null}
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
        </CardContent>
      </Card>

    </div>
  )
}
