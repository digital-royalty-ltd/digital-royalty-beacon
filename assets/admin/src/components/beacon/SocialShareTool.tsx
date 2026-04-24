import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ArrowLeft, CalendarClock, CheckCircle2, Loader2, Power, Search, Share2, Trash2 } from 'lucide-react'
import { api } from '@/lib/api'

interface Platform {
  slug:      string
  label:     string
  connected: boolean
}

interface Source {
  slug:  string
  label: string
  count: number
}

interface PostItem {
  id:      number
  title:   string
  url:     string
  excerpt: string
  date:    string
}

interface Schedule {
  id:             string
  automation_key: string
  frequency:      string
  time:           string
  day_of_week:    string | null
  end_behavior:   string
  parameters:     Record<string, unknown>
  enabled:        boolean
  next_run_at:    string | null
  last_run_at:    string | null
  cycle_count:    number
  exhausted:      boolean
}

interface Props {
  onBack: () => void
}

const FREQUENCY_LABELS: Record<string, string> = {
  daily: 'Daily', every_other_day: 'Every other day', weekly: 'Weekly',
}
const END_BEHAVIOR_LABELS: Record<string, string> = {
  infinite: 'Run forever', exhaust: 'Stop when all shared', infinite_cycle: 'Cycle forever',
}
const DAY_OPTIONS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

export function SocialShareTool({ onBack }: Props) {
  // Data
  const [platforms, setPlatforms] = useState<Platform[]>([])
  const [sources,   setSources]   = useState<Source[]>([])
  const [posts,     setPosts]     = useState<PostItem[]>([])
  const [loading,   setLoading]   = useState(true)

  // Single mode state
  const [selectedSource,    setSelectedSource]    = useState('')
  const [selectedPost,      setSelectedPost]      = useState<PostItem | null>(null)
  const [selectedPlatforms, setSelectedPlatforms] = useState<string[]>([])
  const [searchQuery,       setSearchQuery]       = useState('')
  const [loadingPosts,      setLoadingPosts]      = useState(false)
  const [generating,        setGenerating]        = useState(false)
  const [queued,            setQueued]            = useState(false)
  const [error,             setError]             = useState<string | null>(null)

  // Schedule state
  const [schedules,         setSchedules]         = useState<Schedule[]>([])
  const [loadingSchedules,  setLoadingSchedules]  = useState(true)
  const [showScheduleForm,  setShowScheduleForm]  = useState(false)
  const [schedSources,      setSchedSources]      = useState<string[]>([])
  const [schedPlatforms,    setSchedPlatforms]    = useState<string[]>([])
  const [schedFrequency,    setSchedFrequency]    = useState('daily')
  const [schedTime,         setSchedTime]         = useState('09:00')
  const [schedDay,          setSchedDay]          = useState('monday')
  const [schedEndBehavior,  setSchedEndBehavior]  = useState('infinite_cycle')
  const [savingSchedule,    setSavingSchedule]    = useState(false)

  useEffect(() => {
    Promise.all([
      api.get<Platform[]>('/social-share/platforms'),
      api.get<Source[]>('/social-share/sources'),
    ]).then(([plat, src]) => {
      setPlatforms(plat)
      setSources(src)
      if (src.length > 0) setSelectedSource(src[0].slug)
    }).catch(() => setError('Could not load platforms or sources.'))
      .finally(() => setLoading(false))

    fetchSchedules()
  }, [])

  // Fetch posts when source changes
  useEffect(() => {
    if (!selectedSource) return
    setLoadingPosts(true)
    setSelectedPost(null)
    api.get<PostItem[]>(`/social-share/posts?post_type=${selectedSource}${searchQuery ? `&search=${encodeURIComponent(searchQuery)}` : ''}`)
      .then(setPosts)
      .catch(() => setPosts([]))
      .finally(() => setLoadingPosts(false))
  }, [selectedSource, searchQuery])

  const fetchSchedules = () => {
    setLoadingSchedules(true)
    api.get<Schedule[]>('/automation-schedules?automation_key=social_share')
      .then(setSchedules)
      .catch(() => {})
      .finally(() => setLoadingSchedules(false))
  }

  const togglePlatform = (slug: string) => {
    setSelectedPlatforms(prev =>
      prev.includes(slug) ? prev.filter(p => p !== slug) : [...prev, slug]
    )
  }

  const toggleSchedSource = (slug: string) => {
    setSchedSources(prev =>
      prev.includes(slug) ? prev.filter(s => s !== slug) : [...prev, slug]
    )
  }

  const toggleSchedPlatform = (slug: string) => {
    setSchedPlatforms(prev =>
      prev.includes(slug) ? prev.filter(p => p !== slug) : [...prev, slug]
    )
  }

  const canSubmit = selectedPost && selectedPlatforms.length > 0 && !generating

  const handleGenerate = async () => {
    if (!canSubmit) return
    setGenerating(true)
    setError(null)
    try {
      await api.post('/social-share/generate', {
        post_id: selectedPost.id,
        platforms: selectedPlatforms,
      })
      setQueued(true)
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Request failed.')
    } finally {
      setGenerating(false)
    }
  }

  const handleReset = () => {
    setQueued(false)
    setSelectedPost(null)
    setSelectedPlatforms([])
    setError(null)
  }

  const handleCreateSchedule = async () => {
    if (schedSources.length === 0 || schedPlatforms.length === 0) return
    setSavingSchedule(true)
    setError(null)
    try {
      await api.post('/automation-schedules', {
        automation_key: 'social_share',
        frequency: schedFrequency,
        time: schedTime,
        day_of_week: schedFrequency === 'weekly' ? schedDay : undefined,
        end_behavior: schedEndBehavior,
        parameters: {
          source_types: schedSources,
          platforms: schedPlatforms,
        },
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
    try { await api.delete(`/automation-schedules/${id}`); fetchSchedules() }
    catch { setError('Failed to delete schedule.') }
  }

  const handleToggleSchedule = async (id: string, enabled: boolean) => {
    try { await api.patch(`/automation-schedules/${id}`, { enabled }); fetchSchedules() }
    catch { setError('Failed to update schedule.') }
  }

  const connectedPlatforms = platforms.filter(p => p.connected)
  const disconnectedPlatforms = platforms.filter(p => !p.connected)

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-muted-foreground text-sm py-8">
        <Loader2 className="h-4 w-4 animate-spin" /> Loading…
      </div>
    )
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={onBack}
          className="p-1.5 rounded-lg text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-xl font-semibold text-[#390d58]">Social Media Sharer</h2>
          <p className="text-sm text-muted-foreground">Share your content across social platforms with AI-crafted posts.</p>
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
              <p className="text-lg font-semibold text-[#390d58]">Social posts queued</p>
              <p className="text-sm text-muted-foreground mt-1 max-w-sm">
                Beacon is crafting platform-specific posts for <strong className="text-[#390d58]">{selectedPlatforms.length} platform{selectedPlatforms.length > 1 ? 's' : ''}</strong>. They will be published to your connected accounts when ready.
              </p>
            </div>
            <Button onClick={handleReset} variant="outline" className="border-[#390d58]/20 text-[#390d58]">
              Share another
            </Button>
          </CardContent>
        </Card>
      ) : (
        <>
          {/* Single share card */}
          <Card className="border-[#390d58]/20 overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
            <CardHeader>
              <CardTitle className="text-lg text-[#390d58] flex items-center gap-2">
                <Share2 className="h-5 w-5" />
                Share a post
              </CardTitle>
              <CardDescription>
                Pick a published post and choose which platforms to share it on. Beacon generates tailored copy for each platform.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
              {error && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
              )}

              {/* Source type */}
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">Content type</label>
                <div className="flex flex-wrap gap-2">
                  {sources.map(src => (
                    <button key={src.slug} onClick={() => { setSelectedSource(src.slug); setSearchQuery('') }}
                      className={`rounded-lg border px-3 py-1.5 text-sm transition-all ${
                        selectedSource === src.slug
                          ? 'border-[#390d58] bg-[#390d58]/5 text-[#390d58] font-medium ring-1 ring-[#390d58]/20'
                          : 'border-[#390d58]/15 text-muted-foreground hover:border-[#390d58]/30'
                      }`}>
                      {src.label} <span className="text-[10px] opacity-60 ml-1">{src.count}</span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Post picker */}
              {selectedSource && (
                <div>
                  <label className="text-sm font-medium text-[#390d58] mb-2 block">Select post</label>
                  <div className="relative mb-2">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                    <input type="text" value={searchQuery}
                      onChange={e => setSearchQuery(e.target.value)}
                      placeholder="Search…"
                      className="w-full text-sm border border-[#390d58]/20 rounded-lg pl-9 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
                  </div>
                  {loadingPosts ? (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                      <Loader2 className="h-4 w-4 animate-spin" /> Loading…
                    </div>
                  ) : posts.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No published posts found.</p>
                  ) : (
                    <div className="max-h-48 overflow-y-auto rounded-lg border border-[#390d58]/10 divide-y divide-[#390d58]/5">
                      {posts.map(p => (
                        <button key={p.id} onClick={() => setSelectedPost(p)}
                          className={`w-full text-left px-3 py-2.5 transition-colors ${
                            selectedPost?.id === p.id
                              ? 'bg-[#390d58]/5'
                              : 'hover:bg-[#390d58]/[0.02]'
                          }`}>
                          <p className="text-sm font-medium text-[#390d58] line-clamp-1">{p.title}</p>
                          <p className="text-xs text-muted-foreground line-clamp-1 mt-0.5">{p.excerpt}</p>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {/* Platform selection */}
              {selectedPost && (
                <div>
                  <label className="text-sm font-medium text-[#390d58] mb-2 block">Platforms</label>
                  {connectedPlatforms.length === 0 ? (
                    <p className="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                      No social platforms connected. Configure your API connections to enable sharing.
                    </p>
                  ) : (
                    <div className="flex flex-wrap gap-2">
                      {connectedPlatforms.map(p => {
                        const sel = selectedPlatforms.includes(p.slug)
                        return (
                          <button key={p.slug} onClick={() => togglePlatform(p.slug)}
                            className={`rounded-lg border px-3 py-1.5 text-sm transition-all ${
                              sel ? 'border-[#390d58] bg-[#390d58] text-white' : 'border-[#390d58]/20 text-[#390d58] hover:border-[#390d58]/40'
                            }`}>
                            {p.label}
                          </button>
                        )
                      })}
                    </div>
                  )}
                  {disconnectedPlatforms.length > 0 && (
                    <div className="flex flex-wrap gap-1.5 mt-2">
                      {disconnectedPlatforms.map(p => (
                        <span key={p.slug} className="rounded-lg border border-dashed border-gray-200 px-3 py-1.5 text-sm text-gray-400">
                          {p.label}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {selectedPost && (
                <Button onClick={handleGenerate} disabled={!canSubmit}
                  className="w-full gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white disabled:opacity-50">
                  {generating
                    ? <><Loader2 className="h-4 w-4 animate-spin" /> Generating posts…</>
                    : <><Share2 className="h-4 w-4" /> Generate &amp; share</>}
                </Button>
              )}
            </CardContent>
          </Card>

          {/* Schedule card */}
          <Card className="border-[#390d58]/20 overflow-hidden">
            <CardHeader>
              <CardTitle className="text-lg text-[#390d58] flex items-center gap-2">
                <CalendarClock className="h-5 w-5" />
                Scheduled sharing
              </CardTitle>
              <CardDescription>
                Automatically share content from chosen sources on a recurring schedule. Beacon picks the next unshared post and crafts platform-specific copy.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Existing schedules */}
              {!loadingSchedules && schedules.length > 0 && (
                <div className="space-y-2">
                  {schedules.map(sched => {
                    const params = sched.parameters as Record<string, unknown>
                    const srcTypes = (Array.isArray(params?.source_types) ? params.source_types : []) as string[]
                    const platList = (Array.isArray(params?.platforms) ? params.platforms : []) as string[]
                    return (
                      <div key={sched.id}
                        className={`flex items-center justify-between rounded-lg border px-4 py-3 ${
                          sched.enabled && !sched.exhausted
                            ? 'border-[#390d58]/20 bg-[#390d58]/[0.02]'
                            : 'border-gray-200 bg-gray-50 opacity-60'
                        }`}>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-[#390d58]">
                            {FREQUENCY_LABELS[sched.frequency] ?? sched.frequency}
                            {' at '}{sched.time}
                            {sched.day_of_week && <> on <span className="capitalize">{sched.day_of_week}</span></>}
                            {' · '}<span className="text-xs font-normal text-muted-foreground">{END_BEHAVIOR_LABELS[sched.end_behavior] ?? sched.end_behavior}</span>
                          </p>
                          <p className="text-xs text-muted-foreground mt-0.5 truncate">
                            {srcTypes.join(', ')} → {platList.join(', ')}
                          </p>
                          {sched.exhausted && (
                            <p className="text-[10px] text-amber-600 mt-0.5">Exhausted — all items shared</p>
                          )}
                          {sched.next_run_at && !sched.exhausted && (
                            <p className="text-[10px] text-muted-foreground mt-0.5">
                              Next: {new Date(sched.next_run_at + 'Z').toLocaleString()}
                              {sched.cycle_count > 0 && ` · Cycle ${sched.cycle_count + 1}`}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center gap-1.5 ml-3 shrink-0">
                          <button onClick={() => handleToggleSchedule(sched.id, !sched.enabled)}
                            className={`p-1.5 rounded-lg transition-colors ${sched.enabled ? 'text-green-600 hover:bg-green-50' : 'text-gray-400 hover:bg-gray-100'}`}
                            title={sched.enabled ? 'Pause' : 'Resume'}>
                            <Power className="h-4 w-4" />
                          </button>
                          <button onClick={() => handleDeleteSchedule(sched.id)}
                            className="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                            title="Delete schedule">
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      </div>
                    )
                  })}
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
                  {/* Source types */}
                  <div>
                    <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Share from</label>
                    <div className="flex flex-wrap gap-2">
                      {sources.map(src => {
                        const sel = schedSources.includes(src.slug)
                        return (
                          <button key={src.slug} onClick={() => toggleSchedSource(src.slug)}
                            className={`rounded-lg border px-3 py-1.5 text-xs transition-all ${
                              sel ? 'border-[#390d58] bg-[#390d58] text-white' : 'border-[#390d58]/20 text-[#390d58] hover:border-[#390d58]/40'
                            }`}>
                            {src.label}
                          </button>
                        )
                      })}
                    </div>
                  </div>

                  {/* Platforms */}
                  <div>
                    <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Share to</label>
                    {connectedPlatforms.length === 0 ? (
                      <p className="text-xs text-amber-600">No platforms connected.</p>
                    ) : (
                      <div className="flex flex-wrap gap-2">
                        {connectedPlatforms.map(p => {
                          const sel = schedPlatforms.includes(p.slug)
                          return (
                            <button key={p.slug} onClick={() => toggleSchedPlatform(p.slug)}
                              className={`rounded-lg border px-3 py-1.5 text-xs transition-all ${
                                sel ? 'border-[#390d58] bg-[#390d58] text-white' : 'border-[#390d58]/20 text-[#390d58] hover:border-[#390d58]/40'
                              }`}>
                              {p.label}
                            </button>
                          )
                        })}
                      </div>
                    )}
                  </div>

                  {/* Frequency + time */}
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Frequency</label>
                      <select value={schedFrequency} onChange={e => setSchedFrequency(e.target.value)}
                        className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30">
                        {Object.entries(FREQUENCY_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Time (UTC)</label>
                      <input type="time" value={schedTime} onChange={e => setSchedTime(e.target.value)}
                        className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
                    </div>
                  </div>

                  {schedFrequency === 'weekly' && (
                    <div>
                      <label className="text-xs font-medium text-[#390d58] mb-1.5 block">Day of week</label>
                      <div className="flex flex-wrap gap-1.5">
                        {DAY_OPTIONS.map(day => (
                          <button key={day} onClick={() => setSchedDay(day)}
                            className={`rounded-lg border px-2.5 py-1 text-xs capitalize transition-all ${
                              schedDay === day ? 'border-[#390d58] bg-[#390d58] text-white' : 'border-[#390d58]/20 text-[#390d58]'
                            }`}>
                            {day.slice(0, 3)}
                          </button>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* End behavior */}
                  <div>
                    <label className="text-xs font-medium text-[#390d58] mb-1.5 block">When all posts have been shared</label>
                    <select value={schedEndBehavior} onChange={e => setSchedEndBehavior(e.target.value)}
                      className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30">
                      {Object.entries(END_BEHAVIOR_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                  </div>

                  <div className="flex gap-2">
                    <Button onClick={handleCreateSchedule}
                      disabled={savingSchedule || schedSources.length === 0 || schedPlatforms.length === 0}
                      className="flex-1 gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white">
                      {savingSchedule
                        ? <><Loader2 className="h-4 w-4 animate-spin" /> Saving…</>
                        : <><CalendarClock className="h-4 w-4" /> Create schedule</>}
                    </Button>
                    <Button onClick={() => setShowScheduleForm(false)} variant="outline"
                      className="border-[#390d58]/20 text-[#390d58]">
                      Cancel
                    </Button>
                  </div>
                </div>
              ) : (
                <Button onClick={() => setShowScheduleForm(true)} variant="outline"
                  className="w-full gap-2 border-[#390d58]/20 text-[#390d58] hover:bg-[#390d58]/5">
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
