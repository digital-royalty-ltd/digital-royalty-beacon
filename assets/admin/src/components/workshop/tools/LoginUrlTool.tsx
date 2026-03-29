import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

export function LoginUrlTool() {
  const [slug,    setSlug]    = useState('')
  const [loading, setLoading] = useState(true)
  const [saving,  setSaving]  = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  const siteUrl = window.BeaconData?.siteUrl ?? ''

  useEffect(() => {
    api.get<{ slug: string }>('/workshop/login-url').then(d => setSlug(d.slug)).finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      const res = await api.post<{ ok: boolean; slug: string }>('/workshop/login-url', { slug })
      setSlug(res.slug)
      setMsg({ ok: true, text: `Login URL updated to /${res.slug}` })
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
        <CardTitle className="text-lg text-[#390d58]">Custom Login URL</CardTitle>
        <CardDescription>Change your WordPress login URL and block direct wp-login.php access.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            <div className="space-y-1.5">
              <Label>Login Path</Label>
              <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground shrink-0">{siteUrl}/</span>
                <Input
                  value={slug}
                  onChange={e => setSlug(e.target.value)}
                  placeholder="my-login"
                  className="border-[#390d58]/20 focus-visible:ring-[#390d58]"
                />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              Reserved paths (admin, wp-admin, login, etc.) cannot be used.
              Leave blank to use the default wp-login.php.
            </p>
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
