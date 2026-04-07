import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Loader2, RotateCcw, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface AdminCssData {
  css: string
  updated_at: string
}

export function AdminCssTool() {
  const [css, setCss] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [resetting, setResetting] = useState(false)
  const [updatedAt, setUpdatedAt] = useState('')
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)
  const lintWarnings = [
    css.split('{').length !== css.split('}').length ? 'Opening and closing braces are unbalanced.' : '',
    css.includes('<style') ? 'Do not include <style> tags here. Paste CSS rules only.' : '',
    /display\s*:\s*none\s*!important/i.test(css) ? 'This CSS hides elements aggressively. Double-check that you are not masking critical admin controls.' : '',
  ].filter(Boolean)

  const load = () => {
    setLoading(true)
    api.get<AdminCssData>('/workshop/admin-css').then(d => {
      setCss(d.css)
      setUpdatedAt(d.updated_at)
    }).finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const handleSave = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post('/workshop/admin-css', { css })
      setMsg({ ok: true, text: 'Admin CSS saved.' })
      load()
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleReset = async () => {
    setResetting(true)
    setMsg(null)
    try {
      await api.post('/workshop/admin-css', { reset: true })
      setCss('')
      setMsg({ ok: true, text: 'Admin CSS reset.' })
      load()
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Reset failed.' })
    } finally {
      setResetting(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Custom Admin CSS</CardTitle>
        <CardDescription>Inject CSS into the WordPress admin area with live preview and reset support.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <style>{css}</style>
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            <div className="rounded-xl border border-[#390d58]/10 bg-[#390d58]/5 p-4 text-sm text-muted-foreground">
              Preview is live inside this admin screen while you type. Last saved: {updatedAt || 'not yet saved'}.
            </div>
            {lintWarnings.length > 0 && (
              <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                {lintWarnings.map(warning => <p key={warning}>{warning}</p>)}
              </div>
            )}
            <Textarea
              rows={14}
              value={css}
              onChange={e => setCss(e.target.value)}
              placeholder="/* Your admin CSS here */"
              className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]"
            />
            <div className="flex items-center justify-between">
              {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
              <div className="ml-auto flex gap-2">
                <Button variant="outline" onClick={handleReset} disabled={resetting || saving} className="gap-2">
                  {resetting ? <Loader2 className="h-4 w-4 animate-spin" /> : <RotateCcw className="h-4 w-4" />}
                  Reset
                </Button>
                <Button onClick={handleSave} disabled={saving || resetting} className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
                  {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                  Save
                </Button>
              </div>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}
