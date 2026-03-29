import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, RefreshCw } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

export function PermalinkFlushTool() {
  const [loading, setLoading] = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  const handleFlush = async () => {
    setLoading(true); setMsg(null)
    try {
      await api.post('/workshop/permalink-flush')
      setMsg({ ok: true, text: 'Rewrite rules flushed successfully.' })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Flush failed.' })
    } finally {
      setLoading(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Flush Permalinks</CardTitle>
        <CardDescription>Regenerate WordPress rewrite rules to fix broken URLs after structural changes.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {msg && (
          <div className={`p-3 rounded-lg text-sm ${msg.ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
            {msg.text}
          </div>
        )}
        <Button onClick={handleFlush} disabled={loading} className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
          {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
          Flush Rewrite Rules
        </Button>
      </CardContent>
    </Card>
  )
}
