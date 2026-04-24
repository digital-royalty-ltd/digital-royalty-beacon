import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ArrowLeft, CheckCircle2, Image, Loader2, Search, Sparkles } from 'lucide-react'
import { api } from '@/lib/api'

interface PostItem {
  id:            number
  title:         string
  post_type:     string
  status:        string
  has_thumbnail: boolean
  thumbnail_url: string | null
}

interface PostType {
  slug:  string
  label: string
}

interface Props {
  onBack: () => void
}

const STYLE_OPTIONS = [
  { value: 'photographic', label: 'Photographic', desc: 'Realistic, high-quality photo' },
  { value: 'illustration', label: 'Illustration', desc: 'Flat vector, modern blog style' },
  { value: '3d',           label: '3D Render',    desc: 'Stylised 3D, cinematic lighting' },
  { value: 'abstract',     label: 'Abstract',     desc: 'Abstract shapes and colours' },
  { value: 'minimalist',   label: 'Minimalist',   desc: 'Clean, simple, sparse design' },
] as const

const ASPECT_OPTIONS = [
  { value: 'landscape', label: 'Landscape', desc: '16:9 — ideal for featured images' },
  { value: 'square',    label: 'Square',    desc: '1:1 — social media friendly' },
  { value: 'portrait',  label: 'Portrait',  desc: '9:16 — tall format' },
] as const

export function GenerateImageTool({ onBack }: Props) {
  const [postTypes,     setPostTypes]     = useState<PostType[]>([])
  const [posts,         setPosts]         = useState<PostItem[]>([])
  const [loading,       setLoading]       = useState(true)
  const [search,        setSearch]        = useState('')
  const [filterType,    setFilterType]    = useState('')
  const [noImageOnly,   setNoImageOnly]   = useState(true)
  const [selectedPost,  setSelectedPost]  = useState<PostItem | null>(null)
  const [styleHint,     setStyleHint]     = useState('photographic')
  const [aspectRatio,   setAspectRatio]   = useState('landscape')
  const [generating,    setGenerating]    = useState(false)
  const [queued,        setQueued]        = useState(false)
  const [error,         setError]         = useState<string | null>(null)

  const fetchPosts = (s = '', pt = '') => {
    setLoading(true)
    const params = new URLSearchParams()
    if (s) params.set('search', s)
    if (pt) params.set('post_type', pt)
    const qs = params.toString()

    api.get<{ post_types: PostType[], posts: PostItem[] }>(
      `/generate-image/posts${qs ? `?${qs}` : ''}`
    )
      .then(data => {
        setPostTypes(data.post_types)
        setPosts(data.posts)
      })
      .catch(() => setError('Could not load posts.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchPosts()
  }, [])

  const handleSearch = () => {
    fetchPosts(search, filterType)
  }

  const handleFilterType = (slug: string) => {
    setFilterType(slug)
    fetchPosts(search, slug)
  }

  const handleGenerate = async () => {
    if (!selectedPost) return
    setGenerating(true)
    setError(null)
    try {
      await api.post('/generate-image/generate', {
        post_id:      selectedPost.id,
        style_hint:   styleHint,
        aspect_ratio: aspectRatio,
      })
      setQueued(true)
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Image generation request failed.'
      setError(msg)
    } finally {
      setGenerating(false)
    }
  }

  const handleReset = () => {
    setQueued(false)
    setSelectedPost(null)
    setStyleHint('photographic')
    setAspectRatio('landscape')
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
          <h2 className="text-xl font-semibold text-[#390d58]">Image Generator</h2>
          <p className="text-sm text-muted-foreground">Generate an AI-created featured image for any content piece.</p>
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
              <p className="text-lg font-semibold text-[#390d58]">Image queued</p>
              <p className="text-sm text-muted-foreground mt-1 max-w-sm">
                Your featured image is being generated in the background. It will be
                automatically set as the featured image for
                <strong className="text-[#390d58]"> {selectedPost?.title}</strong> when ready.
              </p>
            </div>
            <div className="flex gap-3 mt-2">
              <Button onClick={handleReset} variant="outline" className="border-[#390d58]/20 text-[#390d58]">
                Generate another
              </Button>
              {selectedPost && (
                <Button
                  onClick={() => window.open(
                    `${window.BeaconData?.adminUrl ?? '/wp-admin/'}post.php?post=${selectedPost.id}&action=edit`,
                    '_blank',
                    'noopener,noreferrer'
                  )}
                  className="bg-[#390d58] hover:bg-[#4a1170] text-white"
                >
                  Edit post
                </Button>
              )}
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="border-[#390d58]/20 overflow-hidden">
          <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
          <CardHeader>
            <CardTitle className="text-lg text-[#390d58]">Generate featured image</CardTitle>
            <CardDescription>
              Pick a content piece, choose a visual style, and Beacon will create a featured image for it.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">

            {error && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
            )}

            {/* Step 1 — Pick content */}
            <div>
              <label className="text-sm font-medium text-[#390d58] mb-2 block">
                Content piece
              </label>

              {/* Search & filter bar */}
              <div className="flex gap-2 mb-3">
                <div className="relative flex-1">
                  <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                  <Input
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && handleSearch()}
                    placeholder="Search posts..."
                    className="pl-8 border-[#390d58]/20 focus:border-[#390d58] focus:ring-[#390d58]/20"
                  />
                </div>
                {postTypes.length > 1 && (
                  <select
                    value={filterType}
                    onChange={e => handleFilterType(e.target.value)}
                    className="text-sm border border-[#390d58]/20 rounded-md px-2 py-1.5 bg-background focus:outline-none focus:ring-2 focus:ring-[#390d58]/20"
                  >
                    <option value="">All types</option>
                    {postTypes.map(pt => (
                      <option key={pt.slug} value={pt.slug}>{pt.label}</option>
                    ))}
                  </select>
                )}
              </div>

              <label className="flex items-center gap-2 mb-3 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={noImageOnly}
                  onChange={e => setNoImageOnly(e.target.checked)}
                  className="rounded border-[#390d58]/30 text-[#390d58] focus:ring-[#390d58]/30"
                />
                <span className="text-sm text-muted-foreground">Only show content without a featured image</span>
              </label>

              {loading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                  <Loader2 className="h-4 w-4 animate-spin" /> Loading posts…
                </div>
              ) : posts.length === 0 ? (
                <p className="text-sm text-muted-foreground">No posts found.</p>
              ) : (() => {
                const filtered = noImageOnly ? posts.filter(p => !p.has_thumbnail) : posts
                return filtered.length === 0 ? (
                  <p className="text-sm text-muted-foreground">All content already has a featured image. Uncheck the filter above to see everything.</p>
                ) : (
                <div className="max-h-64 overflow-y-auto rounded-lg border border-[#390d58]/15 divide-y divide-[#390d58]/10">
                  {filtered.map(post => (
                    <button
                      key={post.id}
                      onClick={() => setSelectedPost(post)}
                      className={`w-full text-left px-4 py-3 flex items-center gap-3 transition-all ${
                        selectedPost?.id === post.id
                          ? 'bg-[#390d58]/5 border-l-2 border-l-[#390d58]'
                          : 'hover:bg-[#390d58]/[0.02]'
                      }`}
                    >
                      {post.thumbnail_url ? (
                        <img src={post.thumbnail_url} alt="" className="h-10 w-10 rounded object-cover flex-shrink-0" />
                      ) : (
                        <div className="h-10 w-10 rounded bg-[#390d58]/10 flex items-center justify-center flex-shrink-0">
                          <Image className="h-4 w-4 text-[#390d58]/40" />
                        </div>
                      )}
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-[#390d58] truncate">{post.title}</p>
                        <p className="text-xs text-muted-foreground">
                          {post.post_type} &middot; {post.status}
                          {post.has_thumbnail && <span className="text-amber-600 ml-1">&middot; has image</span>}
                        </p>
                      </div>
                    </button>
                  ))}
                </div>
                )
              })()}
            </div>

            {/* Step 2 — Style */}
            {selectedPost && (
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">
                  Visual style
                </label>
                <div className="grid gap-2 sm:grid-cols-2">
                  {STYLE_OPTIONS.map(opt => (
                    <button
                      key={opt.value}
                      onClick={() => setStyleHint(opt.value)}
                      className={`text-left rounded-xl border px-4 py-3 transition-all ${
                        styleHint === opt.value
                          ? 'border-[#390d58] bg-[#390d58]/5 ring-1 ring-[#390d58]/20'
                          : 'border-[#390d58]/15 hover:border-[#390d58]/30 hover:bg-[#390d58]/[0.02]'
                      }`}
                    >
                      <p className="text-sm font-medium text-[#390d58]">{opt.label}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{opt.desc}</p>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Step 3 — Aspect ratio */}
            {selectedPost && (
              <div>
                <label className="text-sm font-medium text-[#390d58] mb-2 block">
                  Aspect ratio
                </label>
                <div className="grid gap-2 sm:grid-cols-3">
                  {ASPECT_OPTIONS.map(opt => (
                    <button
                      key={opt.value}
                      onClick={() => setAspectRatio(opt.value)}
                      className={`text-left rounded-xl border px-4 py-3 transition-all ${
                        aspectRatio === opt.value
                          ? 'border-[#390d58] bg-[#390d58]/5 ring-1 ring-[#390d58]/20'
                          : 'border-[#390d58]/15 hover:border-[#390d58]/30 hover:bg-[#390d58]/[0.02]'
                      }`}
                    >
                      <p className="text-sm font-medium text-[#390d58]">{opt.label}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{opt.desc}</p>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {selectedPost && (
              <Button
                onClick={handleGenerate}
                disabled={generating}
                className="w-full gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white"
              >
                {generating
                  ? <><Loader2 className="h-4 w-4 animate-spin" /> Queuing…</>
                  : <><Sparkles className="h-4 w-4" /> Generate image</>}
              </Button>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
