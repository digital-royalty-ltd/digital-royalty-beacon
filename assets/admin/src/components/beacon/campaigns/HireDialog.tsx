import { useEffect, useState } from 'react'
import { X, Loader2, CheckCircle2, AlertTriangle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { AiCharacter } from './AiCharacterCard'
import { api } from '@/lib/api'

export interface ChannelOption {
  key:   string
  label: string
}

interface HireQuote {
  channels:            string[]
  count:               number
  per_channel_fee:     number
  first_charge:        number
  pro_rata_days:       number
  days_in_month:       number
  includes_next_month: boolean
  required_balance:    number
  current_balance:     number
  can_afford:          boolean
}

interface Props {
  agentKey:             string
  agent:                AiCharacter
  channels:             ChannelOption[]
  preselectedChannels?: string[]
  /** If true, the preselected channel is locked (used when hiring for a specific empty channel). */
  lockPreselected?:     boolean
  onComplete:           () => void
  onDismiss:            () => void
}

/**
 * Simplified single-step hire dialog.
 *
 * Shows the chosen agent at the top, then a checkbox list of marketing
 * channels. The user picks one or more and submits — no multi-step wizard.
 * Deeper channel setup happens later inside each channel's own screen.
 */
export function HireDialog({
  agentKey,
  agent,
  channels,
  preselectedChannels = [],
  lockPreselected = false,
  onComplete,
  onDismiss,
}: Props) {
  const [selected, setSelected] = useState<string[]>(preselectedChannels)
  const [saving, setSaving]     = useState(false)
  const [error, setError]       = useState<string | null>(null)
  const [quote, setQuote]       = useState<HireQuote | null>(null)
  const [quoting, setQuoting]   = useState(false)

  // Fetch a fresh quote whenever the channel selection changes.
  useEffect(() => {
    if (selected.length === 0) { setQuote(null); return }

    let cancelled = false
    setQuoting(true)
    const params = new URLSearchParams()
    selected.forEach(c => params.append('channels[]', c))
    api.get<HireQuote>(`/campaigns/hire-quote?${params.toString()}`)
      .then(q => { if (!cancelled) setQuote(q) })
      .catch(() => { if (!cancelled) setQuote(null) })
      .finally(() => { if (!cancelled) setQuoting(false) })

    return () => { cancelled = true }
  }, [selected.join(',')])

  const toggle = (key: string) => {
    if (lockPreselected && preselectedChannels.includes(key)) return
    setSelected(prev => prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key])
  }

  const handleSubmit = async () => {
    if (selected.length === 0 || saving) return
    setSaving(true)
    setError(null)
    try {
      await api.post('/campaigns/hire', { agent_key: agentKey, channels: selected })
      onComplete()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to hire agent.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden">
        {/* Agent header */}
        <div
          className="relative px-6 py-5 text-white flex items-center gap-4"
          style={{ background: `linear-gradient(135deg, ${agent.color}dd, ${agent.color}99)` }}
        >
          <span className="text-4xl leading-none">{agent.emoji}</span>
          <div className="flex-1 min-w-0">
            <p className="text-[10px] uppercase tracking-wide font-semibold text-white/70">Hiring</p>
            <h3 className="text-lg font-bold">{agent.label}</h3>
            <p className="text-xs text-white/80 truncate">{agent.tagline}</p>
          </div>
          <button
            onClick={onDismiss}
            className="text-white/70 hover:text-white"
            aria-label="Close"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Channel picker */}
        <div className="px-6 py-5">
          <p className="text-sm font-medium text-[#390d58] mb-1">
            Which channels should {agent.label} run?
          </p>
          <p className="text-xs text-muted-foreground mb-4">
            You can change this later. Pick one or more to get started.
          </p>

          <div className="space-y-2">
            {channels.map(ch => {
              const isSelected = selected.includes(ch.key)
              const isLocked   = lockPreselected && preselectedChannels.includes(ch.key)
              return (
                <button
                  key={ch.key}
                  type="button"
                  onClick={() => toggle(ch.key)}
                  disabled={isLocked}
                  className={`w-full flex items-center justify-between rounded-lg border px-4 py-3 text-sm transition-all
                    ${isSelected
                      ? 'border-[#390d58] bg-[#390d58]/5 text-[#390d58]'
                      : 'border-border bg-white hover:bg-muted/40'}
                    ${isLocked ? 'opacity-80 cursor-not-allowed' : ''}
                  `}
                >
                  <span className="font-medium">{ch.label}</span>
                  {isSelected
                    ? <CheckCircle2 className="h-4 w-4" style={{ color: agent.color }} />
                    : <span className="h-4 w-4 rounded-full border border-muted-foreground/30" />
                  }
                </button>
              )
            })}
          </div>

          {/* Cost preview — live quote */}
          {selected.length > 0 && (
            <div className="mt-4 rounded-lg border bg-muted/30 px-4 py-3 text-xs space-y-1.5">
              <div className="flex items-center justify-between text-[11px] uppercase tracking-wide text-muted-foreground">
                <span>Charge preview</span>
                {quoting && <Loader2 className="h-3 w-3 animate-spin" />}
              </div>
              {quote ? (
                <>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">
                      {quote.count} channel{quote.count === 1 ? '' : 's'} × {quote.per_channel_fee.toLocaleString()} cr/mo
                    </span>
                  </div>
                  {quote.includes_next_month ? (
                    <>
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Pro-rata this month ({quote.pro_rata_days}/{quote.days_in_month} days)</span>
                        <span>{(quote.first_charge - (quote.count * quote.per_channel_fee)).toLocaleString()} cr</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Next full month</span>
                        <span>{(quote.count * quote.per_channel_fee).toLocaleString()} cr</span>
                      </div>
                    </>
                  ) : (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">This month</span>
                      <span>{quote.first_charge.toLocaleString()} cr</span>
                    </div>
                  )}
                  <div className="flex justify-between pt-1.5 border-t font-semibold">
                    <span>Due now</span>
                    <span>{quote.first_charge.toLocaleString()} cr</span>
                  </div>
                  <div className="flex justify-between text-[11px] pt-1">
                    <span className="text-muted-foreground">Your balance</span>
                    <span className={quote.can_afford ? 'text-muted-foreground' : 'text-red-600 font-medium'}>
                      {quote.current_balance.toLocaleString()} cr
                    </span>
                  </div>
                  {!quote.can_afford && (
                    <div className="flex items-start gap-1.5 text-[11px] text-red-700 bg-red-50 rounded px-2 py-1.5 mt-1">
                      <AlertTriangle className="h-3 w-3 shrink-0 mt-0.5" />
                      <span>
                        You need {quote.required_balance.toLocaleString()} credits to hire (2× the charge, as working capital).
                        Top up before continuing.
                      </span>
                    </div>
                  )}
                </>
              ) : !quoting && (
                <p className="text-muted-foreground">Could not load quote.</p>
              )}
            </div>
          )}

          {error && (
            <p className="mt-4 text-xs text-red-600 bg-red-50 border border-red-100 rounded px-3 py-2">
              {error}
            </p>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 px-6 py-4 border-t bg-muted/20">
          <Button variant="outline" size="sm" onClick={onDismiss} disabled={saving}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={handleSubmit}
            disabled={selected.length === 0 || saving || (quote !== null && !quote.can_afford)}
            className="text-white"
            style={{ backgroundColor: agent.color }}
          >
            {saving && <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />}
            Hire {agent.label}
          </Button>
        </div>
      </div>
    </div>
  )
}
