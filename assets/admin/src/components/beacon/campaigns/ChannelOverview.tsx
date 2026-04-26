import React, { useEffect, useState } from 'react'
import {
    Loader2,
    AlertTriangle,
    Info,
    Settings,
    Brain,
    Activity,
    TrendingUp,
    CheckCircle2,
    XCircle,
    Clock,
    RefreshCw,
    Zap,
    Wallet,
    CalendarDays,
    Eye,
    ListChecks,
    HelpCircle,
    Target,
    MessageSquare,
    Send,
    ChevronRight,
    Sparkles,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import type { ChannelEntry } from './ChannelSidebar'
import { CapabilitiesModal } from './CapabilitiesModal'

interface RequestStatus {
    status:       'pending' | 'claimed' | 'completed' | 'failed'
    retry_count:  number
    max_retries:  number
    claimed_at:   string | null
    completed_at: string | null
    error:        string | null
}

interface LedgerEntry {
    id:                      number
    channel:                 string | null
    entry_type:              string
    title:                   string
    summary:                 string | null
    data:                    Record<string, unknown>
    agent:                   { key: string; name: string } | null
    created_at:              string
    related_request_id?:     string | null
    related_request_status?: RequestStatus | null
}

/**
 * Activity-timeline filter buckets. Each bucket matches a set of entry_type
 * prefixes or exact types. Multi-select; "All" is mutually exclusive with
 * the rest.
 */
const FILTER_BUCKETS: { key: string; label: string; match: (type: string) => boolean }[] = [
    { key: 'all',      label: 'All',      match: () => true },
    {
        key: 'ai',
        label: 'AI Agent',
        match: (t) => t === 'agent_thinking' || t === 'agent_turn_cost' || t === 'operator_answer',
    },
    {
        key: 'work',
        label: 'Work',
        match: (t) => t.startsWith('automation_') || t === 'work_spend' || t === 'work_tracked',
    },
    {
        key: 'campaign',
        label: 'Campaign',
        match: (t) => t.startsWith('channel_') || t.startsWith('cycle_') || t === 'warmup_reset' || t === 'stop_trigger_hit',
    },
    {
        key: 'billing',
        label: 'Billing',
        match: (t) => t === 'management_fee_charged' || t === 'channel_paused_no_credits',
    },
]

interface Props {
    channel:   ChannelEntry
    onEdit:    () => void
    onResume:  () => Promise<void>
    onUnhire:  () => Promise<void>
    onSwap:    () => void
    busy:      null | 'resume' | 'unhire'
}

/**
 * Post-hire reporting view for a channel. Default state when a channel has
 * an agent — the setup form is accessed via the Edit button.
 *
 * Layout:
 *   - Agent banner (large, identity)
 *   - Status / warm-up info
 *   - Stats grid (budget, days remaining, automation counts)
 *   - "Agent's current take" — latest agent_thinking ledger entry
 *   - Activity timeline — recent ledger entries with icons
 *   - Actions: Edit setup / Swap agent / Unhire
 */
export function ChannelOverview({ channel, onEdit, onResume, onUnhire, onSwap, busy }: Props) {
    const [entries, setEntries]       = useState<LedgerEntry[]>([])
    const [loading, setLoading]       = useState(true)
    const [error, setError]           = useState<string | null>(null)
    const [answerDrafts, setDrafts]   = useState<Record<string, string>>({})
    const [sendingHash, setSending]   = useState<string | null>(null)
    const [activeFilters, setFilters] = useState<Set<string>>(new Set(['all']))
    const [capabilitiesOpen, setCapabilitiesOpen] = useState(false)

    const loadLedger = async () => {
        setLoading(true)
        setError(null)
        try {
            const res = await api.get<{ entries: LedgerEntry[] }>(
                `/campaigns/channels/${channel.key}/ledger?limit=50`
            )
            setEntries(res.entries ?? [])
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Could not load activity.')
        } finally {
            setLoading(false)
        }
    }

    const sendAnswer = async (question: string, questionHash: string) => {
        const answer = (answerDrafts[questionHash] ?? '').trim()
        if (!answer) return
        setSending(questionHash)
        try {
            await api.post(`/campaigns/channels/${channel.key}/answers`, { question, answer })
            setDrafts(d => { const copy = { ...d }; delete copy[questionHash]; return copy })
            await loadLedger()
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Could not send answer.')
        } finally {
            setSending(null)
        }
    }

    useEffect(() => { loadLedger() }, [channel.key])

    const agent = channel.agent!
    const warmup = channel.warmup
    const billing = channel.billing
    const setup = channel.setup

    const latestThinking = entries.find(e => e.entry_type === 'agent_thinking')
    const auditPlan = latestThinking && typeof latestThinking.data?.audit_plan === 'object'
        ? latestThinking.data.audit_plan as {
            observations?:   string[]
            checklist?:      string[]
            open_questions?: string[]
            priorities?:     string[]
          }
        : null

    // Match questions to answers by normalised question text. The backend
    // also stamps an MD5 hash for its own use; we match client-side by text
    // so the two sides stay decoupled.
    const norm = (s: string) => s.trim().toLowerCase().replace(/\s+/g, ' ')

    const answeredByText = new Map<string, { question: string; answer: string; at: string }>()
    for (const e of entries) {
        if (e.entry_type !== 'operator_answer') continue
        const q = String(e.data?.question ?? '')
        const a = String(e.data?.answer ?? '')
        if (!q || !a) continue
        answeredByText.set(norm(q), { question: q, answer: a, at: e.created_at })
    }

    const seenQuestions = new Set<string>()
    // Apply active filters to the ledger entries.
    const filteredEntries = activeFilters.has('all')
        ? entries
        : entries.filter(e => {
            for (const bucketKey of activeFilters) {
                const bucket = FILTER_BUCKETS.find(b => b.key === bucketKey)
                if (bucket && bucket.match(e.entry_type)) return true
            }
            return false
        })

    const unansweredQuestions: { text: string; key: string }[] = []
    const answeredExchanges: { question: string; answer: string; at: string; key: string }[] = []
    for (const e of entries) {
        if (e.entry_type !== 'agent_thinking') continue
        const plan = e.data?.audit_plan as { open_questions?: string[] } | undefined
        for (const q of plan?.open_questions ?? []) {
            const key = norm(q)
            if (seenQuestions.has(key)) continue
            seenQuestions.add(key)
            const answered = answeredByText.get(key)
            if (answered) {
                answeredExchanges.push({ ...answered, key })
            } else {
                unansweredQuestions.push({ text: q, key })
            }
        }
    }
    const automationEntries = entries.filter(e => e.entry_type.startsWith('automation_'))
    const automationsCompleted = automationEntries.filter(e => e.entry_type === 'automation_completed').length
    const automationsFailed = automationEntries.filter(e => e.entry_type === 'automation_failed').length
    const automationsPending = automationEntries.filter(e => e.entry_type === 'automation_requested').length

    const budgetPct = billing?.monthly_work_cap
        ? Math.min(100, Math.round((billing.monthly_work_spent / billing.monthly_work_cap) * 100))
        : null

    // Days remaining in current calendar month.
    const now = new Date()
    const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()
    const daysRemaining = daysInMonth - now.getDate() + 1

    return (
        <div className="flex-1 min-w-0 space-y-6">
            {/* Agent banner */}
            <div
                className="rounded-2xl p-5 text-white flex items-center gap-5"
                style={{ background: `linear-gradient(135deg, ${agent.color}dd, ${agent.color}99)` }}
            >
                <span className="text-5xl leading-none shrink-0">{agent.emoji}</span>
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-medium text-white/70 mb-0.5">Managing {channel.label}</p>
                    <p className="text-xl font-bold">{agent.label}</p>
                    <p className="text-xs text-white/80 mt-0.5 truncate">{agent.tagline}</p>
                    <button
                        type="button"
                        onClick={() => setCapabilitiesOpen(true)}
                        className="mt-1.5 inline-flex items-center gap-1 text-[11px] text-white/85 hover:text-white underline-offset-2 hover:underline"
                    >
                        <Sparkles className="h-3 w-3" />
                        Capabilities
                    </button>
                </div>
                {channel.pacing?.next_turn_at && (
                    <div className="bg-white/15 rounded-lg px-3 py-2 text-right shrink-0">
                        <p className="text-[10px] uppercase tracking-wide text-white/70">Next check-in</p>
                        <p className="text-sm font-semibold">{nextTurnLabel(channel.pacing.next_turn_at)}</p>
                    </div>
                )}
                {warmup?.active && (
                    <div className="bg-white/15 rounded-lg px-3 py-2 text-right shrink-0">
                        <p className="text-[10px] uppercase tracking-wide text-white/70">Warm-up</p>
                        <p className="text-sm font-semibold">Day {warmup.day ?? 0} of {warmup.total_days}</p>
                    </div>
                )}
            </div>

            {/* Status / warm-up banners */}
            {warmup?.active && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 flex items-start gap-2">
                    <Info className="h-4 w-4 shrink-0 mt-0.5" />
                    <span>
                        Channel is in its 90-day warm-up. {agent.label} is focused on audit, analysis, and initial setup —
                        don't judge performance yet.
                    </span>
                </div>
            )}

            {billing && billing.status !== 'active' && (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900 flex items-start gap-3">
                    <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                    <div className="flex-1">
                        {billing.status === 'monitor_mode'
                            ? `Budget cap reached. ${agent.label} is in monitor mode — no new work until the 1st.`
                            : `Channel paused — couldn't charge the monthly fee. Top up credits to resume.`}
                    </div>
                    {billing.status === 'paused_no_credits' && (
                        <Button
                            size="sm"
                            onClick={onResume}
                            disabled={busy === 'resume'}
                            className="bg-red-600 hover:bg-red-700 text-white"
                        >
                            {busy === 'resume' && <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />}
                            Resume
                        </Button>
                    )}
                </div>
            )}

            {/* Stats grid */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Stat
                    icon={<Wallet className="h-4 w-4" />}
                    label="Work budget"
                    value={billing?.monthly_work_cap != null
                        ? `${billing.monthly_work_spent.toLocaleString()} / ${billing.monthly_work_cap.toLocaleString()} cr`
                        : 'No cap set'}
                    sublabel={budgetPct != null ? `${budgetPct}% used` : undefined}
                    progress={budgetPct}
                    accent={agent.color}
                />
                <Stat
                    icon={<CalendarDays className="h-4 w-4" />}
                    label="Days left in month"
                    value={`${daysRemaining}`}
                    sublabel={`${daysInMonth}-day month`}
                    accent={agent.color}
                />
                <Stat
                    icon={<CheckCircle2 className="h-4 w-4" />}
                    label="Work completed"
                    value={`${automationsCompleted}`}
                    sublabel={automationsFailed > 0 ? `${automationsFailed} failed` : 'recent'}
                    accent={agent.color}
                />
                <Stat
                    icon={<Clock className="h-4 w-4" />}
                    label="In flight"
                    value={`${automationsPending}`}
                    sublabel="queued or running"
                    accent={agent.color}
                />
            </div>

            {/* Goal + KPI snapshot */}
            {(setup?.goal || setup?.primary_kpi) && (
                <div className="rounded-xl border bg-white p-5 space-y-3">
                    <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Strategy</h4>
                    {setup?.goal && (
                        <div>
                            <p className="text-xs text-muted-foreground">Goal</p>
                            <p className="text-sm mt-0.5">{setup.goal}</p>
                        </div>
                    )}
                    {setup?.primary_kpi && (
                        <div>
                            <p className="text-xs text-muted-foreground">Primary KPI</p>
                            <p className="text-sm mt-0.5">
                                <span className="capitalize">{setup.primary_kpi}</span>
                                {setup.kpi_target_value != null && (
                                    <span className="text-muted-foreground">
                                        {' '}— target {setup.kpi_target_value}
                                        {setup.kpi_target_unit ? ` ${setup.kpi_target_unit}` : ''}
                                    </span>
                                )}
                            </p>
                        </div>
                    )}
                </div>
            )}

            {/* Agent's current take */}
            <div className="rounded-xl border bg-white p-5">
                <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                        <Brain className="h-4 w-4" style={{ color: agent.color }} />
                        <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {agent.label}'s latest take
                        </h4>
                    </div>
                    {latestThinking && (
                        <span className="text-[11px] text-muted-foreground">
                            {timeAgo(latestThinking.created_at)}
                        </span>
                    )}
                </div>
                {latestThinking ? (
                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                        {typeof latestThinking.data?.thinking === 'string'
                            ? latestThinking.data.thinking
                            : (latestThinking.summary ?? '')}
                    </p>
                ) : (
                    <p className="text-sm text-muted-foreground italic">
                        {agent.label} hasn't run a turn yet. The first scheduled turn will write an overview here.
                    </p>
                )}
            </div>

            {/* Audit plan — structured output from warm-up turns */}
            {auditPlan && (
                <div className="rounded-xl border bg-white p-5 space-y-4">
                    <div className="flex items-center gap-2">
                        <ListChecks className="h-4 w-4" style={{ color: agent.color }} />
                        <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {agent.label}'s plan
                        </h4>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <PlanList
                            icon={<Eye className="h-3.5 w-3.5" />}
                            label="Observations"
                            items={auditPlan.observations ?? []}
                            accent={agent.color}
                        />
                        <PlanList
                            icon={<ListChecks className="h-3.5 w-3.5" />}
                            label="Checklist"
                            items={auditPlan.checklist ?? []}
                            accent={agent.color}
                        />
                        <PlanList
                            icon={<HelpCircle className="h-3.5 w-3.5" />}
                            label="Open questions"
                            items={auditPlan.open_questions ?? []}
                            accent={agent.color}
                            emphasise
                        />
                        <PlanList
                            icon={<Target className="h-3.5 w-3.5" />}
                            label="Priorities"
                            items={auditPlan.priorities ?? []}
                            accent={agent.color}
                        />
                    </div>
                </div>
            )}

            {/* Open questions awaiting operator input */}
            {unansweredQuestions.length > 0 && (
                <div className="rounded-xl border bg-white p-5 space-y-4">
                    <div className="flex items-center gap-2">
                        <MessageSquare className="h-4 w-4" style={{ color: agent.color }} />
                        <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {agent.label} needs your input
                        </h4>
                        <span
                            className="ml-auto text-[10px] font-medium px-2 py-0.5 rounded-full"
                            style={{ backgroundColor: `${agent.color}15`, color: agent.color }}
                        >
                            {unansweredQuestions.length} open
                        </span>
                    </div>
                    <p className="text-xs text-muted-foreground -mt-2">
                        Your answers will be read on the next turn, so {agent.label} doesn't have to re-ask.
                    </p>
                    <ul className="space-y-4">
                        {unansweredQuestions.map(q => (
                            <li key={q.key} className="space-y-2">
                                <p className="text-sm font-medium">{q.text}</p>
                                <div className="flex gap-2">
                                    <textarea
                                        value={answerDrafts[q.key] ?? ''}
                                        onChange={e => setDrafts(d => ({ ...d, [q.key]: e.target.value }))}
                                        placeholder={`Reply to ${agent.label}…`}
                                        rows={2}
                                        className="flex-1 rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                                    />
                                    <Button
                                        size="sm"
                                        onClick={() => sendAnswer(q.text, q.key)}
                                        disabled={!(answerDrafts[q.key] ?? '').trim() || sendingHash === q.key}
                                        className="text-white self-end"
                                        style={{ backgroundColor: agent.color }}
                                    >
                                        {sendingHash === q.key
                                            ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                            : <Send className="h-3.5 w-3.5" />}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Previously answered exchanges, collapsed */}
            {answeredExchanges.length > 0 && (
                <details className="rounded-xl border bg-white p-5 group">
                    <summary className="flex items-center gap-2 cursor-pointer list-none">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                        <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Answered exchanges
                        </span>
                        <span className="text-[10px] text-muted-foreground ml-auto">
                            {answeredExchanges.length} · click to expand
                        </span>
                    </summary>
                    <ul className="mt-4 space-y-3">
                        {answeredExchanges.map(ex => (
                            <li key={ex.key} className="border-l-2 pl-3" style={{ borderColor: `${agent.color}40` }}>
                                <p className="text-sm font-medium text-muted-foreground">{ex.question}</p>
                                <p className="text-sm mt-1 whitespace-pre-wrap">{ex.answer}</p>
                                <p className="text-[11px] text-muted-foreground mt-1">{timeAgo(ex.at)}</p>
                            </li>
                        ))}
                    </ul>
                </details>
            )}

            {/* Activity timeline */}
            <div className="rounded-xl border bg-white">
                <div className="flex items-center justify-between px-5 py-3 border-b gap-3 flex-wrap">
                    <div className="flex items-center gap-2">
                        <Activity className="h-4 w-4 text-muted-foreground" />
                        <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Recent activity
                        </h4>
                    </div>
                    <div className="flex items-center gap-1.5 flex-wrap">
                        {FILTER_BUCKETS.map(b => {
                            const active = activeFilters.has(b.key)
                            return (
                                <button
                                    key={b.key}
                                    onClick={() => setFilters(prev => {
                                        const next = new Set(prev)
                                        if (b.key === 'all') { return new Set(['all']) }
                                        // Selecting any specific bucket drops "all"
                                        next.delete('all')
                                        if (active) { next.delete(b.key) } else { next.add(b.key) }
                                        // Empty → reset to all
                                        return next.size === 0 ? new Set(['all']) : next
                                    })}
                                    className={`text-[11px] px-2 py-1 rounded-full border transition-colors ${
                                        active
                                            ? 'border-[#390d58] bg-[#390d58] text-white'
                                            : 'border-border text-muted-foreground hover:border-[#390d58]/40'
                                    }`}
                                >
                                    {b.label}
                                </button>
                            )
                        })}
                        <button
                            onClick={loadLedger}
                            className="text-[11px] text-muted-foreground hover:text-foreground flex items-center gap-1 ml-1"
                        >
                            <RefreshCw className="h-3 w-3" /> Refresh
                        </button>
                    </div>
                </div>

                {loading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                    </div>
                ) : error ? (
                    <p className="text-sm text-red-600 px-5 py-4">{error}</p>
                ) : filteredEntries.length === 0 ? (
                    <p className="text-sm text-muted-foreground px-5 py-6 text-center">
                        {entries.length === 0
                            ? 'No activity yet. Your first agent turn will land here.'
                            : 'No entries match the active filters.'}
                    </p>
                ) : (
                    <ul className="divide-y">
                        {filteredEntries.map(entry => (
                            <LedgerRow key={entry.id} entry={entry} accent={agent.color} />
                        ))}
                    </ul>
                )}
            </div>

            {/* Footer actions */}
            <div className="flex items-center justify-between gap-2 pt-2">
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" onClick={onSwap}>
                        Swap agent
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onUnhire}
                        disabled={busy === 'unhire'}
                        className="text-red-600 border-red-200 hover:bg-red-50"
                    >
                        {busy === 'unhire' && <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />}
                        Unhire
                    </Button>
                </div>
                <Button
                    size="sm"
                    onClick={onEdit}
                    className="bg-[#390d58] hover:bg-[#2d0a47] text-white"
                >
                    <Settings className="h-3.5 w-3.5 mr-1.5" />
                    Edit setup
                </Button>
            </div>

            {capabilitiesOpen && (
                <CapabilitiesModal
                    channel={channel.key}
                    agentLabel={agent.label}
                    onClose={() => setCapabilitiesOpen(false)}
                />
            )}
        </div>
    )
}

// ── Audit plan list ───────────────────────────────────────────────────────

function PlanList({
    icon,
    label,
    items,
    accent,
    emphasise = false,
}: {
    icon:       React.ReactNode
    label:      string
    items:      string[]
    accent:     string
    emphasise?: boolean
}) {
    return (
        <div>
            <div className="flex items-center gap-1.5 text-muted-foreground text-[11px] uppercase tracking-wide mb-2" style={emphasise ? { color: accent } : undefined}>
                {icon}
                <span>{label}</span>
            </div>
            {items.length === 0 ? (
                <p className="text-xs text-muted-foreground italic">(none)</p>
            ) : (
                <ul className="space-y-1.5">
                    {items.map((item, i) => (
                        <li key={i} className="text-sm leading-snug flex gap-2">
                            <span className="shrink-0 mt-1.5 h-1 w-1 rounded-full" style={{ background: accent }} />
                            <span>{item}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    )
}

// ── Stat card ─────────────────────────────────────────────────────────────

function Stat({
    icon,
    label,
    value,
    sublabel,
    progress,
    accent,
}: {
    icon:      React.ReactNode
    label:     string
    value:     string
    sublabel?: string
    progress?: number | null
    accent:    string
}) {
    return (
        <div className="rounded-xl border bg-white p-4">
            <div className="flex items-center gap-1.5 text-muted-foreground text-[11px] uppercase tracking-wide mb-1.5">
                {icon}
                <span>{label}</span>
            </div>
            <p className="text-lg font-semibold">{value}</p>
            {sublabel && <p className="text-[11px] text-muted-foreground mt-0.5">{sublabel}</p>}
            {progress != null && (
                <div className="mt-2 h-1.5 rounded-full bg-muted overflow-hidden">
                    <div
                        className="h-full rounded-full transition-all"
                        style={{ width: `${progress}%`, background: accent }}
                    />
                </div>
            )}
        </div>
    )
}

// ── Ledger row ────────────────────────────────────────────────────────────

const ENTRY_META: Record<string, { icon: React.ReactNode; color: string }> = {
    agent_thinking:             { icon: <Brain className="h-3.5 w-3.5" />,       color: 'text-violet-600' },
    operator_answer:            { icon: <MessageSquare className="h-3.5 w-3.5" />, color: 'text-blue-600' },
    automation_requested:       { icon: <Zap className="h-3.5 w-3.5" />,         color: 'text-blue-600' },
    automation_completed:       { icon: <CheckCircle2 className="h-3.5 w-3.5" />, color: 'text-emerald-600' },
    automation_failed:          { icon: <XCircle className="h-3.5 w-3.5" />,     color: 'text-red-600' },
    automation_retry:           { icon: <RefreshCw className="h-3.5 w-3.5" />,   color: 'text-amber-600' },
    automation_rejected:        { icon: <XCircle className="h-3.5 w-3.5" />,     color: 'text-red-600' },
    management_fee_charged:     { icon: <Wallet className="h-3.5 w-3.5" />,      color: 'text-slate-600' },
    channel_hired:              { icon: <TrendingUp className="h-3.5 w-3.5" />,  color: 'text-emerald-600' },
    channel_rehired:            { icon: <TrendingUp className="h-3.5 w-3.5" />,  color: 'text-emerald-600' },
    channel_unhired:            { icon: <XCircle className="h-3.5 w-3.5" />,     color: 'text-slate-600' },
    channel_updated:            { icon: <Settings className="h-3.5 w-3.5" />,    color: 'text-slate-600' },
    channel_resumed:            { icon: <TrendingUp className="h-3.5 w-3.5" />,  color: 'text-emerald-600' },
    channel_paused_no_credits:  { icon: <AlertTriangle className="h-3.5 w-3.5" />, color: 'text-red-600' },
    channel_monitor_mode:       { icon: <AlertTriangle className="h-3.5 w-3.5" />, color: 'text-amber-600' },
    stop_trigger_hit:           { icon: <AlertTriangle className="h-3.5 w-3.5" />, color: 'text-red-600' },
    warmup_reset:               { icon: <RefreshCw className="h-3.5 w-3.5" />,   color: 'text-amber-600' },
    cycle_opened:               { icon: <CalendarDays className="h-3.5 w-3.5" />, color: 'text-blue-600' },
    cycle_closed:               { icon: <CalendarDays className="h-3.5 w-3.5" />, color: 'text-slate-600' },
    work_spend:                 { icon: <Wallet className="h-3.5 w-3.5" />,      color: 'text-slate-600' },
    agent_turn_cost:            { icon: <Brain className="h-3.5 w-3.5" />,       color: 'text-violet-400' },
    automation_cost:            { icon: <Wallet className="h-3.5 w-3.5" />,      color: 'text-slate-600' },
    work_tracked:               { icon: <Wallet className="h-3.5 w-3.5" />,      color: 'text-slate-600' },
}

const REQUEST_STATUS_STYLE: Record<string, string> = {
    pending:   'bg-amber-100 text-amber-900',
    claimed:   'bg-blue-100 text-blue-900',
    completed: 'bg-emerald-100 text-emerald-900',
    failed:    'bg-red-100 text-red-900',
}

function LedgerRow({ entry, accent }: { entry: LedgerEntry; accent: string }) {
    const [open, setOpen] = useState(false)
    const meta = ENTRY_META[entry.entry_type] ?? { icon: <Activity className="h-3.5 w-3.5" />, color: 'text-muted-foreground' }
    const reqStatus = entry.related_request_status
    const hasDetail = Object.keys(entry.data ?? {}).length > 0 || (entry.summary?.length ?? 0) > 240

    return (
        <li className={`px-5 py-3 hover:bg-muted/30 ${hasDetail ? 'cursor-pointer' : ''}`}
            onClick={hasDetail ? () => setOpen(o => !o) : undefined}>
            <div className="flex items-start gap-3">
                <div className={`shrink-0 mt-0.5 ${meta.color}`}>{meta.icon}</div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <p className="text-sm font-medium truncate">{entry.title}</p>
                        {reqStatus && (
                            <span
                                className={`text-[10px] font-medium px-1.5 py-0.5 rounded-full ${REQUEST_STATUS_STYLE[reqStatus.status] ?? 'bg-slate-100 text-slate-700'}`}
                                title={reqStatus.error ?? undefined}
                            >
                                {reqStatus.status}
                                {reqStatus.retry_count > 0 && ` · retry ${reqStatus.retry_count}/${reqStatus.max_retries}`}
                            </span>
                        )}
                        {hasDetail && (
                            <ChevronRight className={`h-3 w-3 text-muted-foreground ml-auto transition-transform ${open ? 'rotate-90' : ''}`} />
                        )}
                    </div>
                    {entry.summary && !open && (
                        <p className="text-xs text-muted-foreground mt-0.5 whitespace-pre-wrap line-clamp-3">
                            {entry.summary}
                        </p>
                    )}
                    {reqStatus?.error && !open && (
                        <p className="text-[11px] text-red-600 mt-1 line-clamp-2">{reqStatus.error}</p>
                    )}
                    <div className="flex items-center gap-2 text-[11px] text-muted-foreground mt-1.5">
                        {entry.agent && <span style={{ color: accent }}>{entry.agent.name}</span>}
                        {entry.channel && <span>· {entry.channel}</span>}
                        <span className="ml-auto">{timeAgo(entry.created_at)}</span>
                    </div>
                </div>
            </div>

            {open && <LedgerRowDetail entry={entry} reqStatus={reqStatus} />}
        </li>
    )
}

/**
 * Rich per-type rendering of a ledger entry's `data` field. Different entry
 * types carry different structured payloads; render the useful bits inline
 * (parameters, reasoning, full thinking, audit plans, results, errors) and
 * fall back to a raw JSON dump for anything unknown.
 */
function LedgerRowDetail({
    entry,
    reqStatus,
}: {
    entry:     LedgerEntry
    reqStatus: RequestStatus | null | undefined
}) {
    const d = entry.data ?? {}
    const t = entry.entry_type

    const sections: { label: string; content: React.ReactNode }[] = []

    if (entry.summary && entry.summary.length > 0) {
        // Always available and may be long — show in full when expanded.
        sections.push({
            label: 'Summary',
            content: <p className="text-sm whitespace-pre-wrap">{entry.summary}</p>,
        })
    }

    if (t === 'agent_thinking' && typeof d.thinking === 'string' && d.thinking) {
        sections.push({
            label: 'Full reasoning',
            content: <p className="text-sm whitespace-pre-wrap">{d.thinking}</p>,
        })
    }

    if (t === 'automation_requested') {
        if (typeof d.reasoning === 'string' && d.reasoning) {
            sections.push({
                label: 'Reasoning',
                content: <p className="text-sm whitespace-pre-wrap">{d.reasoning}</p>,
            })
        }
        if (d.parameters && typeof d.parameters === 'object') {
            sections.push({
                label: 'Parameters sent',
                content: <KeyValueBlock data={d.parameters as Record<string, unknown>} />,
            })
        }
    }

    if (t === 'automation_completed' || t === 'automation_failed' || t === 'automation_retry') {
        if (typeof d.error === 'string' && d.error) {
            sections.push({
                label: 'Error',
                content: <p className="text-sm whitespace-pre-wrap text-red-700">{d.error}</p>,
            })
        }
        if (d.gaps && Array.isArray(d.gaps)) {
            sections.push({
                label: 'Gaps identified',
                content: <ol className="space-y-2 list-decimal list-inside text-sm">
                    {(d.gaps as Record<string, unknown>[]).map((g, i) => (
                        <li key={i}>
                            <span className="font-medium">{String(g.title ?? 'Untitled')}</span>
                            {g.priority ? <span className="text-[10px] uppercase ml-1.5 px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-700">{String(g.priority)}</span> : null}
                            {g.rationale ? <p className="text-xs text-muted-foreground mt-0.5">{String(g.rationale)}</p> : null}
                        </li>
                    ))}
                </ol>,
            })
        }
    }

    if (reqStatus) {
        sections.push({
            label: 'Request state',
            content: <KeyValueBlock data={{
                status:       reqStatus.status,
                retry_count:  `${reqStatus.retry_count}/${reqStatus.max_retries}`,
                claimed_at:   reqStatus.claimed_at,
                completed_at: reqStatus.completed_at,
                error:        reqStatus.error,
            }} />,
        })
    }

    // Always let power users see the raw data too.
    if (Object.keys(d).length > 0) {
        sections.push({
            label: 'Raw data',
            content: <pre className="text-[11px] bg-muted/40 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all max-h-[280px] overflow-y-auto">
                {JSON.stringify(d, null, 2)}
            </pre>,
        })
    }

    return (
        <div className="mt-3 ml-7 space-y-3" onClick={e => e.stopPropagation()}>
            {sections.map((s, i) => (
                <div key={i}>
                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground mb-1">{s.label}</p>
                    {s.content}
                </div>
            ))}
        </div>
    )
}

function KeyValueBlock({ data }: { data: Record<string, unknown> }) {
    const entries = Object.entries(data).filter(([, v]) => v !== null && v !== undefined && v !== '')
    if (entries.length === 0) {
        return <p className="text-xs text-muted-foreground italic">(no values)</p>
    }
    return (
        <dl className="grid grid-cols-[120px_1fr] gap-x-3 gap-y-1 text-xs">
            {entries.map(([k, v]) => (
                <React.Fragment key={k}>
                    <dt className="text-muted-foreground font-mono">{k}</dt>
                    <dd className="whitespace-pre-wrap break-words">
                        {typeof v === 'object' ? JSON.stringify(v, null, 2) : String(v)}
                    </dd>
                </React.Fragment>
            ))}
        </dl>
    )
}

// ── Helpers ───────────────────────────────────────────────────────────────

function nextTurnLabel(iso: string): string {
    const then = new Date(iso).getTime()
    const minsFromNow = Math.floor((then - Date.now()) / 60000)
    if (minsFromNow <= 0)      return 'now'
    if (minsFromNow < 60)      return `in ${minsFromNow}m`
    const hours = Math.floor(minsFromNow / 60)
    if (hours < 24)            return `in ${hours}h`
    const days = Math.floor(hours / 24)
    if (days < 7)              return `in ${days}d`
    return new Date(iso).toLocaleDateString()
}

function timeAgo(iso: string): string {
    const then = new Date(iso).getTime()
    const mins = Math.floor((Date.now() - then) / 60000)
    if (mins < 1)    return 'just now'
    if (mins < 60)   return `${mins}m ago`
    const hours = Math.floor(mins / 60)
    if (hours < 24)  return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 7)    return `${days}d ago`
    return new Date(iso).toLocaleDateString()
}
