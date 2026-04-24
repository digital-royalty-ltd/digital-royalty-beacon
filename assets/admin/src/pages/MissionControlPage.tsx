import { useEffect, useState } from 'react'
import { Target, Loader2 } from 'lucide-react'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'
import { PremiumGate } from '@/components/beacon/PremiumGate'
import { AiCharacterCard, AiCharacter } from '@/components/beacon/campaigns/AiCharacterCard'
import { HireDialog, ChannelOption } from '@/components/beacon/campaigns/HireDialog'
import { ChannelSidebar, ChannelEntry } from '@/components/beacon/campaigns/ChannelSidebar'
import { ChannelDetail } from '@/components/beacon/campaigns/ChannelDetail'
import { api } from '@/lib/api'

interface AiResponse {
  selected:   string | null
  characters: Record<string, AiCharacter>
}

interface ChannelsResponse {
  channels: ChannelEntry[]
}

/**
 * Two UI states:
 *  1. No channel has an agent yet → show the agent grid. Picking an agent
 *     opens the HireDialog with all four channels available.
 *  2. At least one channel has an agent → two-pane layout. Left is the
 *     always-four-channel sidebar; right is either the channel's setup view
 *     (for hired channels) or the agent picker (for empty channels).
 */
export function CampaignsPage() {
  const hasApiKey = window.BeaconData?.hasApiKey ?? false

  const [aiData,       setAiData]       = useState<AiResponse | null>(null)
  const [channels,     setChannels]     = useState<ChannelEntry[]>([])
  const [selectedKey,  setSelectedKey]  = useState<string | null>(null)
  const [loading,      setLoading]      = useState(true)
  const [swapping,     setSwapping]     = useState(false)
  const [hireContext,  setHireContext]  = useState<{ agentKey: string; preselect: string[]; locked: boolean } | null>(null)

  const loadChannels = async () => {
    try {
      const res = await api.get<ChannelsResponse>('/campaigns/channels')
      setChannels(res.channels ?? [])
      return res.channels ?? []
    } catch {
      setChannels([])
      return []
    }
  }

  useEffect(() => {
    if (!hasApiKey) { setLoading(false); return }

    Promise.all([
      api.get<AiResponse>('/campaigns/ai').catch(() => null),
      loadChannels(),
    ])
      .then(([ai, ch]) => {
        setAiData(ai)
        // Default selection: first channel with an agent, else first channel overall.
        const firstHired = ch.find(c => c.agent)
        setSelectedKey((firstHired ?? ch[0])?.key ?? null)
      })
      .finally(() => setLoading(false))
  }, [hasApiKey])

  if (!hasApiKey) {
    return (
      <PremiumGate
        feature="Campaigns"
        description="Digital Royalty's campaign strategies — built on years of agency expertise — executed automatically by AI on your site. Requires a Beacon API key."
        icon={<Target className="h-10 w-10" />}
        gradient="from-[#2d0a47] to-[#390d58]"
      />
    )
  }

  const hiredChannels: ChannelOption[] = channels.map(c => ({ key: c.key, label: c.label }))
  const hasAnyHire = channels.some(c => c.agent)

  // Empty-state agent pick → open dialog with no preselection (user decides channels).
  const handlePickAgentFromGrid = (agentKey: string) => {
    setHireContext({ agentKey, preselect: [], locked: false })
  }

  // Channel-empty pick → open dialog with that single channel locked in.
  const handlePickAgentForChannel = (agentKey: string) => {
    if (!selectedKey) return
    setHireContext({ agentKey, preselect: [selectedKey], locked: true })
  }

  const handleHireComplete = async () => {
    setHireContext(null)
    setSwapping(false)
    const updated = await loadChannels()
    // If this was the first hire, move selection to the first hired channel.
    const firstHired = updated.find(c => c.agent)
    if (firstHired && !hasAnyHire) {
      setSelectedKey(firstHired.key)
    }
  }

  const selectedChannel = channels.find(c => c.key === selectedKey) ?? null

  return (
    <>
      <OnboardingOverlay screen="campaigns" />

      {hireContext && aiData?.characters[hireContext.agentKey] && (
        <HireDialog
          agentKey={hireContext.agentKey}
          agent={aiData.characters[hireContext.agentKey]}
          channels={hiredChannels}
          preselectedChannels={hireContext.preselect}
          lockPreselected={hireContext.locked}
          onComplete={handleHireComplete}
          onDismiss={() => setHireContext(null)}
        />
      )}

      <div className="space-y-8">
        {/* Header */}
        <div>
          <h2 className="text-xl font-semibold tracking-tight text-[#390d58]">Campaigns</h2>
          <p className="text-sm text-muted-foreground mt-1">
            {hasAnyHire
              ? 'Long-running campaigns executed by your marketing agents'
              : "Hire your first marketing agent — each is a proven Digital Royalty methodology, executed by AI"}
          </p>
        </div>

        {loading ? (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="h-6 w-6 animate-spin text-[#390d58]" />
          </div>
        ) : !aiData ? (
          <p className="text-sm text-muted-foreground text-center py-16">Could not load AI agents.</p>
        ) : !hasAnyHire ? (
          // First-time experience — big agent grid, picking opens HireDialog.
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 items-stretch">
            {Object.entries(aiData.characters).map(([key, character]) => (
              <AiCharacterCard
                key={key}
                id={key}
                character={character}
                selected={false}
                onSelect={handlePickAgentFromGrid}
                saving={false}
              />
            ))}
          </div>
        ) : (
          // Hired-state two-pane layout.
          <div className="flex flex-col lg:flex-row gap-6">
            <ChannelSidebar
              channels={channels}
              selectedKey={selectedKey}
              onSelect={key => { setSelectedKey(key); setSwapping(false); }}
            />
            {selectedChannel
              ? (
                <ChannelDetail
                  channel={selectedChannel}
                  characters={aiData.characters}
                  onPickAgent={handlePickAgentForChannel}
                  onUpdated={updated => { setChannels(updated); setSwapping(false); }}
                  onRequestSwap={() => setSwapping(true)}
                  forcePicker={swapping}
                />
              )
              : (
                <div className="flex-1 rounded-xl border-2 border-dashed border-muted p-10 text-center">
                  <p className="text-sm font-medium">Select a channel</p>
                </div>
              )
            }
          </div>
        )}
      </div>
    </>
  )
}
