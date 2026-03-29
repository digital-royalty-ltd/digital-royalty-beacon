import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Loader2, Trash2 } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

type Counts = Record<string, number>

const items: { key: string; label: string; warning?: boolean }[] = [
  { key: 'revisions',          label: 'Post Revisions' },
  { key: 'auto_drafts',        label: 'Auto-drafts' },
  { key: 'trashed_posts',      label: 'Trashed Posts' },
  { key: 'spam_comments',      label: 'Spam Comments' },
  { key: 'trashed_comments',   label: 'Trashed Comments' },
  { key: 'orphan_postmeta',    label: 'Orphaned Post Meta' },
  { key: 'orphan_commentmeta', label: 'Orphaned Comment Meta' },
  { key: 'transients',         label: 'Expired Transients' },
]

export function DatabaseCleanupTool() {
  const [counts,  setCounts]  = useState<Counts>({})
  const [loading, setLoading] = useState(true)
  const [running, setRunning] = useState<string | null>(null)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  const fetchCounts = () => {
    setLoading(true)
    api.get<Counts>('/workshop/database-cleanup').then(setCounts).finally(() => setLoading(false))
  }

  useEffect(() => { fetchCounts() }, [])

  const handleClean = async (key: string) => {
    setRunning(key); setMsg(null)
    try {
      const res = await api.post<{ ok: boolean; deleted: number }>('/workshop/database-cleanup', { type: key })
      setMsg({ ok: true, text: `Removed ${res.deleted} items.` })
      fetchCounts()
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Cleanup failed.' })
    } finally {
      setRunning(null)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Database Cleanup</CardTitle>
        <CardDescription>Remove junk data to keep your database lean.</CardDescription>
      </CardHeader>
      <CardContent>
        {msg && (
          <div className={`mb-4 p-3 rounded-lg text-sm ${msg.ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
            {msg.text}
          </div>
        )}
        <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
          {items.map((item, idx) => (
            <div key={item.key}>
              <div className="flex items-center justify-between px-4 py-3 bg-[#390d58]/[0.02] hover:bg-[#390d58]/5">
                <span className="text-sm">{item.label}</span>
                <div className="flex items-center gap-3">
                  {loading ? (
                    <span className="text-xs text-muted-foreground w-6 text-right">…</span>
                  ) : (
                    <span className={`text-sm font-mono font-medium ${(counts[item.key] ?? 0) > 0 ? 'text-amber-600' : 'text-muted-foreground'}`}>
                      {(counts[item.key] ?? 0).toLocaleString()}
                    </span>
                  )}
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={running === item.key || loading || (counts[item.key] ?? 0) === 0}
                    onClick={() => handleClean(item.key)}
                    className="gap-1 text-xs h-7 text-red-600 hover:bg-red-50 hover:border-red-300 hover:text-red-700"
                  >
                    {running === item.key ? <Loader2 className="h-3 w-3 animate-spin" /> : <Trash2 className="h-3 w-3" />}
                    Clean
                  </Button>
                </div>
              </div>
              {idx < items.length - 1 && <Separator className="bg-[#390d58]/10" />}
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
