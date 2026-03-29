import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Loader2, Sparkles, ArrowLeft, CheckCircle2 } from 'lucide-react'
import { api } from '@/lib/api'

interface ContentArea {
  key:     string
  label:   string
  intent:  string
  topics:  string[]
}

interface Props {
  onBack: () => void
}

export function ContentGeneratorTool({ onBack }: Props) {
  const [areas,         setAreas]         = useState<ContentArea[]>([])
  const [loadingAreas,  setLoadingAreas]  = useState(true)
  const [selectedArea,  setSelectedArea]  = useState<ContentArea | null>(null)
  const [topic,         setTopic]         = useState('')
  const [generating,    setGenerating]    = useState(false)
  const [queued,        setQueued]        = useState(false)
  const [error,         setError]         = useState<string | null>(null)

  useEffect(() => {
    api.get<ContentArea[]>('/content-generator/content-areas')
      .then(setAreas)
      .catch(() => setError('Could not load content areas. Make sure site reports have been run.'))
      .finally(() => setLoadingAreas(false))
  }, [])

  const handleGenerate = async () => {
    if (!selectedArea || !topic.trim()) return
    setGenerating(true)
    setError(null)
    try {
      await api.post('/content-generator/generate', {
        content_area_key: selectedArea.key,
        topic: topic.trim(),
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
    setTopic('')
    setSelectedArea(null)
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
                <strong className="text-[#390d58]"> Posts → Drafts</strong> when ready.
              </p>
            </div>
            <div className="flex gap-3 mt-2">
              <Button onClick={handleReset} variant="outline" className="border-[#390d58]/20 text-[#390d58]">
                Generate another
              </Button>
              <Button
                onClick={() => window.open(
                  `${window.BeaconData?.adminUrl ?? '/wp-admin/'}edit.php?post_status=draft`,
                  '_blank',
                  'noopener,noreferrer'
                )}
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
              Choose a content area and describe the topic. Beacon will write the full draft.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">

            {error && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
            )}

            {/* Step 1 — content area */}
            <div>
              <label className="text-sm font-medium text-[#390d58] mb-2 block">
                Content area
              </label>
              {loadingAreas ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                  <Loader2 className="h-4 w-4 animate-spin" /> Loading content areas…
                </div>
              ) : areas.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No content areas found.{' '}
                  <a href="#" onClick={(e) => { e.preventDefault(); onBack() }}
                    className="text-[#390d58] underline underline-offset-2">
                    Run site reports first
                  </a>.
                </p>
              ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                  {areas.map(area => (
                    <button
                      key={area.key}
                      onClick={() => setSelectedArea(area)}
                      className={`text-left rounded-xl border px-4 py-3 transition-all ${
                        selectedArea?.key === area.key
                          ? 'border-[#390d58] bg-[#390d58]/5 ring-1 ring-[#390d58]/20'
                          : 'border-[#390d58]/15 hover:border-[#390d58]/30 hover:bg-[#390d58]/[0.02]'
                      }`}
                    >
                      <p className="text-sm font-medium text-[#390d58]">{area.label}</p>
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

            {/* Step 2 — topic */}
            {selectedArea && (
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">
                  Topic or working title
                </label>
                <input
                  type="text"
                  value={topic}
                  onChange={e => setTopic(e.target.value)}
                  onKeyDown={e => e.key === 'Enter' && !generating && handleGenerate()}
                  placeholder={`e.g. "${selectedArea.topics[0] ?? 'Describe the topic…'}"`}
                  className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
                <p className="text-xs text-muted-foreground mt-1.5">
                  Be specific — the more context you give, the better the draft.
                </p>
              </div>
            )}

            {selectedArea && (
              <Button
                onClick={handleGenerate}
                disabled={!topic.trim() || generating}
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
