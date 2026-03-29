import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface CodeData { header: string; footer: string }

export function CodeInjectionTool() {
  const [data,    setData]    = useState<CodeData>({ header: '', footer: '' })
  const [loading, setLoading] = useState(true)
  const [saving,  setSaving]  = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<CodeData>('/workshop/code-injection').then(setData).finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      await api.post('/workshop/code-injection', data)
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
        <CardTitle className="text-lg text-[#390d58]">Header &amp; Footer Code</CardTitle>
        <CardDescription>Inject HTML, JS, or CSS into every page without editing theme files.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            <div className="space-y-2">
              <Label>Header Code <span className="text-muted-foreground font-normal">(before &lt;/head&gt;)</span></Label>
              <Textarea
                rows={6}
                value={data.header}
                onChange={e => setData(d => ({ ...d, header: e.target.value }))}
                placeholder="<!-- e.g. Google Analytics, meta tags -->"
                className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]"
              />
            </div>
            <div className="space-y-2">
              <Label>Footer Code <span className="text-muted-foreground font-normal">(before &lt;/body&gt;)</span></Label>
              <Textarea
                rows={6}
                value={data.footer}
                onChange={e => setData(d => ({ ...d, footer: e.target.value }))}
                placeholder="<!-- e.g. chat widgets, tracking scripts -->"
                className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]"
              />
            </div>
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
