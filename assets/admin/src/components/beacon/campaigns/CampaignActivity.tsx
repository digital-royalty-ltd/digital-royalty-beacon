import { useEffect, useState } from 'react'
import { Activity, Brain, AlertTriangle, ListChecks, Loader2, RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'

type Campaign = { id: number; name: string; status: string }

type ToolHistoryEntry = {
  iteration: number
  tool: string
  args: Record<string, unknown>
  ok: boolean
  error: string | null
  duration_ms: number
}

type SessionRow = {
  id: number
  channel: string | null
  agent: { key: string; name: string } | null
  started_at: string
  iterations: number
  tool_calls: number
  tokens: number
  next_turn_in_hours: number | null
  summary: string | null
  tool_history?: ToolHistoryEntry[]
}

type MemoryEntry = { key: string; value: Record<string, unknown>; updated_at: string | null }

type WatcherEvent = {
  id: number
  watcher_slug: string
  subject_key: string
  severity: 'info' | 'warning' | 'critical'
  title: string
  summary: string | null
  data: Record<string, unknown>
  evaluated_at: string
}

type ActionLogEntry = {
  id: number
  action_slug: string
  channel: string | null
  transport: string
  status: string
  args: Record<string, unknown>
  result: Record<string, unknown> | null
  error_message: string | null
  actor: string
  started_at: string | null
  completed_at: string | null
  created_at: string | null
}

type Tab = 'sessions' | 'memory' | 'watchers' | 'actions'

interface Props {
  /** When omitted, the panel resolves the project's first active campaign automatically. */
  campaignId?: number
}

/**
 * Read-only observability panel — what the agent has been doing.
 *
 * Four tabs: Sessions (turn-loop summaries), Memory (long-term agent notes),
 * Watcher Events (rule-based alerts), Actions (atomic write audit trail).
 * Pulls from the new /admin/marketing/campaigns/{id}/* endpoints.
 */
export function CampaignActivity({ campaignId: explicitCampaignId }: Props) {
  const [campaigns, setCampaigns] = useState<Campaign[]>([])
  const [campaignId, setCampaignId] = useState<number | null>(explicitCampaignId ?? null)
  const [tab, setTab] = useState<Tab>('sessions')
  const [resolvingCampaign, setResolvingCampaign] = useState(!explicitCampaignId)

  useEffect(() => {
    if (explicitCampaignId) {
      setCampaignId(explicitCampaignId)
      return
    }
    setResolvingCampaign(true)
    api.get<{ campaigns: Campaign[] }>('/admin/marketing/campaigns')
      .then((res) => {
        const list = res.campaigns ?? []
        setCampaigns(list)
        const active = list.find((c) => c.status === 'active') ?? list[0]
        if (active) setCampaignId(active.id)
      })
      .catch(() => undefined)
      .finally(() => setResolvingCampaign(false))
  }, [explicitCampaignId])

  if (resolvingCampaign) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground py-6">
        <Loader2 className="h-4 w-4 animate-spin" /> Looking up campaigns…
      </div>
    )
  }

  if (!campaignId) {
    return (
      <Card>
        <CardContent className="pt-6 text-sm text-muted-foreground">
          No campaign found for this project. Create one to see agent activity.
        </CardContent>
      </Card>
    )
  }

  const selected = campaigns.find((c) => c.id === campaignId)

  return (
    <div className="space-y-4">
      {campaigns.length > 1 ? (
        <div className="flex items-center gap-2 text-xs">
          <span className="text-muted-foreground">Activity for:</span>
          <select
            value={campaignId}
            onChange={(e) => setCampaignId(Number(e.target.value))}
            className="rounded-md border bg-white px-2 py-1 text-xs"
          >
            {campaigns.map((c) => (
              <option key={c.id} value={c.id}>{c.name} ({c.status})</option>
            ))}
          </select>
        </div>
      ) : (
        selected && (
          <div className="text-xs text-muted-foreground">Activity for: <span className="font-medium">{selected.name}</span></div>
        )
      )}

      <div className="flex flex-wrap gap-1 border-b border-muted">
        <TabButton current={tab} value="sessions" onClick={setTab} icon={<Activity className="h-3.5 w-3.5" />} label="Sessions" />
        <TabButton current={tab} value="memory" onClick={setTab} icon={<Brain className="h-3.5 w-3.5" />} label="Memory" />
        <TabButton current={tab} value="watchers" onClick={setTab} icon={<AlertTriangle className="h-3.5 w-3.5" />} label="Watcher Events" />
        <TabButton current={tab} value="actions" onClick={setTab} icon={<ListChecks className="h-3.5 w-3.5" />} label="Actions" />
      </div>

      {tab === 'sessions' && <SessionsTab campaignId={campaignId} />}
      {tab === 'memory' && <MemoryTab campaignId={campaignId} />}
      {tab === 'watchers' && <WatcherTab campaignId={campaignId} />}
      {tab === 'actions' && <ActionsTab campaignId={campaignId} />}
    </div>
  )
}

function TabButton({ current, value, onClick, icon, label }: { current: Tab; value: Tab; onClick: (t: Tab) => void; icon: React.ReactNode; label: string }) {
  const active = current === value
  return (
    <button
      type="button"
      onClick={() => onClick(value)}
      className={`px-3 py-1.5 -mb-px text-xs font-medium rounded-t-md inline-flex items-center gap-1.5 transition-colors ${
        active ? 'bg-white border border-b-white border-muted text-[#390d58]' : 'text-muted-foreground hover:text-[#390d58]'
      }`}
    >
      {icon}
      {label}
    </button>
  )
}

// ── Sessions ────────────────────────────────────────────────────────────────

function SessionsTab({ campaignId }: { campaignId: number }) {
  const [rows, setRows] = useState<SessionRow[] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get<{ sessions: SessionRow[] }>(`/admin/marketing/campaigns/${campaignId}/sessions?limit=25`)
      .then((d) => setRows(d.sessions ?? []))
      .catch(() => setError('Could not load sessions.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
    const t = window.setInterval(load, 30_000)
    return () => window.clearInterval(t)
  }, [campaignId])

  return (
    <PanelShell loading={loading} error={error} onRefresh={load} empty={rows?.length === 0 ? 'No sessions yet — the agent has not run.' : null}>
      <div className="space-y-3">
        {rows?.map((s) => <SessionRow key={s.id} session={s} />)}
      </div>
    </PanelShell>
  )
}

function SessionRow({ session: s }: { session: SessionRow }) {
  const [showTools, setShowTools] = useState(false)
  const tools = s.tool_history ?? []

  return (
    <div className="rounded-lg border bg-white p-3">
      <div className="flex items-center justify-between gap-3 text-xs">
        <div className="flex items-center gap-2">
          <span className="font-mono text-muted-foreground">{formatTime(s.started_at)}</span>
          {s.channel && <Badge variant="secondary" className="text-[10px]">{s.channel}</Badge>}
          {s.agent && <span className="text-muted-foreground">· {s.agent.name}</span>}
        </div>
        <div className="text-muted-foreground tabular-nums">
          {s.iterations} iter · {s.tool_calls} tools · {s.tokens.toLocaleString()} tokens
          {s.next_turn_in_hours !== null && <> · next {s.next_turn_in_hours}h</>}
        </div>
      </div>
      {s.summary && <p className="text-sm mt-2 leading-snug">{s.summary}</p>}
      {tools.length > 0 && (
        <div className="mt-2">
          <button
            type="button"
            className="text-[10px] uppercase tracking-wide text-[#390d58] hover:underline"
            onClick={() => setShowTools(v => !v)}
          >
            {showTools ? 'Hide' : 'Show'} tool trail ({tools.length})
          </button>
          {showTools && (
            <ol className="mt-2 space-y-1">
              {tools.map((t, i) => (
                <li key={i} className={`text-[11px] flex items-start gap-2 rounded border px-2 py-1 ${t.ok ? 'bg-muted/30' : 'bg-destructive/5 border-destructive/40'}`}>
                  <span className="font-mono tabular-nums text-muted-foreground w-6">#{t.iteration}</span>
                  <span className="font-mono">{t.tool}</span>
                  <span className="text-muted-foreground tabular-nums ml-auto">{t.duration_ms}ms</span>
                  {!t.ok && t.error && <span className="text-destructive">{t.error}</span>}
                </li>
              ))}
            </ol>
          )}
        </div>
      )}
    </div>
  )
}

// ── Memory ──────────────────────────────────────────────────────────────────

function MemoryTab({ campaignId }: { campaignId: number }) {
  const [data, setData] = useState<Record<string, MemoryEntry[]> | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get<{ memory: Record<string, MemoryEntry[]> }>(`/admin/marketing/campaigns/${campaignId}/memory`)
      .then((d) => setData(d.memory ?? {}))
      .catch(() => setError('Could not load memory.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
    const t = window.setInterval(load, 30_000)
    return () => window.clearInterval(t)
  }, [campaignId])

  const channels = data ? Object.keys(data) : []

  return (
    <PanelShell loading={loading} error={error} onRefresh={load} empty={channels.length === 0 ? 'Agent has not written any memory entries yet.' : null}>
      <div className="space-y-4">
        {channels.map((channel) => (
          <Card key={channel}>
            <CardHeader>
              <CardTitle className="text-sm">{channel}</CardTitle>
              <CardDescription>{data?.[channel].length ?? 0} entries</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              {data?.[channel].map((entry) => (
                <div key={entry.key} className="rounded-md border bg-muted/30 p-2">
                  <div className="flex justify-between text-xs">
                    <span className="font-mono font-semibold">{entry.key}</span>
                    {entry.updated_at && <span className="text-muted-foreground">{formatTime(entry.updated_at)}</span>}
                  </div>
                  <pre className="text-xs mt-1 whitespace-pre-wrap break-all">{JSON.stringify(entry.value, null, 2)}</pre>
                </div>
              ))}
            </CardContent>
          </Card>
        ))}
      </div>
    </PanelShell>
  )
}

// ── Watcher Events ──────────────────────────────────────────────────────────

function WatcherTab({ campaignId }: { campaignId: number }) {
  const [rows, setRows] = useState<WatcherEvent[] | null>(null)
  const [severity, setSeverity] = useState<string>('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    setError(null)
    const q = new URLSearchParams({ limit: '50', since_days: '30' })
    if (severity) q.set('severity', severity)
    api.get<{ events: WatcherEvent[] }>(`/admin/marketing/campaigns/${campaignId}/watcher-events?${q.toString()}`)
      .then((d) => setRows(d.events ?? []))
      .catch(() => setError('Could not load watcher events.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
    const t = window.setInterval(load, 30_000)
    return () => window.clearInterval(t)
  }, [campaignId, severity])

  return (
    <PanelShell loading={loading} error={error} onRefresh={load} empty={rows?.length === 0 ? 'No watcher events fired in the last 30 days.' : null}>
      <div className="space-y-3">
        <div className="flex gap-1 text-xs">
          {[
            { v: '', label: 'All' },
            { v: 'warning', label: 'Warning' },
            { v: 'critical', label: 'Critical' },
            { v: 'info', label: 'Info' },
          ].map((opt) => (
            <button
              key={opt.v}
              type="button"
              onClick={() => setSeverity(opt.v)}
              className={`px-2 py-0.5 rounded-md ${severity === opt.v ? 'bg-[#390d58] text-white' : 'bg-muted hover:bg-muted/70'}`}
            >
              {opt.label}
            </button>
          ))}
        </div>

        {rows?.map((e) => (
          <div
            key={e.id}
            className={`rounded-lg border p-3 ${
              e.severity === 'critical' ? 'border-destructive/40 bg-destructive/5' : e.severity === 'warning' ? 'border-amber-300 bg-amber-50' : 'bg-white'
            }`}
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <div className="text-xs font-mono text-muted-foreground">{e.watcher_slug} · {e.subject_key}</div>
                <p className="text-sm font-semibold">{e.title}</p>
                {e.summary && <p className="text-xs mt-1">{e.summary}</p>}
              </div>
              <div className="text-xs text-muted-foreground whitespace-nowrap">
                <Badge variant={e.severity === 'critical' ? 'destructive' : 'secondary'} className="text-[10px]">{e.severity}</Badge>
                <div className="mt-1">{formatTime(e.evaluated_at)}</div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </PanelShell>
  )
}

// ── Action log ──────────────────────────────────────────────────────────────

function ActionsTab({ campaignId }: { campaignId: number }) {
  const [rows, setRows] = useState<ActionLogEntry[] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get<{ entries: ActionLogEntry[] }>(`/admin/marketing/campaigns/${campaignId}/action-log?limit=50`)
      .then((d) => setRows(d.entries ?? []))
      .catch(() => setError('Could not load action log.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
    const t = window.setInterval(load, 30_000)
    return () => window.clearInterval(t)
  }, [campaignId])

  return (
    <PanelShell loading={loading} error={error} onRefresh={load} empty={rows?.length === 0 ? 'No actions taken yet.' : null}>
      <table className="w-full text-xs">
        <thead className="text-left text-muted-foreground">
          <tr>
            <th className="pb-2 font-medium">When</th>
            <th className="font-medium">Action</th>
            <th className="font-medium">Status</th>
            <th className="font-medium">Actor</th>
            <th className="font-medium">Channel</th>
          </tr>
        </thead>
        <tbody>
          {rows?.map((row) => (
            <tr key={row.id} className="border-t">
              <td className="py-2 font-mono">{row.created_at ? formatTime(row.created_at) : '—'}</td>
              <td className="font-mono">{row.action_slug}</td>
              <td>
                <Badge
                  variant={row.status === 'succeeded' ? 'secondary' : row.status === 'failed' ? 'destructive' : 'secondary'}
                  className="text-[10px]"
                >
                  {row.status}
                </Badge>
              </td>
              <td className="text-muted-foreground">{row.actor}</td>
              <td className="text-muted-foreground">{row.channel ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </PanelShell>
  )
}

// ── Shared shell ────────────────────────────────────────────────────────────

function PanelShell({
  loading,
  error,
  onRefresh,
  empty,
  children,
}: {
  loading: boolean
  error: string | null
  onRefresh: () => void
  empty: string | null
  children: React.ReactNode
}) {
  return (
    <div className="space-y-3">
      <div className="flex items-center justify-end">
        <Button variant="ghost" size="sm" onClick={onRefresh} disabled={loading}>
          <RefreshCw className={`h-3 w-3 mr-1 ${loading ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>
      {error && (
        <div className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive">{error}</div>
      )}
      {loading ? (
        <div className="flex items-center gap-2 text-xs text-muted-foreground py-4">
          <Loader2 className="h-3 w-3 animate-spin" /> Loading…
        </div>
      ) : empty ? (
        <div className="text-sm text-muted-foreground py-6 text-center">{empty}</div>
      ) : (
        children
      )}
    </div>
  )
}

function formatTime(iso: string): string {
  try {
    const d = new Date(iso)
    return d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}
