import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Loader2, Search, Play, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'

interface PreviewItem {
  id: number
  title: string
  post_type: string
  match_count: number
  before: string
  after: string
  table: string
  identifier: string
  serialised: boolean
}

interface PreviewResult {
  total: number
  find: string
  replace: string
  scope: string
  tables: string[]
  regex: boolean
  case_sensitive: boolean
  serialised_safe: boolean
  truncated: boolean
  progress_mode: string
  previews: PreviewItem[]
}

interface ExecuteResult {
  updated: number
  find: string
  replace: string
  scope: string
  tables: string[]
  regex: boolean
  case_sensitive: boolean
  serialised_safe: boolean
}

const tableOptions = [
  { value: 'posts', label: 'Posts table' },
  { value: 'postmeta', label: 'Post meta' },
  { value: 'options', label: 'Options' },
]

export function FindReplaceTool() {
  const [find, setFind] = useState('')
  const [replace, setReplace] = useState('')
  const [scope, setScope] = useState('post_content')
  const [tables, setTables] = useState<string[]>(['posts'])
  const [regex, setRegex] = useState(false)
  const [caseSensitive, setCaseSensitive] = useState(false)
  const [preview, setPreview] = useState<PreviewResult | null>(null)
  const [loading, setLoading] = useState(false)
  const [executing, setExecuting] = useState(false)
  const [done, setDone] = useState<ExecuteResult | null>(null)
  const [progress, setProgress] = useState<{ current: number; total: number } | null>(null)

  const handlePreview = async () => {
    if (!find.trim()) return
    setLoading(true)
    setPreview(null)
    setDone(null)
    try {
      const data = await api.post<PreviewResult>('/workshop/find-replace/preview', {
        find,
        replace,
        scope,
        tables,
        regex,
        case_sensitive: caseSensitive,
      })
      setPreview(data)
    } finally {
      setLoading(false)
    }
  }

  const handleExecute = async () => {
    if (!preview || !confirm(`Replace all occurrences in ${preview.total} rows? This cannot be undone.`)) return
    setExecuting(true)
    setProgress({ current: 0, total: preview.total })
    try {
      let offset = 0
      const batchSize = 100
      let totalUpdated = 0
      let lastResult: ExecuteResult | null = null
      while (offset < preview.total) {
        const data = await api.post<ExecuteResult & { batch_updated?: number }>('/workshop/find-replace/execute', {
          find,
          replace,
          scope,
          tables,
          regex,
          case_sensitive: caseSensitive,
          offset,
          batch_size: batchSize,
        })
        totalUpdated += data.batch_updated ?? data.updated
        offset += batchSize
        setProgress({ current: Math.min(offset, preview.total), total: preview.total })
        lastResult = { ...data, updated: totalUpdated }
      }
      setDone(lastResult)
      setPreview(null)
    } finally {
      setExecuting(false)
      setProgress(null)
    }
  }

  const toggleTable = (table: string) => {
    setTables(current => current.includes(table)
      ? current.filter(item => item !== table)
      : [...current, table])
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Find &amp; Replace</CardTitle>
        <CardDescription>Replace text across post fields, post meta, or options. Preview first, with serialised values handled safely.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {done && (
          <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="text-green-700 font-medium">Replaced in {done.updated} row{done.updated !== 1 ? 's' : ''}.</p>
          </div>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className="mb-1 block text-xs text-muted-foreground">Find</label>
            <input type="text" value={find} onChange={e => setFind(e.target.value)} placeholder="Text to find..." className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
          </div>
          <div>
            <label className="mb-1 block text-xs text-muted-foreground">Replace with</label>
            <input type="text" value={replace} onChange={e => setReplace(e.target.value)} placeholder="Replacement text..." className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="mb-1 block text-xs text-muted-foreground">Post field scope</label>
            <select value={scope} onChange={e => setScope(e.target.value)} className="text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30">
              <option value="post_content">Post Content</option>
              <option value="post_title">Post Title</option>
              <option value="post_excerpt">Post Excerpt</option>
            </select>
          </div>
          <div>
            <label className="mb-1 block text-xs text-muted-foreground">Target tables</label>
            <div className="flex flex-wrap gap-3 rounded-xl border border-[#390d58]/10 p-3">
              {tableOptions.map(option => (
                <label key={option.value} className="flex items-center gap-2 text-sm text-muted-foreground">
                  <input type="checkbox" checked={tables.includes(option.value)} onChange={() => toggleTable(option.value)} />
                  {option.label}
                </label>
              ))}
            </div>
          </div>
        </div>

        <div className="flex flex-wrap gap-4">
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            <input type="checkbox" checked={regex} onChange={e => setRegex(e.target.checked)} />
            Regex
          </label>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            <input type="checkbox" checked={caseSensitive} onChange={e => setCaseSensitive(e.target.checked)} />
            Case sensitive
          </label>
        </div>

        <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
          Serialised values are traversed and re-saved safely for `postmeta` and `options`. Large jobs run in batches of 100 rows with progress tracking.
        </div>

        {progress && (
          <div className="space-y-1">
            <div className="flex justify-between text-xs text-muted-foreground">
              <span>Processing rows...</span>
              <span>{progress.current} / {progress.total}</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-[#390d58]/10">
              <div className="h-full rounded-full bg-[#390d58] transition-all" style={{ width: `${Math.round((progress.current / progress.total) * 100)}%` }} />
            </div>
          </div>
        )}

        <div className="flex gap-2">
          <Button size="sm" onClick={handlePreview} disabled={loading || !find.trim() || tables.length === 0} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
            {loading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
            Preview Changes
          </Button>

          {preview && preview.total > 0 && (
            <Button size="sm" variant="outline" onClick={handleExecute} disabled={executing} className="gap-1 text-red-600 hover:bg-red-50 hover:border-red-300 border-red-200">
              {executing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Play className="h-3.5 w-3.5" />}
              Apply to {preview.total} row{preview.total !== 1 ? 's' : ''}
            </Button>
          )}
        </div>

        {preview && (
          <div className="space-y-2">
            {preview.total === 0 ? (
              <p className="py-4 text-center text-sm text-muted-foreground">No matches found.</p>
            ) : (
              <>
                <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-600">
                  <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                  Previewing first 50 matching rows. Apply will replace all rows returned by the selected tables.
                </div>
                <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
                  {preview.previews.map((item, idx) => (
                    <div key={`${item.table}-${item.id}-${item.identifier}`} className={`border-b border-[#390d58]/5 px-4 py-3 last:border-0 ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                      <div className="mb-2 flex items-center justify-between gap-2">
                        <span className="text-xs font-medium text-[#390d58]">{item.title || item.identifier}</span>
                        <div className="flex gap-2">
                          <Badge variant="outline" className="text-[9px]">{item.table}</Badge>
                          {item.serialised && <Badge variant="outline" className="text-[9px]">serialised</Badge>}
                          <Badge variant="outline" className="text-[9px]">{item.match_count} match{item.match_count !== 1 ? 'es' : ''}</Badge>
                        </div>
                      </div>
                      <p className="mb-2 text-[10px] text-muted-foreground">{item.identifier}</p>
                      <div className="grid grid-cols-2 gap-2 text-xs font-mono">
                        <div className="line-clamp-3 rounded bg-red-50 p-2 text-red-700">{item.before}</div>
                        <div className="line-clamp-3 rounded bg-green-50 p-2 text-green-700">{item.after}</div>
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
