import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Loader2, Trash2, Clock, Search } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface PostResult {
  ID: number
  post_title: string
  post_type: string
  post_status: string
}

interface ExpiryRow {
  ID: string
  post_title: string
  post_status: string
  post_type: string
  expire_at: string
  expire_action?: string
}

interface ExpiryResponse {
  rows: ExpiryRow[]
  settings: {
    notify_email: string
  }
}

export function PostExpiryTool() {
  const [rows, setRows] = useState<ExpiryRow[]>([])
  const [loading, setLoading] = useState(true)
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<PostResult[]>([])
  const [selected, setSelected] = useState<PostResult[]>([])
  const [expireAt, setExpireAt] = useState('')
  const [expireAction, setExpireAction] = useState('draft')
  const [notifyEmail, setNotifyEmail] = useState('')
  const [saving, setSaving] = useState(false)
  const [searching, setSearching] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)
  const [filterPostType, setFilterPostType] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [selectedRows, setSelectedRows] = useState<string[]>([])

  const fetchRows = (postType = filterPostType, from = dateFrom, to = dateTo) => {
    setLoading(true)
    const params = new URLSearchParams()
    if (postType) params.set('post_type', postType)
    if (from) params.set('date_from', from)
    if (to) params.set('date_to', to)
    api.get<ExpiryResponse>(`/workshop/post-expiry${params.toString() ? `?${params.toString()}` : ''}`)
      .then(result => {
        setRows(result.rows)
        setNotifyEmail(result.settings.notify_email)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchRows('', '', '') }, [])

  const handleSearch = async () => {
    if (!query.trim()) return
    setSearching(true)
    try {
      const data = await api.get<PostResult[]>(`/workshop/post-search?q=${encodeURIComponent(query)}`)
      setResults(data)
    } finally {
      setSearching(false)
    }
  }

  const handleSet = async () => {
    if (selected.length === 0 || !expireAt) return
    setSaving(true)
    setMsg(null)
    try {
      await api.post('/workshop/post-expiry', {
        post_ids: selected.map(item => item.ID),
        expire_at: expireAt,
        expire_action: expireAction,
        notify_email: notifyEmail,
      })
      setMsg({ ok: true, text: 'Expiry saved.' })
      setSelected([])
      setResults([])
      setQuery('')
      setExpireAt('')
      fetchRows()
    } catch (error) {
      setMsg({ ok: false, text: error instanceof ApiError ? error.message : 'Failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleRemove = async (id: string) => {
    await api.delete(`/workshop/post-expiry/${id}`)
    fetchRows()
  }

  const handleBulkRemove = async () => {
    for (const id of selectedRows) {
      await api.delete(`/workshop/post-expiry/${id}`)
    }
    setSelectedRows([])
    fetchRows()
  }

  return (
    <div className="space-y-4">
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <CardTitle className="text-lg text-[#390d58]">Set Post Expiry</CardTitle>
          <CardDescription>Search for one or more posts, choose an expiry time, and decide whether they should become draft, private, or trashed.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
          <div className="flex gap-2">
            <Input value={query} onChange={e => setQuery(e.target.value)} onKeyDown={e => e.key === 'Enter' && handleSearch()} placeholder="Search posts or pages" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            <Button onClick={handleSearch} disabled={searching} variant="outline" className="gap-1">
              {searching ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
              Search
            </Button>
          </div>

          {results.length > 0 && (
            <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
              {results.map(result => (
                <button key={result.ID} onClick={() => setSelected(current => current.some(item => item.ID === result.ID) ? current.filter(item => item.ID !== result.ID) : [...current, result])} className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-[#390d58]/5">
                  <span className="text-[#390d58]">{result.post_title}</span>
                  <span className="text-[10px] text-muted-foreground">{result.post_type} - {result.post_status}</span>
                </button>
              ))}
            </div>
          )}

          {selected.length > 0 && (
            <div className="space-y-3 rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4">
              <div>
                <p className="text-sm font-medium text-[#390d58]">Selected posts</p>
                <p className="text-xs text-muted-foreground">{selected.map(item => item.post_title).join(', ')}</p>
              </div>
              <div className="grid gap-3 md:grid-cols-3">
                <Input type="datetime-local" value={expireAt} onChange={e => setExpireAt(e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
                <select value={expireAction} onChange={e => setExpireAction(e.target.value)} className="rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm">
                  <option value="draft">Move to draft</option>
                  <option value="private">Make private</option>
                  <option value="trash">Move to trash</option>
                </select>
                <Input type="email" value={notifyEmail} onChange={e => setNotifyEmail(e.target.value)} placeholder="Notify email (optional)" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              </div>
              <p className="text-xs text-muted-foreground">Beacon also adds a side-panel expiry box directly in the classic editor for supported post types.</p>
              <Button onClick={handleSet} disabled={saving || !expireAt} className="gap-2 bg-[#390d58] text-white hover:bg-[#4a1170]">
                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Clock className="h-4 w-4" />}
                Save Expiry
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex flex-col gap-3">
            <div>
              <CardTitle className="text-lg text-[#390d58]">Scheduled Expirations</CardTitle>
              <CardDescription>Filter upcoming expirations by post type and date, then manage selected rows in bulk.</CardDescription>
            </div>
            <div className="grid gap-3 md:grid-cols-4">
              <Input value={filterPostType} onChange={e => setFilterPostType(e.target.value)} placeholder="Post type slug" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              <Input type="datetime-local" value={dateFrom} onChange={e => setDateFrom(e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              <Input type="datetime-local" value={dateTo} onChange={e => setDateTo(e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              <Button variant="outline" onClick={() => fetchRows()} className="gap-2">Filter</Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {selectedRows.length > 0 && (
            <div className="mb-3 flex justify-end">
              <Button size="sm" variant="outline" className="text-red-600 hover:bg-red-50 hover:text-red-700" onClick={handleBulkRemove}>
                Remove {selectedRows.length} Selected
              </Button>
            </div>
          )}
          {loading ? (
            <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" />
          ) : rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">No posts have an expiry date set.</p>
          ) : (
            <div className="space-y-2">
              {rows.map(row => (
                <div key={row.ID} className="flex items-center justify-between rounded-lg border border-[#390d58]/10 bg-[#390d58]/5 p-3">
                  <div className="flex items-start gap-3">
                    <input type="checkbox" checked={selectedRows.includes(row.ID)} onChange={() => setSelectedRows(current => current.includes(row.ID) ? current.filter(item => item !== row.ID) : [...current, row.ID])} />
                    <div>
                      <p className="text-sm font-medium">{row.post_title || `Post #${row.ID}`}</p>
                      <p className="text-xs text-muted-foreground">{row.post_type} - {row.post_status} - {row.expire_action || 'draft'} at {row.expire_at}</p>
                    </div>
                  </div>
                  <Button size="sm" variant="ghost" className="text-red-500 hover:bg-red-50 hover:text-red-700" onClick={() => handleRemove(row.ID)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
