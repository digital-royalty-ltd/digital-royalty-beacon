import { useEffect, useState } from 'react'
import { Radio, Loader2, CheckCircle2, XCircle, ChevronRight, Zap, Clock, AlertTriangle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { api } from '@/lib/api'

interface PollerTrace {
    poll_ok:       boolean
    poll_code:     number | null
    poll_message:  string | null
    pending_count: number
    processed:     { id: string; key: string; action: string; message: string | null }[]
    duration_ms:   number
}

interface CronEvent {
    hook:        string
    label:       string
    scheduled:   boolean
    next_run_at: string | null
    recurrence:  string | null
    interval:    number | null
}

interface CronStatus {
    disable_wp_cron:   boolean
    alternate_wp_cron: boolean
    doing_cron:        boolean
    server_time_utc:   string
    events:            CronEvent[]
}

interface HeartbeatTrace {
    heartbeat: {
        payload: Record<string, unknown>
        ok:      boolean
        code:    number | null
        message: string | null
        body:    Record<string, unknown> | null
    }
    catalog: {
        published?:         boolean
        changed?:           boolean
        skipped_reason?:    string | null
        hash?:              string
        previous_hash?:     string
        count?:             number
        response_code?:     number | null
        response_body?:     Record<string, unknown> | null
        response_message?:  string | null
        catalog_keys?:      string[]
        error?:             string
    }
    duration_ms: number
}

/**
 * Runs the plugin → Laravel heartbeat + catalog publish on demand and renders
 * the full trace. Exists because the automation catalog is empty until the
 * plugin publishes, and the only way to see what's failing was to dig in
 * the Debug logs. This makes it a one-click operator action.
 */
export function HeartbeatDiagnostics() {
    const [trace, setTrace]         = useState<HeartbeatTrace | null>(null)
    const [pollerTrace, setPoller]  = useState<PollerTrace | null>(null)
    const [cron, setCron]           = useState<CronStatus | null>(null)
    const [running, setRunning]     = useState(false)
    const [polling, setPolling]     = useState(false)
    const [error, setError]         = useState<string | null>(null)

    // Load cron status on first mount — cheap read.
    useEffect(() => {
        api.get<CronStatus>('/campaigns/diagnostics/cron-status')
            .then(setCron)
            .catch(() => null)
    }, [])

    const run = async (force: boolean) => {
        setRunning(true)
        setError(null)
        try {
            const res = await api.post<HeartbeatTrace>('/campaigns/diagnostics/heartbeat', { force_catalog: force })
            setTrace(res)
            setExpand(true)
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Heartbeat run failed.')
        } finally {
            setRunning(false)
        }
    }

    const runPoller = async () => {
        setPolling(true)
        setError(null)
        try {
            const res = await api.post<PollerTrace>('/campaigns/diagnostics/poll', {})
            setPoller(res)
            setExpand(true)
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Poller run failed.')
        } finally {
            setPolling(false)
        }
    }

    return (
        <Card className="border-[#390d58]/20 overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
                            <Radio className="h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle className="text-lg text-[#390d58]">Beacon diagnostics</CardTitle>
                            <CardDescription>Run a heartbeat, publish the catalog, or tick the automation poller on demand</CardDescription>
                        </div>
                    </div>
                    {trace && (
                        <span className="text-[11px] text-muted-foreground">
                            Last run: {trace.duration_ms}ms
                        </span>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    {cron && <CronStatusBlock cron={cron} />}

                    <div className="flex gap-2 flex-wrap">
                        <Button size="sm" onClick={() => run(false)} disabled={running} className="bg-[#390d58] hover:bg-[#2d0a47] text-white">
                            {running && <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />}
                            Run heartbeat now
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => run(true)} disabled={running}>
                            Force-publish catalog
                        </Button>
                        <Button size="sm" variant="outline" onClick={runPoller} disabled={polling}>
                            {polling
                                ? <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />
                                : <Zap className="h-3.5 w-3.5 mr-1.5" />}
                            Run automation poller now
                        </Button>
                    </div>

                    {error && (
                        <p className="text-xs text-red-600 bg-red-50 border border-red-100 rounded px-3 py-2">{error}</p>
                    )}

                    {trace && (
                        <div className="space-y-4">
                            {/* Heartbeat section */}
                            <TraceBlock
                                title="Heartbeat"
                                ok={trace.heartbeat.ok}
                                code={trace.heartbeat.code}
                                summary={trace.heartbeat.message ?? (trace.heartbeat.ok ? 'OK' : 'No response')}
                                sections={[
                                    { label: 'Payload sent',  data: trace.heartbeat.payload },
                                    { label: 'Response body', data: trace.heartbeat.body ?? null },
                                ]}
                            />

                            {/* Catalog section */}
                            <TraceBlock
                                title="Catalog publish"
                                ok={trace.catalog.error ? false : (trace.catalog.published ?? !trace.catalog.skipped_reason)}
                                code={trace.catalog.response_code ?? null}
                                summary={catalogSummary(trace.catalog)}
                                sections={[
                                    {
                                        label: 'Local catalog',
                                        data: {
                                            count: trace.catalog.count,
                                            hash: trace.catalog.hash,
                                            previous_hash: trace.catalog.previous_hash,
                                            automation_keys: trace.catalog.catalog_keys,
                                        },
                                    },
                                    { label: 'Response body', data: trace.catalog.response_body ?? null },
                                ]}
                            />

                            <p className="text-[11px] text-muted-foreground">
                                Duration: {trace.duration_ms}ms. Full verbose logs are in the Debug page.
                            </p>
                        </div>
                    )}

                    {pollerTrace && (
                        <div className="space-y-2">
                            <TraceBlock
                                title="Automation poller"
                                ok={pollerTrace.poll_ok}
                                code={pollerTrace.poll_code}
                                summary={pollerTrace.poll_ok
                                    ? `Processed ${pollerTrace.processed.length} of ${pollerTrace.pending_count} pending`
                                    : (pollerTrace.poll_message ?? 'Poll failed')}
                                sections={[
                                    {
                                        label: 'Processed requests',
                                        data: pollerTrace.processed.length === 0 ? null : pollerTrace.processed,
                                    },
                                ]}
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Duration: {pollerTrace.duration_ms}ms.
                            </p>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    )
}

function catalogSummary(c: HeartbeatTrace['catalog']): string {
    if (c.error) return `Error: ${c.error}`
    if (c.skipped_reason) return `Skipped — ${c.skipped_reason}`
    if (c.published) return `Published ${c.count ?? 0} automations`
    if (c.response_message) return c.response_message
    return 'No catalog action'
}

function CronStatusBlock({ cron }: { cron: CronStatus }) {
    const cronDisabled = cron.disable_wp_cron
    const hasScheduledEvents = cron.events.every(e => e.scheduled)
    const overallOk = !cronDisabled && hasScheduledEvents

    return (
        <div className={`rounded-lg border px-4 py-3 ${overallOk ? 'bg-muted/20' : 'border-amber-200 bg-amber-50'}`}>
            <div className="flex items-center gap-2 mb-2">
                <Clock className="h-4 w-4 text-muted-foreground" />
                <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    WP cron status
                </span>
                {overallOk
                    ? <CheckCircle2 className="h-4 w-4 text-emerald-600 ml-auto" />
                    : <AlertTriangle className="h-4 w-4 text-amber-600 ml-auto" />}
            </div>

            {cronDisabled && (
                <p className="text-xs text-amber-900 mb-2">
                    <span className="font-medium">DISABLE_WP_CRON is set in wp-config.php.</span> Scheduled events
                    won't fire automatically. Set up a real system cron hitting{' '}
                    <code className="bg-white/70 rounded px-1 text-[11px]">wp-cron.php</code>{' '}
                    or remove the constant.
                </p>
            )}

            {cron.alternate_wp_cron && (
                <p className="text-xs text-muted-foreground mb-2">
                    ALTERNATE_WP_CRON is enabled — cron will fire on frontend requests only.
                </p>
            )}

            <ul className="space-y-1.5 text-xs">
                {cron.events.map(e => (
                    <li key={e.hook} className="flex items-center gap-2">
                        {e.scheduled
                            ? <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                            : <XCircle className="h-3.5 w-3.5 text-red-600 shrink-0" />}
                        <span className="font-medium">{e.label}</span>
                        <span className="text-muted-foreground ml-auto">
                            {e.scheduled && e.next_run_at
                                ? `next: ${nextRunLabel(e.next_run_at)}`
                                : 'NOT SCHEDULED'}
                        </span>
                    </li>
                ))}
            </ul>

            <p className="text-[10px] text-muted-foreground mt-2">
                Server time (UTC): {cron.server_time_utc}
            </p>
        </div>
    )
}

function nextRunLabel(iso: string): string {
    const then = new Date(iso).getTime()
    const ms = then - Date.now()
    if (ms < 0) {
        const overdueMins = Math.floor(-ms / 60000)
        return `OVERDUE by ${overdueMins}m`
    }
    const mins = Math.floor(ms / 60000)
    if (mins < 1)  return 'in <1m'
    if (mins < 60) return `in ${mins}m`
    const hours = Math.floor(mins / 60)
    if (hours < 24) return `in ${hours}h${mins % 60 > 0 ? ` ${mins % 60}m` : ''}`
    return new Date(iso).toLocaleString()
}

function TraceBlock({
    title,
    ok,
    code,
    summary,
    sections,
}: {
    title:    string
    ok:       boolean
    code:     number | null
    summary:  string
    sections: { label: string; data: unknown }[]
}) {
    return (
        <div className="rounded-lg border">
            <div className="flex items-center gap-2 px-4 py-3 border-b">
                {ok ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : <XCircle className="h-4 w-4 text-red-600" />}
                <span className="text-sm font-semibold">{title}</span>
                {code !== null && (
                    <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${ok ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}`}>
                        {code}
                    </span>
                )}
                <span className="text-xs text-muted-foreground ml-auto truncate">{summary}</span>
            </div>
            <div className="divide-y">
                {sections.map(s => (
                    <details key={s.label} className="px-4 py-2 group">
                        <summary className="cursor-pointer text-xs text-muted-foreground list-none flex items-center gap-1">
                            <ChevronRight className="h-3 w-3 group-open:rotate-90 transition-transform" />
                            {s.label}
                        </summary>
                        <pre className="mt-2 text-[11px] bg-muted/40 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all max-h-[200px] overflow-y-auto">
                            {s.data ? JSON.stringify(s.data, null, 2) : '(empty)'}
                        </pre>
                    </details>
                ))}
            </div>
        </div>
    )
}
