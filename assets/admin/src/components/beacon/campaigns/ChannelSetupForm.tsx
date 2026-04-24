import { useMemo, useState } from 'react'
import { Loader2, AlertTriangle, Info, RotateCcw, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import type { ChannelEntry, ChannelSetup } from './ChannelSidebar'
import { WarmupResetModal } from './WarmupResetModal'

interface Props {
  channel:     ChannelEntry
  onUpdated:   (updated: ChannelEntry[]) => void
  /** Fired when the user clicks "Swap agent" — parent switches the right pane to the agent picker. */
  onRequestSwap: () => void
  /** Fired when the user clicks Cancel — parent switches back to overview. */
  onCancel:    () => void
}

const MANAGEMENT_FEE_PER_CHANNEL = 50_000

type FormState = {
  goal:              string
  primary_kpi:       string
  kpi_target_value:  string
  kpi_target_unit:   string
  monthly_work_cap:  string
  autonomy:          ChannelSetup['autonomy']
  cadence:           ChannelSetup['cadence']
  risk_tolerance:    number
  guardrails:        string
}

const KPIS: { value: string; label: string }[] = [
  { value: '',             label: '— Not set —' },
  { value: 'traffic',      label: 'Traffic' },
  { value: 'leads',        label: 'Leads' },
  { value: 'conversions',  label: 'Conversions' },
  { value: 'revenue',      label: 'Revenue' },
  { value: 'custom',       label: 'Custom' },
]

const AUTONOMY: { value: FormState['autonomy']; label: string; hint: string }[] = [
  { value: 'autopilot',   label: 'Autopilot',   hint: 'Agent acts, publishes, spends within budget.' },
  { value: 'review',      label: 'Review',      hint: 'Drafts queue for your approval before anything ships.' },
  { value: 'suggestions', label: 'Suggestions', hint: 'Nothing happens without you manually triggering it.' },
]

const CADENCE: { value: FormState['cadence']; label: string }[] = [
  { value: 'daily',     label: 'Daily' },
  { value: 'weekly',    label: 'Weekly' },
  { value: 'on_demand', label: 'On-demand' },
]

function asInitial(s: ChannelSetup | null): FormState {
  return {
    goal:              s?.goal ?? '',
    primary_kpi:       s?.primary_kpi ?? '',
    kpi_target_value:  s?.kpi_target_value != null ? String(s.kpi_target_value) : '',
    kpi_target_unit:   s?.kpi_target_unit ?? '',
    monthly_work_cap:  s?.monthly_work_cap != null ? String(s.monthly_work_cap) : '',
    autonomy:          s?.autonomy ?? 'autopilot',
    cadence:           s?.cadence ?? 'weekly',
    risk_tolerance:    s?.risk_tolerance ?? 3,
    guardrails:        s?.guardrails ?? '',
  }
}

function toPayload(form: FormState, initial: FormState): Record<string, unknown> {
  const payload: Record<string, unknown> = {}
  const addIfChanged = <K extends keyof FormState>(k: K, transform?: (v: FormState[K]) => unknown) => {
    if (form[k] !== initial[k]) payload[k] = transform ? transform(form[k]) : form[k]
  }
  addIfChanged('goal',             v => (v as string).trim() || null)
  addIfChanged('primary_kpi',      v => (v as string) || null)
  addIfChanged('kpi_target_value', v => v === '' ? null : Number(v))
  addIfChanged('kpi_target_unit',  v => (v as string).trim() || null)
  addIfChanged('monthly_work_cap', v => v === '' ? null : Number(v))
  addIfChanged('autonomy')
  addIfChanged('cadence')
  addIfChanged('risk_tolerance')
  addIfChanged('guardrails', v => (v as string).trim() || null)
  return payload
}

/**
 * Channel setup form: strategy, operation, guardrails. Billing is disclosed
 * but not editable. Fields that reset warm-up show an amber icon; the Save
 * button triggers a confirmation modal before persisting warm-up-resetting
 * changes.
 */
export function ChannelSetupForm({ channel, onUpdated, onCancel }: Props) {
  const [initial, setInitial] = useState<FormState>(asInitial(channel.setup))
  const [form, setForm]       = useState<FormState>(asInitial(channel.setup))
  const [saving, setSaving]   = useState(false)
  const [error, setError]     = useState<string | null>(null)
  const [showResetModal, setShowResetModal] = useState(false)

  // Recompute initial when the selected channel changes.
  useMemo(() => {
    const init = asInitial(channel.setup)
    setInitial(init)
    setForm(init)
    setError(null)
  }, [channel.key, channel.agent?.key])

  const resetTriggers: string[] = []
  if (form.goal.trim() !== initial.goal.trim() && initial.goal.trim() !== '') {
    resetTriggers.push('goal')
  }
  if (form.primary_kpi !== initial.primary_kpi && initial.primary_kpi !== '') {
    resetTriggers.push('primary_kpi')
  }

  const dirty = Object.keys(toPayload(form, initial)).length > 0

  const save = async () => {
    setSaving(true)
    setError(null)
    try {
      const payload = toPayload(form, initial)
      const res = await api.put<{ channels: ChannelEntry[] }>(
        `/campaigns/channels/${channel.key}`,
        payload
      )
      onUpdated(res.channels)
      setInitial(form)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not save changes.')
    } finally {
      setSaving(false)
      setShowResetModal(false)
    }
  }

  const handleSaveClick = () => {
    if (resetTriggers.length > 0) {
      setShowResetModal(true)
      return
    }
    save()
  }

  const warmup = channel.warmup
  const billing = channel.billing
  const agent = channel.agent

  return (
    <>
      {showResetModal && (
        <WarmupResetModal
          changedFields={resetTriggers}
          currentDay={warmup?.day ?? null}
          totalDays={warmup?.total_days ?? 90}
          onConfirm={save}
          onCancel={() => setShowResetModal(false)}
        />
      )}

      <div className="space-y-6">
        {/* Agent banner */}
        {agent && (
          <div
            className="rounded-2xl p-5 text-white flex items-center gap-5"
            style={{ background: `linear-gradient(135deg, ${agent.color}dd, ${agent.color}99)` }}
          >
            <span className="text-5xl leading-none">{agent.emoji}</span>
            <div className="flex-1 min-w-0">
              <p className="text-xs font-medium text-white/70 mb-0.5">Managing {channel.label}</p>
              <p className="text-xl font-bold">{agent.label}</p>
              <p className="text-xs text-white/80 mt-0.5 truncate">{agent.tagline}</p>
            </div>
            {warmup?.active && (
              <div className="bg-white/15 rounded-lg px-3 py-2 text-right shrink-0">
                <p className="text-[10px] uppercase tracking-wide text-white/70">Warm-up</p>
                <p className="text-sm font-semibold">
                  Day {warmup.day ?? 0} of {warmup.total_days}
                </p>
              </div>
            )}
          </div>
        )}

        {/* Warm-up info */}
        {warmup?.active && (
          <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 flex items-start gap-2">
            <Info className="h-4 w-4 shrink-0 mt-0.5" />
            <span>
              This channel is in its 90-day warm-up. Your agent is focused on audit and setup — performance
              judgements aren't reliable until warm-up ends.
              Changing the goal or KPI will restart this clock.
            </span>
          </div>
        )}

        {/* Status warning — resume/unhire actions live in the Overview view */}
        {billing && billing.status !== 'active' && (
          <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900 flex items-start gap-3">
            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
            <div className="flex-1">
              {billing.status === 'monitor_mode'
                ? 'Budget cap reached for this month. The agent is in monitor mode — no new work until the 1st.'
                : "This channel is paused — couldn't charge the monthly fee. Return to the overview to resume."}
            </div>
          </div>
        )}

        {/* ── Strategy ─────────────────────────────── */}
        <Section title="Strategy">
          <Field label="Goal" reset hint="What should this channel achieve?">
            <textarea
              value={form.goal}
              onChange={e => setForm(f => ({ ...f, goal: e.target.value }))}
              rows={3}
              className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              placeholder="e.g. Grow qualified leads by 25% over the next quarter"
            />
          </Field>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Primary KPI" reset>
              <select
                value={form.primary_kpi}
                onChange={e => setForm(f => ({ ...f, primary_kpi: e.target.value }))}
                className="w-full rounded-md border px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              >
                {KPIS.map(k => <option key={k.value} value={k.value}>{k.label}</option>)}
              </select>
            </Field>

            <Field label="KPI target (optional)">
              <div className="flex gap-2">
                <input
                  type="number"
                  value={form.kpi_target_value}
                  onChange={e => setForm(f => ({ ...f, kpi_target_value: e.target.value }))}
                  placeholder="250"
                  className="flex-1 min-w-0 rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
                <input
                  type="text"
                  value={form.kpi_target_unit}
                  onChange={e => setForm(f => ({ ...f, kpi_target_unit: e.target.value }))}
                  placeholder="/mo"
                  className="w-20 rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
              </div>
            </Field>
          </div>
        </Section>

        {/* ── Operation ────────────────────────────── */}
        <Section title="Operation">
          <Field label="Monthly work budget (credits)" hint="Hard cap on agent decisions + automation spend. Management fee is separate.">
            <input
              type="number"
              value={form.monthly_work_cap}
              onChange={e => setForm(f => ({ ...f, monthly_work_cap: e.target.value }))}
              placeholder="No cap"
              className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
            />
          </Field>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Autonomy">
              <select
                value={form.autonomy}
                onChange={e => setForm(f => ({ ...f, autonomy: e.target.value as FormState['autonomy'] }))}
                className="w-full rounded-md border px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              >
                {AUTONOMY.map(a => <option key={a.value} value={a.value}>{a.label}</option>)}
              </select>
              <p className="text-[11px] text-muted-foreground mt-1">
                {AUTONOMY.find(a => a.value === form.autonomy)?.hint}
              </p>
            </Field>

            <Field label="Cadence">
              <select
                value={form.cadence}
                onChange={e => setForm(f => ({ ...f, cadence: e.target.value as FormState['cadence'] }))}
                className="w-full rounded-md border px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              >
                {CADENCE.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
              </select>
            </Field>
          </div>

          <Field label="Risk tolerance" hint="Optional — 1 conservative, 5 bold. Modulates the agent's default style.">
            <div className="flex items-center gap-3">
              <input
                type="range"
                min={1}
                max={5}
                step={1}
                value={form.risk_tolerance}
                onChange={e => setForm(f => ({ ...f, risk_tolerance: Number(e.target.value) }))}
                className="flex-1"
              />
              <span className="text-sm font-medium w-8 text-center">{form.risk_tolerance}</span>
            </div>
          </Field>
        </Section>

        {/* ── Guardrails ───────────────────────────── */}
        <Section title="Guardrails">
          <Field label="Never do / avoid" hint="Hard rules. The agent reads these on every turn.">
            <textarea
              value={form.guardrails}
              onChange={e => setForm(f => ({ ...f, guardrails: e.target.value }))}
              rows={4}
              className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              placeholder="e.g. Never mention competitor X. Avoid discussing pending litigation. Stay formal in all written copy."
            />
          </Field>
        </Section>

        {/* ── Billing ──────────────────────────────── */}
        <Section title="Billing">
          <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div className="flex justify-between">
              <dt className="text-muted-foreground">Management fee</dt>
              <dd className="font-medium">{MANAGEMENT_FEE_PER_CHANNEL.toLocaleString()} cr/month</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-muted-foreground">Status</dt>
              <dd className="font-medium capitalize">{billing?.status?.replace(/_/g, ' ') ?? '—'}</dd>
            </div>
            {billing?.monthly_work_cap != null && (
              <>
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">Work spent this month</dt>
                  <dd className="font-medium">
                    {billing.monthly_work_spent.toLocaleString()} / {billing.monthly_work_cap.toLocaleString()} cr
                  </dd>
                </div>
              </>
            )}
            {warmup?.reset_count ? (
              <div className="flex justify-between">
                <dt className="text-muted-foreground">Warm-up resets</dt>
                <dd className="font-medium">{warmup.reset_count}</dd>
              </div>
            ) : null}
          </dl>
        </Section>

        {error && (
          <p className="text-xs text-red-600 bg-red-50 border border-red-100 rounded px-3 py-2">
            {error}
          </p>
        )}

        <div className="flex items-center justify-between gap-2 pt-2">
          {/* Left: return to overview */}
          <Button variant="outline" size="sm" onClick={onCancel} disabled={saving}>
            <X className="h-3.5 w-3.5 mr-1.5" />
            Cancel
          </Button>

          {/* Right: discard + save */}
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={!dirty || saving}
              onClick={() => setForm(initial)}
            >
              <RotateCcw className="h-3.5 w-3.5 mr-1.5" />
              Discard
            </Button>
            <Button
              size="sm"
              disabled={!dirty || saving}
              onClick={handleSaveClick}
              className="bg-[#390d58] hover:bg-[#2d0a47] text-white"
            >
              {saving && <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />}
              Save
            </Button>
          </div>
        </div>
      </div>
    </>
  )
}

// ── Layout helpers ─────────────────────────────────────────────────────────

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-xl border bg-white p-5 space-y-4">
      <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{title}</h4>
      {children}
    </section>
  )
}

function Field({
  label,
  hint,
  reset = false,
  children,
}: {
  label:    string
  hint?:    string
  reset?:   boolean
  children: React.ReactNode
}) {
  return (
    <div>
      <div className="flex items-center gap-1.5 mb-1">
        <label className="text-xs font-medium text-muted-foreground">{label}</label>
        {reset && (
          <span
            className="text-amber-600"
            title="Changing this restarts the 90-day warm-up"
          >
            <AlertTriangle className="h-3 w-3" />
          </span>
        )}
      </div>
      {children}
      {hint && <p className="text-[11px] text-muted-foreground mt-1">{hint}</p>}
    </div>
  )
}
