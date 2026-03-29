import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, Search, ArrowRight } from 'lucide-react'
import { api } from '@/lib/api'

interface PostResult {
  ID:          number
  post_title:  string
  post_type:   string
  post_status: string
}

interface SwitchResult {
  post_id:   number
  post_type: string
  edit_url:  string
}

export function PostTypeSwitcherTool() {
  const [query,     setQuery]     = useState('')
  const [results,   setResults]   = useState<PostResult[]>([])
  const [searching, setSearching] = useState(false)
  const [selected,  setSelected]  = useState<PostResult | null>(null)
  const [newType,   setNewType]   = useState('')
  const [switching, setSwitching] = useState(false)
  const [done,      setDone]      = useState<SwitchResult | null>(null)

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

  const handleSwitch = async () => {
    if (!selected || !newType.trim()) return
    setSwitching(true)
    try {
      const result = await api.post<SwitchResult>('/workshop/post-type-switch', {
        post_id: selected.ID, post_type: newType.trim(),
      })
      setDone(result)
      setResults([])
      setSelected(null)
    } finally {
      setSwitching(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Post Type Switcher</CardTitle>
        <CardDescription>Change the post type of any post or page.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">

        {done && (
          <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="text-green-700 font-medium">Post type changed successfully.</p>
            <a href={done.edit_url} target="_blank" rel="noreferrer"
              className="text-green-600 underline underline-offset-2 text-xs mt-1 inline-block">
              Edit post #{done.post_id}
            </a>
          </div>
        )}

        {/* Step 1 — search */}
        <div>
          <p className="text-sm font-medium text-[#390d58] mb-2">Step 1: Find a post</p>
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
            <div className="mt-2 rounded-xl border border-[#390d58]/10 overflow-hidden">
              {results.map(r => (
                <button key={r.ID} onClick={() => { setSelected(r); setResults([]) }}
                  className={`w-full flex items-center justify-between px-3 py-2 text-sm text-left hover:bg-[#390d58]/5 transition-colors ${selected?.ID === r.ID ? 'bg-[#390d58]/10' : ''}`}>
                  <span className="text-[#390d58]">{r.post_title}</span>
                  <span className="text-[10px] text-muted-foreground">{r.post_type} · {r.post_status}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Step 2 — choose new type */}
        {selected && (
          <div>
            <p className="text-sm font-medium text-[#390d58] mb-2">
              Step 2: Switch "{selected.post_title}"
              <span className="font-normal text-muted-foreground ml-2">({selected.post_type})</span>
            </p>
            <div className="flex gap-2 items-center">
              <span className="text-xs text-muted-foreground">{selected.post_type}</span>
              <ArrowRight className="h-3.5 w-3.5 text-muted-foreground" />
              <input
                type="text"
                value={newType}
                onChange={e => setNewType(e.target.value)}
                placeholder="new_post_type"
                className="flex-1 text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              />
              <Button size="sm" onClick={handleSwitch} disabled={switching || !newType.trim()}
                className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                {switching ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
                Switch
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
