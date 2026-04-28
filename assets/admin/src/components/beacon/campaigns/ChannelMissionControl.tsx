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

  if (candidate?.summary) {
    return { content: candidate.summary, timestamp: candidate.created_at }
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
  const [loading, setLoading]           = useState(true)
  const [error, setError]               = useState<string | null>(null)
  const [answerInputs, setAnswerInputs] = useState<Record<string, string>>({})
  const [submittingQuestion, setSubmittingQuestion] = useState<string | null>(null)
  const [showResolved, setShowResolved] = useState(false)

  // Match the existing ChannelOverview pattern: fetch ledger + memory +
  // commitments in parallel on mount and whenever the selected channel
  // changes. Keeps the new view a drop-in replacement.
  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [ledgerRes, memoryRes, commitmentsRes] = await Promise.all([
        api.get<{ entries: LedgerEntry[] }>(`/campaigns/channels/${channel.key}/ledger?limit=50`),
        api.get<{ memory: Memory }>(`/campaigns/channels/${channel.key}/memory`).catch(() => ({ memory: {} as Memory })),
        api.get<{ open: Commitment[]; recently_resolved: Commitment[] }>(
          `/campaigns/channels/${channel.key}/commitments`,
        ).catch(() => ({ open: [] as Commitment[], recently_resolved: [] as Commitment[] })),
      ])
      setLedger(ledgerRes.entries ?? [])
      setMemory(memoryRes.memory ?? ({} as Memory))
      setCommitments({
        open: commitmentsRes.open ?? [],
        recently_resolved: commitmentsRes.recently_resolved ?? [],
      })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load channel state.')
    } finally {
      setLoading(false)
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
                <p className="text-white/95 text-lg leading-relaxed font-bold">
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
                  <p className="text-white/60 text-xs leading-tight">{agent.label}</p>
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
        <Tabs defaultValue="plan" className="w-full">
          <CardHeader className="pb-0 px-0 pt-0">
            <TabsList className="w-full justify-start bg-transparent px-6 pt-4 pb-0 h-auto gap-0 border-b border-border rounded-none">
              <TabTrigger value="plan" label="Plan" />
              <TabTrigger value="commitments" label="Commitments" count={overdueCount} warning />
              <TabTrigger value="questions" label="Questions" count={questions.length} />
              <TabTrigger value="activity" label="Activity" />
              <TabTrigger value="config" label="Configuration" />
            </TabsList>
          </CardHeader>

          <CardContent className="pt-6">
            {/* Plan */}
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

function TabTrigger({ value, label, count, warning }: { value: string; label: string; count?: number; warning?: boolean }) {
  return (
    <TabsTrigger
      value={value}
      className="rounded-none rounded-t-lg border-b-2 border-transparent data-[state=active]:border-[#390d58] data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 py-3 text-sm font-medium text-muted-foreground data-[state=active]:text-[#390d58] transition-colors"
    >
      {label}
      {count !== undefined && count > 0 && (
        <span
          className={`ml-2 px-1.5 py-0.5 text-[10px] rounded-full font-medium ${
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
