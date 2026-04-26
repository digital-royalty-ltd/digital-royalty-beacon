import { useEffect, useState } from 'react'
import { X, Eye, Wand2, Activity, ScanEye, Workflow, FileEdit, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'

interface Signal {
  slug: string
  provider: string
  label: string
  description: string
  auth_strategy: string
  cost_credits: number
  cache_ttl_seconds: number
}

interface Action {
  slug: string
  description: string
  transport: string
  permission_scope: string
  default_approval_threshold: number | null
}

interface SynthesisTool {
  slug: string
  label: string
  description: string
}

interface Watcher {
  slug: string
  description: string
}

interface Automation {
  key: string
  label: string
  description: string
  categories: string[]
  modes: string[]
}

interface WpAction {
  slug: string
  description: string
}

interface CapabilitiesPayload {
  channel: string
  label: string
  signals: Signal[]
  actions: Action[]
  synthesis_tools: SynthesisTool[]
  watchers: Watcher[]
  automations: Automation[]
  wp_actions: WpAction[]
  upstream_unavailable?: boolean
  upstream_message?: string
}

interface Props {
  channel: string
  agentLabel: string
  onClose: () => void
}

/**
 * Read-only modal showing every capability available to the agent on this
 * channel. Per the system docs, signals/actions/synthesis are agent-facing
 * primitives — this modal exposes the inventory for transparency without
 * inviting the operator to run any of it themselves.
 */
export function CapabilitiesModal({ channel, agentLabel, onClose }: Props) {
  const [payload, setPayload] = useState<CapabilitiesPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    setError(null)
    api
      .get<CapabilitiesPayload>(`/marketing/channels/${channel}/capabilities`)
      .then(d => setPayload(d))
      .catch(e => setError(e?.message ?? 'Could not load capabilities.'))
      .finally(() => setLoading(false))
  }, [channel])

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div
        className="w-full max-w-3xl max-h-[85vh] bg-white rounded-2xl shadow-xl overflow-hidden flex flex-col"
        onClick={e => e.stopPropagation()}
      >
        <div className="px-5 py-4 border-b flex items-center justify-between shrink-0">
          <div>
            <h3 className="text-sm font-semibold">{agentLabel} — Capabilities</h3>
            <p className="text-xs text-muted-foreground mt-0.5">
              Everything the agent can do on the {payload?.label ?? channel} channel.
            </p>
          </div>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground" aria-label="Close">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="overflow-y-auto px-5 py-4 space-y-6 flex-1 text-sm">
          {loading && (
            <p className="text-muted-foreground text-xs">Loading capabilities…</p>
          )}

          {error && !loading && (
            <p className="text-red-700 text-xs">{error}</p>
          )}

          {payload?.upstream_unavailable && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 flex items-start gap-2">
              <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
              <span>
                Beacon API was unreachable when loading this view, so signals, actions, synthesis, and watchers
                are missing. Plugin-side automations and WP actions are shown.
              </span>
            </div>
          )}

          {payload && !loading && (
            <>
              <Section
                icon={<Eye className="h-3.5 w-3.5" />}
                title="Signals"
                blurb="Read-only data fetches the agent uses to understand what's happening. Cached, credit-gated."
                empty="No signals registered for this channel."
              >
                {payload.signals.map(s => (
                  <Row
                    key={s.slug}
                    title={s.label}
                    code={s.slug}
                    description={s.description}
                    badges={[
                      s.cost_credits > 0 ? `${s.cost_credits} credits` : 'free',
                      s.auth_strategy === 'oauth_per_project' ? 'OAuth required' : 'central auth',
                      humaniseSeconds(s.cache_ttl_seconds),
                    ]}
                  />
                ))}
              </Section>

              <Section
                icon={<Wand2 className="h-3.5 w-3.5" />}
                title="Actions"
                blurb="Atomic writes the agent can dispatch. Approval gates apply for above-threshold spend in non-autopilot mode."
                empty="No actions registered for this channel."
              >
                {payload.actions.map(a => (
                  <Row
                    key={a.slug}
                    title={titleFromSlug(a.slug)}
                    code={a.slug}
                    description={a.description}
                    badges={[
                      a.transport,
                      a.default_approval_threshold !== null
                        ? `approval > ${a.default_approval_threshold.toLocaleString()}`
                        : 'no threshold',
                    ]}
                  />
                ))}
              </Section>

              <Section
                icon={<ScanEye className="h-3.5 w-3.5" />}
                title="Synthesis tools"
                blurb="Multi-step AI workflows the agent runs to reason over signal data and produce structured briefs."
                empty="No synthesis tools available for this channel."
              >
                {payload.synthesis_tools.map(t => (
                  <Row
                    key={t.slug}
                    title={t.label}
                    code={t.slug}
                    description={t.description}
                  />
                ))}
              </Section>

              <Section
                icon={<Activity className="h-3.5 w-3.5" />}
                title="Watchers"
                blurb="Background monitors that fire alerts when key metrics drift or break thresholds."
                empty="No watchers registered for this channel."
              >
                {payload.watchers.map(w => (
                  <Row
                    key={w.slug}
                    title={titleFromSlug(w.slug)}
                    code={w.slug}
                    description={w.description}
                  />
                ))}
              </Section>

              <Section
                icon={<Workflow className="h-3.5 w-3.5" />}
                title="Automations"
                blurb="Named workflows from this site's automation catalogue that the agent can queue."
                empty="This site hasn't published any automations yet."
              >
                {payload.automations.map(a => (
                  <Row
                    key={a.key}
                    title={a.label}
                    code={a.key}
                    description={a.description}
                    badges={a.categories}
                  />
                ))}
              </Section>

              {payload.wp_actions.length > 0 && (
                <Section
                  icon={<FileEdit className="h-3.5 w-3.5" />}
                  title="WordPress actions"
                  blurb="Direct CMS mutations the agent can dispatch through the plugin when no automation fits."
                  empty=""
                >
                  {payload.wp_actions.map(a => (
                    <Row
                      key={a.slug}
                      title={titleFromSlug(a.slug)}
                      code={a.slug}
                      description={a.description}
                    />
                  ))}
                </Section>
              )}
            </>
          )}
        </div>

        <div className="px-5 py-3 border-t bg-muted/20 text-xs text-muted-foreground shrink-0">
          The agent decides when and how to use these. Operators don't trigger them directly — review what was used
          via the channel activity timeline.
        </div>
      </div>
    </div>
  )
}

interface SectionProps {
  icon: React.ReactNode
  title: string
  blurb: string
  empty: string
  children: React.ReactNode
}

function Section({ icon, title, blurb, empty, children }: SectionProps) {
  const hasChildren = Array.isArray(children) ? children.length > 0 : !!children
  return (
    <section>
      <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1">
        {icon}
        <span>{title}</span>
      </div>
      <p className="text-xs text-muted-foreground mb-2">{blurb}</p>
      {hasChildren ? (
        <div className="space-y-1.5">{children}</div>
      ) : (
        empty && <p className="text-xs text-muted-foreground italic">{empty}</p>
      )}
    </section>
  )
}

interface RowProps {
  title: string
  code: string
  description: string
  badges?: string[]
}

function Row({ title, code, description, badges }: RowProps) {
  return (
    <div className="rounded-lg border border-border bg-card px-3 py-2">
      <div className="flex items-baseline justify-between gap-3 mb-0.5">
        <p className="text-sm font-medium">{title}</p>
        <code className="text-[10px] text-muted-foreground font-mono shrink-0">{code}</code>
      </div>
      {description && (
        <p className="text-xs text-muted-foreground leading-relaxed">{description}</p>
      )}
      {badges && badges.length > 0 && (
        <div className="flex flex-wrap gap-1 mt-1.5">
          {badges.map((b, i) => (
            <span
              key={i}
              className="px-1.5 py-0.5 rounded bg-muted text-[10px] text-muted-foreground"
            >
              {b}
            </span>
          ))}
        </div>
      )}
    </div>
  )
}

function titleFromSlug(slug: string): string {
  return slug
    .split(/[._]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function humaniseSeconds(secs: number): string {
  if (secs <= 0) return 'no cache'
  if (secs < 3600) return `${Math.round(secs / 60)}m cache`
  if (secs < 86400) return `${Math.round(secs / 3600)}h cache`
  return `${Math.round(secs / 86400)}d cache`
}
