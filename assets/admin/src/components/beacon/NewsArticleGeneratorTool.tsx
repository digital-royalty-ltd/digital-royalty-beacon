import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ArrowLeft, CalendarClock, CheckCircle2, Loader2, Newspaper, Power, Trash2 } from 'lucide-react'
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

interface Destination {
  slug:       string
  label:      string
  taxonomies: Taxonomy[]
}

interface Schedule {
  id:             string
  automation_key: string
  frequency:      string
  time:           string
  day_of_week:    string | null
  parameters:     Record<string, unknown>
  enabled:        boolean
  next_run_at:    string | null
  last_run_at:    string | null
}

interface Props {
  onBack: () => void
}

const FREQUENCY_LABELS: Record<string, string> = {
  daily: 'Daily',
  every_other_day: 'Every other day',
  weekly: 'Weekly',
}

const DAY_OPTIONS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

export function NewsArticleGeneratorTool({ onBack }: Props) {
  const [destinations,  setDestinations]  = useState<Destination[]>([])
  const [loadingDest,   setLoadingDest]   = useState(true)
  const [topic,         setTopic]         = useState('')
  const [niche,         setNiche]         = useState('')
  const [postType,      setPostType]      = useState('')
  const [selectedTerms, setSelectedTerms] = useState<Record<string, number[]>>({})
  const [generating,    setGenerating]    = useState(false)
  const [queued,        setQueued]        = useState(false)
  const [error,         setError]         = useState<string | null>(null)

  // Schedule state
  const [schedules,        setSchedules]        = useState<Schedule[]>([])
  const [loadingSchedules, setLoadingSchedules] = useState(true)
  const [showScheduleForm, setShowScheduleForm] = useState(false)
  const [schedFrequency,   setSchedFrequency]   = useState('daily')
  const [schedTime,        setSchedTime]        = useState('09:00')
  const [schedDay,         setSchedDay]         = useState('monday')
  const [savingSchedule,   setSavingSchedule]   = useState(false)

  useEffect(() => {
    api.get<Destination[]>('/news-article-generator/destinations')
      .then(data => {
        setDestinations(data)
        if (data.some(d => d.slug === 'post')) {
          setPostType('post')
        } else if (data.length > 0) {
          setPostType(data[0].slug)
        }
      })
      .catch(() => {})
      .finally(() => setLoadingDest(false))

    fetchSchedules()
  }, [])

  const fetchSchedules = () => {
    setLoadingSchedules(true)
    api.get<Schedule[]>('/automation-schedules?automation_key=news_article_generator')
      .then(setSchedules)
      .catch(() => {})
      .finally(() => setLoadingSchedules(false))
  }

  const currentDestination = destinations.find(d => d.slug === postType) ?? null

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

  const canSubmit = topic.trim() && niche.trim() && !generating

  const buildPayload = () => {
    const taxonomies: Record<string, number[]> = {}
    for (const [taxSlug, termIds] of Object.entries(selectedTerms)) {
      if (termIds.length > 0) {
        taxonomies[taxSlug] = termIds
      }
    }

    return {
      topic: topic.trim(),
      niche: niche.trim(),
      post_type: postType || undefined,
      taxonomies: Object.keys(taxonomies).length > 0 ? taxonomies : undefined,
      adapter_context: {
        post_type: postType || 'post',
        taxonomies,
      },
    }
  }

  const handleGenerate = async () => {
    if (!canSubmit) return
    setGenerating(true)
    setError(null)
    try {
      await api.post('/news-article-generator/generate', buildPayload())
      setQueued(true)
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Request failed.'
      setError(msg)
    } finally {
      setGenerating(false)
    }
  }

  const handleReset = () => {
    setQueued(false)
    setTopic('')
    setNiche('')
    setSelectedTerms({})
    setError(null)
  }

  const handleCreateSchedule = async () => {
    if (!topic.trim() || !niche.trim()) return
    setSavingSchedule(true)
    setError(null)
    try {
      await api.post('/automation-schedules', {
        automation_key: 'news_article_generator',
        frequency: schedFrequency,
        time: schedTime,
        day_of_week: schedFrequency === 'weekly' ? schedDay : undefined,
        end_behavior: 'infinite',
        parameters: buildPayload(),
      })
      setShowScheduleForm(false)
      fetchSchedules()
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to create schedule.')
    } finally {
      setSavingSchedule(false)
    }
  }

  const handleDeleteSchedule = async (id: string) => {
    try {
      await api.delete(`/automation-schedules/${id}`)
      fetchSchedules()
    } catch {
      setError('Failed to delete schedule.')
    }
  }

  const handleToggleSchedule = async (id: string, enabled: boolean) => {
    try {
      await api.patch(`/automation-schedules/${id}`, { enabled })
      fetchSchedules()
    } catch {
      setError('Failed to update schedule.')
    }
  }

  const destLabel = currentDestination?.label ?? 'Posts'

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={onBack}
          className="p-1.5 rounded-lg text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-xl font-semibold text-[#390d58]">News Article Generator</h2>
          <p className="text-sm text-muted-foreground">Find the latest news on any topic and publish an original report.</p>
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
              <p className="text-lg font-semibold text-[#390d58]">News article queued</p>
              <p className="text-sm text-muted-foreground mt-1 max-w-sm">
                Beacon is searching for the latest news and writing your report. It will appear in
                <strong className="text-[#390d58]"> {destLabel} &rarr; Drafts</strong> when ready.
              </p>
            </div>
            <div className="flex gap-3 mt-2">
              <Button onClick={handleReset} variant="outline" className="border-[#390d58]/20 text-[#390d58]">
                Generate another
              </Button>
              <Button
                onClick={() => {
                  const editUrl = postType && postType !== 'post'
                    ? `edit.php?post_type=${postType}&post_status=draft`
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
        <>
          <Card className="border-[#390d58]/20 overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
            <CardHeader>
              <CardTitle className="text-lg text-[#390d58] flex items-center gap-2">
                <Newspaper className="h-5 w-5" />
                New news article
              </CardTitle>
              <CardDescription>
                Describe a topic and industry niche. Beacon will find a relevant recent news article online and write an original report from your perspective.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">

              {error && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
              )}

              {/* Topic */}
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">
                  Topic
                </label>
                <input
                  type="text"
                  value={topic}
                  onChange={e => setTopic(e.target.value)}
                  placeholder='e.g. "AI regulation in the EU" or "latest renewable energy breakthroughs"'
                  className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
                <p className="text-xs text-muted-foreground mt-1">
                  What news story should Beacon look for?
                </p>
              </div>

              {/* Niche */}
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">
                  Industry / Niche
                </label>
                <input
                  type="text"
                  value={niche}
                  onChange={e => setNiche(e.target.value)}
                  placeholder='e.g. "Technology", "Healthcare", "Financial Services"'
                  className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
                <p className="text-xs text-muted-foreground mt-1">
                  Narrows the search to relevant sources in this industry.
                </p>
              </div>

              {/* Destination type */}
              {!loadingDest && destinations.length > 0 && (
                <div>
                  <label className="text-sm font-medium text-[#390d58] mb-2 block">
                    Create as
                  </label>
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

              {/* Taxonomies */}
              {currentDestination && currentDestination.taxonomies.length > 0 && (
                <div className="space-y-4">
                  {currentDestination.taxonomies.map(tax => (
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

              <Button
                onClick={handleGenerate}
                disabled={!canSubmit}
                className="w-full gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white disabled:opacity-50"
              >
                {generating
                  ? <><Loader2 className="h-4 w-4 animate-spin" /> Searching &amp; writing…</>
                  : <><Newspaper className="h-4 w-4" /> Generate news article</>}
              </Button>
            </CardContent>
          </Card>

          {/* Schedule section */}
          <Card className="border-[#390d58]/20 overflow-hidden">
            <CardHeader>
              <CardTitle className="text-lg text-[#390d58] flex items-center gap-2">
                <CalendarClock className="h-5 w-5" />
                Scheduled runs
              </CardTitle>
              <CardDescription>
                Set up recurring news article generation. Beacon will automatically search for fresh news and create a draft on your schedule.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">

              {/* Existing schedules */}
              {!loadingSchedules && schedules.length > 0 && (
                <div className="space-y-2">
                  {schedules.map(sched => (
                    <div key={sched.id}
                      className={`flex items-center justify-between rounded-lg border px-4 py-3 ${
                        sched.enabled
                          ? 'border-[#390d58]/20 bg-[#390d58]/[0.02]'
                          : 'border-gray-200 bg-gray-50 opacity-60'
                      }`}>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-[#390d58]">
                          {FREQUENCY_LABELS[sched.frequency] ?? sched.frequency}
                          {' at '}{sched.time}
                          {sched.day_of_week && <> on <span className="capitalize">{sched.day_of_week}</span></>}
                        </p>
                        <p className="text-xs text-muted-foreground mt-0.5 truncate">
                          {(sched.parameters as Record<string, unknown>)?.topic as string ?? 'No topic'}
                          {' · '}
                          {(sched.parameters as Record<string, unknown>)?.niche as string ?? 'No niche'}
                        </p>
                        {sched.next_run_at && (
                          <p className="text-[10px] text-muted-foreground mt-0.5">
                            Next: {new Date(sched.next_run_at + 'Z').toLocaleString()}
                          </p>
                        )}
                      </div>
                      <div className="flex items-center gap-1.5 ml-3 shrink-0">
                        <button
                          onClick={() => handleToggleSchedule(sched.id, !sched.enabled)}
                          className={`p-1.5 rounded-lg transition-colors ${
                            sched.enabled
                              ? 'text-green-600 hover:bg-green-50'
                              : 'text-gray-400 hover:bg-gray-100'
                          }`}
                          title={sched.enabled ? 'Pause' : 'Resume'}
                        >
                          <Power className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => handleDeleteSchedule(sched.id)}
                          className="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                          title="Delete schedule"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}

              {loadingSchedules && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                  <Loader2 className="h-4 w-4 animate-spin" /> Loading schedules…
                </div>
              )}

              {/* Create schedule form */}
              {showScheduleForm ? (
                <div className="rounded-lg border border-[#390d58]/20 p-4 space-y-4">
                  {!topic.trim() || !niche.trim() ? (
                    <p className="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                      Fill in the topic and niche above first — the schedule will use those values for each run.
                    </p>
                  ) : (
                    <>
                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Frequency</label>
                          <select
                            value={schedFrequency}
                            onChange={e => setSchedFrequency(e.target.value)}
                            className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                          >
                            {Object.entries(FREQUENCY_LABELS).map(([val, label]) => (
                              <option key={val} value={val}>{label}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Time (UTC)</label>
                          <input
                            type="time"
                            value={schedTime}
                            onChange={e => setSchedTime(e.target.value)}
                            className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                          />
                        </div>
                      </div>

                      {schedFrequency === 'weekly' && (
                        <div>
                          <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Day of week</label>
                          <div className="flex flex-wrap gap-1.5">
                            {DAY_OPTIONS.map(day => (
                              <button
                                key={day}
                                onClick={() => setSchedDay(day)}
                                className={`rounded-lg border px-2.5 py-1 text-xs capitalize transition-all ${
                                  schedDay === day
                                    ? 'border-[#390d58] bg-[#390d58] text-white'
                                    : 'border-[#390d58]/20 text-[#390d58] hover:border-[#390d58]/40'
                                }`}
                              >
                                {day.slice(0, 3)}
                              </button>
                            ))}
                          </div>
                        </div>
                      )}

                      <p className="text-xs text-muted-foreground">
                        Will generate an article about <strong className="text-[#390d58]">{topic.trim()}</strong> in <strong className="text-[#390d58]">{niche.trim()}</strong> {FREQUENCY_LABELS[schedFrequency]?.toLowerCase() ?? schedFrequency} at {schedTime} UTC.
                      </p>

                      <div className="flex gap-2">
                        <Button
                          onClick={handleCreateSchedule}
                          disabled={savingSchedule}
                          className="flex-1 gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white"
                        >
                          {savingSchedule
                            ? <><Loader2 className="h-4 w-4 animate-spin" /> Saving…</>
                            : <><CalendarClock className="h-4 w-4" /> Create schedule</>}
                        </Button>
                        <Button
                          onClick={() => setShowScheduleForm(false)}
                          variant="outline"
                          className="border-[#390d58]/20 text-[#390d58]"
                        >
                          Cancel
                        </Button>
                      </div>
                    </>
                  )}
                </div>
              ) : (
                <Button
                  onClick={() => setShowScheduleForm(true)}
                  variant="outline"
                  className="w-full gap-2 border-[#390d58]/20 text-[#390d58] hover:bg-[#390d58]/5"
                >
                  <CalendarClock className="h-4 w-4" /> Add schedule
                </Button>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}
