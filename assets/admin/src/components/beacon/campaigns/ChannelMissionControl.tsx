import { useEffect, useState } from 'react'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import {
  Send,
  CheckCircle2,
  XCircle,
  ChevronDown,
  ChevronRight,
  Trash2,
  RefreshCw,
  Play,
  Loader2,
  Zap,
  Target,
  Calendar,
  Gauge,
  Clock,
  AlertTriangle,
  Sparkles,
  Settings,
  FileText,
  ArrowLeft,
  TrendingUp,
  ThumbsUp,
  Activity as ActivityIcon,
  FileBarChart,
  MessageSquare,
  CalendarDays,
} from 'lucide-react'
import { api } from '@/lib/api'
import type { ChannelEntry } from './ChannelSidebar'

interface LedgerEntry {
  id:                   number
  entry_type:           string
  title:                string
  summary:              string | null
  data:                 Record<string, unknown> | null
  agent:                { key: string; name: string } | null
  created_at:           string
  related_request_id?:  string | null
  session_id?:          string | null
}

interface Commitment {
  id:                string
  status:            'open' | 'resolved_success' | 'resolved_failed' | 'abandoned'
  opportunity:       string
  action:            string
  expected_signal:   string
  measure_at:        string
  is_overdue:        boolean
  resolution_notes:  string | null
  evidence:          Record<string, unknown> | null
  resolved_at:       string | null
  created_at:        string
}

interface OperatorQuestion {
  question:        string
  asked_at:        string
  question_hash?:  string
}

interface DocumentSummary {
  id:              string
  kind:            string
  title:           string
  format:          string
  channel:         string | null
  campaign_level:  boolean
  created_at:      string
  updated_at:      string
}

interface DocumentDetail extends DocumentSummary {
  payload:  Record<string, unknown>
  meta:     Record<string, unknown> | null
}

interface CalendarEvent {
  type:     string
  title:    string
  summary:  string | null
  time:     string
}

interface CalendarDay {
  date:    string                 // YYYY-MM-DD
  counts:  {
    sessions:             number
    automations:          number
    commitments_opened:   number
    commitments_closed:   number
    documents:            number
    operator_replies:     number
  }
  events:  CalendarEvent[]
}

interface CycleCalendar {
  cycle:  { id: number; period: string | null; cycle_start: string; cycle_end: string }
  days:   CalendarDay[]
}

interface ProgressMonth {
  cycle_id:           number
  period:             string | null
  period_start:       string
  period_end:         string
  is_current:         boolean
  status_badge:       'open' | 'reviewed' | 'pending_review'
  activity:           {
    session_count:        number
    automation_count:     number
    commitments_closed:   number
  }
  retro:              {
    document_id:    string
    what_worked:    string[]
    what_didnt:     string[]
    lessons:        string[]
  } | null
  report_document_id: string | null
}

interface Memory {
  cycle_plan?: { goals?: string[]; priorities?: string[]; rationale?: string }
  current_focus?: { theme?: string; reason?: string }
  open_questions_for_operator?: { items?: OperatorQuestion[] }
  [key: string]: unknown
}

interface Props {
  channel:           ChannelEntry
  onEdit:            () => void
  onResume:          () => Promise<void>
  onUnhire:          () => Promise<void>
  onSwap:            () => void
  onStartOnboarding: () => void
  busy:              null | 'resume' | 'unhire'
}

// ── Time helpers ──────────────────────────────────────────────────────────

function formatRelativeTime(dateString: string | null): string {
  if (!dateString) return 'never'
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays === 1) return 'yesterday'
  if (diffDays < 7) return `${diffDays} days ago`
  return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' })
}

function formatFutureTime(dateString: string | null): string {
  if (!dateString) return 'not scheduled'
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = date.getTime() - now.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)

  if (diffMins < 0) return 'overdue'
  if (diffMins < 60) return `in ${diffMins}m`
  if (diffHours < 24) return `in ${diffHours}h`
  return `in ${Math.floor(diffHours / 24)}d`
}

function getStatusInfo(channel: ChannelEntry): { label: string } {
  const billing = channel.billing
  const warmup = channel.warmup

  if (billing?.status === 'awaiting_onboarding') return { label: 'Awaiting setup' }
  if (billing?.status === 'paused_no_credits') return { label: 'Paused' }
  if (billing?.status === 'awaiting_dependencies') return { label: 'Needs reconnection' }
  if (warmup?.active && warmup.day !== null) return { label: 'Warming up' }
  return { label: 'Active' }
}

// Pull the most recent agent_thinking / session_summary entry as the
// hero "what's Atlas saying right now" line.
function getMainTake(
  ledger: LedgerEntry[],
  channel: ChannelEntry,
): { content: string; timestamp: string | null } {
  const candidate = ledger
    .filter(
      (e) =>
        (e.entry_type === 'agent_thinking' || e.entry_type === 'session_summary') &&
        e.summary,
    )
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())[0]

  if (candidate) {
    // The ledger's `summary` column is truncated to 500 chars for index
    // hygiene; the full text lives in data.summary. Prefer the full
    // version so the hero card renders the agent's complete take.
    const dataSummary = typeof candidate.data?.summary === 'string'
      ? (candidate.data.summary as string)
      : null
    const fullText = dataSummary ?? candidate.summary
    if (fullText) {
      return { content: fullText, timestamp: candidate.created_at }
    }
  }

  // Friendly empty-state copy in the agent's voice when no posts exist yet.
  const billing = channel.billing
  const agentName = channel.agent?.name ?? 'your agent'
  const agentLabel = channel.agent?.label?.toLowerCase() ?? 'channel agent'

  if (billing?.status === 'awaiting_onboarding') {
    return {
      content: `Hi, I'm ${agentName} — your ${agentLabel}. Once you finish onboarding I'll get to work and share what I'm seeing.`,
      timestamp: null,
    }
  }
  if (billing?.status === 'paused_no_credits') {
    return {
      content: `I'm paused right now — top up credits and I'll pick up where I left off.`,
      timestamp: null,
    }
  }
  if (!channel.dependencies.met) {
    return {
      content: `I'm waiting on a couple of data sources before I can really dig in. Reconnect them and I'll get started.`,
      timestamp: null,
    }
  }
  return {
    content: `Just getting started — I'll share my first take after my next session.`,
    timestamp: null,
  }
}

export function ChannelMissionControl({
  channel,
  onEdit,
  onResume,
  onUnhire,
  onSwap,
  onStartOnboarding,
  busy,
}: Props) {
  const [ledger, setLedger]             = useState<LedgerEntry[]>([])
  const [memory, setMemory]             = useState<Memory>({})
  const [commitments, setCommitments]   = useState<{ open: Commitment[]; recently_resolved: Commitment[] }>({ open: [], recently_resolved: [] })
  const [documents, setDocuments]       = useState<DocumentSummary[]>([])
  const [progress, setProgress]         = useState<ProgressMonth[]>([])
  const [loading, setLoading]           = useState(true)
  const [error, setError]               = useState<string | null>(null)
  const [answerInputs, setAnswerInputs] = useState<Record<string, string>>({})
  const [submittingQuestion, setSubmittingQuestion] = useState<string | null>(null)
  const [showResolved, setShowResolved] = useState(false)
  const [selectedDocument, setSelectedDocument] = useState<DocumentDetail | null>(null)
  const [loadingDocument, setLoadingDocument]   = useState(false)

  // Match the existing ChannelOverview pattern: fetch ledger + memory +
  // commitments in parallel on mount and whenever the selected channel
  // changes. Keeps the new view a drop-in replacement.
  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [ledgerRes, memoryRes, commitmentsRes, documentsRes, progressRes] = await Promise.all([
        api.get<{ entries: LedgerEntry[] }>(`/campaigns/channels/${channel.key}/ledger?limit=50`),
        api.get<{ memory: Memory }>(`/campaigns/channels/${channel.key}/memory`).catch(() => ({ memory: {} as Memory })),
        api.get<{ open: Commitment[]; recently_resolved: Commitment[] }>(
          `/campaigns/channels/${channel.key}/commitments`,
        ).catch(() => ({ open: [] as Commitment[], recently_resolved: [] as Commitment[] })),
        api.get<{ documents: DocumentSummary[] }>(`/campaigns/channels/${channel.key}/documents`)
          .catch(() => ({ documents: [] as DocumentSummary[] })),
        api.get<{ months: ProgressMonth[] }>(`/campaigns/channels/${channel.key}/progress`)
          .catch(() => ({ months: [] as ProgressMonth[] })),
      ])
      setLedger(ledgerRes.entries ?? [])
      setMemory(memoryRes.memory ?? ({} as Memory))
      setCommitments({
        open: commitmentsRes.open ?? [],
        recently_resolved: commitmentsRes.recently_resolved ?? [],
      })
      setDocuments(documentsRes.documents ?? [])
      setProgress(progressRes.months ?? [])
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load channel state.')
    } finally {
      setLoading(false)
    }
  }

  const loadDocument = async (id: string) => {
    setLoadingDocument(true)
    try {
      const res = await api.get<{ document: DocumentDetail }>(
        `/campaigns/channels/${channel.key}/documents/${id}`,
      )
      setSelectedDocument(res.document)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load document.')
    } finally {
      setLoadingDocument(false)
    }
  }

  useEffect(() => { loadData() }, [channel.key])

  const submitAnswer = async (questionHash: string) => {
    const answer = answerInputs[questionHash]
    if (!answer?.trim()) return

    // Fall back to the question text itself when the hash isn't present
    // — older question entries don't carry hashes. The backend accepts
    // either as the dedup key.
    const questionText = (memory.open_questions_for_operator?.items ?? [])
      .find((q) => q.question_hash === questionHash || q.question === questionHash)?.question
      ?? questionHash

    setSubmittingQuestion(questionHash)
    try {
      await api.post(`/campaigns/channels/${channel.key}/answers`, {
        question: questionText,
        answer: answer.trim(),
      })
      setAnswerInputs((prev) => {
        const next = { ...prev }
        delete next[questionHash]
        return next
      })
      await loadData()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not send answer.')
    } finally {
      setSubmittingQuestion(null)
    }
  }

  const agent = channel.agent!
  const billing = channel.billing
  const pacing = channel.pacing
  const setup = channel.setup
  const warmup = channel.warmup

  const statusInfo = getStatusInfo(channel)
  const questions = memory.open_questions_for_operator?.items ?? []
  const mainTake = getMainTake(ledger, channel)

  const sortedOpenCommitments = [...commitments.open].sort((a, b) => {
    if (a.is_overdue && !b.is_overdue) return -1
    if (!a.is_overdue && b.is_overdue) return 1
    return new Date(a.measure_at).getTime() - new Date(b.measure_at).getTime()
  })

  const overdueCount = commitments.open.filter((c) => c.is_overdue).length

  const workPercentage = billing?.monthly_work_cap
    ? Math.round((billing.monthly_work_spent / billing.monthly_work_cap) * 100)
    : 0

  return (
    <div className="space-y-6 flex-1 min-w-0">
      {/* ── Hero card: agent dialogue + portrait ───────────────────────── */}
      <Card className="border-0 shadow-lg overflow-hidden bg-[#390d58]">
        <CardContent className="p-0">
          <div className="flex items-stretch">
            {/* Dialogue side */}
            <div className="flex-1 flex flex-col justify-between p-6">
              <div className="flex justify-end mb-3">
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-white/10 text-white/80 border border-white/20">
                  <span
                    className={`w-1.5 h-1.5 rounded-full ${
                      statusInfo.label === 'Active'
                        ? 'bg-emerald-400'
                        : statusInfo.label === 'Paused'
                          ? 'bg-red-400'
                          : 'bg-amber-400'
                    }`}
                  />
                  {statusInfo.label}
                </span>
              </div>

              <div className="flex-1">
                <p className="text-[10px] uppercase tracking-wider text-white/50 font-semibold mb-2">
                  Latest Campaign Work
                </p>
                <p className="text-white/95 text-base leading-relaxed whitespace-pre-wrap">
                  {mainTake.content}
                </p>
                {mainTake.timestamp && (
                  <p className="text-white/40 text-xs mt-3">
                    {formatRelativeTime(mainTake.timestamp)}
                  </p>
                )}
              </div>

              {billing?.status === 'awaiting_onboarding' && (
                <Button
                  onClick={onStartOnboarding}
                  size="sm"
                  className="mt-4 bg-white text-[#390d58] hover:bg-white/90 self-start font-semibold"
                >
                  Complete onboarding
                </Button>
              )}
              {billing?.status === 'paused_no_credits' && (
                <Button
                  onClick={onResume}
                  size="sm"
                  disabled={busy === 'resume'}
                  className="mt-4 bg-white text-[#390d58] hover:bg-white/90 self-start font-semibold"
                >
                  {busy === 'resume' && <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />}
                  <Play className="h-3.5 w-3.5 mr-1.5" />
                  Resume with credits
                </Button>
              )}
            </div>

            {/* Portrait column */}
            <div className="relative flex-shrink-0 w-[240px] flex flex-col items-center justify-center bg-gradient-to-b from-[#2a0a42] to-[#390d58] border-l border-white/10 px-6 py-8">
              <div
                className="absolute inset-0 opacity-10"
                style={{
                  backgroundImage:
                    'repeating-linear-gradient(0deg,transparent,transparent 20px,rgba(255,255,255,0.15) 20px,rgba(255,255,255,0.15) 21px),repeating-linear-gradient(90deg,transparent,transparent 20px,rgba(255,255,255,0.15) 20px,rgba(255,255,255,0.15) 21px)',
                }}
              />
              <div className="relative z-10 flex flex-col items-center gap-4 w-full">
                <div className="relative">
                  <Avatar className="h-32 w-32 border-2 border-white/30 shadow-2xl">
                    <AvatarImage src={agent.image_url ?? undefined} alt={agent.name} />
                    <AvatarFallback className="text-6xl bg-[#2a0a42] text-white">
                      {agent.emoji}
                    </AvatarFallback>
                  </Avatar>
                  {statusInfo.label === 'Active' && (
                    <span className="absolute -bottom-1 -right-1 w-5 h-5 bg-emerald-400 border-2 border-[#390d58] rounded-full" />
                  )}
                </div>
                <div className="w-full text-center bg-white/10 border border-white/20 rounded px-3 py-2">
                  <p className="text-white font-bold text-sm tracking-wide leading-tight">
                    {agent.name}
                  </p>
                  <p className="text-white/60 text-xs leading-tight mt-0.5">
                    Managing {channel.label}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Stats strip */}
          <div className="flex flex-wrap gap-x-6 gap-y-3 px-5 py-4 bg-[#2a0a42]/60 border-t border-white/10">
            {warmup?.active && warmup.day !== null && (
              <Stat icon={<Gauge className="h-3.5 w-3.5 text-amber-400" />} label="Warm-up" value={`Day ${warmup.day}/${warmup.total_days}`} />
            )}
            {billing?.monthly_work_cap !== null && billing?.monthly_work_cap !== undefined && (
              <Stat
                icon={<Zap className="h-3.5 w-3.5 text-violet-300" />}
                label="Budget"
                value={
                  <>
                    {billing.monthly_work_spent.toLocaleString()}/{billing.monthly_work_cap.toLocaleString()}{' '}
                    <span className="text-white/40 font-normal">({workPercentage}%)</span>
                  </>
                }
              />
            )}
            {pacing?.next_turn_at && (
              <Stat
                icon={<Clock className="h-3.5 w-3.5 text-violet-300" />}
                label="Next session"
                value={formatFutureTime(pacing.next_turn_at)}
              />
            )}
            <Stat
              icon={<Target className="h-3.5 w-3.5 text-violet-300" />}
              label="Autonomy"
              value={<span className="capitalize">{setup.autonomy}</span>}
            />
            <Stat
              icon={<Calendar className="h-3.5 w-3.5 text-violet-300" />}
              label="Cadence"
              value={<span className="capitalize">{setup.cadence.replace('_', ' ')}</span>}
            />
          </div>
        </CardContent>
      </Card>

      {error && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900">
          {error}
        </div>
      )}

      {/* ── Tabs ────────────────────────────────────────────────────────── */}
      <Card className="border-0 shadow-md">
        <Tabs defaultValue="progress" className="w-full">
          <CardHeader className="pb-0 px-0 pt-0">
            <TabsList className="w-full justify-start bg-transparent px-6 pt-4 pb-0 h-auto gap-0 border-b border-border rounded-none">
              <TabTrigger value="progress" label="Progress" icon={<TrendingUp className="h-3.5 w-3.5" />} />
              <TabTrigger value="plan" label="Plan" icon={<Target className="h-3.5 w-3.5" />} />
              <TabTrigger value="commitments" label="Commitments" icon={<CheckCircle2 className="h-3.5 w-3.5" />} count={overdueCount} warning />
              <TabTrigger value="questions" label="Questions" icon={<MessageSquare className="h-3.5 w-3.5" />} count={questions.length} />
              <TabTrigger value="files" label="Files" icon={<FileText className="h-3.5 w-3.5" />} count={documents.length} />
              <TabTrigger value="activity" label="Activity" icon={<ActivityIcon className="h-3.5 w-3.5" />} />
              <TabTrigger value="config" label="Configuration" icon={<Settings className="h-3.5 w-3.5" />} />
            </TabsList>
          </CardHeader>

          <CardContent className="pt-6">
            {/* Plan */}
            {/* Progress — month-by-month timeline */}
            <TabsContent value="progress" className="mt-0">
              {selectedDocument ? (
                <DocumentDetailView
                  document={selectedDocument}
                  loading={loadingDocument}
                  onBack={() => setSelectedDocument(null)}
                />
              ) : progress.length > 0 ? (
                <div className="space-y-3">
                  {progress.map((month) => (
                    <ProgressMonthCard
                      key={month.cycle_id}
                      month={month}
                      channelKey={channel.key}
                      onOpenReport={(reportId) => loadDocument(reportId)}
                    />
                  ))}
                </div>
              ) : (
                <EmptyHint icon={<TrendingUp className="h-6 w-6 text-[#390d58]" />}>
                  No cycles yet — your first month will appear here once it kicks off.
                </EmptyHint>
              )}
            </TabsContent>

            <TabsContent value="plan" className="mt-0">
              {memory.cycle_plan ? (
                <div className="space-y-6">
                  {memory.current_focus?.theme && (
                    <div className="p-4 rounded-xl bg-[#390d58]/5 border border-[#390d58]/10">
                      <div className="flex items-center gap-2 mb-2">
                        <Target className="h-4 w-4 text-[#390d58]" />
                        <h4 className="text-xs font-semibold text-[#390d58] uppercase tracking-wide">
                          Current focus
                        </h4>
                      </div>
                      <p className="text-sm font-medium text-foreground">
                        {memory.current_focus.theme}
                      </p>
                      {memory.current_focus.reason && (
                        <p className="text-sm text-muted-foreground mt-1">
                          {memory.current_focus.reason}
                        </p>
                      )}
                    </div>
                  )}
                  {memory.cycle_plan.goals && memory.cycle_plan.goals.length > 0 && (
                    <div>
                      <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-3">
                        Cycle goals
                      </h4>
                      <div className="space-y-2">
                        {memory.cycle_plan.goals.map((goal, i) => (
                          <div key={i} className="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
                            <div className="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0 bg-[#390d58]">
                              {i + 1}
                            </div>
                            <p className="text-sm text-foreground pt-0.5">{goal}</p>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                  {memory.cycle_plan.priorities && memory.cycle_plan.priorities.length > 0 && (
                    <div>
                      <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-3">
                        Priorities
                      </h4>
                      <div className="flex flex-wrap gap-2">
                        {memory.cycle_plan.priorities.map((p, i) => (
                          <Badge key={i} className="bg-[#390d58]/10 text-[#390d58] hover:bg-[#390d58]/20 border-0">
                            {p}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  )}
                  {memory.cycle_plan.rationale && (
                    <div>
                      <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">
                        Rationale
                      </h4>
                      <p className="text-sm text-muted-foreground">{memory.cycle_plan.rationale}</p>
                    </div>
                  )}
                </div>
              ) : (
                <EmptyHint icon={<Target className="h-6 w-6 text-[#390d58]" />}>
                  I&apos;ll build out my plan after the first session — stay tuned.
                </EmptyHint>
              )}
            </TabsContent>

            {/* Commitments */}
            <TabsContent value="commitments" className="mt-0">
              {sortedOpenCommitments.length > 0 ? (
                <div className="space-y-3">
                  {sortedOpenCommitments.map((c) => (
                    <div
                      key={c.id}
                      className={`p-4 rounded-xl border text-sm transition-colors ${
                        c.is_overdue
                          ? 'border-amber-200 bg-amber-50'
                          : 'border-border hover:border-[#390d58]/30 hover:bg-[#390d58]/5'
                      }`}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <p className="font-medium text-foreground">{c.opportunity}</p>
                          <p className="text-muted-foreground mt-1">{c.action}</p>
                          <p className="text-xs text-muted-foreground mt-2 flex items-center gap-1">
                            <CheckCircle2 className="h-3 w-3" />
                            Signal: {c.expected_signal}
                          </p>
                        </div>
                        {c.is_overdue ? (
                          <Badge className="shrink-0 bg-amber-100 text-amber-700 border-0 hover:bg-amber-100">
                            <AlertTriangle className="h-3 w-3 mr-1" />
                            Overdue
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="shrink-0">
                            {formatRelativeTime(c.measure_at)}
                          </Badge>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <EmptyHint icon={<CheckCircle2 className="h-6 w-6 text-[#390d58]" />}>
                  No active commitments — I&apos;ll start tracking once I&apos;ve made some progress.
                </EmptyHint>
              )}

              {commitments.recently_resolved.length > 0 && (
                <div className="mt-6 pt-4 border-t border-border">
                  <button
                    onClick={() => setShowResolved(!showResolved)}
                    className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                  >
                    {showResolved ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                    Recently resolved ({commitments.recently_resolved.length})
                  </button>
                  {showResolved && (
                    <div className="mt-3 space-y-2">
                      {commitments.recently_resolved.map((c) => (
                        <div
                          key={c.id}
                          className="p-3 rounded-lg border border-border bg-muted/30 opacity-80 text-sm space-y-1"
                        >
                          <div className="flex items-center gap-2">
                            {c.status === 'resolved_success' && <CheckCircle2 className="h-4 w-4 text-emerald-500" />}
                            {c.status === 'resolved_failed' && <XCircle className="h-4 w-4 text-red-500" />}
                            {c.status === 'abandoned' && <XCircle className="h-4 w-4 text-muted-foreground" />}
                            <span className="text-foreground font-medium">{c.opportunity}</span>
                          </div>
                          {c.resolution_notes && (
                            <p className="text-xs text-muted-foreground italic ml-6">{c.resolution_notes}</p>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </TabsContent>

            {/* Questions */}
            <TabsContent value="questions" className="mt-0">
              {questions.length > 0 ? (
                <div className="space-y-4">
                  {questions.map((q, i) => {
                    const key = q.question_hash ?? q.question
                    return (
                      <div
                        key={key + i}
                        className="p-4 rounded-xl border border-[#390d58]/20 bg-[#390d58]/5"
                      >
                        <p className="text-sm font-medium text-foreground mb-3">{q.question}</p>
                        <p className="text-xs text-muted-foreground mb-3">
                          Asked {formatRelativeTime(q.asked_at)}
                        </p>
                        <div className="flex gap-2">
                          <Textarea
                            placeholder="Type your answer..."
                            value={answerInputs[key] ?? ''}
                            onChange={(e) =>
                              setAnswerInputs((prev) => ({ ...prev, [key]: e.target.value }))
                            }
                            className="min-h-[60px] text-sm resize-none bg-white"
                          />
                          <Button
                            size="sm"
                            onClick={() => submitAnswer(key)}
                            disabled={!answerInputs[key]?.trim() || submittingQuestion === key}
                            className="shrink-0 bg-[#390d58] hover:bg-[#4a1a6e] text-white"
                          >
                            {submittingQuestion === key ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <Send className="h-4 w-4" />
                            )}
                          </Button>
                        </div>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <EmptyHint icon={<Sparkles className="h-6 w-6 text-[#390d58]" />}>
                  No questions right now — I&apos;ll reach out if I need input.
                </EmptyHint>
              )}
            </TabsContent>

            {/* Files */}
            <TabsContent value="files" className="mt-0">
              {selectedDocument ? (
                <DocumentDetailView
                  document={selectedDocument}
                  loading={loadingDocument}
                  onBack={() => setSelectedDocument(null)}
                />
              ) : documents.length > 0 ? (
                <div className="space-y-2">
                  {documents.map((doc) => (
                    <button
                      key={doc.id}
                      onClick={() => loadDocument(doc.id)}
                      className="w-full flex items-start gap-3 p-3 rounded-lg border border-border hover:border-[#390d58]/30 hover:bg-[#390d58]/5 transition-colors text-left"
                    >
                      <div className="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-[#390d58]/10">
                        <FileText className="h-5 w-5 text-[#390d58]" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <p className="text-sm font-medium text-foreground truncate">{doc.title}</p>
                          {doc.campaign_level && (
                            <Badge variant="outline" className="text-[10px] py-0 h-4 shrink-0">
                              campaign-wide
                            </Badge>
                          )}
                        </div>
                        <p className="text-xs text-muted-foreground mt-0.5">
                          <span className="font-mono">{doc.kind}</span>
                          {' · updated '}
                          {formatRelativeTime(doc.updated_at)}
                        </p>
                      </div>
                      <ChevronRight className="h-4 w-4 text-muted-foreground shrink-0 mt-2" />
                    </button>
                  ))}
                </div>
              ) : (
                <EmptyHint icon={<FileText className="h-6 w-6 text-[#390d58]" />}>
                  No documents yet — keyword research, calendars, refresh queues and similar work will appear here as I produce it.
                </EmptyHint>
              )}
            </TabsContent>

            {/* Activity */}
            <TabsContent value="activity" className="mt-0">
              {loading ? (
                <div className="text-center py-12">
                  <Loader2 className="h-5 w-5 animate-spin text-muted-foreground mx-auto" />
                </div>
              ) : ledger.length > 0 ? (
                <div className="space-y-1">
                  {ledger.slice(0, 25).map((entry) => (
                    <div
                      key={entry.id}
                      className="flex items-start gap-3 py-3 border-b border-border last:border-0"
                    >
                      <div
                        className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${
                          entry.entry_type === 'automation_dispatched'
                            ? 'bg-[#390d58]/10'
                            : entry.entry_type === 'operator_answer'
                              ? 'bg-emerald-100'
                              : 'bg-muted'
                        }`}
                      >
                        {entry.entry_type === 'automation_dispatched' && <Zap className="h-4 w-4 text-[#390d58]" />}
                        {entry.entry_type === 'operator_answer' && <Send className="h-4 w-4 text-emerald-600" />}
                        {entry.entry_type === 'session_summary' && <CheckCircle2 className="h-4 w-4 text-muted-foreground" />}
                        {entry.entry_type === 'agent_thinking' && <Sparkles className="h-4 w-4 text-muted-foreground" />}
                        {entry.entry_type === 'feature_request' && <Target className="h-4 w-4 text-muted-foreground" />}
                        {!['automation_dispatched', 'operator_answer', 'session_summary', 'agent_thinking', 'feature_request'].includes(entry.entry_type) && <Clock className="h-4 w-4 text-muted-foreground" />}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm text-foreground">{entry.summary || entry.title}</p>
                        <p className="text-xs text-muted-foreground mt-1">
                          {formatRelativeTime(entry.created_at)}
                          {entry.agent && ` • ${entry.agent.name}`}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <EmptyHint icon={<Clock className="h-6 w-6 text-[#390d58]" />}>
                  No activity yet — check back after my first session.
                </EmptyHint>
              )}
            </TabsContent>

            {/* Configuration */}
            <TabsContent value="config" className="mt-0">
              <div className="space-y-6">
                {setup.goal && <ConfigField label="Goal" value={setup.goal} />}
                {setup.primary_kpi && <ConfigField label="Primary KPI" value={setup.primary_kpi} />}
                {setup.guardrails && <ConfigField label="Guardrails" value={setup.guardrails} />}

                <div>
                  <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-3">
                    Data sources
                  </h4>
                  <div className="space-y-2">
                    {channel.dependencies.required.map((dep) => (
                      <DependencyRow key={dep.provider} dep={dep} />
                    ))}
                    {channel.dependencies.optional.map((dep) => (
                      <DependencyRow key={dep.provider} dep={dep} optional />
                    ))}
                  </div>
                </div>

                <div className="pt-6 mt-6 border-t border-border">
                  <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-4">
                    Settings
                  </h4>
                  <div className="flex flex-wrap gap-3">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={onEdit}
                      className="text-foreground"
                    >
                      <Settings className="h-4 w-4 mr-2" />
                      Edit setup
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={onSwap}
                      className="text-muted-foreground hover:text-foreground"
                    >
                      <RefreshCw className="h-4 w-4 mr-2" />
                      Swap agent
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={onUnhire}
                      disabled={busy === 'unhire'}
                      className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                    >
                      {busy === 'unhire' && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                      <Trash2 className="h-4 w-4 mr-2" />
                      Remove from channel
                    </Button>
                  </div>
                </div>
              </div>
            </TabsContent>
          </CardContent>
        </Tabs>
      </Card>
    </div>
  )
}

// ── Local layout helpers ──────────────────────────────────────────────────

function Stat({ icon, label, value }: { icon: React.ReactNode; label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center gap-2">
      {icon}
      <span className="text-xs text-white/50">{label}</span>
      <span className="text-xs font-semibold text-white">{value}</span>
    </div>
  )
}

function TabTrigger({
  value,
  label,
  icon,
  count,
  warning,
}: {
  value: string
  label: string
  icon?: React.ReactNode
  count?: number
  warning?: boolean
}) {
  return (
    <TabsTrigger
      value={value}
      className="rounded-none rounded-t-lg border-b-2 border-transparent data-[state=active]:border-[#390d58] data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 py-3 text-sm font-medium text-muted-foreground data-[state=active]:text-[#390d58] transition-colors gap-2"
    >
      {icon && <span className="inline-flex">{icon}</span>}
      {label}
      {count !== undefined && count > 0 && (
        <span
          className={`ml-1 px-1.5 py-0.5 text-[10px] rounded-full font-medium ${
            warning ? 'bg-amber-100 text-amber-700' : 'bg-[#390d58] text-white'
          }`}
        >
          {count}
        </span>
      )}
    </TabsTrigger>
  )
}

function EmptyHint({ icon, children }: { icon: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="text-center py-12">
      <div className="w-12 h-12 rounded-full bg-[#390d58]/10 flex items-center justify-center mx-auto mb-4">
        {icon}
      </div>
      <p className="text-sm text-muted-foreground">{children}</p>
    </div>
  )
}

function ConfigField({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">
        {label}
      </h4>
      <p className="text-sm text-foreground whitespace-pre-wrap">{value}</p>
    </div>
  )
}

function ProgressMonthCard({ month, channelKey, onOpenReport }: { month: ProgressMonth; channelKey: string; onOpenReport: (id: string) => void }) {
  const [calendar, setCalendar] = useState<CycleCalendar | null>(null)
  const [calendarOpen, setCalendarOpen] = useState(false)
  const [calendarLoading, setCalendarLoading] = useState(false)

  const periodLabel = (() => {
    if (month.period) return month.period
    try {
      return new Date(month.period_start).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
    } catch {
      return month.period_start
    }
  })()

  const toggleCalendar = async () => {
    if (calendarOpen) {
      setCalendarOpen(false)
      return
    }
    setCalendarOpen(true)
    if (calendar) return  // already loaded
    setCalendarLoading(true)
    try {
      const res = await api.get<CycleCalendar>(`/campaigns/channels/${channelKey}/progress/${month.cycle_id}/calendar`)
      setCalendar(res)
    } catch {
      // Fail quiet; calendar block will show an empty/error state
    } finally {
      setCalendarLoading(false)
    }
  }

  const badgeStyle = month.status_badge === 'open'
    ? 'bg-emerald-100 text-emerald-700'
    : month.status_badge === 'reviewed'
      ? 'bg-[#390d58]/10 text-[#390d58]'
      : 'bg-amber-100 text-amber-700'
  const badgeLabel = month.status_badge === 'open'
    ? 'Open · ongoing'
    : month.status_badge === 'reviewed'
      ? 'Reviewed'
      : 'Pending review'

  return (
    <div className={`rounded-xl border p-5 ${month.is_current ? 'border-[#390d58]/30 bg-[#390d58]/5' : 'border-border bg-white'}`}>
      <div className="flex items-start justify-between gap-3 mb-3">
        <div className="min-w-0">
          <h4 className="text-base font-semibold text-foreground">{periodLabel}</h4>
          <p className="text-[11px] text-muted-foreground mt-0.5">
            {formatRelativeTime(month.period_start)} → {formatRelativeTime(month.period_end)}
          </p>
        </div>
        <span className={`shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${badgeStyle}`}>
          {badgeLabel}
        </span>
      </div>

      {!month.is_current && (
        <div className="flex flex-wrap gap-x-5 gap-y-1.5 mb-3 text-xs text-muted-foreground">
          <span className="inline-flex items-center gap-1.5">
            <ActivityIcon className="h-3 w-3" />
            <span className="text-foreground font-medium">{month.activity.session_count}</span> sessions
          </span>
          <span className="inline-flex items-center gap-1.5">
            <Zap className="h-3 w-3" />
            <span className="text-foreground font-medium">{month.activity.automation_count}</span> automations
          </span>
          <span className="inline-flex items-center gap-1.5">
            <CheckCircle2 className="h-3 w-3" />
            <span className="text-foreground font-medium">{month.activity.commitments_closed}</span> commitments closed
          </span>
        </div>
      )}

      {month.retro && (
        <div className="space-y-2 text-xs">
          {month.retro.what_worked.length > 0 && (
            <div>
              <p className="text-[11px] uppercase tracking-wide text-muted-foreground font-semibold mb-1 inline-flex items-center gap-1.5">
                <ThumbsUp className="h-3 w-3 text-emerald-600" /> What worked
              </p>
              <ul className="list-disc list-outside pl-4 space-y-0.5 text-foreground/80">
                {month.retro.what_worked.slice(0, 3).map((it, i) => <li key={i}>{it}</li>)}
              </ul>
            </div>
          )}
          {month.retro.lessons.length > 0 && (
            <div>
              <p className="text-[11px] uppercase tracking-wide text-muted-foreground font-semibold mb-1">Lessons</p>
              <ul className="list-disc list-outside pl-4 space-y-0.5 text-foreground/80">
                {month.retro.lessons.slice(0, 3).map((it, i) => <li key={i}>{it}</li>)}
              </ul>
            </div>
          )}
        </div>
      )}

      {!month.is_current && !month.retro && (
        <p className="text-xs text-muted-foreground italic">No retro yet — the agent will write one once the cycle wraps.</p>
      )}

      <div className="mt-4 pt-3 border-t border-border flex flex-wrap items-center gap-2">
        <Button
          size="sm"
          variant="outline"
          onClick={toggleCalendar}
          className="text-foreground"
        >
          <CalendarDays className="h-3.5 w-3.5 mr-1.5" />
          {calendarOpen ? 'Hide calendar' : 'View calendar'}
        </Button>
        {month.report_document_id && (
          <Button
            size="sm"
            variant="outline"
            onClick={() => onOpenReport(month.report_document_id as string)}
            className="text-[#390d58] border-[#390d58]/30 hover:bg-[#390d58]/5"
          >
            <FileBarChart className="h-3.5 w-3.5 mr-1.5" />
            View end-of-month report
          </Button>
        )}
      </div>

      {calendarOpen && (
        <div className="mt-4 pt-4 border-t border-border">
          {calendarLoading ? (
            <div className="flex justify-center py-6">
              <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
            </div>
          ) : calendar ? (
            <CycleCalendarGrid calendar={calendar} />
          ) : (
            <p className="text-xs text-muted-foreground italic">Could not load calendar.</p>
          )}
        </div>
      )}
    </div>
  )
}

// ── Calendar grid + day cell + day-detail popover ────────────────────────

function CycleCalendarGrid({ calendar }: { calendar: CycleCalendar }) {
  const [openDate, setOpenDate] = useState<string | null>(null)

  const days = calendar.days
  if (days.length === 0) {
    return <p className="text-xs text-muted-foreground italic">No days in this cycle.</p>
  }

  // Compute leading empty cells so the first day lands under its
  // weekday column. Week starts Monday (UK locale convention).
  const firstDayDate = new Date(days[0].date)
  const firstDow = (firstDayDate.getDay() + 6) % 7  // 0 = Mon ... 6 = Sun
  const leading = Array.from({ length: firstDow }, () => null)

  const cells: (CalendarDay | null)[] = [...leading, ...days]
  // Pad to a full weeks grid for visual stability.
  while (cells.length % 7 !== 0) {
    cells.push(null)
  }

  const weekdayHeaders = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
  const openDay = openDate ? days.find((d) => d.date === openDate) ?? null : null

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-7 gap-1">
        {weekdayHeaders.map((label) => (
          <div key={label} className="text-[10px] uppercase tracking-wide text-muted-foreground text-center pb-1">
            {label}
          </div>
        ))}
        {cells.map((day, i) => (
          day === null ? (
            <div key={`empty-${i}`} className="aspect-square" />
          ) : (
            <CalendarDayCell
              key={day.date}
              day={day}
              selected={openDate === day.date}
              onClick={() => setOpenDate(openDate === day.date ? null : day.date)}
            />
          )
        ))}
      </div>

      {openDay && (
        <CalendarDayDetail day={openDay} onClose={() => setOpenDate(null)} />
      )}
    </div>
  )
}

function CalendarDayCell({ day, selected, onClick }: { day: CalendarDay; selected: boolean; onClick: () => void }) {
  const totalEvents = day.counts.sessions + day.counts.automations + day.counts.commitments_opened + day.counts.commitments_closed + day.counts.documents + day.counts.operator_replies
  const dayNum = parseInt(day.date.slice(8, 10), 10)

  // Heatmap intensity — a few days of high activity, most low.
  const intensity = totalEvents === 0
    ? 'bg-white border-border'
    : totalEvents <= 2
      ? 'bg-[#390d58]/10 border-[#390d58]/15'
      : totalEvents <= 5
        ? 'bg-[#390d58]/25 border-[#390d58]/30'
        : 'bg-[#390d58]/45 border-[#390d58]/40'

  const selectedRing = selected ? 'ring-2 ring-[#390d58]' : ''

  return (
    <button
      onClick={onClick}
      disabled={totalEvents === 0}
      className={`aspect-square rounded border ${intensity} ${selectedRing} flex flex-col items-center justify-between p-1 transition-colors ${
        totalEvents > 0 ? 'cursor-pointer hover:opacity-80' : 'cursor-default opacity-60'
      }`}
    >
      <span className={`text-[11px] font-medium ${totalEvents > 5 ? 'text-white' : 'text-foreground'}`}>
        {dayNum}
      </span>
      {totalEvents > 0 && (
        <div className="flex items-center gap-0.5 flex-wrap justify-center">
          {day.counts.sessions > 0 && <span title={`${day.counts.sessions} session(s)`} className={`w-1.5 h-1.5 rounded-full ${totalEvents > 5 ? 'bg-white' : 'bg-[#390d58]'}`} />}
          {day.counts.automations > 0 && <span title={`${day.counts.automations} automation(s)`} className="w-1.5 h-1.5 rounded-full bg-violet-500" />}
          {day.counts.commitments_opened > 0 && <span title={`${day.counts.commitments_opened} commitment(s) opened`} className="w-1.5 h-1.5 rounded-full bg-amber-500" />}
          {day.counts.commitments_closed > 0 && <span title={`${day.counts.commitments_closed} commitment(s) closed`} className="w-1.5 h-1.5 rounded-full bg-emerald-500" />}
          {day.counts.documents > 0 && <span title={`${day.counts.documents} document(s)`} className="w-1.5 h-1.5 rounded-full bg-blue-500" />}
          {day.counts.operator_replies > 0 && <span title={`${day.counts.operator_replies} operator reply`} className="w-1.5 h-1.5 rounded-full bg-pink-500" />}
        </div>
      )}
    </button>
  )
}

function CalendarDayDetail({ day, onClose }: { day: CalendarDay; onClose: () => void }) {
  const dateLabel = new Date(day.date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })

  return (
    <div className="rounded-lg border border-border bg-muted/30 p-4 space-y-3">
      <div className="flex items-center justify-between gap-3">
        <h5 className="text-sm font-semibold text-foreground">{dateLabel}</h5>
        <button onClick={onClose} className="text-muted-foreground hover:text-foreground text-xs">
          Close
        </button>
      </div>

      <div className="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted-foreground">
        {day.counts.sessions > 0 && <span><span className="font-medium text-foreground">{day.counts.sessions}</span> session(s)</span>}
        {day.counts.automations > 0 && <span><span className="font-medium text-foreground">{day.counts.automations}</span> automation(s)</span>}
        {day.counts.commitments_opened > 0 && <span><span className="font-medium text-foreground">{day.counts.commitments_opened}</span> commitment(s) opened</span>}
        {day.counts.commitments_closed > 0 && <span><span className="font-medium text-foreground">{day.counts.commitments_closed}</span> closed</span>}
        {day.counts.documents > 0 && <span><span className="font-medium text-foreground">{day.counts.documents}</span> document(s)</span>}
        {day.counts.operator_replies > 0 && <span><span className="font-medium text-foreground">{day.counts.operator_replies}</span> operator reply</span>}
      </div>

      {day.events.length > 0 && (
        <ul className="space-y-2 text-xs">
          {day.events.slice(0, 25).map((e, i) => (
            <li key={i} className="flex items-start gap-2 border-l-2 border-[#390d58]/20 pl-3">
              <div className="flex-1 min-w-0">
                <p className="font-medium text-foreground">{e.title}</p>
                {e.summary && <p className="text-muted-foreground mt-0.5">{e.summary}</p>}
                <p className="text-[10px] text-muted-foreground mt-0.5">
                  {new Date(e.time).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
                  {' · '}
                  <span className="font-mono">{e.type}</span>
                </p>
              </div>
            </li>
          ))}
          {day.events.length > 25 && (
            <li className="text-[11px] text-muted-foreground italic">+ {day.events.length - 25} more event(s) — see Activity tab for the full list.</li>
          )}
        </ul>
      )}
    </div>
  )
}

function DocumentDetailView({ document, loading, onBack }: { document: DocumentDetail; loading: boolean; onBack: () => void }) {
  const isAgentDoc = document.kind === 'agent_document'
  const body = typeof document.payload?.body === 'string' ? (document.payload.body as string) : null
  const format = typeof document.payload?.format === 'string' ? (document.payload.format as string) : 'json'

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Button variant="outline" size="sm" onClick={onBack}>
          <ArrowLeft className="h-3.5 w-3.5 mr-1.5" />
          Back to files
        </Button>
        <div className="flex-1 min-w-0">
          <h3 className="text-sm font-semibold text-foreground truncate">{document.title}</h3>
          <p className="text-[11px] text-muted-foreground">
            <span className="font-mono">{document.kind}</span>
            {' · updated '}
            {formatRelativeTime(document.updated_at)}
            {document.campaign_level && ' · campaign-wide'}
          </p>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-8">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </div>
      ) : isAgentDoc && body ? (
        // Agent documents are markdown — render as preformatted text for now;
        // a proper markdown renderer is a follow-up.
        <div className={`rounded-lg border border-border bg-muted/30 p-4 text-sm ${format === 'json' ? 'font-mono whitespace-pre overflow-x-auto' : 'whitespace-pre-wrap'}`}>
          {body}
        </div>
      ) : (
        // Strict-typed documents render as pretty-printed JSON for now —
        // per-type rich rendering (table for keyword clusters, list for
        // refresh queue, etc) is a follow-up. Operator can read the
        // structured data directly.
        <div className="rounded-lg border border-border bg-muted/30 p-4 text-xs font-mono whitespace-pre overflow-x-auto">
          {JSON.stringify(document.payload, null, 2)}
        </div>
      )}
    </div>
  )
}

function DependencyRow({ dep, optional }: { dep: { provider: string; label: string; status: string; hint: string | null }; optional?: boolean }) {
  const ok = dep.status === 'ok'
  return (
    <div
      className={`flex items-center justify-between p-3 rounded-lg border ${optional ? 'border-dashed border-border' : 'border-border'}`}
    >
      <div className="min-w-0">
        <span className="text-sm text-foreground">{dep.label}</span>
        {optional && <span className="text-xs text-muted-foreground ml-2">(optional)</span>}
        {!ok && dep.hint && (
          <p className="text-[11px] text-muted-foreground mt-0.5">{dep.hint}</p>
        )}
      </div>
      {ok ? (
        <Badge className="shrink-0 bg-emerald-100 text-emerald-700 border-0 hover:bg-emerald-100">
          <CheckCircle2 className="h-3 w-3 mr-1" />
          Connected
        </Badge>
      ) : (
        <Badge variant="outline" className="shrink-0 text-muted-foreground capitalize">
          {dep.status.replace(/_/g, ' ')}
        </Badge>
      )}
    </div>
  )
}
