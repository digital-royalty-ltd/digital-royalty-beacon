import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, Search, ArrowRight, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'

interface PostResult {
  ID: number
  post_title: string
  post_type: string
  post_status: string
}

interface PostTypeOption {
  slug: string
  label: string
}

interface SwitchItem {
  post_id: number
  post_type: string
  edit_url: string
  warnings: string[]
  ok?: boolean
}

interface SwitchResult {
  ok: boolean
  updated: number
  items: SwitchItem[]
}

export function PostTypeSwitcherTool() {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<PostResult[]>([])
  const [searching, setSearching] = useState(false)
  const [selected, setSelected] = useState<PostResult[]>([])
  const [postTypes, setPostTypes] = useState<PostTypeOption[]>([])
  const [newType, setNewType] = useState('')
  const [switching, setSwitching] = useState(false)
  const [done, setDone] = useState<SwitchResult | null>(null)

  useEffect(() => {
    api.get<PostTypeOption[]>('/workshop/post-types').then(setPostTypes).catch(() => {})
  }, [])

  const handleSearch = async () => {
    if (!query.trim()) return
    setSearching(true)
    setSelected([])
    setDone(null)
    try {
      const data = await api.get<PostResult[]>(`/workshop/post-search?q=${encodeURIComponent(query)}`)
      setResults(data)
    } finally {
      setSearching(false)
    }
  }

  const toggleSelected = (post: PostResult) => {
    setSelected(current => current.some(item => item.ID === post.ID)
      ? current.filter(item => item.ID !== post.ID)
      : [...current, post])
  }

  const handleSwitch = async () => {
    if (selected.length === 0 || !newType.trim()) return
    setSwitching(true)
    try {
      const result = await api.post<SwitchItem | SwitchResult>('/workshop/post-type-switch', {
        post_ids: selected.map(item => item.ID),
        post_type: newType.trim(),
      })
      setDone('items' in result ? result : { ok: true, updated: 1, items: [result] })
      setResults([])
      setSelected([])
    } finally {
      setSwitching(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Post Type Switcher</CardTitle>
        <CardDescription>Search for one or more posts, pick a destination type, and review compatibility warnings before switching.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {done && (
          <div className="space-y-2 rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="font-medium text-green-700">Post type changed on {done.updated} item{done.updated !== 1 ? 's' : ''}.</p>
            {done.items.map(item => (
              <div key={item.post_id} className="rounded-lg border border-[#390d58]/10 bg-white px-3 py-2">
                {item.warnings.length > 0 && (
                  <div className="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-700">
                    {item.warnings.map(warning => <p key={warning}>{warning}</p>)}
                  </div>
                )}
                <a href={item.edit_url} target="_blank" rel="noreferrer" className="inline-block text-xs text-green-600 underline underline-offset-2">
                  Edit post #{item.post_id}
                </a>
              </div>
            ))}
          </div>
        )}

        <div>
          <p className="mb-2 text-sm font-medium text-[#390d58]">Step 1: Find posts</p>
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
            <div className="mt-2 rounded-xl border border-[#390d58]/10 overflow-hidden">
              {results.map(result => (
                <button
                  key={result.ID}
                  onClick={() => toggleSelected(result)}
                  className={`flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-[#390d58]/5 ${selected.some(item => item.ID === result.ID) ? 'bg-[#390d58]/10' : ''}`}
                >
                  <span className="text-[#390d58]">{result.post_title}</span>
                  <span className="text-[10px] text-muted-foreground">{result.post_type} - {result.post_status}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        {selected.length > 0 && (
          <div className="space-y-3">
            <p className="text-sm font-medium text-[#390d58]">Step 2: Switch {selected.length} selected post{selected.length !== 1 ? 's' : ''}</p>
            <div className="rounded-lg border border-[#390d58]/10 bg-[#390d58]/[0.03] px-3 py-2 text-xs text-muted-foreground">
              {selected.map(item => `${item.post_title} (${item.post_type})`).join(', ')}
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <ArrowRight className="h-3.5 w-3.5 text-muted-foreground" />
              <select value={newType} onChange={e => setNewType(e.target.value)} className="min-w-[220px] rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm">
                <option value="">Choose destination type</option>
                {postTypes.map(type => (
                  <option key={type.slug} value={type.slug}>{type.label} ({type.slug})</option>
                ))}
              </select>
              <Button size="sm" onClick={handleSwitch} disabled={switching || !newType.trim()} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                {switching ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
                Switch
              </Button>
            </div>
            <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
              <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
              Beacon checks taxonomy compatibility for each selected post and warns when the destination type does not support inherited taxonomies.
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
