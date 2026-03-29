import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Loader2, Save, Send } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface SmtpData {
  host: string; port: number; encryption: string
  username: string; from_email: string; from_name: string
}

export function SmtpTool() {
  const [data,      setData]      = useState<SmtpData>({ host: '', port: 587, encryption: 'tls', username: '', from_email: '', from_name: '' })
  const [password,  setPassword]  = useState('')
  const [loading,   setLoading]   = useState(true)
  const [saving,    setSaving]    = useState(false)
  const [testTo,    setTestTo]    = useState('')
  const [testing,   setTesting]   = useState(false)
  const [msg,       setMsg]       = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<SmtpData>('/workshop/smtp').then(setData).finally(() => setLoading(false))
  }, [])

  const set = <K extends keyof SmtpData>(k: K, v: SmtpData[K]) => setData(d => ({ ...d, [k]: v }))

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      await api.post('/workshop/smtp', { ...data, password })
      setMsg({ ok: true, text: 'Saved.' })
      setPassword('')
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleTest = async () => {
    setTesting(true); setMsg(null)
    try {
      const res = await api.post<{ ok: boolean; message: string }>('/workshop/test-email', { to: testTo })
      setMsg({ ok: res.ok, text: res.message })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Test failed.' })
    } finally {
      setTesting(false)
    }
  }

  if (loading) return <Loader2 className="h-5 w-5 animate-spin text-[#390d58] m-6" />

  const field = (label: string, el: React.ReactNode) => (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      {el}
    </div>
  )

  return (
    <div className="space-y-4">
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <CardTitle className="text-lg text-[#390d58]">SMTP Configuration</CardTitle>
          <CardDescription>Route WordPress emails through an SMTP server for reliable delivery.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid sm:grid-cols-2 gap-4">
            {field('Host', <Input value={data.host} onChange={e => set('host', e.target.value)} placeholder="smtp.example.com" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
            {field('Port', <Input type="number" value={data.port} onChange={e => set('port', parseInt(e.target.value) || 587)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
          </div>
          {field('Encryption',
            <Select value={data.encryption} onValueChange={v => set('encryption', v)}>
              <SelectTrigger className="border-[#390d58]/20"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tls">TLS (Recommended)</SelectItem>
                <SelectItem value="ssl">SSL</SelectItem>
                <SelectItem value="none">None</SelectItem>
              </SelectContent>
            </Select>
          )}
          <div className="grid sm:grid-cols-2 gap-4">
            {field('Username', <Input value={data.username} onChange={e => set('username', e.target.value)} placeholder="user@example.com" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
            {field('Password', <Input type="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="Leave blank to keep current" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            {field('From Email', <Input value={data.from_email} onChange={e => set('from_email', e.target.value)} placeholder="no-reply@example.com" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
            {field('From Name', <Input value={data.from_name} onChange={e => set('from_name', e.target.value)} placeholder="My Site" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
          </div>
          <div className="flex items-center justify-between pt-1">
            {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
            <Button onClick={handleSave} disabled={saving} className="ml-auto bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
              Save
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <CardTitle className="text-lg text-[#390d58]">Send Test Email</CardTitle>
          <CardDescription>Verify your SMTP settings by sending a test message.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex gap-3">
            <Input value={testTo} onChange={e => setTestTo(e.target.value)} placeholder="test@example.com" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            <Button onClick={handleTest} disabled={testing || !testTo} className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2 shrink-0">
              {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
              Send
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
