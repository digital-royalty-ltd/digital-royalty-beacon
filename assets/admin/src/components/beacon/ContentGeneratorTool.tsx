import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Loader2, Sparkles, ArrowLeft, CheckCircle2, FileText, LayoutGrid } from 'lucide-react'
import { api } from '@/lib/api'

interface Term {
  id:   number
  name: string
}

interface Taxonomy {
  slug:         string
  label:        string
  hierarchical: boolean
  terms:        Term[]
}

interface ContentArea {
  key:              string
  label:            string
  intent:           string
  topics:           string[]
  post_type?:       string
  post_type_label?: string
  taxonomies?:      Taxonomy[]
}

interface Destination {
  slug:       string
  label:      string
  taxonomies: Taxonomy[]
}

interface ExistingItem {
  title:   string
  excerpt: string
}

type Mode = 'area' | 'create_as'

interface Props {
  onBack: () => void
}

export function ContentGeneratorTool({ onBack }: Props) {
  const [areas,          setAreas]          = useState<ContentArea[]>([])
  const [destinations,   setDestinations]   = useState<Destination[]>([])
  const [loadingAreas,   setLoadingAreas]   = useState(true)
  const [loadingDest,    setLoadingDest]    = useState(true)

  const [mode,           setMode]           = useState<Mode>('area')
  const [selectedArea,   setSelectedArea]   = useState<ContentArea | null>(null)
  const [postType,       setPostType]       = useState('')
  const [selectedTerms,  setSelectedTerms]  = useState<Record<string, number[]>>({})

  const [title,          setTitle]          = useState('')
  const [brief,          setBrief]          = useState('')

  const [generating,     setGenerating]     = useState(false)
  const [queued,         setQueued]         = useState(false)
  const [error,          setError]          = useState<string | null>(null)

  useEffect(() => {
    api.get<ContentArea[]>('/content-generator/content-areas')
      .then(setAreas)
      .catch(() => {})
      .finally(() => setLoadingAreas(false))

    api.get<Destination[]>('/content-generator/destinations')
      .then(setDestinations)
      .catch(() => {})
      .finally(() => setLoadingDest(false))
  }, [])

  // Resolve which taxonomies to show based on mode.
  const activeTaxonomies: Taxonomy[] = (() => {
    if (mode === 'area' && selectedArea?.taxonomies) {
      return selectedArea.taxonomies
    }
    if (mode === 'create_as') {
      const dest = destinations.find(d => d.slug === postType)
      return dest?.taxonomies ?? []
    }
    return []
  })()

  // Resolve the post type for the generate request.
  const resolvedPostType: string = (() => {
    if (mode === 'create_as' && postType) return postType
    if (mode === 'area' && selectedArea?.post_type) return selectedArea.post_type
    return 'post'
  })()

  const resolvedLabel: string = (() => {
    if (mode === 'create_as') {
      return destinations.find(d => d.slug === postType)?.label ?? 'Posts'
    }
    return selectedArea?.post_type_label ?? selectedArea?.label ?? 'Posts'
  })()

  const hasDestination = mode === 'area' ? !!selectedArea : !!postType

  const handleModeSwitch = (m: Mode) => {
    setMode(m)
    setSelectedArea(null)
    setPostType('')
    setSelectedTerms({})
    setError(null)
  }

  const handleAreaSelect = (area: ContentArea) => {
    setSelectedArea(area)
    setSelectedTerms({})
    setError(null)
  }

  const handlePostTypeChange = (slug: string) => {
    setPostType(slug)
    setSelectedTerms({})
  }

  const handleTermToggle = (taxSlug: string, termId: number) => {
    setSelectedTerms(prev => {
      const current = prev[taxSlug] ?? []
      const updated = current.includes(termId)
        ? current.filter(id => id !== termId)
        : [...current, termId]
      return { ...prev, [taxSlug]: updated }
    })
  }

  const handleGenerate = async () => {
    if (!hasDestination) return
    setGenerating(true)
    setError(null)
    try {
      // Build taxonomies payload.
      const taxonomies: Record<string, number[]> = {}
      for (const [taxSlug, termIds] of Object.entries(selectedTerms)) {
        if (termIds.length > 0) {
          taxonomies[taxSlug] = termIds
        }
      }

      // If no brief provided, fetch existing content for AI context.
      let existingContent: ExistingItem[] | undefined
      if (!title.trim() && !brief.trim()) {
        try {
          existingContent = await api.get<ExistingItem[]>(
            `/content-generator/existing-content?post_type=${resolvedPostType}`
          )
          if (existingContent.length === 0) existingContent = undefined
        } catch {
          // Non-critical — generate without context.
        }
      }

      await api.post('/content-generator/generate', {
        content_area_key: selectedArea?.key || undefined,
        topic: title.trim() || undefined,
        brief: brief.trim() || undefined,
        post_type: mode === 'create_as' ? postType : undefined,
        taxonomies: Object.keys(taxonomies).length > 0 ? taxonomies : undefined,
        existing_content: existingContent,
      })
      setQueued(true)
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Generation request failed.'
      setError(msg)
    } finally {
      setGenerating(false)
    }
  }

  const handleReset = () => {
    setQueued(false)
    setTitle('')
    setBrief('')
    setSelectedArea(null)
    setPostType('')
    setSelectedTerms({})
    setError(null)
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={onBack}
          className="p-1.5 rounded-lg text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-xl font-semibold text-[#390d58]">Content Generator</h2>
          <p className="text-sm text-muted-foreground">Generate AI-drafted content for your site.</p>
        </div>
      </div>

      {queued ? (
        <Card className="border-[#390d58]/20 overflow-hidden">
          <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
          <CardContent className="pt-8 pb-10 flex flex-col items-center text-center gap-4">
            <div className="rounded-2xl bg-green-50 p-4">
              <CheckCircle2 className="h-10 w-10 text-green-600" />
            </div>
            <div>
              <p className="text-lg font-semibold text-[#390d58]">Draft queued</p>
              <p className="text-sm text-muted-foreground mt-1 max-w-sm">
                Your draft is being generated in the background and will appear in
                <strong className="text-[#390d58]"> {resolvedLabel} &rarr; Drafts</strong> when ready.
              </p>
            </div>
            <div className="flex gap-3 mt-2">
              <Button onClick={handleReset} variant="outline" className="border-[#390d58]/20 text-[#390d58]">
                Generate another
              </Button>
              <Button
                onClick={() => {
                  const pt = resolvedPostType
                  const editUrl = pt && pt !== 'post'
                    ? `edit.php?post_type=${pt}&post_status=draft`
                    : 'edit.php?post_status=draft'
                  window.open(
                    `${window.BeaconData?.adminUrl ?? '/wp-admin/'}${editUrl}`,
                    '_blank',
                    'noopener,noreferrer'
                  )
                }}
                className="bg-[#390d58] hover:bg-[#4a1170] text-white"
              >
                View drafts
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="border-[#390d58]/20 overflow-hidden">
          <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
          <CardHeader>
            <CardTitle className="text-lg text-[#390d58]">New draft</CardTitle>
            <CardDescription>
              Choose a content area to generate into, or pick a specific destination type.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">

            {error && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
            )}

            {/* Mode toggle */}
            <div className="flex gap-2">
              <button
                onClick={() => handleModeSwitch('area')}
                className={`flex items-center gap-2 rounded-lg border px-4 py-2 text-sm transition-all ${
                  mode === 'area'
                    ? 'border-[#390d58] bg-[#390d58]/5 text-[#390d58] font-medium ring-1 ring-[#390d58]/20'
                    : 'border-[#390d58]/15 text-muted-foreground hover:border-[#390d58]/30'
                }`}
              >
                <LayoutGrid className="h-4 w-4" />
                Content area
              </button>
              <button
                onClick={() => handleModeSwitch('create_as')}
                className={`flex items-center gap-2 rounded-lg border px-4 py-2 text-sm transition-all ${
                  mode === 'create_as'
                    ? 'border-[#390d58] bg-[#390d58]/5 text-[#390d58] font-medium ring-1 ring-[#390d58]/20'
                    : 'border-[#390d58]/15 text-muted-foreground hover:border-[#390d58]/30'
                }`}
              >
                <FileText className="h-4 w-4" />
                Create as
              </button>
            </div>

            {/* Content area selection */}
            {mode === 'area' && (
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">Content area</label>
                {loadingAreas ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                    <Loader2 className="h-4 w-4 animate-spin" /> Loading…
                  </div>
                ) : areas.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    No content areas found.{' '}
                    <a href="#" onClick={(e) => { e.preventDefault(); onBack() }}
                      className="text-[#390d58] underline underline-offset-2">Run site reports first</a>.
                  </p>
                ) : (
                  <div className="grid gap-2 sm:grid-cols-2">
                    {areas.map(area => (
                      <button
                        key={area.key}
                        onClick={() => handleAreaSelect(area)}
                        className={`text-left rounded-xl border px-4 py-3 transition-all ${
                          selectedArea?.key === area.key
                            ? 'border-[#390d58] bg-[#390d58]/5 ring-1 ring-[#390d58]/20'
                            : 'border-[#390d58]/15 hover:border-[#390d58]/30 hover:bg-[#390d58]/[0.02]'
                        }`}
                      >
                        <div className="flex items-center justify-between gap-2">
                          <p className="text-sm font-medium text-[#390d58]">{area.label}</p>
                          {area.post_type_label && (
                            <Badge variant="outline" className="text-[9px] px-1.5 py-0 border-[#390d58]/20 text-[#390d58] shrink-0">
                              {area.post_type_label}
                            </Badge>
                          )}
                        </div>
                        {area.intent && (
                          <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">{area.intent}</p>
                        )}
                        {area.topics.length > 0 && (
                          <div className="flex flex-wrap gap-1 mt-2">
                            {area.topics.slice(0, 3).map(t => (
                              <Badge key={t} variant="outline"
                                className="text-[9px] px-1.5 py-0 border-[#390d58]/20 text-[#390d58]">
                                {t}
                              </Badge>
                            ))}
                            {area.topics.length > 3 && (
                              <span className="text-[9px] text-muted-foreground self-center">
                                +{area.topics.length - 3}
                              </span>
                            )}
                          </div>
                        )}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Create as — post type selection */}
            {mode === 'create_as' && !loadingDest && (
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">Create as</label>
                <div className="flex flex-wrap gap-2">
                  {destinations.map(dest => (
                    <button
                      key={dest.slug}
                      onClick={() => handlePostTypeChange(dest.slug)}
                      className={`rounded-lg border px-3 py-1.5 text-sm transition-all ${
                        postType === dest.slug
                          ? 'border-[#390d58] bg-[#390d58]/5 text-[#390d58] font-medium ring-1 ring-[#390d58]/20'
                          : 'border-[#390d58]/15 text-muted-foreground hover:border-[#390d58]/30 hover:bg-[#390d58]/[0.02]'
                      }`}
                    >
                      {dest.label}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Taxonomies — shown for either mode once a destination is resolved */}
            {hasDestination && activeTaxonomies.length > 0 && (
              <div className="space-y-4">
                {activeTaxonomies.map(tax => (
                  <div key={tax.slug}>
                    <label className="text-sm font-medium text-[#390d58] mb-2 block">
                      {tax.label}
                      <span className="text-xs font-normal text-muted-foreground ml-1">(optional)</span>
                    </label>
                    {tax.terms.length === 0 ? (
                      <p className="text-xs text-muted-foreground">No {tax.label.toLowerCase()} terms found.</p>
                    ) : (
                      <div className="flex flex-wrap gap-1.5">
                        {tax.terms.map(term => {
                          const selected = (selectedTerms[tax.slug] ?? []).includes(term.id)
                          return (
                            <button
                              key={term.id}
                              onClick={() => handleTermToggle(tax.slug, term.id)}
                              className={`rounded-full border px-2.5 py-1 text-xs transition-all ${
                                selected
                                  ? 'border-[#390d58] bg-[#390d58] text-white'
                                  : 'border-[#390d58]/20 text-[#390d58] hover:border-[#390d58]/40 hover:bg-[#390d58]/5'
                              }`}
                            >
                              {term.name}
                            </button>
                          )
                        })}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}

            {/* Optional brief — title + description */}
            {hasDestination && (
              <div className="space-y-3">
                <div>
                  <label className="text-sm font-medium text-[#390d58] mb-2 block">
                    Title
                    <span className="text-xs font-normal text-muted-foreground ml-1">(optional)</span>
                  </label>
                  <input
                    type="text"
                    value={title}
                    onChange={e => setTitle(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && !generating && handleGenerate()}
                    placeholder={selectedArea?.topics[0] ? `e.g. "${selectedArea.topics[0]}"` : 'Working title or topic…'}
                    className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                  />
                </div>
                <div>
                  <label className="text-sm font-medium text-[#390d58] mb-2 block">
                    Brief
                    <span className="text-xs font-normal text-muted-foreground ml-1">(optional)</span>
                  </label>
                  <textarea
                    value={brief}
                    onChange={e => setBrief(e.target.value)}
                    rows={3}
                    placeholder="Describe what the draft should cover, the angle, or any specific points to include…"
                    className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30 resize-y"
                  />
                  {!title.trim() && !brief.trim() && (
                    <p className="text-xs text-muted-foreground mt-1.5">
                      No brief? Beacon will review existing content in this area and generate something new automatically.
                    </p>
                  )}
                </div>
              </div>
            )}

            {hasDestination && (
              <Button
                onClick={handleGenerate}
                disabled={generating}
                className="w-full gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white"
              >
                {generating
                  ? <><Loader2 className="h-4 w-4 animate-spin" /> Queuing…</>
                  : <><Sparkles className="h-4 w-4" /> Generate draft</>}
              </Button>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
