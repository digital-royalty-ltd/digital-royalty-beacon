import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Loader2, Search, Play, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'

interface PreviewItem {
  id:          number
  title:       string
  post_type:   string
  match_count: number
  before:      string
  after:       string
}

interface PreviewResult {
  total:    number
  find:     string
  replace:  string
  scope:    string
  previews: PreviewItem[]
}

interface ExecuteResult {
  updated: number
  find:    string
  replace: string
  scope:   string
}

export function FindReplaceTool() {
  const [find,      setFind]      = useState('')
  const [replace,   setReplace]   = useState('')
  const [scope,     setScope]     = useState('post_content')
  const [preview,   setPreview]   = useState<PreviewResult | null>(null)
  const [loading,   setLoading]   = useState(false)
  const [executing, setExecuting] = useState(false)
  const [done,      setDone]      = useState<ExecuteResult | null>(null)

  const handlePreview = async () => {
    if (!find.trim()) return
    setLoading(true)
    setPreview(null)
    setDone(null)
    try {
      const data = await api.post<PreviewResult>('/workshop/find-replace/preview', { find, replace, scope })
      setPreview(data)
    } finally {
      setLoading(false)
    }
  }

  const handleExecute = async () => {
    if (!preview || !confirm(`Replace all occurrences in ${preview.total} posts? This cannot be undone.`)) return
    setExecuting(true)
    try {
      const data = await api.post<ExecuteResult>('/workshop/find-replace/execute', { find, replace, scope })
      setDone(data)
      setPreview(null)
    } finally {
      setExecuting(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Find &amp; Replace</CardTitle>
        <CardDescription>Replace text across post content, titles, or excerpts. Always preview first.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">

        {done && (
          <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="text-green-700 font-medium">Replaced in {done.updated} post{done.updated !== 1 ? 's' : ''}.</p>
          </div>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className="text-xs text-muted-foreground mb-1 block">Find</label>
            <input type="text" value={find} onChange={e => setFind(e.target.value)}
              placeholder="Text to find…"
              className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
          </div>
          <div>
            <label className="text-xs text-muted-foreground mb-1 block">Replace with</label>
            <input type="text" value={replace} onChange={e => setReplace(e.target.value)}
              placeholder="Replacement text…"
              className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
          </div>
        </div>

        <div>
          <label className="text-xs text-muted-foreground mb-1 block">Scope</label>
          <select value={scope} onChange={e => setScope(e.target.value)}
            className="text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30">
            <option value="post_content">Post Content</option>
            <option value="post_title">Post Title</option>
            <option value="post_excerpt">Post Excerpt</option>
          </select>
        </div>

        <div className="flex gap-2">
          <Button size="sm" onClick={handlePreview} disabled={loading || !find.trim()}
            className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
            {loading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
            Preview Changes
          </Button>

          {preview && preview.total > 0 && (
            <Button size="sm" variant="outline" onClick={handleExecute} disabled={executing}
              className="gap-1 text-red-600 hover:bg-red-50 hover:border-red-300 border-red-200">
              {executing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Play className="h-3.5 w-3.5" />}
              Apply to {preview.total} post{preview.total !== 1 ? 's' : ''}
            </Button>
          )}
        </div>

        {preview && (
          <div className="space-y-2">
            {preview.total === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-4">No matches found.</p>
            ) : (
              <>
                <div className="flex items-center gap-2 text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                  <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                  Previewing first 50 matches. Apply will replace all occurrences in the database.
                </div>
                <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
                  {preview.previews.map((item, idx) => (
                    <div key={item.id}
                      className={`px-4 py-3 border-b border-[#390d58]/5 last:border-0 ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-medium text-[#390d58]">{item.title}</span>
                        <Badge variant="outline" className="text-[9px]">
                          {item.match_count} match{item.match_count !== 1 ? 'es' : ''}
                        </Badge>
                      </div>
                      <div className="grid grid-cols-2 gap-2 text-xs font-mono">
                        <div className="bg-red-50 rounded p-2 text-red-700 line-clamp-2">{item.before}</div>
                        <div className="bg-green-50 rounded p-2 text-green-700 line-clamp-2">{item.after}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
