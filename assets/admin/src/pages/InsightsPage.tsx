import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, Loader2, Search, Sparkles, Lock, RefreshCw, Coins, Clock, Database } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { api } from '@/lib/api'
import { PremiumGate } from '@/components/beacon/PremiumGate'

type SignalInput = {
  key: string
  label: string
  type: 'text' | 'number'
  required?: boolean
  placeholder?: string
}

type SignalEntry = {
  slug: string
  provider: string
  operation: string
  label: string
  description: string
  discipline: string
  auth: 'central' | 'oauth_per_project'
  auth_provider?: string
  cost_credits: number
  cache_ttl_label: string
  inputs: SignalInput[]
}

type SignalCallResponse = {
  ok?: boolean
  signal?: string
  data: Record<string, unknown> | null
  cache: { hit: boolean; fetched_at: string | null; age_seconds: number; expires_at: string | null }
  cost: { credits_charged: number; would_charge_if_fresh: number }
  message?: string
  connection_required?: string
}

type View = { type: 'list' } | { type: 'tile'; slug: string }

export function InsightsPage() {
  const hasApiKey = window.BeaconData?.hasApiKey ?? false

  if (!hasApiKey) {
    return (
      <PremiumGate
        feature="Insights"
        description="Live, on-demand intelligence from across the marketing data ecosystem — backlinks, search performance, SERPs, keyword volume — surfaced without leaving WordPress. Requires a Beacon API key."
        icon={<Sparkles className="h-10 w-10" />}
        gradient="from-[#390d58] to-violet-600"
      />
    )
  }

  const [view, setView] = useState<View>({ type: 'list' })
  const [registry, setRegistry] = useState<SignalEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.get<{ signals: SignalEntry[] }>('/admin/insights/registry')
      .then((d) => setRegistry(d.signals ?? []))
      .catch(() => setError('Could not load the insights registry.'))
      .finally(() => setLoading(false))
  }, [])

  if (view.type === 'tile') {
    const entry = registry.find((s) => s.slug === view.slug)
    if (!entry) {
      return (
        <div>
          <p className="text-sm text-muted-foreground">Unknown insight: {view.slug}</p>
          <Button onClick={() => setView({ type: 'list' })} variant="ghost" className="mt-4">
            <ArrowLeft className="h-4 w-4 mr-2" /> Back
          </Button>
        </div>
      )
    }
    return <SignalTile entry={entry} onBack={() => setView({ type: 'list' })} />
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Insights</h1>
        <p className="text-sm text-muted-foreground mt-1">
          On-demand data lookups across SEO, content, and search performance. Cached results are free; fresh fetches charge credits.
        </p>
      </div>

      {error && (
        <Card className="border-destructive/40 bg-destructive/5">
          <CardContent className="pt-6 text-sm text-destructive">{error}</CardContent>
        </Card>
      )}

      {loading ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" /> Loading insights…
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {registry.map((s) => (
            <button
              key={s.slug}
              type="button"
              onClick={() => setView({ type: 'tile', slug: s.slug })}
              className="text-left"
            >
              <Card className="h-full hover:border-[#390d58]/30 hover:shadow-md transition-all">
                <CardHeader>
                  <div className="flex items-start justify-between gap-3">
                    <CardTitle className="text-base">{s.label}</CardTitle>
                    <Badge variant="secondary" className="shrink-0 text-[10px]">{s.discipline}</Badge>
                  </div>
                  <CardDescription>{s.description}</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1">
                      <Coins className="h-3 w-3" /> {s.cost_credits === 0 ? 'free' : `${s.cost_credits} cr`}
                    </span>
                    <span className="inline-flex items-center gap-1">
                      <Clock className="h-3 w-3" /> cache {s.cache_ttl_label}
                    </span>
                    {s.auth === 'oauth_per_project' && (
                      <span className="inline-flex items-center gap-1">
                        <Lock className="h-3 w-3" /> needs {s.auth_provider}
                      </span>
                    )}
                  </div>
                </CardContent>
              </Card>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

function SignalTile({ entry, onBack }: { entry: SignalEntry; onBack: () => void }) {
  const [args, setArgs] = useState<Record<string, string>>(() =>
    Object.fromEntries(entry.inputs.map((i) => [i.key, ''])),
  )
  const [forceFresh, setForceFresh] = useState(false)
  const [running, setRunning] = useState(false)
  const [response, setResponse] = useState<SignalCallResponse | null>(null)
  const [error, setError] = useState<string | null>(null)

  const allRequiredFilled = useMemo(
    () => entry.inputs.every((i) => !i.required || (args[i.key] ?? '').trim() !== ''),
    [args, entry.inputs],
  )

  const submit = async () => {
    setError(null)
    setRunning(true)
    setResponse(null)
    try {
      const cleanArgs: Record<string, string | number> = {}
      for (const i of entry.inputs) {
        const v = (args[i.key] ?? '').trim()
        if (v === '') continue
        cleanArgs[i.key] = i.type === 'number' ? Number(v) : v
      }
      const r = await api.post<SignalCallResponse>('/admin/insights/signal', {
        provider: entry.provider,
        operation: entry.operation,
        args: cleanArgs,
        options: { force_fresh: forceFresh },
      })
      setResponse(r)
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Signal lookup failed.'
      setError(msg)
    } finally {
      setRunning(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="h-4 w-4 mr-1" /> Back
        </Button>
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{entry.label}</h1>
          <p className="text-sm text-muted-foreground">{entry.description}</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Inputs</CardTitle>
          <CardDescription>
            Cached result is free. {entry.cost_credits > 0 ? `Fresh fetch costs ${entry.cost_credits} credits.` : 'This signal is free.'}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {entry.inputs.map((i) => (
            <div key={i.key} className="space-y-2">
              <Label htmlFor={`input-${i.key}`}>{i.label}{i.required && <span className="text-destructive ml-1">*</span>}</Label>
              <Input
                id={`input-${i.key}`}
                type={i.type === 'number' ? 'number' : 'text'}
                placeholder={i.placeholder}
                value={args[i.key] ?? ''}
                onChange={(e) => setArgs((prev) => ({ ...prev, [i.key]: e.target.value }))}
              />
            </div>
          ))}
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={forceFresh}
              onChange={(e) => setForceFresh(e.target.checked)}
              className="rounded"
            />
            <RefreshCw className="h-3 w-3" />
            Force fresh fetch (bypass cache, charges credits)
          </label>
          <div className="flex justify-end">
            <Button onClick={submit} disabled={!allRequiredFilled || running}>
              {running ? <><Loader2 className="h-4 w-4 mr-2 animate-spin" /> Looking up…</> : <><Search className="h-4 w-4 mr-2" /> Run lookup</>}
            </Button>
          </div>
        </CardContent>
      </Card>

      {error && (
        <Card className="border-destructive/40 bg-destructive/5">
          <CardContent className="pt-6 text-sm text-destructive whitespace-pre-wrap">{error}</CardContent>
        </Card>
      )}

      {response && <ResultPanel entry={entry} response={response} />}
    </div>
  )
}

function ResultPanel({ entry, response }: { entry: SignalEntry; response: SignalCallResponse }) {
  if (response.connection_required) {
    return (
      <Card className="border-amber-300 bg-amber-50">
        <CardContent className="pt-6 space-y-2">
          <p className="text-sm font-semibold">Provider not connected</p>
          <p className="text-sm text-muted-foreground">
            This insight needs <span className="font-mono">{response.connection_required}</span> connected. Open Configuration → Connections to authorise it.
          </p>
        </CardContent>
      </Card>
    )
  }

  const cacheBadge = response.cache?.hit ? (
    <Badge variant="secondary" className="text-[10px]">cached · {formatAge(response.cache.age_seconds)}</Badge>
  ) : (
    <Badge className="text-[10px]">fresh fetch</Badge>
  )

  const charged = response.cost?.credits_charged ?? 0
  const wouldCharge = response.cost?.would_charge_if_fresh ?? 0

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between gap-3">
          <CardTitle className="text-base">Result</CardTitle>
          <div className="flex items-center gap-2">
            {cacheBadge}
            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
              <Coins className="h-3 w-3" /> {charged === 0 ? 'no charge' : `${charged} cr charged`}
              {charged === 0 && wouldCharge > 0 && ` · would cost ${wouldCharge} fresh`}
            </span>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <SignalResultBody entry={entry} data={response.data} />
      </CardContent>
    </Card>
  )
}

function SignalResultBody({ entry, data }: { entry: SignalEntry; data: Record<string, unknown> | null }) {
  if (!data) {
    return <p className="text-sm text-muted-foreground">No data available.</p>
  }

  // Provider-specific formatting for the signals we know about.
  if (entry.slug === 'dataforseo.backlinks.summary') return <BacklinksSummary data={data} />
  if (entry.slug === 'dataforseo.backlinks.new_lost') return <BacklinksNewLost data={data} />
  if (entry.slug === 'dataforseo.backlinks.referring_domains') return <ReferringDomains data={data} />
  if (entry.slug === 'dataforseo.keywords.suggestions') return <KeywordSuggestions data={data} />
  if (entry.slug === 'dataforseo.serp.organic') return <SerpOrganic data={data} />
  if (entry.slug === 'dataforseo.serp.position_tracking') return <PositionTracking data={data} />
  if (entry.slug === 'gsc.queries.top') return <GscQueries data={data} />
  if (entry.slug === 'gsc.pages.top') return <GscPages data={data} />
  if (entry.slug === 'gsc.summary') return <GscSummary data={data} />
  if (entry.slug === 'psi.cwv') return <PsiCwv data={data} />
  if (entry.slug === 'ga4.acquisition.channel') return <Ga4Acquisition data={data} />
  if (entry.slug === 'ga4.engagement.pages') return <Ga4Engagement data={data} />
  if (entry.slug === 'ga4.conversions.by_event') return <Ga4Conversions data={data} />
  if (entry.slug === 'googleads.campaigns.performance') return <GoogleAdsCampaigns data={data} />
  if (entry.slug === 'googleads.search_terms') return <GoogleAdsSearchTerms data={data} />
  if (entry.slug === 'googleads.quality_score') return <GoogleAdsQualityScore data={data} />
  if (entry.slug === 'meta.page.insights') return <MetaPageInsights data={data} />
  if (entry.slug === 'meta.page.posts') return <MetaPagePosts data={data} />

  // Fallback: pretty-printed JSON.
  return (
    <pre className="text-xs whitespace-pre-wrap break-all bg-muted/50 rounded-md p-3 overflow-x-auto">
      {JSON.stringify(data, null, 2)}
    </pre>
  )
}

// ── Per-signal renderers ───────────────────────────────────────────────────

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-lg border bg-muted/30 px-3 py-2">
      <div className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="text-lg font-semibold">{typeof value === 'number' ? value.toLocaleString() : value}</div>
    </div>
  )
}

function BacklinksSummary({ data }: { data: Record<string, unknown> }) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <Stat label="Backlinks" value={(data.backlinks_total as number | undefined) ?? 0} />
        <Stat label="Referring domains" value={(data.referring_domains as number | undefined) ?? 0} />
        <Stat label="Referring IPs" value={(data.referring_ips as number | undefined) ?? 0} />
        <Stat label="Broken backlinks" value={(data.broken_backlinks as number | undefined) ?? 0} />
      </div>
      {data.country_distribution && typeof data.country_distribution === 'object' && (
        <div>
          <p className="text-sm font-semibold mb-2">Top countries</p>
          <DistributionList items={data.country_distribution as Record<string, number>} />
        </div>
      )}
    </div>
  )
}

function KeywordSuggestions({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No keyword suggestions returned.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Keyword</th><th>Volume</th><th>Competition</th><th>CPC</th></tr>
      </thead>
      <tbody>
        {items.map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2">{String(row.keyword ?? '')}</td>
            <td>{row.search_volume == null ? '—' : Number(row.search_volume).toLocaleString()}</td>
            <td>{String(row.competition ?? '—')}</td>
            <td>{row.cpc == null ? '—' : `£${Number(row.cpc).toFixed(2)}`}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function SerpOrganic({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No SERP results returned.</p>
  return (
    <ol className="space-y-2 text-sm">
      {items.slice(0, 20).map((row, i) => (
        <li key={i} className="border rounded-md px-3 py-2">
          <div className="text-[10px] uppercase tracking-wide text-muted-foreground">#{row.rank} · {String(row.domain ?? '')}</div>
          <div className="font-medium">{String(row.title ?? '')}</div>
          <a href={String(row.url ?? '')} className="text-xs text-[#390d58] break-all" target="_blank" rel="noreferrer">{String(row.url ?? '')}</a>
        </li>
      ))}
    </ol>
  )
}

function GscQueries({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No queries returned.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Query</th><th>Clicks</th><th>Impr.</th><th>CTR</th><th>Pos.</th></tr>
      </thead>
      <tbody>
        {items.slice(0, 30).map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2">{String(row.query ?? '')}</td>
            <td>{Number(row.clicks ?? 0).toLocaleString()}</td>
            <td>{Number(row.impressions ?? 0).toLocaleString()}</td>
            <td>{((Number(row.ctr ?? 0)) * 100).toFixed(2)}%</td>
            <td>{Number(row.position ?? 0).toFixed(1)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function GscPages({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No pages returned.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Page</th><th>Clicks</th><th>Impr.</th><th>CTR</th><th>Pos.</th></tr>
      </thead>
      <tbody>
        {items.slice(0, 30).map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2 max-w-md truncate">{String(row.page ?? '')}</td>
            <td>{Number(row.clicks ?? 0).toLocaleString()}</td>
            <td>{Number(row.impressions ?? 0).toLocaleString()}</td>
            <td>{((Number(row.ctr ?? 0)) * 100).toFixed(2)}%</td>
            <td>{Number(row.position ?? 0).toFixed(1)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function DistributionList({ items }: { items: Record<string, number> }) {
  const entries = Object.entries(items)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 8)
  if (entries.length === 0) return <p className="text-xs text-muted-foreground">No data.</p>
  return (
    <ul className="space-y-1 text-sm">
      {entries.map(([k, v]) => (
        <li key={k} className="flex justify-between border-b last:border-b-0 py-1">
          <span><Database className="h-3 w-3 inline mr-1" />{k}</span>
          <span className="font-mono text-xs text-muted-foreground">{v.toLocaleString()}</span>
        </li>
      ))}
    </ul>
  )
}

function formatAge(seconds: number): string {
  if (seconds < 60) return `${seconds}s old`
  if (seconds < 3600) return `${Math.round(seconds / 60)}m old`
  if (seconds < 86400) return `${Math.round(seconds / 3600)}h old`
  return `${Math.round(seconds / 86400)}d old`
}

// ── Renderers added in round 9 ──────────────────────────────────────────────

function BacklinksNewLost({ data }: { data: Record<string, unknown> }) {
  const totals = (data.totals as Record<string, number> | undefined) ?? {}
  const series = Array.isArray(data.series) ? (data.series as Array<Record<string, unknown>>) : []
  const net = (totals.new ?? 0) - (totals.lost ?? 0)
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-3 gap-3">
        <Stat label="Gained" value={totals.new ?? 0} />
        <Stat label="Lost" value={totals.lost ?? 0} />
        <Stat label="Net" value={net} />
      </div>
      {series.length > 0 && (
        <table className="w-full text-sm">
          <thead className="text-left text-xs text-muted-foreground">
            <tr><th className="py-2">Date</th><th>Backlinks total</th><th>Ref. domains</th><th>+</th><th>−</th></tr>
          </thead>
          <tbody>
            {series.map((row, i) => (
              <tr key={i} className="border-t">
                <td className="py-2">{String(row.date ?? '')}</td>
                <td>{Number(row.backlinks_total ?? 0).toLocaleString()}</td>
                <td>{Number(row.referring_domains ?? 0).toLocaleString()}</td>
                <td className="text-green-700">+{Number(row.new_backlinks ?? 0)}</td>
                <td className="text-red-700">−{Number(row.lost_backlinks ?? 0)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

function ReferringDomains({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No referring domains returned.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Domain</th><th>Backlinks</th><th>Rank</th><th>First seen</th></tr>
      </thead>
      <tbody>
        {items.slice(0, 30).map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2 font-mono text-xs">{String(row.domain ?? '')}</td>
            <td>{Number(row.backlinks ?? 0).toLocaleString()}</td>
            <td>{row.rank == null ? '—' : String(row.rank)}</td>
            <td className="text-muted-foreground">{String(row.first_seen ?? '').split('T')[0]}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function PositionTracking({ data }: { data: Record<string, unknown> }) {
  const found = Boolean(data.found)
  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <Stat label="Keyword" value={String(data.keyword ?? '—')} />
        <Stat label="Target" value={String(data.target ?? '—')} />
        <Stat label="Rank" value={found ? String(data.rank ?? '—') : 'Not in top 100'} />
      </div>
      {found && data.url && (
        <p className="text-sm">
          <span className="text-muted-foreground">URL:</span>{' '}
          <a href={String(data.url)} target="_blank" rel="noreferrer" className="text-[#390d58] break-all">{String(data.url)}</a>
        </p>
      )}
    </div>
  )
}

function GscSummary({ data }: { data: Record<string, unknown> }) {
  const totals = (data.totals as Record<string, number> | undefined) ?? {}
  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <Stat label="Clicks" value={totals.clicks ?? 0} />
      <Stat label="Impressions" value={totals.impressions ?? 0} />
      <Stat label="CTR" value={`${((totals.ctr ?? 0) * 100).toFixed(2)}%`} />
      <Stat label="Avg position" value={(totals.position ?? 0).toFixed(1)} />
    </div>
  )
}

function PsiCwv({ data }: { data: Record<string, unknown> }) {
  const lab = (data.lab as Record<string, number | null> | undefined) ?? {}
  const field = data.field as Record<string, unknown> | null | undefined
  const score = Number(data.performance_score ?? 0)
  const scoreColor = score >= 0.9 ? 'text-green-700' : score >= 0.5 ? 'text-amber-700' : 'text-red-700'
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="rounded-lg border bg-muted/30 px-3 py-2">
          <div className="text-[10px] uppercase tracking-wide text-muted-foreground">Performance score</div>
          <div className={`text-lg font-semibold ${scoreColor}`}>{(score * 100).toFixed(0)}</div>
        </div>
        <Stat label="LCP (ms)" value={lab.lcp_ms ?? '—'} />
        <Stat label="INP (ms)" value={lab.inp_ms ?? '—'} />
        <Stat label="CLS" value={lab.cls ?? '—'} />
      </div>
      {field && (
        <div>
          <p className="text-sm font-semibold mb-2">Field data ({String((field.overall_category ?? '?'))})</p>
          <div className="grid grid-cols-3 gap-3 text-sm">
            <Stat label="LCP" value={String(field.lcp_ms ?? '—')} />
            <Stat label="INP" value={String(field.inp_ms ?? '—')} />
            <Stat label="CLS" value={String(field.cls ?? '—')} />
          </div>
        </div>
      )}
    </div>
  )
}

function Ga4Acquisition({ data }: { data: Record<string, unknown> }) {
  const rows = Array.isArray(data.rows) ? (data.rows as Array<Record<string, unknown>>) : []
  if (rows.length === 0) return <p className="text-sm text-muted-foreground">No data returned.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Channel</th><th>Sessions</th><th>Engaged</th><th>Conversions</th></tr>
      </thead>
      <tbody>
        {rows.map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2">{String(row.channel ?? '')}</td>
            <td>{Number(row.sessions ?? 0).toLocaleString()}</td>
            <td>{Number(row.engaged_sessions ?? 0).toLocaleString()}</td>
            <td>{Number(row.conversions ?? 0).toLocaleString()}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function Ga4Engagement({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No data returned.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Page</th><th>Views</th><th>Avg engagement (s)</th></tr>
      </thead>
      <tbody>
        {items.slice(0, 30).map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2 max-w-md truncate">{String(row.page_path ?? '')}</td>
            <td>{Number(row.views ?? 0).toLocaleString()}</td>
            <td>{Number(row.avg_engagement_seconds ?? 0)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function Ga4Conversions({ data }: { data: Record<string, unknown> }) {
  const events = Array.isArray(data.events) ? (data.events as Array<Record<string, unknown>>) : []
  if (events.length === 0) return <p className="text-sm text-muted-foreground">No conversion events returned.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Event</th><th>Count</th><th>Value</th></tr>
      </thead>
      <tbody>
        {events.map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2 font-mono text-xs">{String(row.event ?? '')}</td>
            <td>{Number(row.count ?? 0).toLocaleString()}</td>
            <td>£{Number(row.value ?? 0).toFixed(2)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function GoogleAdsCampaigns({ data }: { data: Record<string, unknown> }) {
  const campaigns = Array.isArray(data.campaigns) ? (data.campaigns as Array<Record<string, unknown>>) : []
  if (campaigns.length === 0) return <p className="text-sm text-muted-foreground">No campaigns returned.</p>
  const totalCost = campaigns.reduce((sum, row) => sum + Number(row.cost_micros ?? 0), 0)
  const totalConv = campaigns.reduce((sum, row) => sum + Number(row.conversions ?? 0), 0)
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <Stat label="Campaigns" value={campaigns.length} />
        <Stat label="Total spend" value={`£${(totalCost / 1_000_000).toFixed(2)}`} />
        <Stat label="Total conversions" value={totalConv.toFixed(1)} />
      </div>
      <table className="w-full text-sm">
        <thead className="text-left text-xs text-muted-foreground">
          <tr><th className="py-2">Campaign</th><th>Status</th><th>Spend</th><th>Clicks</th><th>Conv.</th></tr>
        </thead>
        <tbody>
          {campaigns.map((row, i) => (
            <tr key={i} className="border-t">
              <td className="py-2">{String(row.name ?? '')}</td>
              <td><Badge variant="secondary" className="text-[10px]">{String(row.status ?? '')}</Badge></td>
              <td>£{(Number(row.cost_micros ?? 0) / 1_000_000).toFixed(2)}</td>
              <td>{Number(row.clicks ?? 0).toLocaleString()}</td>
              <td>{Number(row.conversions ?? 0).toFixed(1)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function GoogleAdsSearchTerms({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No search-term data.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Search term</th><th>Campaign</th><th>Clicks</th><th>Cost</th><th>Conv.</th></tr>
      </thead>
      <tbody>
        {items.slice(0, 50).map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2">{String(row.search_term ?? '')}</td>
            <td className="text-xs text-muted-foreground">{String(row.campaign ?? '')}</td>
            <td>{Number(row.clicks ?? 0).toLocaleString()}</td>
            <td>£{(Number(row.cost_micros ?? 0) / 1_000_000).toFixed(2)}</td>
            <td>{Number(row.conversions ?? 0).toFixed(1)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function GoogleAdsQualityScore({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No keyword data.</p>
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-xs text-muted-foreground">
        <tr><th className="py-2">Keyword</th><th>Match</th><th>QS</th><th>Expected CTR</th><th>Ad relev.</th><th>Landing pg.</th></tr>
      </thead>
      <tbody>
        {items.slice(0, 50).map((row, i) => (
          <tr key={i} className="border-t">
            <td className="py-2">{String(row.keyword ?? '')}</td>
            <td className="text-xs text-muted-foreground">{String(row.match_type ?? '')}</td>
            <td className="font-semibold">{String(row.quality_score ?? '—')}</td>
            <td className="text-xs">{String(row.expected_ctr ?? '—')}</td>
            <td className="text-xs">{String(row.ad_relevance ?? '—')}</td>
            <td className="text-xs">{String(row.landing_page_quality ?? '—')}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function MetaPageInsights({ data }: { data: Record<string, unknown> }) {
  const metrics = (data.metrics as Record<string, number | null> | undefined) ?? {}
  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <Stat label="Reach" value={metrics.page_impressions ?? 0} />
      <Stat label="Engagements" value={metrics.page_post_engagements ?? 0} />
      <Stat label="Page views" value={metrics.page_views_total ?? 0} />
      <Stat label="Followers" value={metrics.page_fan_count ?? '—'} />
    </div>
  )
}

function MetaPagePosts({ data }: { data: Record<string, unknown> }) {
  const items = Array.isArray(data.items) ? (data.items as Array<Record<string, unknown>>) : []
  if (items.length === 0) return <p className="text-sm text-muted-foreground">No recent posts.</p>
  return (
    <ul className="space-y-2 text-sm">
      {items.map((row, i) => (
        <li key={i} className="border rounded-md px-3 py-2">
          <div className="text-[10px] text-muted-foreground">{String(row.created_time ?? '').split('T')[0]}</div>
          <div className="text-sm">{String(row.message ?? '').slice(0, 220)}</div>
          <div className="text-xs text-muted-foreground mt-1">
            {Number(row.reactions ?? 0)} reactions · {Number(row.comments ?? 0)} comments · {Number(row.shares ?? 0)} shares
          </div>
        </li>
      ))}
    </ul>
  )
}
