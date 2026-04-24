import { useEffect, useState } from 'react'
import { AiCharacterCard, AiCharacter } from './AiCharacterCard'
import type { ChannelEntry } from './ChannelSidebar'
import { ChannelSetupForm } from './ChannelSetupForm'
import { ChannelOverview } from './ChannelOverview'
import { api } from '@/lib/api'

interface Props {
  channel:    ChannelEntry
  characters: Record<string, AiCharacter>
  /** Fired when the user picks an agent to hire for an empty channel. */
  onPickAgent: (agentKey: string) => void
  /** Fired after any state change — parent gets the fresh channel list from Laravel. */
  onUpdated:   (updated: ChannelEntry[]) => void
  /** Fired when the user clicks "Swap agent" on a hired channel. */
  onRequestSwap: () => void
  /** When true, render the agent picker even if the channel already has an agent. */
  forcePicker?: boolean
}

type ViewMode = 'overview' | 'setup'

/**
 * Right-hand detail pane for the campaigns screen.
 *
 * Three logical modes, driven by a combination of props and local state:
 *  - Force-picker: agent grid for this channel (swap flow or empty channel)
 *  - Overview (default for hired channels): agent banner, stats, ledger feed
 *  - Setup: the full config form — entered via "Edit setup", exits on save
 */
export function ChannelDetail({ channel, characters, onPickAgent, onUpdated, onRequestSwap, forcePicker }: Props) {
  const [view, setView]           = useState<ViewMode>('overview')
  const [busy, setBusy]           = useState<null | 'resume' | 'unhire'>(null)

  // Reset to overview whenever the selected channel changes.
  useEffect(() => { setView('overview') }, [channel.key, channel.agent?.key])

  const handleResume = async () => {
    setBusy('resume')
    try {
      const res = await api.post<{ channels: ChannelEntry[] }>(`/campaigns/channels/${channel.key}/resume`)
      onUpdated(res.channels)
    } catch {
      // Surface via toast later; keep quiet for now
    } finally {
      setBusy(null)
    }
  }

  const handleUnhire = async () => {
    if (!window.confirm(`Unhire ${channel.agent?.label ?? 'the agent'} from ${channel.label}? All setup on this channel will be deleted.`)) return
    setBusy('unhire')
    try {
      const res = await api.delete<{ channels: ChannelEntry[] }>(`/campaigns/channels/${channel.key}`)
      onUpdated(res.channels)
    } catch {
      setBusy(null)
    }
  }

  // Force-picker overrides everything — used for the Swap agent flow.
  if (channel.agent && forcePicker) {
    return (
      <EmptyView
        channel={channel}
        characters={characters}
        onPickAgent={onPickAgent}
        headerOverride={`Swap agent for ${channel.label}`}
      />
    )
  }

  // Empty channel — show the hire grid.
  if (!channel.agent) {
    return (
      <EmptyView
        channel={channel}
        characters={characters}
        onPickAgent={onPickAgent}
      />
    )
  }

  // Hired channel — overview by default, setup form on edit.
  if (view === 'setup') {
    return (
      <div className="flex-1 min-w-0">
        <ChannelSetupForm
          channel={channel}
          onUpdated={(updated) => { onUpdated(updated); setView('overview') }}
          onRequestSwap={onRequestSwap}
          onCancel={() => setView('overview')}
        />
      </div>
    )
  }

  return (
    <ChannelOverview
      channel={channel}
      onEdit={() => setView('setup')}
      onResume={handleResume}
      onUnhire={handleUnhire}
      onSwap={onRequestSwap}
      busy={busy}
    />
  )
}

function EmptyView({
  channel,
  characters,
  onPickAgent,
  headerOverride,
}: {
  channel:        ChannelEntry
  characters:     Record<string, AiCharacter>
  onPickAgent:    (agentKey: string) => void
  headerOverride?: string
}) {
  return (
    <div className="flex-1 min-w-0 space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-[#390d58]">
          {headerOverride ?? `Hire an agent for ${channel.label}`}
        </h3>
        <p className="text-sm text-muted-foreground mt-1">
          {headerOverride
            ? 'Picking a different agent will restart the 90-day warm-up for this channel.'
            : 'Pick a marketing agent below. You can change this later.'}
        </p>
      </div>

      <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 items-stretch">
        {Object.entries(characters).map(([key, character]) => (
          <AiCharacterCard
            key={key}
            id={key}
            character={character}
            selected={false}
            onSelect={onPickAgent}
            saving={false}
          />
        ))}
      </div>
    </div>
  )
}
