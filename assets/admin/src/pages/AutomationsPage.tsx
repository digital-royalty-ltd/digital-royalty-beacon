import { useEffect, useState } from 'react'
import { Loader2, Sparkles, RefreshCw, Zap } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { AutomationCard, AutomationListItem, AutomationCategory } from '@/components/beacon/automations/AutomationCard'
import { GapAnalysisResults } from '@/components/beacon/automations/GapAnalysisResults'
import { ContentGeneratorTool } from '@/components/beacon/ContentGeneratorTool'
import { ContentFromSampleTool } from '@/components/beacon/ContentFromSampleTool'
import { GenerateImageTool } from '@/components/beacon/GenerateImageTool'
import { ContentEnrichmentTool } from '@/components/beacon/ContentEnrichmentTool'
import { NewsArticleGeneratorTool } from '@/components/beacon/NewsArticleGeneratorTool'
import { SocialShareTool } from '@/components/beacon/SocialShareTool'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'
import { PremiumGate } from '@/components/beacon/PremiumGate'

type View =
  | { type: 'list' }
  | { type: 'tool'; key: string }
  | { type: 'results'; key: string; data: unknown }

export function AutomationsPage() {
  const hasApiKey = window.BeaconData?.hasApiKey ?? false

  if (!hasApiKey) {
    return (
      <PremiumGate
        feature="Automations"
        description="The same content analysis, gap identification, and draft generation Digital Royalty runs for agency clients — automated on your site. Requires a Beacon API key."
        icon={<Zap className="h-10 w-10" />}
        gradient="from-[#390d58] to-violet-600"
      />
    )
  }

  const [view, setView] = useState<View>({ type: 'list' })
  const [automations, setAutomations] = useState<AutomationListItem[]>([])
  const [loading, setLoading] = useState(true)
  const [running, setRunning] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [activeFilters, setActiveFilters] = useState<AutomationCategory[]>([])

  const fetchAutomations = () => {
    setLoading(true)
    setError(null)
    api.get<AutomationListItem[]>('/automations')
      .then(setAutomations)
      .catch(() => setError('Could not load automations.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchAutomations()
  }, [])

  // --- Content Generator tool view ---
  if (view.type === 'tool' && view.key === 'content_generator') {
    return <ContentGeneratorTool onBack={() => setView({ type: 'list' })} />
  }

  // --- Content From Sample tool view ---
  if (view.type === 'tool' && view.key === 'content_from_sample') {
    return <ContentFromSampleTool onBack={() => setView({ type: 'list' })} />
  }

  // --- Generate Image tool view ---
  if (view.type === 'tool' && view.key === 'generate_image') {
    return <GenerateImageTool onBack={() => setView({ type: 'list' })} />
  }

  // --- Content Image Enrichment tool view ---
  if (view.type === 'tool' && view.key === 'content_image_enrichment') {
    return <ContentEnrichmentTool onBack={() => setView({ type: 'list' })} />
  }

  // --- News Article Generator tool view ---
  if (view.type === 'tool' && view.key === 'news_article_generator') {
    return <NewsArticleGeneratorTool onBack={() => setView({ type: 'list' })} />
  }

  // --- Social Share tool view ---
  if (view.type === 'tool' && view.key === 'social_share') {
    return <SocialShareTool onBack={() => setView({ type: 'list' })} />
  }

  // --- Gap Analysis results view ---
  if (view.type === 'results' && view.key === 'gap_analysis') {
    return (
      <GapAnalysisResults
        results={view.data as Parameters<typeof GapAnalysisResults>[0]['results']}
        onBack={() => setView({ type: 'list' })}
      />
    )
  }

  // --- Main list view ---
  const handleOpenTool = (key: string) => {
    setView({ type: 'tool', key })
  }

  const handleRun = async (key: string) => {
    setRunning(key)
    setError(null)
    try {
      await api.post(`/automations/${key}/run`, {})
      // Refresh list to reflect new status after a brief moment
      setTimeout(fetchAutomations, 1500)
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to start automation.')
    } finally {
      setRunning(null)
    }
  }

  const handleViewResults = async (key: string) => {
    try {
      const data = await api.get(`/automations/${key}/result`)
      setView({ type: 'results', key, data })
    } catch {
      setError('Could not load results.')
    }
  }

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-muted-foreground text-sm py-8">
        <Loader2 className="h-4 w-4 animate-spin" /> Loading automations…
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <OnboardingOverlay screen="automations" />
      <div className="flex items-start justify-between">
        <div>
          <h2 className="text-xl font-semibold tracking-tight text-[#390d58]">Automations</h2>
          <p className="text-sm text-muted-foreground mt-1">
            Proven agency workflows, running automatically on your site
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="outline"
            onClick={fetchAutomations}
            disabled={loading}
            className="gap-1"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
          </Button>
          <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#390d58]/10 text-[#390d58]">
            <Sparkles className="h-4 w-4" />
            <span className="text-sm font-medium">{automations.length} available</span>
          </div>
        </div>
      </div>

      {/* Category filters */}
      {(() => {
        const allCategories = Array.from(new Set(automations.flatMap(a => a.categories))) as AutomationCategory[]
        if (allCategories.length <= 1) return null

        const CATEGORY_STYLES: Record<AutomationCategory, { active: string; inactive: string }> = {
          content: { active: 'bg-violet-600 text-white border-violet-600', inactive: 'border-violet-200 text-violet-700 hover:bg-violet-50' },
          seo:     { active: 'bg-emerald-600 text-white border-emerald-600', inactive: 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' },
          ppc:     { active: 'bg-orange-500 text-white border-orange-500', inactive: 'border-orange-200 text-orange-700 hover:bg-orange-50' },
          social:  { active: 'bg-sky-600 text-white border-sky-600', inactive: 'border-sky-200 text-sky-700 hover:bg-sky-50' },
        }
        const CATEGORY_LABELS: Record<AutomationCategory, string> = { content: 'Content', seo: 'SEO', ppc: 'PPC', social: 'Social' }

        const toggleFilter = (cat: AutomationCategory) => {
          setActiveFilters(prev =>
            prev.includes(cat) ? prev.filter(c => c !== cat) : [...prev, cat]
          )
        }

        return (
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-medium text-muted-foreground mr-1">Filter:</span>
            {allCategories.map(cat => {
              const isActive = activeFilters.includes(cat)
              const styles = CATEGORY_STYLES[cat]
              return (
                <button
                  key={cat}
                  onClick={() => toggleFilter(cat)}
                  className={`rounded-full border px-3 py-1 text-xs font-medium transition-all ${isActive ? styles.active : styles.inactive}`}
                >
                  {CATEGORY_LABELS[cat]}
                </button>
              )
            })}
            {activeFilters.length > 0 && (
              <button
                onClick={() => setActiveFilters([])}
                className="text-xs text-muted-foreground hover:text-[#390d58] underline underline-offset-2 ml-1"
              >
                Clear
              </button>
            )}
          </div>
        )
      })()}

      {error && (
        <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {error}
        </p>
      )}

      <div className="grid gap-5 md:grid-cols-2">
        {automations.filter(a =>
          activeFilters.length === 0 || activeFilters.some(f => a.categories.includes(f))
        ).map(automation => (
          <AutomationCard
            key={automation.key}
            automation={automation}
            running={running === automation.key}
            onOpenTool={() => handleOpenTool(automation.key)}
            onRun={() => handleRun(automation.key)}
            onViewResults={() => handleViewResults(automation.key)}
          />
        ))}
      </div>
    </div>
  )
}
