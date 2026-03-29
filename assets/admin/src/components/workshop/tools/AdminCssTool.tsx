import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

export function AdminCssTool() {
  const [css,     setCss]     = useState('')
  const [loading, setLoading] = useState(true)
  const [saving,  setSaving]  = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<{ css: string }>('/workshop/admin-css').then(d => setCss(d.css)).finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      await api.post('/workshop/admin-css', { css })
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
        <CardTitle className="text-lg text-[#390d58]">Custom Admin CSS</CardTitle>
        <CardDescription>Inject CSS into the WordPress admin area for white-labelling or style tweaks.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            <Textarea
              rows={12}
              value={css}
              onChange={e => setCss(e.target.value)}
              placeholder="/* Your admin CSS here */"
              className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]"
            />
            <div className="flex items-center justify-between">
              {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
              <Button onClick={handleSave} disabled={saving} className="ml-auto bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
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
