import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, Search, Copy } from 'lucide-react'
import { api } from '@/lib/api'

interface PostResult {
  ID: number
  post_title: string
  post_type: string
  post_status: string
}

interface CloneItem {
  new_post_id: number
  source_post_id?: number
  title: string
  edit_url: string
}

interface CloneResult {
  count?: number
  items?: CloneItem[]
  new_post_id?: number
  title?: string
  edit_url?: string
}

interface ClonePreview {
  title: string
  post_type: string
  post_status: string
  author: string
  featured_image_id: number
  included_meta: string[]
  excluded_meta: string[]
  taxonomies: string[]
  edit_url: string
}

interface CloneSettings {
  excluded_meta_keys: string[]
}

export function ClonePostTool() {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<PostResult[]>([])
  const [selected, setSelected] = useState<PostResult[]>([])
  const [searching, setSearching] = useState(false)
  const [cloning, setCloning] = useState(false)
  const [done, setDone] = useState<CloneItem[] | null>(null)
  const [preview, setPreview] = useState<ClonePreview | null>(null)
  const [excludedMeta, setExcludedMeta] = useState('')

  useEffect(() => {
    api.get<CloneSettings>('/workshop/clone-post/settings')
      .then(result => setExcludedMeta(result.excluded_meta_keys.join('\n')))
      .catch(() => {})
  }, [])

  const handleSearch = async () => {
    if (!query.trim()) return
    setSearching(true)
    setSelected([])
    setDone(null)
    setPreview(null)
    try {
      const data = await api.get<PostResult[]>(`/workshop/post-search?q=${encodeURIComponent(query)}`)
      setResults(data)
    } finally {
      setSearching(false)
    }
  }

  const togglePost = async (post: PostResult) => {
    setSelected(current => {
      const exists = current.some(item => item.ID === post.ID)
      return exists ? current.filter(item => item.ID !== post.ID) : [...current, post]
    })

    const result = await api.get<ClonePreview>(`/workshop/clone-post/preview?post_id=${post.ID}`)
    setPreview(result)
    setResults([])
  }

  const saveSettings = async () => {
    const excluded_meta_keys = excludedMeta.split('\n').map(item => item.trim()).filter(Boolean)
    await api.post('/workshop/clone-post/settings', { excluded_meta_keys })
    if (selected[0]) {
      const result = await api.get<ClonePreview>(`/workshop/clone-post/preview?post_id=${selected[0].ID}`)
      setPreview(result)
    }
  }

  const handleClone = async () => {
    if (selected.length === 0) return
    setCloning(true)
    try {
      const result = await api.post<CloneResult>('/workshop/clone-post', { post_ids: selected.map(item => item.ID) })
      setDone(result.items ?? (result.new_post_id ? [{
        new_post_id: result.new_post_id,
        title: result.title ?? '',
        edit_url: result.edit_url ?? '',
      }] : []))
      setSelected([])
      setPreview(null)
      setResults([])
      setQuery('')
    } finally {
      setCloning(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Clone Post</CardTitle>
        <CardDescription>Preview the clone payload, then duplicate one or more posts as drafts with taxonomy copying and meta exclusions.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {done && (
          <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="font-medium text-green-700">Created {done.length} draft clone{done.length !== 1 ? 's' : ''}.</p>
            <div className="mt-2 space-y-1">
              {done.map(item => (
                <a key={item.new_post_id} href={item.edit_url} target="_blank" rel="noreferrer" className="block text-xs text-green-600 underline underline-offset-2">
                  Edit post #{item.new_post_id}
                </a>
              ))}
            </div>
          </div>
        )}

        <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
          <div className="space-y-4">
            <div className="flex gap-2">
              <input
                type="text"
                value={query}
                onChange={e => setQuery(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && handleSearch()}
                placeholder="Search by title"
                className="flex-1 rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              />
              <Button size="sm" onClick={handleSearch} disabled={searching} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                {searching ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
              </Button>
            </div>

            {results.length > 0 && (
              <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
                {results.map(result => (
                  <button key={result.ID} onClick={() => togglePost(result)} className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-[#390d58]/5">
                    <span className="text-[#390d58]">{result.post_title}</span>
                    <span className="text-[10px] text-muted-foreground">{result.post_type} - {result.post_status}</span>
                  </button>
                ))}
              </div>
            )}

            {selected.length > 0 && (
              <div className="space-y-3 rounded-xl border border-[#390d58]/10 px-4 py-3">
                <div>
                  <p className="text-sm font-medium text-[#390d58]">Selected items</p>
                  <p className="text-xs text-muted-foreground">{selected.map(item => item.post_title).join(', ')}</p>
                </div>
                {preview && (
                  <div className="grid gap-3 md:grid-cols-2">
                    <div>
                      <p className="mb-1 text-xs font-medium text-[#390d58]">Preview</p>
                      <div className="rounded bg-[#390d58]/[0.03] p-2 text-[11px] text-muted-foreground">
                        <p>Status: {preview.post_status}</p>
                        <p>Author: {preview.author || 'Unknown'}</p>
                        <p>Featured image: {preview.featured_image_id > 0 ? `#${preview.featured_image_id}` : 'None'}</p>
                      </div>
                    </div>
                    <div>
                      <p className="mb-1 text-xs font-medium text-[#390d58]">Taxonomies</p>
                      <div className="rounded bg-[#390d58]/[0.03] p-2 text-[11px] text-muted-foreground">
                        {preview.taxonomies.length > 0 ? preview.taxonomies.join(', ') : 'No taxonomies.'}
                      </div>
                    </div>
                    <div className="md:col-span-2">
                      <p className="mb-1 text-xs font-medium text-[#390d58]">Included meta</p>
                      <div className="max-h-32 overflow-auto rounded bg-[#390d58]/[0.03] p-2 text-[11px] text-muted-foreground">
                        {preview.included_meta.length > 0 ? preview.included_meta.join(', ') : 'No meta will be copied.'}
                      </div>
                    </div>
                  </div>
                )}
                <Button size="sm" onClick={handleClone} disabled={cloning} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                  {cloning ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Copy className="h-3.5 w-3.5" />}
                  Clone Selected
                </Button>
              </div>
            )}
          </div>

          <div className="rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4 space-y-3">
            <p className="text-sm font-medium text-[#390d58]">Excluded Meta Keys</p>
            <p className="text-xs text-muted-foreground">One exact meta key per line. These keys will not be copied into new clones.</p>
            <textarea
              value={excludedMeta}
              onChange={e => setExcludedMeta(e.target.value)}
              rows={10}
              className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
            />
            <Button size="sm" onClick={saveSettings} className="bg-[#390d58] hover:bg-[#4a1170] text-white">
              Save Clone Settings
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
