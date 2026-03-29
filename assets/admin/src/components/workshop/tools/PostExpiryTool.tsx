import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Loader2, Trash2, Clock } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface ExpiryRow { ID: string; post_title: string; post_status: string; post_type: string; expire_at: string }

export function PostExpiryTool() {
  const [rows,    setRows]    = useState<ExpiryRow[]>([])
  const [loading, setLoading] = useState(true)
  const [postId,  setPostId]  = useState('')
  const [expireAt, setExpireAt] = useState('')
  const [saving,  setSaving]  = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  const fetchRows = () => {
    setLoading(true)
    api.get<ExpiryRow[]>('/workshop/post-expiry').then(setRows).finally(() => setLoading(false))
  }

  useEffect(() => { fetchRows() }, [])

  const handleSet = async () => {
    if (!postId || !expireAt) return
    setSaving(true); setMsg(null)
    try {
      await api.post('/workshop/post-expiry', { post_id: parseInt(postId), expire_at: expireAt })
      setMsg({ ok: true, text: 'Expiry set.' })
      setPostId(''); setExpireAt('')
      fetchRows()
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleRemove = async (id: string) => {
    try {
      await api.delete(`/workshop/post-expiry/${id}`)
      fetchRows()
    } catch { /* ignore */ }
  }

  return (
    <div className="space-y-4">
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <CardTitle className="text-lg text-[#390d58]">Set Post Expiry</CardTitle>
          <CardDescription>Schedule a post or page to move to draft on a specific date.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex gap-3 flex-wrap">
            <Input value={postId} onChange={e => setPostId(e.target.value)} placeholder="Post ID" className="w-32 border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            <Input type="datetime-local" value={expireAt} onChange={e => setExpireAt(e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            <Button onClick={handleSet} disabled={saving || !postId || !expireAt} className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Clock className="h-4 w-4" />}
              Set Expiry
            </Button>
          </div>
          {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
        </CardContent>
      </Card>

      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <CardTitle className="text-lg text-[#390d58]">Scheduled Expirations</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" />
          ) : rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">No posts have an expiry date set.</p>
          ) : (
            <div className="space-y-2">
              {rows.map(row => (
                <div key={row.ID} className="flex items-center justify-between p-3 rounded-lg bg-[#390d58]/5 border border-[#390d58]/10">
                  <div>
                    <p className="text-sm font-medium">{row.post_title || `Post #${row.ID}`}</p>
                    <p className="text-xs text-muted-foreground">{row.post_type} · {row.post_status} · expires {row.expire_at}</p>
                  </div>
                  <Button size="sm" variant="ghost" className="text-red-500 hover:text-red-700 hover:bg-red-50" onClick={() => handleRemove(row.ID)}>
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
