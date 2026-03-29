import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Sparkles, ArrowRight, CheckCircle2, AlertCircle, Clock,
  Wrench, Zap, Target, Link2, FileText, LayoutGrid, Map,
  Loader2, RefreshCw,
} from 'lucide-react'
import { api } from '@/lib/api'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ReportRow {
  type:         string
  status:       string
  submitted_at: string | null
}

interface BootstrapData {
  connection: {
    has_api_key:  boolean
    connected_at: string | null
  }
  reports:           ReportRow[]
  report_stale_days:  Record<string, number | null>
  oauth_connections:  Record<string, boolean>
}

// ─── Score calculation ────────────────────────────────────────────────────────

const CORE_WEIGHT        = 0.7
const INTEGRATION_WEIGHT = 0.3

const REPORT_META: Record<string, { label: string; icon: React.ReactNode }> = {
  website_profile:       { label: 'Website Profile',  icon: <FileText  className="h-4 w-4" /> },
  website_content_areas: { label: 'Content Areas',    icon: <LayoutGrid className="h-4 w-4" /> },
  website_sitemap:       { label: 'Site Map',         icon: <Map        className="h-4 w-4" /> },
}

const PROVIDER_LABELS: Record<string, string> = {
  'google-search-console': 'Google Search Console',
  'google-analytics':      'Google Analytics',
  'google-ads':            'Google Ads',
  'bing-ads':              'Bing Ads',
  'facebook':              'Facebook',
  'twitter':               'Twitter / X',
  'linkedin':              'LinkedIn',
}

function isReportFresh(report: ReportRow | undefined, staleDays: number | null): boolean {
  if (!report || report.status !== 'submitted' || !report.submitted_at) return false
  if (staleDays === null) return true
  const age = (Date.now() - new Date(report.submitted_at).getTime()) / 86_400_000
  return age <= staleDays
}

function calcScore(data: BootstrapData): {
  pct:                number
  coreComplete:       number
  coreTotal:          number
  integrationCount:   number
  integrationTotal:   number
  coreItems:          { label: string; ok: boolean; icon: React.ReactNode }[]
  integrationItems:   { label: string; ok: boolean }[]
} {
  // Core items
  const reportTypes   = Object.keys(REPORT_META)
  const coreItems = [
    {
      label: 'Beacon API key connected',
      ok:    data.connection.has_api_key,
      icon:  <Sparkles className="h-4 w-4" />,
    },
    ...reportTypes.map(type => {
      const report    = data.reports.find(r => r.type === type)
      const staleDays = data.report_stale_days[type] ?? null
      const fresh     = isReportFresh(report, staleDays)
      const meta      = REPORT_META[type]
      return {
        label: meta.label + (staleDays ? ` (fresh within ${staleDays}d)` : ''),
        ok:    fresh,
        icon:  meta.icon,
      }
    }),
  ]

  const coreComplete = coreItems.filter(i => i.ok).length
  const coreTotal    = coreItems.length

  // Integration items
  const integrationItems = Object.entries(PROVIDER_LABELS).map(([key, label]) => ({
    label,
    ok: !!data.oauth_connections[key],
  }))

  const integrationCount = integrationItems.filter(i => i.ok).length
  const integrationTotal = integrationItems.length

  const coreScore        = coreTotal        > 0 ? coreComplete / coreTotal               : 0
  const integrationScore = integrationTotal > 0 ? integrationCount / integrationTotal : 0

  const pct = Math.round((coreScore * CORE_WEIGHT + integrationScore * INTEGRATION_WEIGHT) * 100)

  return { pct, coreComplete, coreTotal, integrationCount, integrationTotal, coreItems, integrationItems }
}

// ─── Subcomponents ────────────────────────────────────────────────────────────

function ScoreRing({ pct }: { pct: number }) {
  const radius      = 52
  const circumference = 2 * Math.PI * radius
  const dash        = (pct / 100) * circumference

  const color = pct >= 80 ? '#10b981' : pct >= 50 ? '#f59e0b' : '#390d58'

  return (
    <div className="relative flex items-center justify-center w-36 h-36">
      <svg className="absolute inset-0 -rotate-90" viewBox="0 0 120 120">
        <circle cx="60" cy="60" r={radius} fill="none" stroke="#e5e7eb" strokeWidth="10" />
        <circle
          cx="60" cy="60" r={radius}
          fill="none"
          stroke={color}
          strokeWidth="10"
          strokeLinecap="round"
          strokeDasharray={`${dash} ${circumference}`}
          style={{ transition: 'stroke-dasharray 0.6s ease' }}
        />
      </svg>
      <div className="text-center relative">
        <p className="text-3xl font-bold text-[#390d58] leading-none">{pct}%</p>
        <p className="text-xs text-muted-foreground mt-1">complete</p>
      </div>
    </div>
  )
}

function ScoreItem({ label, ok, icon }: { label: string; ok: boolean; icon?: React.ReactNode }) {
  return (
    <div className="flex items-center gap-2.5 py-1.5">
      <span className={ok ? 'text-emerald-500' : 'text-muted-foreground/40'}>
        {ok
          ? <CheckCircle2 className="h-4 w-4" />
          : <AlertCircle  className="h-4 w-4" />}
      </span>
      {icon && <span className={`${ok ? 'text-[#390d58]' : 'text-muted-foreground/50'}`}>{icon}</span>}
      <span className={`text-sm ${ok ? 'text-foreground' : 'text-muted-foreground'}`}>{label}</span>
    </div>
  )
}

// ─── Phase 1: Orientation ─────────────────────────────────────────────────────

const PILLARS = [
  {
    key:       'workshop',
    label:     'Workshop',
    icon:      <Wrench className="h-8 w-8" />,
    gradient:  'from-indigo-700 to-[#390d58]',
    badge:     'Available now',
    badgeCls:  'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
    summary:   'Security hardening, performance tools, redirects, SMTP, custom code, and more — no API key required.',
    ctaLabel:  'Go to Workshop',
    ctaRoute:  '/workshop',
    ctaPrimary: true,
  },
  {
    key:       'automations',
    label:     'Automations',
    icon:      <Zap className="h-8 w-8" />,
    gradient:  'from-[#390d58] to-violet-600',
    badge:     'Requires API key',
    badgeCls:  'bg-amber-500/10 text-amber-700 border-amber-500/20',
    summary:   'Digital Royalty\'s content and SEO analysis running automatically on your site — the same work our agency team does for clients, engineered into software.',
    ctaLabel:  'Set up Automations',
    ctaRoute:  '/configuration',
    ctaPrimary: false,
  },
  {
    key:       'campaigns',
    label:     'Campaigns',
    icon:      <Target className="h-8 w-8" />,
    gradient:  'from-[#2d0a47] to-[#390d58]',
    badge:     'Requires API key',
    badgeCls:  'bg-amber-500/10 text-amber-700 border-amber-500/20',
    summary:   'Choose a proven Digital Royalty campaign methodology, then let AI execute it — strategy, content, and direction all built on real agency playbooks.',
    ctaLabel:  'Set up Campaigns',
    ctaRoute:  '/configuration',
    ctaPrimary: false,
  },
]

function OrientationScreen() {
  const navigate = useNavigate()

  return (
    <div className="space-y-10">
      {/* Welcome header */}
      <div className="text-center pt-4">
        <div className="inline-flex items-center justify-center rounded-2xl bg-[#390d58] p-4 mb-5 shadow-lg shadow-[#390d58]/20">
          <Sparkles className="h-8 w-8 text-white" />
        </div>
        <h1 className="text-2xl font-bold text-[#390d58] mb-2">Welcome to Beacon</h1>
        <p className="text-sm text-muted-foreground max-w-lg mx-auto leading-relaxed">
          Beacon brings Digital Royalty's agency expertise to your WordPress site. Start with the Workshop right now — no sign-up needed. Connect a Beacon key to put our proven strategies to work, executed by AI.
        </p>
      </div>

      {/* Pillar cards */}
      <div className="grid gap-5 md:grid-cols-3">
        {PILLARS.map(p => (
          <Card key={p.key} className="overflow-hidden border-[#390d58]/20 flex flex-col">
            {/* Illustration */}
            <div className={`h-28 bg-gradient-to-br ${p.gradient} flex items-center justify-center relative overflow-hidden`}>
              <div className="absolute -top-6 -right-6 w-28 h-28 rounded-full bg-white/5" />
              <div className="absolute -bottom-8 -left-4 w-20 h-20 rounded-full bg-white/5" />
              <span className="text-white relative">{p.icon}</span>
            </div>

            <CardHeader className="pb-2">
              <div className="flex items-center justify-between gap-2">
                <CardTitle className="text-base text-[#390d58]">{p.label}</CardTitle>
                <Badge variant="outline" className={`text-[10px] shrink-0 ${p.badgeCls}`}>{p.badge}</Badge>
              </div>
              <CardDescription className="text-xs leading-relaxed">{p.summary}</CardDescription>
            </CardHeader>

            <CardContent className="pt-0 mt-auto">
              <Button
                onClick={() => navigate(p.ctaRoute)}
                className={`w-full gap-2 text-sm ${
                  p.ctaPrimary
                    ? 'bg-[#390d58] hover:bg-[#4a1170] text-white'
                    : 'bg-white border border-[#390d58]/30 text-[#390d58] hover:bg-[#390d58]/5'
                }`}
                variant={p.ctaPrimary ? 'default' : 'outline'}
              >
                {p.ctaLabel} <ArrowRight className="h-3.5 w-3.5" />
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}

// ─── Report run progress ──────────────────────────────────────────────────────

interface RunReport { type: string; label: string; status: string }

function RunningReportsProgress({ reports }: { reports: RunReport[] }) {
  const total     = reports.length || Object.keys(REPORT_META).length
  const completed = reports.filter(r => r.status === 'submitted' || r.status === 'failed').length
  const pct       = Math.round((completed / total) * 100)

  // Use the known report meta order so items appear even before the first poll returns
  const display = Object.entries(REPORT_META).map(([type, meta]) => {
    const live = reports.find(r => r.type === type)
    return { type, label: meta.label, status: live?.status ?? 'pending' }
  })

  return (
    <div className="rounded-2xl border-2 border-[#390d58]/25 bg-gradient-to-br from-[#390d58]/[0.03] to-transparent p-6">
      <div className="flex items-center gap-3 mb-1">
        <div className="rounded-xl bg-[#390d58] p-3 text-white shrink-0 shadow-md shadow-[#390d58]/20">
          <Loader2 className="h-5 w-5 animate-spin" />
        </div>
        <div>
          <h3 className="text-base font-semibold text-[#390d58]">Running site analysis…</h3>
          <p className="text-sm text-muted-foreground">
            {completed} of {total} reports complete
          </p>
        </div>
      </div>

      {/* Progress bar */}
      <div className="w-full bg-[#390d58]/10 rounded-full h-2 my-4">
        <div
          className="bg-[#390d58] h-2 rounded-full transition-all duration-700"
          style={{ width: `${pct}%` }}
        />
      </div>

      {/* Per-report rows */}
      <div className="space-y-2.5">
        {display.map(r => (
          <div key={r.type} className="flex items-center gap-3">
            {r.status === 'submitted' ? (
              <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0" />
            ) : r.status === 'failed' ? (
              <AlertCircle className="h-4 w-4 text-red-500 shrink-0" />
            ) : (
              <Loader2 className="h-4 w-4 text-[#390d58]/50 animate-spin shrink-0" />
            )}
            <span className={`text-sm flex-1 ${r.status === 'submitted' ? 'text-foreground' : 'text-muted-foreground'}`}>
              {r.label}
            </span>
            <span className={`text-xs font-medium ${
              r.status === 'submitted' ? 'text-emerald-600'
              : r.status === 'failed'  ? 'text-red-500'
              : 'text-muted-foreground'
            }`}>
              {r.status === 'submitted' ? 'Done'
               : r.status === 'failed'  ? 'Failed'
               : r.status === 'generated' ? 'Submitting…'
               : 'Waiting…'}
            </span>
          </div>
        ))}
      </div>
    </div>
  )
}

// ─── Phase 2: Intelligence dashboard ─────────────────────────────────────────

function IntelligenceDashboard({ data, onRefresh, refreshing }: {
  data:       BootstrapData
  onRefresh:  () => void
  refreshing: boolean
}) {
  const navigate = useNavigate()
  const score    = calcScore(data)

  const [running,        setRunning]        = useState(false)
  const [runningReports, setRunningReports] = useState<RunReport[]>([])

  const pollRef    = useRef<ReturnType<typeof setInterval> | null>(null)
  const safetyRef  = useRef<ReturnType<typeof setTimeout>  | null>(null)
  const onRefreshRef = useRef(onRefresh)
  useEffect(() => { onRefreshRef.current = onRefresh }, [onRefresh])

  const firstRun = data.reports.length === 0

  const stopPolling = () => {
    if (pollRef.current)   { clearInterval(pollRef.current);  pollRef.current   = null }
    if (safetyRef.current) { clearTimeout(safetyRef.current); safetyRef.current = null }
  }

  const startPolling = () => {
    // Guard — don't double-start
    if (pollRef.current) return

    safetyRef.current = setTimeout(() => {
      stopPolling()
      setRunning(false)
      onRefreshRef.current()
    }, 180_000)

    pollRef.current = setInterval(async () => {
      try {
        const reports = await api.get<RunReport[]>('/reports')
        setRunningReports(reports)
        const total = Object.keys(REPORT_META).length
        const done  = reports.filter(r => r.status === 'submitted' || r.status === 'failed').length
        if (reports.length > 0 && done >= total) {
          stopPolling()
          setRunning(false)
          onRefreshRef.current()
        }
      } catch {
        // transient — keep polling
      }
    }, 2000)
  }

  // On mount: if the server already has reports in-progress (e.g. user navigated away
  // mid-run and came back), restore the progress UI and resume polling automatically.
  useEffect(() => {
    const inProgress = data.reports.some(
      r => r.status === 'pending' || r.status === 'generated'
    )
    if (inProgress) {
      setRunning(true)
      setRunningReports(
        data.reports.map(r => ({
          type:   r.type,
          label:  REPORT_META[r.type]?.label ?? r.type,
          status: r.status,
        }))
      )
      startPolling()
    }
    return stopPolling
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const handleRunAnalysis = () => {
    setRunning(true)
    setRunningReports([])
    startPolling()
    // Fire-and-forget — polling tracks progress independently
    api.post('/reports/run', {}).catch(() => {
      stopPolling()
      setRunning(false)
    })
  }

  return (
    <div className="space-y-6">

      {/* Header row */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-semibold tracking-tight text-[#390d58]">Site Profile</h2>
          <p className="text-sm text-muted-foreground mt-1">
            How complete is Beacon's knowledge of your site?
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={onRefresh} disabled={refreshing || running}
            className="gap-1.5 border-[#390d58]/20 text-[#390d58]">
            <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
          {!firstRun && !running && (
            <Button size="sm" onClick={handleRunAnalysis} disabled={refreshing}
              className="gap-1.5 bg-[#390d58] hover:bg-[#4a1170] text-white">
              <Sparkles className="h-3.5 w-3.5" /> Re-run analysis
            </Button>
          )}
        </div>
      </div>

      {/* Running progress — replaces first-run banner while analysis is in progress */}
      {running && <RunningReportsProgress reports={runningReports} />}

      {/* First-run prompt — shown when connected but no reports have ever run */}
      {firstRun && !running && (
        <div className="rounded-2xl border-2 border-[#390d58]/25 bg-gradient-to-br from-[#390d58]/[0.03] to-transparent p-6 flex items-start gap-5">
          <div className="rounded-xl bg-[#390d58] p-3 text-white shrink-0 shadow-md shadow-[#390d58]/20">
            <Sparkles className="h-5 w-5" />
          </div>
          <div className="flex-1">
            <h3 className="text-base font-semibold text-[#390d58] mb-1">
              Run your first site analysis
            </h3>
            <p className="text-sm text-muted-foreground leading-relaxed mb-4">
              Before Beacon can guide your campaigns and automations, it needs to build a profile of your site. This runs automatically and typically completes in under a minute.
            </p>
            <Button
              onClick={handleRunAnalysis}
              className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2"
            >
              <Sparkles className="h-4 w-4" /> Run site analysis
            </Button>
          </div>
        </div>
      )}

      {/* Score + breakdown */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardContent className="pt-6">
          <div className="flex flex-col sm:flex-row gap-8 items-start">

            {/* Ring */}
            <div className="flex flex-col items-center gap-3 shrink-0">
              <ScoreRing pct={score.pct} />
              <div className="text-center">
                <p className="text-xs text-muted-foreground">
                  Core: {score.coreComplete}/{score.coreTotal}
                </p>
                <p className="text-xs text-muted-foreground">
                  Integrations: {score.integrationCount}/{score.integrationTotal}
                </p>
              </div>
            </div>

            {/* Breakdown columns */}
            <div className="flex-1 grid gap-6 sm:grid-cols-2 w-full">

              {/* Core setup */}
              <div>
                <p className="text-xs font-semibold text-[#390d58] uppercase tracking-wide mb-2">Core setup</p>
                <div className="divide-y divide-[#390d58]/5">
                  {score.coreItems.map(item => (
                    <ScoreItem key={item.label} label={item.label} ok={item.ok} icon={item.icon} />
                  ))}
                </div>
                {score.coreComplete < score.coreTotal && (
                  <Button size="sm" variant="outline"
                    onClick={() => navigate('/configuration')}
                    className="mt-3 gap-1.5 border-[#390d58]/20 text-[#390d58] text-xs">
                    Complete setup <ArrowRight className="h-3 w-3" />
                  </Button>
                )}
              </div>

              {/* Integrations */}
              <div>
                <p className="text-xs font-semibold text-[#390d58] uppercase tracking-wide mb-2">Integrations</p>
                <div className="divide-y divide-[#390d58]/5">
                  {score.integrationItems.map(item => (
                    <ScoreItem key={item.label} label={item.label} ok={item.ok} />
                  ))}
                </div>
                {score.integrationCount < score.integrationTotal && (
                  <Button size="sm" variant="outline"
                    onClick={() => navigate('/configuration')}
                    className="mt-3 gap-1.5 border-[#390d58]/20 text-[#390d58] text-xs">
                    Connect platforms <Link2 className="h-3 w-3" />
                  </Button>
                )}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Quick actions */}
      <div>
        <p className="text-sm font-medium text-[#390d58] mb-3">Quick actions</p>
        <div className="grid gap-3 sm:grid-cols-3">
          {[
            { label: 'Workshop',    icon: <Wrench className="h-4 w-4" />, route: '/workshop',    desc: 'Site tools & hardening'      },
            { label: 'Automations', icon: <Zap    className="h-4 w-4" />, route: '/automations', desc: 'AI content tools'            },
            { label: 'Campaigns',   icon: <Target  className="h-4 w-4" />, route: '/campaigns',   desc: 'Campaign management'        },
          ].map(({ label, icon, route, desc }) => (
            <button
              key={route}
              onClick={() => navigate(route)}
              className="flex items-center gap-3 p-4 rounded-xl border border-[#390d58]/15 bg-white hover:border-[#390d58]/40 hover:bg-[#390d58]/[0.02] transition-all text-left"
            >
              <span className="rounded-lg bg-[#390d58]/10 p-2 text-[#390d58] shrink-0">{icon}</span>
              <div>
                <p className="text-sm font-medium text-[#390d58]">{label}</p>
                <p className="text-xs text-muted-foreground">{desc}</p>
              </div>
              <ArrowRight className="h-3.5 w-3.5 text-[#390d58]/40 ml-auto shrink-0" />
            </button>
          ))}
        </div>
      </div>

    </div>
  )
}

// ─── Root export ──────────────────────────────────────────────────────────────

export function Dashboard() {
  const [data,       setData]       = useState<BootstrapData | null>(null)
  const [loading,    setLoading]    = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = (isRefresh = false) => {
    if (isRefresh) setRefreshing(true)
    else setLoading(true)

    api.get<BootstrapData>('/bootstrap')
      .then(setData)
      .catch(() => null)
      .finally(() => {
        setLoading(false)
        setRefreshing(false)
      })
  }

  useEffect(() => { load() }, [])

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <Loader2 className="h-6 w-6 animate-spin text-[#390d58]" />
      </div>
    )
  }

  if (!data?.connection.has_api_key) {
    return <OrientationScreen />
  }

  return (
    <IntelligenceDashboard
      data={data}
      onRefresh={() => load(true)}
      refreshing={refreshing}
    />
  )
}
