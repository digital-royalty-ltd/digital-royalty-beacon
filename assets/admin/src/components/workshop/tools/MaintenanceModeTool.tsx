import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface MaintData { enabled: boolean; message: string }

export function MaintenanceModeTool() {
  const [data,    setData]    = useState<MaintData>({ enabled: false, message: '' })
  const [loading, setLoading] = useState(true)
  const [saving,  setSaving]  = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<MaintData>('/workshop/maintenance-mode').then(setData).finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      await api.post('/workshop/maintenance-mode', data)
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
        <CardTitle className="text-lg text-[#390d58]">Maintenance Mode</CardTitle>
        <CardDescription>Show a maintenance page to visitors while you work on the site.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            {data.enabled && (
              <div className="p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                Maintenance mode is <strong>active</strong>. Logged-in admins can still view the site.
              </div>
            )}
            <div className="flex items-center justify-between p-4 rounded-xl bg-[#390d58]/5">
              <Label htmlFor="maint-toggle" className="cursor-pointer">Enable Maintenance Mode</Label>
              <Switch id="maint-toggle" checked={data.enabled} onCheckedChange={v => setData(d => ({ ...d, enabled: v }))} />
            </div>
            <div className="space-y-1.5">
              <Label>Maintenance Message</Label>
              <Textarea
                rows={4}
                value={data.message}
                onChange={e => setData(d => ({ ...d, message: e.target.value }))}
                className="border-[#390d58]/20 focus-visible:ring-[#390d58]"
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
