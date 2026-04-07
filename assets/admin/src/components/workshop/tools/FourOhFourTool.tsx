import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Loader2, Trash2, RefreshCw, ArrowRight, Download } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface LogRow {
  id: string
  path: string
  referrer: string | null
  user_agent: string | null
  ip_hash: string | null
  hit_count: string
  first_seen_at: string
  last_seen_at: string
}

interface LogsResponse {
  rows: LogRow[]
  settings: {
    exclusions: string[]
  }
}

export function FourOhFourTool() {
  const [rows, setRows] = useState<LogRow[]>([])
  const [loading, setLoading] = useState(true)
  const [clearing, setClearing] = useState(false)
  const [search, setSearch] = useState('')
  const [sort, setSort] = useState('hits')
  const [exclusions, setExclusions] = useState('')
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)
  const [redirectTargets, setRedirectTargets] = useState<Record<string, string>>({})

  const fetchRows = () => {
    setLoading(true)
    api.get<LogsResponse>(`/workshop/404-logs?search=${encodeURIComponent(search)}&sort=${encodeURIComponent(sort)}`)
      .then(result => {
        setRows(result.rows)
        setExclusions(result.settings.exclusions.join('\n'))
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchRows() }, [sort])

  const handleClear = async () => {
    if (!confirm('Clear all 404 log entries?')) return
    setClearing(true)
    try {
      await api.delete('/workshop/404-logs')
      setRows([])
    } finally {
      setClearing(false)
    }
  }

  const handlePrune = async () => {
    setClearing(true)
    try {
      const result = await api.delete<{ deleted: number }>('/workshop/404-logs?older_than_days=30')
      setMessage({ ok: true, text: `Deleted ${result.deleted} entries older than 30 days.` })
      fetchRows()
    } finally {
      setClearing(false)
    }
  }

  const saveExclusions = async () => {
    const parsed = exclusions.split('\n').map(item => item.trim()).filter(Boolean)
    try {
      await api.post('/workshop/404-logs/settings', { exclusions: parsed })
      setMessage({ ok: true, text: '404 exclusions saved.' })
    } catch (error) {
      setMessage({ ok: false, text: error instanceof ApiError ? error.message : 'Failed to save exclusions.' })
    }
  }

  const createRedirect = async (path: string) => {
    const targetUrl = redirectTargets[path]?.trim()
    if (!targetUrl) return

    try {
      await api.post('/workshop/404-logs/redirect', { path, target_url: targetUrl, redirect_type: 301 })
      setMessage({ ok: true, text: `Redirect created for ${path}.` })
    } catch (error) {
      setMessage({ ok: false, text: error instanceof ApiError ? error.message : 'Failed to create redirect.' })
    }
  }

  const deleteRow = async (id: string) => {
    await api.delete(`/workshop/404-logs/${id}`)
    fetchRows()
  }

  const exportRows = async () => {
    const result = await api.get<{ rows: LogRow[]; exported_at: string }>(`/workshop/404-logs/export?search=${encodeURIComponent(search)}&sort=${encodeURIComponent(sort)}`)
    const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `beacon-404-log-${result.exported_at.replace(/[: ]/g, '-')}.json`
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle className="text-lg text-[#390d58]">404 Monitor</CardTitle>
            <CardDescription>Track 404 traffic with search, sorting, exclusions, direct redirect creation, export, and per-row cleanup.</CardDescription>
          </div>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" onClick={exportRows} className="gap-1">
              <Download className="h-3.5 w-3.5" />
              Export
            </Button>
            <Button size="sm" variant="outline" onClick={fetchRows} disabled={loading} className="gap-1">
              <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
            </Button>
            <Button size="sm" variant="outline" onClick={handlePrune} disabled={clearing} className="gap-1">
              {clearing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" />}
              Prune 30d
            </Button>
            <Button size="sm" variant="outline" onClick={handleClear} disabled={clearing || rows.length === 0} className="gap-1 text-red-600 hover:border-red-300 hover:bg-red-50">
              {clearing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" />}
              Clear
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {message && (
          <div className={`rounded-lg border px-3 py-2 text-sm ${message.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
            {message.text}
          </div>
        )}

        <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
          <div className="space-y-3">
            <div className="flex gap-2">
              <input type="text" value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && fetchRows()} placeholder="Search path or referrer" className="flex-1 rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
              <select value={sort} onChange={e => setSort(e.target.value)} className="rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm">
                <option value="hits">Most Hits</option>
                <option value="recent">Most Recent</option>
                <option value="path">Path</option>
                <option value="referrer">Referrer</option>
              </select>
              <Button size="sm" variant="outline" onClick={fetchRows}>Search</Button>
            </div>

            {loading ? (
              <div className="flex justify-center py-8"><Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /></div>
            ) : rows.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">No 404 errors recorded yet.</p>
            ) : (
              <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                      <TableHead className="font-semibold text-[#390d58]">Path</TableHead>
                      <TableHead className="font-semibold text-[#390d58]">Referrer</TableHead>
                      <TableHead className="w-16 text-right font-semibold text-[#390d58]">Hits</TableHead>
                      <TableHead className="w-36 font-semibold text-[#390d58]">Last Seen</TableHead>
                      <TableHead className="w-20" />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {rows.map((row, idx) => (
                      <TableRow key={row.id} className={`align-top text-xs ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                        <TableCell className="space-y-2">
                          <div className="font-mono text-[#390d58]">{row.path}</div>
                          <div className="text-[11px] text-muted-foreground">{row.user_agent || 'No user agent recorded'}</div>
                          <div className="flex gap-2">
                            <input type="text" value={redirectTargets[row.path] ?? ''} onChange={e => setRedirectTargets(prev => ({ ...prev, [row.path]: e.target.value }))} placeholder="/new-target" className="min-w-[180px] rounded border border-[#390d58]/20 px-2 py-1 text-[11px]" />
                            <Button size="sm" variant="outline" onClick={() => createRedirect(row.path)} className="h-7 gap-1 text-[11px]">
                              <ArrowRight className="h-3 w-3" /> Redirect
                            </Button>
                          </div>
                        </TableCell>
                        <TableCell className="max-w-[220px] truncate text-muted-foreground">{row.referrer || 'Direct / unknown'}</TableCell>
                        <TableCell className="text-right">
                          <Badge variant="outline" className={parseInt(row.hit_count) > 10 ? 'border-red-200 bg-red-50 text-red-600' : 'border-[#390d58]/20 bg-[#390d58]/5 text-[#390d58]'}>
                            {row.hit_count}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{row.last_seen_at}</TableCell>
                        <TableCell>
                          <Button size="sm" variant="ghost" onClick={() => deleteRow(row.id)} className="text-red-500 hover:bg-red-50 hover:text-red-700">
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>

          <div className="rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4 space-y-3">
            <p className="text-sm font-medium text-[#390d58]">Exclusions</p>
            <p className="text-xs text-muted-foreground">One substring per line. Matching paths will not be logged.</p>
            <textarea value={exclusions} onChange={e => setExclusions(e.target.value)} rows={10} className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" placeholder={'/favicon.ico\n/wp-json/'} />
            <Button size="sm" onClick={saveExclusions} className="bg-[#390d58] text-white hover:bg-[#4a1170]">
              Save Exclusions
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
