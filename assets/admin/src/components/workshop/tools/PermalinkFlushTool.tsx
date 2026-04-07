import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, RefreshCw, Link2 } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface PermalinkContext {
  structure: string
  settings_url: string
  guidance: string
  rule_count: number
  rules: Record<string, string>
}

export function PermalinkFlushTool() {
  const [loading, setLoading] = useState(false)
  const [context, setContext] = useState<PermalinkContext | null>(null)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  const load = () => {
    api.get<PermalinkContext>('/workshop/permalink-flush').then(setContext).catch(() => {})
  }

  useEffect(() => { load() }, [])

  const handleFlush = async () => {
    setLoading(true)
    setMsg(null)
    try {
      const result = await api.post<{ flushed_at: string }>('/workshop/permalink-flush')
      setMsg({ ok: true, text: `Rewrite rules flushed successfully at ${result.flushed_at}.` })
      load()
    } catch (error) {
      setMsg({ ok: false, text: error instanceof ApiError ? error.message : 'Flush failed.' })
    } finally {
      setLoading(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Flush Permalinks</CardTitle>
        <CardDescription>Regenerate rewrite rules after post type, taxonomy, or rewrite changes, with a quick rule inspection before acting.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {msg && (
          <div className={`rounded-lg border p-3 text-sm ${msg.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
            {msg.text}
          </div>
        )}

        {context && (
          <>
            <div className="rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4 space-y-2">
              <p className="text-sm font-medium text-[#390d58]">Current structure</p>
              <p className="rounded bg-white px-3 py-2 font-mono text-xs text-muted-foreground">{context.structure || 'Plain permalinks'}</p>
              <p className="text-xs text-muted-foreground">{context.guidance}</p>
              <a href={context.settings_url} className="inline-flex items-center gap-1 text-xs text-[#390d58] underline underline-offset-2">
                <Link2 className="h-3 w-3" /> Open core permalink settings
              </a>
            </div>

            <div className="rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4 space-y-2">
              <p className="text-sm font-medium text-[#390d58]">Rewrite diagnostics</p>
              <p className="text-xs text-muted-foreground">Rule count: {context.rule_count}</p>
              <div className="max-h-64 overflow-auto rounded bg-white p-2 text-[11px] font-mono text-muted-foreground">
                {Object.entries(context.rules).map(([pattern, target]) => (
                  <div key={pattern} className="border-b border-[#390d58]/10 py-1 last:border-0">
                    <div>{pattern}</div>
                    <div className="text-[10px] opacity-80">{target}</div>
                  </div>
                ))}
              </div>
            </div>
          </>
        )}

        <Button onClick={handleFlush} disabled={loading} className="gap-2 bg-[#390d58] text-white hover:bg-[#4a1170]">
          {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
          Flush Rewrite Rules
        </Button>
      </CardContent>
    </Card>
  )
}
