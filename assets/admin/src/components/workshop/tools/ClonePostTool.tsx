import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, Search, Copy } from 'lucide-react'
import { api } from '@/lib/api'

interface PostResult {
  ID:          number
  post_title:  string
  post_type:   string
  post_status: string
}

interface CloneResult {
  new_post_id: number
  title:       string
  edit_url:    string
}

export function ClonePostTool() {
  const [query,    setQuery]    = useState('')
  const [results,  setResults]  = useState<PostResult[]>([])
  const [selected, setSelected] = useState<PostResult | null>(null)
  const [searching, setSearching] = useState(false)
  const [cloning,  setCloning]  = useState(false)
  const [done,     setDone]     = useState<CloneResult | null>(null)

  const handleSearch = async () => {
    if (!query.trim()) return
    setSearching(true)
    setSelected(null)
    setDone(null)
    try {
      const data = await api.get<PostResult[]>(`/workshop/post-search?q=${encodeURIComponent(query)}`)
      setResults(data)
    } finally {
      setSearching(false)
    }
  }

  const handleClone = async () => {
    if (!selected) return
    setCloning(true)
    try {
      const result = await api.post<CloneResult>('/workshop/clone-post', { post_id: selected.ID })
      setDone(result)
      setSelected(null)
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
        <CardDescription>Duplicate any post or page as a draft, including all meta.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">

        {done && (
          <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="text-green-700 font-medium">"{done.title}" created as a draft.</p>
            <a href={done.edit_url} target="_blank" rel="noreferrer"
              className="text-green-600 underline underline-offset-2 text-xs mt-1 inline-block">
              Edit post #{done.new_post_id}
            </a>
          </div>
        )}

        <div className="flex gap-2">
          <input
            type="text"
            value={query}
            onChange={e => setQuery(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && handleSearch()}
            placeholder="Search by title…"
            className="flex-1 text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
          />
          <Button size="sm" onClick={handleSearch} disabled={searching}
            className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
            {searching ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
          </Button>
        </div>

        {results.length > 0 && (
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            {results.map(r => (
              <button key={r.ID} onClick={() => { setSelected(r); setResults([]) }}
                className="w-full flex items-center justify-between px-3 py-2 text-sm text-left hover:bg-[#390d58]/5 transition-colors">
                <span className="text-[#390d58]">{r.post_title}</span>
                <span className="text-[10px] text-muted-foreground">{r.post_type} · {r.post_status}</span>
              </button>
            ))}
          </div>
        )}

        {selected && (
          <div className="flex items-center justify-between rounded-xl border border-[#390d58]/10 px-4 py-3">
            <div>
              <p className="text-sm font-medium text-[#390d58]">{selected.post_title}</p>
              <p className="text-xs text-muted-foreground">{selected.post_type} · #{selected.ID}</p>
            </div>
            <Button size="sm" onClick={handleClone} disabled={cloning}
              className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
              {cloning ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Copy className="h-3.5 w-3.5" />}
              Clone
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
