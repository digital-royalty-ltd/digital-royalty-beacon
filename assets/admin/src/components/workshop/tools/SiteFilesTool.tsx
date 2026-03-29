import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Loader2, Save, AlertTriangle } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface SiteFileToolProps { slug: 'robots-editor' | 'htaccess-editor' }

export function SiteFilesTool({ slug }: SiteFileToolProps) {
  const isRobots   = slug === 'robots-editor'
  const endpoint   = isRobots ? '/workshop/robots' : '/workshop/htaccess'
  const [content,  setContent]  = useState('')
  const [writable, setWritable] = useState(true)
  const [loading,  setLoading]  = useState(true)
  const [saving,   setSaving]   = useState(false)
  const [msg,      setMsg]      = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<{ content: string; writable?: boolean }>(endpoint).then(d => {
      setContent(d.content)
      if (d.writable !== undefined) setWritable(d.writable)
    }).finally(() => setLoading(false))
  }, [endpoint])

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      await api.post(endpoint, { content })
      setMsg({ ok: true, text: 'Saved.' })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">{isRobots ? 'Robots.txt Editor' : '.htaccess Editor'}</CardTitle>
        <CardDescription>
          {isRobots
            ? "Edit your site's robots.txt directly from the admin."
            : "Edit your .htaccess file from the admin (Apache only)."}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {!isRobots && !writable && (
          <div className="flex items-center gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
            <AlertTriangle className="h-4 w-4 shrink-0" />
            .htaccess is not writable. Make it writable via FTP or SSH before editing here.
          </div>
        )}
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            <Textarea
              rows={14}
              value={content}
              onChange={e => setContent(e.target.value)}
              disabled={!isRobots && !writable}
              className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]"
            />
            <div className="flex items-center justify-between">
              {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
              <Button onClick={handleSave} disabled={saving || (!isRobots && !writable)} className="ml-auto bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save
              </Button>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}
