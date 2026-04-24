import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { ArrowLeft, CheckCircle2, Image, Loader2, Search, Sparkles, CheckSquare, Square } from 'lucide-react'
import { api } from '@/lib/api'

interface PostItem {
  id:                 number
  title:              string
  post_type:          string
  status:             string
  h2_total:           number
  h2_missing_images:  number
}

interface PostType {
  slug:  string
  label: string
}

interface H2Item {
  index:     number
  text:      string
  content:   string
  has_image: boolean
}

interface AnalyzeResult {
  post_id: number
  title:   string
  h2s:     H2Item[]
  total:   number
  missing: number
}

interface Props {
  onBack: () => void
}

const STYLE_OPTIONS = [
  { value: 'illustration', label: 'Illustration', desc: 'Flat vector, clean editorial style' },
  { value: 'photographic', label: 'Photographic', desc: 'Realistic photo' },
  { value: '3d',           label: '3D Render',    desc: 'Stylised 3D, cinematic' },
  { value: 'abstract',     label: 'Abstract',     desc: 'Geometric shapes and patterns' },
  { value: 'minimalist',   label: 'Minimalist',   desc: 'Ultra-simple, lots of whitespace' },
] as const

const ASPECT_OPTIONS = [
  { value: 'landscape', label: 'Landscape', desc: '16:9 — widescreen inline image' },
  { value: 'square',    label: 'Square',    desc: '1:1 — compact, works mid-text' },
  { value: 'portrait',  label: 'Portrait',  desc: '9:16 — taller format' },
] as const

export function ContentEnrichmentTool({ onBack }: Props) {
  const [postTypes,     setPostTypes]     = useState<PostType[]>([])
  const [posts,         setPosts]         = useState<PostItem[]>([])
  const [loading,       setLoading]       = useState(true)
  const [search,        setSearch]        = useState('')
  const [filterType,    setFilterType]    = useState('')
  const [missingOnly,   setMissingOnly]   = useState(true)

  const [mode,          setMode]          = useState<'single' | 'batch'>('single')
  const [selectedIds,   setSelectedIds]   = useState<Set<number>>(new Set())
  const [analysis,      setAnalysis]      = useState<AnalyzeResult | null>(null)
  const [analyzing,     setAnalyzing]     = useState(false)

  const [styleHint,     setStyleHint]     = useState('illustration')
  const [aspectRatio,   setAspectRatio]   = useState('landscape')

  const [generating,    setGenerating]    = useState(false)
  const [queued,        setQueued]        = useState<{ dispatched: number; skipped: number; errors: string[] } | null>(null)
  const [error,         setError]         = useState<string | null>(null)

  const fetchPosts = (s = '', pt = '') => {
    setLoading(true)
    const params = new URLSearchParams()
    if (s) params.set('search', s)
    if (pt) params.set('post_type', pt)
    const qs = params.toString()

    api.get<{ post_types: PostType[]; posts: PostItem[] }>(
      `/content-enrichment/posts${qs ? `?${qs}` : ''}`
    )
      .then(data => {
        setPostTypes(data.post_types)
        setPosts(data.posts)
      })
      .catch(() => setError('Could not load posts.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchPosts() }, [])

  const handleSearch = () => fetchPosts(search, filterType)

  const handleSelectPost = (post: PostItem) => {
    if (mode === 'single') {
      setSelectedIds(new Set([post.id]))
      setAnalyzing(true)
      api.get<AnalyzeResult>(`/content-enrichment/analyze?post_id=${post.id}`)
        .then(setAnalysis)
        .catch(() => setError('Could not analyze post.'))
        .finally(() => setAnalyzing(false))
    } else {
      setSelectedIds(prev => {
        const next = new Set(prev)
        if (next.has(post.id)) { next.delete(post.id) } else { next.add(post.id) }
        return next
      })
    }
  }

  const handleGenerate = async () => {
    if (selectedIds.size === 0) return
    setGenerating(true)
    setError(null)
    try {
      const result = await api.post<{ dispatched: number; skipped: number; errors: string[] }>(
        '/content-enrichment/generate',
        {
          post_ids:     Array.from(selectedIds),
          style_hint:   styleHint,
          aspect_ratio: aspectRatio,
        }
      )
      setQueued(result)
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Generation failed.')
    } finally {
      setGenerating(false)
    }
  }

  const handleReset = () => {
    setQueued(null)
    setSelectedIds(new Set())
    setAnalysis(null)
    setError(null)
  }

  const visiblePosts = missingOnly ? posts.filter(p => p.h2_missing_images > 0) : posts

  const totalMissingAcrossSelected = Array.from(selectedIds)
    .map(id => posts.find(p => p.id === id)?.h2_missing_images ?? 0)
    .reduce((a, b) => a + b, 0)

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={onBack}
          className="p-1.5 rounded-lg text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-xl font-semibold text-[#390d58]">Content Image Enrichment</h2>
          <p className="text-sm text-muted-foreground">
            Add contextual images after every H2 in your content. Skips sections that already have an image.
          </p>
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
              <p className="text-lg font-semibold text-[#390d58]">
                {queued.dispatched} image{queued.dispatched === 1 ? '' : 's'} queued
              </p>
              <p className="text-sm text-muted-foreground mt-1 max-w-md">
                Images are being generated in the background and will be inserted into your content as each one completes.
                {queued.skipped > 0 && ` ${queued.skipped} H2${queued.skipped === 1 ? '' : 's'} skipped (already have images).`}
              </p>
              {queued.errors.length > 0 && (
                <div className="mt-3 text-xs text-red-600 max-w-md">
                  <p className="font-medium">Errors:</p>
                  {queued.errors.slice(0, 5).map((err, i) => (
                    <p key={i}>• {err}</p>
                  ))}
                </div>
              )}
            </div>
            <Button onClick={handleReset} className="bg-[#390d58] hover:bg-[#4a1170] text-white">
              Enrich another
            </Button>
          </CardContent>
        </Card>
      ) : (
        <>
          {error && (
            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
          )}

          {/* Mode toggle */}
          <Card className="border-[#390d58]/20 overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
            <CardHeader>
              <CardTitle className="text-lg text-[#390d58]">Mode</CardTitle>
              <CardDescription>Enrich a single post or batch-process multiple.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="flex gap-2">
                <button
                  onClick={() => { setMode('single'); setSelectedIds(new Set()); setAnalysis(null) }}
                  className={`flex-1 rounded-lg border px-4 py-3 text-sm transition-all ${
                    mode === 'single'
                      ? 'border-[#390d58] bg-[#390d58]/5 text-[#390d58] font-medium ring-1 ring-[#390d58]/20'
                      : 'border-[#390d58]/15 text-muted-foreground hover:border-[#390d58]/30'
                  }`}
                >
                  Single
                </button>
                <button
                  onClick={() => { setMode('batch'); setSelectedIds(new Set()); setAnalysis(null) }}
                  className={`flex-1 rounded-lg border px-4 py-3 text-sm transition-all ${
                    mode === 'batch'
                      ? 'border-[#390d58] bg-[#390d58]/5 text-[#390d58] font-medium ring-1 ring-[#390d58]/20'
                      : 'border-[#390d58]/15 text-muted-foreground hover:border-[#390d58]/30'
                  }`}
                >
                  Batch
                </button>
              </div>
            </CardContent>
          </Card>

          {/* Image style + aspect */}
          <Card className="border-[#390d58]/20 overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
            <CardHeader>
              <CardTitle className="text-lg text-[#390d58]">Image style</CardTitle>
              <CardDescription>
                Inline images are typically more supportive and restrained than featured images. Pick a style that suits your content.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">Style</label>
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                  {STYLE_OPTIONS.map(opt => (
                    <button
                      key={opt.value}
                      onClick={() => setStyleHint(opt.value)}
                      className={`text-left rounded-xl border px-3 py-2.5 transition-all ${
                        styleHint === opt.value
                          ? 'border-[#390d58] bg-[#390d58]/5 ring-1 ring-[#390d58]/20'
                          : 'border-[#390d58]/15 hover:border-[#390d58]/30'
                      }`}
                    >
                      <p className="text-sm font-medium text-[#390d58]">{opt.label}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{opt.desc}</p>
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">Aspect ratio</label>
                <div className="grid gap-2 sm:grid-cols-3">
                  {ASPECT_OPTIONS.map(opt => (
                    <button
                      key={opt.value}
                      onClick={() => setAspectRatio(opt.value)}
                      className={`text-left rounded-xl border px-3 py-2.5 transition-all ${
                        aspectRatio === opt.value
                          ? 'border-[#390d58] bg-[#390d58]/5 ring-1 ring-[#390d58]/20'
                          : 'border-[#390d58]/15 hover:border-[#390d58]/30'
                      }`}
                    >
                      <p className="text-sm font-medium text-[#390d58]">{opt.label}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{opt.desc}</p>
                    </button>
                  ))}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Posts list */}
          <Card className="border-[#390d58]/20 overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
            <CardHeader>
              <CardTitle className="text-lg text-[#390d58]">
                {mode === 'single' ? 'Pick a post' : `Pick posts (${selectedIds.size} selected)`}
              </CardTitle>
              <CardDescription>
                Shows number of H2 headings missing images. Only posts with missing images are shown by default.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex flex-wrap items-center gap-2">
                <div className="flex-1 min-w-[240px] flex items-center gap-2">
                  <Input
                    placeholder="Search posts..."
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && handleSearch()}
                  />
                  <Button variant="outline" size="sm" onClick={handleSearch} className="gap-1.5 border-[#390d58]/20 text-[#390d58]">
                    <Search className="h-3.5 w-3.5" />
                    Search
                  </Button>
                </div>
                <label className="flex items-center gap-1.5 text-xs text-muted-foreground cursor-pointer">
                  <input type="checkbox" checked={missingOnly} onChange={e => setMissingOnly(e.target.checked)} />
                  Show only posts with missing images
                </label>
              </div>

              {postTypes.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                  <button
                    onClick={() => { setFilterType(''); fetchPosts(search, '') }}
                    className={`rounded-full border px-2.5 py-1 text-xs transition-all ${
                      filterType === '' ? 'border-[#390d58] bg-[#390d58] text-white' : 'border-[#390d58]/20 text-[#390d58]'
                    }`}
                  >
                    All
                  </button>
                  {postTypes.map(pt => (
                    <button
                      key={pt.slug}
                      onClick={() => { setFilterType(pt.slug); fetchPosts(search, pt.slug) }}
                      className={`rounded-full border px-2.5 py-1 text-xs transition-all ${
                        filterType === pt.slug ? 'border-[#390d58] bg-[#390d58] text-white' : 'border-[#390d58]/20 text-[#390d58]'
                      }`}
                    >
                      {pt.label}
                    </button>
                  ))}
                </div>
              )}

              <div className="rounded-xl border border-[#390d58]/10 divide-y divide-[#390d58]/10 max-h-[500px] overflow-y-auto">
                {loading ? (
                  <div className="flex items-center justify-center py-10 text-muted-foreground gap-2">
                    <Loader2 className="h-4 w-4 animate-spin" /> Loading posts...
                  </div>
                ) : visiblePosts.length === 0 ? (
                  <div className="py-10 text-center text-sm text-muted-foreground">
                    {missingOnly ? 'No posts with missing H2 images. Nice work!' : 'No posts found.'}
                  </div>
                ) : (
                  visiblePosts.map(post => {
                    const selected = selectedIds.has(post.id)
                    return (
                      <button
                        key={post.id}
                        onClick={() => handleSelectPost(post)}
                        className={`w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-[#390d58]/[0.03] transition-colors ${
                          selected ? 'bg-[#390d58]/5' : ''
                        }`}
                      >
                        {mode === 'batch' && (
                          selected
                            ? <CheckSquare className="h-4 w-4 text-[#390d58] shrink-0" />
                            : <Square className="h-4 w-4 text-muted-foreground shrink-0" />
                        )}
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium truncate">{post.title}</p>
                          <div className="flex items-center gap-2 mt-0.5">
                            <Badge variant="outline" className="text-[10px] px-1.5 py-0 border-[#390d58]/20 text-[#390d58]">
                              {post.post_type}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                              {post.h2_total} H2{post.h2_total === 1 ? '' : 's'}
                            </span>
                            {post.h2_missing_images > 0 ? (
                              <Badge variant="outline" className="text-[10px] px-1.5 py-0 bg-amber-500/10 text-amber-600 border-amber-500/20">
                                {post.h2_missing_images} missing image{post.h2_missing_images === 1 ? '' : 's'}
                              </Badge>
                            ) : (
                              <Badge variant="outline" className="text-[10px] px-1.5 py-0 bg-emerald-500/10 text-emerald-600 border-emerald-500/20">
                                Fully enriched
                              </Badge>
                            )}
                          </div>
                        </div>
                      </button>
                    )
                  })
                )}
              </div>
            </CardContent>
          </Card>

          {/* Single mode: H2 breakdown */}
          {mode === 'single' && analysis && (
            <Card className="border-[#390d58]/20 overflow-hidden">
              <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
              <CardHeader>
                <CardTitle className="text-lg text-[#390d58]">
                  Sections in "{analysis.title}"
                </CardTitle>
                <CardDescription>
                  {analysis.missing} of {analysis.total} sections will get images. Existing images will be left alone.
                </CardDescription>
              </CardHeader>
              <CardContent>
                {analyzing ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground py-4">
                    <Loader2 className="h-4 w-4 animate-spin" /> Analyzing...
                  </div>
                ) : (
                  <div className="space-y-1.5">
                    {analysis.h2s.map(h2 => (
                      <div key={h2.index} className="flex items-center gap-3 px-3 py-2 rounded-lg border border-[#390d58]/10">
                        {h2.has_image
                          ? <Image className="h-4 w-4 text-emerald-600 shrink-0" />
                          : <Image className="h-4 w-4 text-amber-500 shrink-0" />
                        }
                        <span className="text-sm flex-1 truncate">{h2.text || '(empty heading)'}</span>
                        <Badge variant="outline" className={`text-[10px] ${
                          h2.has_image
                            ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                            : 'bg-amber-500/10 text-amber-600 border-amber-500/20'
                        }`}>
                          {h2.has_image ? 'Has image' : 'Will generate'}
                        </Badge>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* Generate button */}
          {selectedIds.size > 0 && (
            <Card className="border-[#390d58]/20 overflow-hidden">
              <CardContent className="pt-6">
                <div className="flex items-center justify-between gap-4">
                  <div>
                    <p className="text-sm font-medium text-[#390d58]">
                      {totalMissingAcrossSelected} image{totalMissingAcrossSelected === 1 ? '' : 's'} will be generated
                    </p>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      Each image costs 25 credits. Total: ~{totalMissingAcrossSelected * 25} credits.
                    </p>
                  </div>
                  <Button
                    onClick={handleGenerate}
                    disabled={generating || totalMissingAcrossSelected === 0}
                    className="gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white"
                  >
                    {generating
                      ? <><Loader2 className="h-4 w-4 animate-spin" /> Queuing...</>
                      : <><Sparkles className="h-4 w-4" /> Generate images</>}
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  )
}
