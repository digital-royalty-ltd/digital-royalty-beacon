import type { AiCharacter } from './AiCharacterCard'

export interface ChannelSetup {
  goal:              string | null
  primary_kpi:       string | null
  kpi_target_value:  number | null
  kpi_target_unit:   string | null
  monthly_work_cap:  number | null
  autonomy:          'autopilot' | 'review' | 'suggestions'
  cadence:           'daily' | 'weekly' | 'on_demand'
  risk_tolerance:    number | null
  guardrails:        string | null
  channel_setup:     Record<string, unknown>
  onboarded_at:      string | null
}

export interface ChannelWarmup {
  active:      boolean
  day:         number | null
  total_days:  number
  started_at:  string | null
  reset_count: number
}

export interface ChannelBilling {
  status:                 'active' | 'monitor_mode' | 'paused_no_credits' | 'awaiting_onboarding' | 'awaiting_dependencies'
  monthly_fee:            number
  monthly_work_cap:       number | null
  monthly_work_spent:     number
  monthly_fee_charged_at: string | null
}

export interface ChannelPacing {
  last_turn_at: string | null
  next_turn_at: string | null
  cadence:      'daily' | 'weekly' | 'on_demand'
}

export interface ChannelDependency {
  provider: string
  label:    string
  status:   'ok' | 'oauth_missing' | 'oauth_expired' | 'oauth_unauthorized' | 'oauth_token_unreadable' | 'api_disabled' | 'api_unreachable' | 'api_misconfigured' | 'entity_unselected'
  reason:   string | null
  hint:     string | null
}

export interface ChannelDependencies {
  met:                       boolean
  required:                  ChannelDependency[]
  optional:                  ChannelDependency[]
  automation_catalogue:      { published: boolean; last_seen: string | null }
  missing_required_summary:  string[]
}

export interface ChannelEntry {
  key:          string
  label:        string
  agent:        (AiCharacter & { key: string }) | null
  setup:        ChannelSetup | null
  warmup:       ChannelWarmup | null
  billing:      ChannelBilling | null
  pacing:       ChannelPacing | null
  dependencies: ChannelDependencies
}

interface Props {
  channels:   ChannelEntry[]
  selectedKey: string | null
  onSelect:   (key: string) => void
}

/**
 * Left-hand channel list for the campaigns screen.
 *
 * Always shows all four channels. Each row shows an agent avatar square
 * when an agent is hired for that channel, or an empty slot otherwise.
 */
export function ChannelSidebar({ channels, selectedKey, onSelect }: Props) {
  return (
    <ul className="w-full lg:w-72 shrink-0 space-y-2">
      {channels.map(ch => {
        const active  = ch.key === selectedKey
        const agent   = ch.agent
        const isEmpty = !agent

        return (
          <li key={ch.key}>
            <button
              onClick={() => onSelect(ch.key)}
              className={`w-full text-left rounded-xl border p-3 flex items-center gap-3 transition-all
                ${active
                  ? 'border-[#390d58] bg-[#390d58] text-white shadow'
                  : 'bg-white hover:bg-muted/40 border-border'}
              `}
            >
              {/* Avatar square */}
              <div
                className="h-11 w-11 rounded-lg flex items-center justify-center shrink-0 overflow-hidden"
                style={{
                  background: agent
                    ? `linear-gradient(135deg, ${agent.color}ee, ${agent.color}99)`
                    : (active ? 'rgba(255,255,255,0.15)' : '#f1f1f4'),
                }}
              >
                {agent?.image_url ? (
                  <img src={agent.image_url} alt={agent.label} className="h-full w-full object-cover" />
                ) : agent ? (
                  <span className="text-2xl">{agent.emoji}</span>
                ) : (
                  <span className={`text-xs font-medium ${active ? 'text-white/60' : 'text-muted-foreground'}`}>+</span>
                )}
              </div>

              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold truncate">{ch.label}</p>
                <p className={`text-[11px] truncate ${active ? 'text-white/70' : 'text-muted-foreground'}`}>
                  {isEmpty ? 'No agent yet' : agent.label}
                </p>
              </div>
            </button>
          </li>
        )
      })}
    </ul>
  )
}
