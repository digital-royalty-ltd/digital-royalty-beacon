import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader2, Save, Copy } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface LoginUrlData {
  slug: string
  login_url: string
  site_url: string
  blocks_default_login: boolean
  recovery_url: string
  conflicts: string[]
}

export function LoginUrlTool() {
  const [data, setData] = useState<LoginUrlData>({ slug: '', login_url: '', site_url: '', blocks_default_login: false, recovery_url: '', conflicts: [] })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  const load = () => {
    setLoading(true)
    api.get<LoginUrlData>('/workshop/login-url').then(setData).finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const handleSave = async () => {
    setSaving(true)
    setMsg(null)
    try {
      const res = await api.post<{ ok: boolean; slug: string }>('/workshop/login-url', { slug: data.slug })
      setMsg({ ok: true, text: res.slug ? `Login URL updated to /${res.slug}` : 'Default wp-login.php restored.' })
      load()
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const copyUrl = async () => {
    await navigator.clipboard.writeText(data.login_url)
    setMsg({ ok: true, text: 'Login URL copied.' })
  }

  const copyRecoveryUrl = async () => {
    await navigator.clipboard.writeText(data.recovery_url)
    setMsg({ ok: true, text: 'Recovery URL copied.' })
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Custom Login URL</CardTitle>
        <CardDescription>Change the login path, block the default endpoint for logged-out users, and keep recovery guidance visible.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            <div className="rounded-xl border border-[#390d58]/10 bg-[#390d58]/5 p-4 space-y-2">
              <p className="text-sm font-medium text-[#390d58]">Current login URL</p>
              <div className="flex gap-2">
                <Input value={data.login_url} readOnly className="border-[#390d58]/20 bg-white" />
                <Button variant="outline" onClick={copyUrl} className="gap-2">
                  <Copy className="h-4 w-4" />
                  Copy
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                {data.blocks_default_login
                  ? 'Logged-out requests to wp-login.php and wp-admin now return 404.'
                  : 'Default WordPress login remains active until a custom slug is saved.'}
              </p>
            </div>

            <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-2">
              <p className="text-sm font-medium text-[#390d58]">Recovery URL</p>
              <div className="flex gap-2">
                <Input value={data.recovery_url} readOnly className="border-[#390d58]/20 bg-white" />
                <Button variant="outline" onClick={copyRecoveryUrl} className="gap-2">
                  <Copy className="h-4 w-4" />
                  Copy
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">Opening this link clears the custom slug and restores the default WordPress login route.</p>
            </div>

            <div className="space-y-1.5">
              <Label>Login path</Label>
              <div className="flex items-center gap-2">
                <span className="shrink-0 text-sm text-muted-foreground">{data.site_url}/</span>
                <Input
                  value={data.slug}
                  onChange={e => setData(current => ({ ...current, slug: e.target.value }))}
                  placeholder="my-login"
                  className="border-[#390d58]/20 focus-visible:ring-[#390d58]"
                />
              </div>
            </div>

            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 space-y-2">
              <p className="font-medium">Compatibility note</p>
              <p>Avoid slugs used by pages, forms, or third-party auth flows that hard-code `wp-login.php`.</p>
              {data.conflicts.length > 0
                ? data.conflicts.map(conflict => <p key={conflict}>{conflict}</p>)
                : <p className="text-xs">No obvious active plugin conflicts were detected.</p>}
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
