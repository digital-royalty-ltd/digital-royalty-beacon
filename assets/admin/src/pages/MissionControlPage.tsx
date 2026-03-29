import { useEffect, useState } from 'react'
import { Target, Loader2, RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'
import { PremiumGate } from '@/components/beacon/PremiumGate'
import { AiCharacterCard, AiCharacter } from '@/components/beacon/campaigns/AiCharacterCard'
import { CampaignOnboarding } from '@/components/beacon/campaigns/CampaignOnboarding'
import { api } from '@/lib/api'

interface AiResponse {
  selected:   string | null
  characters: Record<string, AiCharacter>
}

export function CampaignsPage() {
  const hasApiKey = window.BeaconData?.hasApiKey ?? false

  const [data,          setData]          = useState<AiResponse | null>(null)
  const [loading,       setLoading]       = useState(true)
  const [deselecting,   setDeselecting]   = useState(false)
  const [onboardingFor, setOnboardingFor] = useState<string | null>(null)

  useEffect(() => {
    if (!hasApiKey) { setLoading(false); return }

    api.get<AiResponse>('/campaigns/ai')
      .then(setData)
      .catch(() => null)
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

  /** Open the onboarding wizard for the given character key. */
  const handleOnboard = (key: string) => {
    setOnboardingFor(key)
  }

  /** Called when the user completes onboarding — refresh AI state from the API. */
  const handleOnboardComplete = () => {
    setOnboardingFor(null)
    api.get<AiResponse>('/campaigns/ai').then(setData).catch(() => null)
  }

  /** Called when the user dismisses onboarding without completing. */
  const handleOnboardDismiss = () => {
    setOnboardingFor(null)
  }

  /** Clear the current AI selection. */
  const handleDeselect = async () => {
    if (!data || deselecting) return
    const prev = data.selected
    setData(d => d ? { ...d, selected: null } : d)
    setDeselecting(true)
    try {
      await api.post('/campaigns/ai', { key: '' })
    } catch {
      setData(d => d ? { ...d, selected: prev } : d)
    } finally {
      setDeselecting(false)
    }
  }

  const selectedChar = data?.selected ? data.characters[data.selected] : null

  return (
    <>
      <OnboardingOverlay screen="campaigns" />

      {/* Onboarding wizard — rendered outside the page flow as a fixed overlay */}
      {onboardingFor && data?.characters[onboardingFor] && (
        <CampaignOnboarding
          character={data.characters[onboardingFor]}
          characterKey={onboardingFor}
          onComplete={handleOnboardComplete}
          onDismiss={handleOnboardDismiss}
        />
      )}

      <div className="space-y-8">

        {/* Header */}
        <div className="flex items-start justify-between">
          <div>
            <h2 className="text-xl font-semibold tracking-tight text-[#390d58]">Campaigns</h2>
            <p className="text-sm text-muted-foreground mt-1">
              Choose a campaign strategy — each is a proven Digital Royalty methodology, executed by AI
            </p>
          </div>
          {deselecting && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" /> Saving…
            </div>
          )}
        </div>

        {/* Active AI banner — shown when one is selected */}
        {selectedChar && data?.selected && (
          <div
            className="rounded-2xl p-5 text-white flex items-center gap-5"
            style={{ background: `linear-gradient(135deg, ${selectedChar.color}dd, ${selectedChar.color}99)` }}
          >
            <span className="text-5xl leading-none">{selectedChar.emoji}</span>
            <div className="flex-1">
              <p className="text-xs font-medium text-white/70 mb-0.5">Active AI Manager</p>
              <p className="text-xl font-bold">{selectedChar.label}</p>
              <div className="flex flex-wrap gap-1.5 mt-2">
                {selectedChar.traits.map(t => (
                  <span key={t} className="text-[10px] font-medium bg-white/20 rounded-full px-2 py-0.5">
                    {t}
                  </span>
                ))}
              </div>
            </div>
            <div className="flex flex-col gap-2 shrink-0">
              <Button
                variant="outline"
                size="sm"
                className="bg-white/10 border-white/30 text-white hover:bg-white/20"
                onClick={() => handleOnboard(data.selected!)}
              >
                Re-onboard
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="bg-white/10 border-white/30 text-white hover:bg-white/20"
                onClick={handleDeselect}
                disabled={deselecting}
              >
                <RefreshCw className="h-3.5 w-3.5 mr-1.5" />
                Change
              </Button>
            </div>
          </div>
        )}

        {/* Loading */}
        {loading ? (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="h-6 w-6 animate-spin text-[#390d58]" />
          </div>
        ) : !data ? (
          <p className="text-sm text-muted-foreground text-center py-16">Could not load AI characters.</p>
        ) : (
          <>
            {/* Selection prompt */}
            {!data.selected && (
              <div className="text-center py-2">
                <p className="text-sm text-muted-foreground">
                  Select an AI manager below to get started. You can change this at any time.
                </p>
              </div>
            )}

            {/* Character grid */}
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 items-stretch">
              {Object.entries(data.characters).map(([key, character]) => (
                <AiCharacterCard
                  key={key}
                  id={key}
                  character={character}
                  selected={data.selected === key}
                  onSelect={handleOnboard}
                  saving={false}
                />
              ))}
            </div>

            {/* Future campaigns placeholder */}
            {data.selected && (
              <div className="rounded-xl border-2 border-dashed border-[#390d58]/20 p-8 text-center">
                <p className="text-sm font-medium text-[#390d58] mb-1">Campaign management coming soon</p>
                <p className="text-xs text-muted-foreground">
                  {selectedChar?.label} is standing by. Campaign creation and management will be available in the next phase.
                </p>
              </div>
            )}
          </>
        )}

      </div>
    </>
  )
}
