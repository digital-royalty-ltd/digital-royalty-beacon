import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ArrowLeft, Loader2, Sparkles, CheckCircle2, ChevronDown, ChevronRight } from 'lucide-react'
import { api } from '@/lib/api'

interface ContentRecommendation {
  content_area_key: string
  content_area_label: string
  topic: string
  rationale: string
}

interface AreaRecommendation {
  label: string
  intent: string
  suggested_topics: string[]
  rationale: string
}

interface Results {
  content_recs: { recommendations: ContentRecommendation[] } | null
  area_recs: { recommendations: AreaRecommendation[] } | null
  completed_at: string | null
}

interface Props {
  results: Results
  onBack: () => void
}

export function GapAnalysisResults({ results, onBack }: Props) {
  const contentRecs = results.content_recs?.recommendations ?? []
  const areaRecs    = results.area_recs?.recommendations    ?? []

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <button
          onClick={onBack}
          className="p-1.5 rounded-lg text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-xl font-semibold text-[#390d58]">Content Gap Analysis</h2>
          <p className="text-sm text-muted-foreground">
            {results.completed_at
              ? `Completed ${new Date(results.completed_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`
              : 'Latest results'}
          </p>
        </div>
      </div>

      {contentRecs.length > 0 && (
        <Section
          title="Content Recommendations"
          description="Topics to create within your existing content areas."
          count={contentRecs.length}
        >
          {contentRecs.map((rec, i) => (
            <ContentRecCard key={i} rec={rec} />
          ))}
        </Section>
      )}

      {areaRecs.length > 0 && (
        <Section
          title="New Area Recommendations"
          description="Entirely new content silos the site should build."
          count={areaRecs.length}
        >
          {areaRecs.map((rec, i) => (
            <AreaRecCard key={i} rec={rec} />
          ))}
        </Section>
      )}

      {contentRecs.length === 0 && areaRecs.length === 0 && (
        <Card className="border-[#390d58]/20">
          <CardContent className="py-12 text-center text-muted-foreground text-sm">
            No recommendations returned. Try re-running the analysis.
          </CardContent>
        </Card>
      )}
    </div>
  )
}

function Section({ title, description, count, children }: {
  title: string
  description: string
  count: number
  children: React.ReactNode
}) {
  return (
    <div className="space-y-3">
      <div className="flex items-center gap-3">
        <div>
          <h3 className="text-base font-semibold text-[#390d58]">{title}</h3>
          <p className="text-sm text-muted-foreground">{description}</p>
        </div>
        <Badge className="ml-auto bg-[#390d58]/10 text-[#390d58] border-0 text-xs">{count}</Badge>
      </div>
      <div className="space-y-2">{children}</div>
    </div>
  )
}

function ContentRecCard({ rec }: { rec: ContentRecommendation }) {
  const [implementing, setImplementing] = useState(false)
  const [done, setDone] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleImplement = async () => {
    setImplementing(true)
    setError(null)
    try {
      await api.post('/automations/gap_analysis/implement', {
        content_area_key: rec.content_area_key,
        topic: rec.topic,
      })
      setDone(true)
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to queue draft.')
    } finally {
      setImplementing(false)
    }
  }

  return (
    <Card className="border-[#390d58]/15">
      <CardContent className="py-3 px-4">
        <div className="flex items-start gap-3">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <Badge variant="outline" className="text-[10px] border-[#390d58]/20 text-[#390d58] shrink-0">
                {rec.content_area_label}
              </Badge>
              <p className="text-sm font-medium text-[#390d58] truncate">{rec.topic}</p>
            </div>
            {rec.rationale && (
              <p className="text-xs text-muted-foreground mt-1">{rec.rationale}</p>
            )}
            {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
          </div>
          <div className="shrink-0">
            {done ? (
              <span className="inline-flex items-center gap-1 text-xs text-green-600">
                <CheckCircle2 className="h-3.5 w-3.5" /> Queued
              </span>
            ) : (
              <Button
                size="sm"
                onClick={handleImplement}
                disabled={implementing}
                className="gap-1 text-xs h-7 bg-[#390d58] hover:bg-[#4a1170] text-white"
              >
                {implementing
                  ? <Loader2 className="h-3 w-3 animate-spin" />
                  : <Sparkles className="h-3 w-3" />}
                Generate
              </Button>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

function AreaRecCard({ rec }: { rec: AreaRecommendation }) {
  const [expanded, setExpanded] = useState(false)

  return (
    <Card className="border-[#390d58]/15">
      <CardContent className="py-3 px-4">
        <button
          onClick={() => setExpanded(e => !e)}
          className="flex items-start gap-2 w-full text-left"
        >
          {expanded
            ? <ChevronDown className="h-4 w-4 text-[#390d58] shrink-0 mt-0.5" />
            : <ChevronRight className="h-4 w-4 text-[#390d58] shrink-0 mt-0.5" />}
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-[#390d58]">{rec.label}</p>
            {rec.intent && (
              <p className="text-xs text-muted-foreground mt-0.5">{rec.intent}</p>
            )}
          </div>
        </button>

        {expanded && (
          <div className="mt-3 pl-6 space-y-3">
            {rec.rationale && (
              <p className="text-xs text-muted-foreground">{rec.rationale}</p>
            )}
            {rec.suggested_topics.length > 0 && (
              <div className="space-y-1.5">
                <p className="text-xs font-medium text-[#390d58]">Suggested topics</p>
                {rec.suggested_topics.map((topic, i) => (
                  <SuggestedTopicRow key={i} topic={topic} />
                ))}
              </div>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function SuggestedTopicRow({ topic }: { topic: string }) {
  const [implementing, setImplementing] = useState(false)
  const [done, setDone] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // New area recommendations don't have a content_area_key yet — they are future silos.
  // For now the UI surfaces them as informational; implementing queues a draft under a
  // best-guess content area (the user can adjust post-generation).
  const handleImplement = async () => {
    setImplementing(true)
    setError(null)
    try {
      await api.post('/automations/gap_analysis/implement', {
        content_area_key: '__new__',
        topic,
      })
      setDone(true)
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to queue draft.')
    } finally {
      setImplementing(false)
    }
  }

  return (
    <div className="flex items-center gap-2">
      <span className="text-xs text-muted-foreground flex-1">{topic}</span>
      {error && <span className="text-[10px] text-red-500">{error}</span>}
      {done ? (
        <span className="text-[10px] text-green-600 flex items-center gap-0.5">
          <CheckCircle2 className="h-3 w-3" /> Queued
        </span>
      ) : (
        <Button
          size="sm"
          variant="outline"
          onClick={handleImplement}
          disabled={implementing}
          className="h-6 px-2 text-[10px] gap-1 border-[#390d58]/20 text-[#390d58] hover:bg-[#390d58]/10"
        >
          {implementing ? <Loader2 className="h-2.5 w-2.5 animate-spin" /> : <Sparkles className="h-2.5 w-2.5" />}
          Draft
        </Button>
      )}
    </div>
  )
}
