import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Loader2, Search, LogIn, RefreshCw, ArrowLeftRight, Download, Save } from 'lucide-react'
import { api } from '@/lib/api'

interface UserRow {
  id: number
  display_name: string
  email: string
  login: string
  role: string
  eligible: boolean
  ineligible_reason: string
}

interface UserListResponse {
  rows: UserRow[]
  switch_log: {
    event: string
    actor_name: string
    target_name: string
    created_at: string
  }[]
  multisite: boolean
  settings: {
    retention_days: number
  }
}

interface SwitchStatus {
  is_switched: boolean
  original_user: { id: number; display_name: string } | null
  switch_back_url: string | null
}

const ROLE_COLOURS: Record<string, string> = {
  administrator: 'bg-red-50 text-red-600 border-red-200',
  editor: 'bg-blue-50 text-blue-600 border-blue-200',
  author: 'bg-amber-50 text-amber-600 border-amber-200',
  contributor: 'bg-green-50 text-green-600 border-green-200',
  subscriber: 'bg-gray-50 text-gray-500 border-gray-200',
}

export function UserSwitcherTool() {
  const [rows, setRows] = useState<UserRow[]>([])
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(true)
  const [switching, setSwitching] = useState<number | null>(null)
  const [switchStatus, setSwitchStatus] = useState<SwitchStatus | null>(null)
  const [switchLog, setSwitchLog] = useState<UserListResponse['switch_log']>([])
  const [multisite, setMultisite] = useState(false)
  const [retentionDays, setRetentionDays] = useState(30)

  const fetchUsers = (q = '') => {
    setLoading(true)
    api.get<UserListResponse>(`/workshop/users${q ? `?q=${encodeURIComponent(q)}` : ''}`)
      .then(result => {
        setRows(result.rows)
        setSwitchLog(result.switch_log)
        setMultisite(result.multisite)
        setRetentionDays(result.settings.retention_days)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchUsers()
    api.get<SwitchStatus>('/user-switch-status').then(setSwitchStatus).catch(() => {})
  }, [])

  const handleSearch = () => fetchUsers(query)

  const handleSwitch = async (userId: number) => {
    if (!confirm('Switch to this user? You will be redirected and logged in as them.')) return
    setSwitching(userId)
    try {
      const { url } = await api.get<{ url: string }>(`/workshop/user-switch-url/${userId}`)
      window.location.href = url
    } finally {
      setSwitching(null)
    }
  }

  const saveSettings = async () => {
    await api.post('/workshop/user-switch-log/settings', { retention_days: retentionDays })
    fetchUsers(query)
  }

  const exportLog = async () => {
    const result = await api.get<{ exported_at: string; rows: UserListResponse['switch_log'] }>('/workshop/user-switch-log/export')
    const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `beacon-user-switch-log-${result.exported_at.replace(/[: ]/g, '-')}.json`
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div className="space-y-4">
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-start justify-between">
            <div>
              <CardTitle className="text-lg text-[#390d58]">User Switcher</CardTitle>
              <CardDescription>Log in as another user, see who is eligible, and review the latest switch events.</CardDescription>
            </div>
            <Button size="sm" variant="outline" onClick={() => fetchUsers(query)} disabled={loading} className="gap-1">
              <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {switchStatus?.is_switched && switchStatus.switch_back_url && (
            <div className="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
              <div className="flex items-center gap-2 text-sm text-amber-700">
                <ArrowLeftRight className="h-4 w-4 shrink-0" />
                <span>
                  You are browsing as another user.
                  {switchStatus.original_user && <> Switch back to <strong>{switchStatus.original_user.display_name}</strong>?</>}
                </span>
              </div>
              <Button size="sm" variant="outline" onClick={() => { window.location.href = switchStatus.switch_back_url! }} className="shrink-0 gap-1 border-amber-300 text-amber-700 hover:bg-amber-100">
                <ArrowLeftRight className="h-3.5 w-3.5" />
                Switch Back
              </Button>
            </div>
          )}

          {multisite && (
            <div className="rounded-lg border border-[#390d58]/20 bg-[#390d58]/[0.02] px-3 py-2 text-xs text-muted-foreground">
              Multisite is enabled. Users who are not members of this site are shown as ineligible.
            </div>
          )}

          <div className="flex gap-2">
            <input type="text" value={query} onChange={e => setQuery(e.target.value)} onKeyDown={e => e.key === 'Enter' && handleSearch()} placeholder="Search users" className="flex-1 rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
            <Button size="sm" onClick={handleSearch} disabled={loading} className="gap-1 bg-[#390d58] text-white hover:bg-[#4a1170]">
              {loading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
            </Button>
          </div>

          {loading && rows.length === 0 ? (
            <div className="flex justify-center py-8"><Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /></div>
          ) : rows.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No users found.</p>
          ) : (
            <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                    <TableHead className="font-semibold text-[#390d58]">User</TableHead>
                    <TableHead className="w-28 font-semibold text-[#390d58]">Role</TableHead>
                    <TableHead className="w-32 font-semibold text-[#390d58]">Availability</TableHead>
                    <TableHead className="w-24" />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((row, idx) => (
                    <TableRow key={row.id} className={`text-sm ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                      <TableCell>
                        <div>
                          <p className="font-medium text-[#390d58]">{row.display_name}</p>
                          <p className="text-xs text-muted-foreground">{row.email}</p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className={`text-[10px] ${ROLE_COLOURS[row.role] ?? ROLE_COLOURS.subscriber}`}>
                          {row.role}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {row.eligible ? 'Eligible' : row.ineligible_reason || 'Unavailable'}
                      </TableCell>
                      <TableCell>
                        <Button size="sm" variant="outline" onClick={() => handleSwitch(row.id)} disabled={switching !== null || !row.eligible} className="gap-1 border-[#390d58]/20 text-xs text-[#390d58] hover:bg-[#390d58]/10">
                          {switching === row.id ? <Loader2 className="h-3 w-3 animate-spin" /> : <LogIn className="h-3 w-3" />}
                          Switch
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center justify-between gap-3">
            <div>
              <CardTitle className="text-lg text-[#390d58]">Recent Switch Events</CardTitle>
              <CardDescription>Export the switch log or set how long Beacon keeps entries.</CardDescription>
            </div>
            <Button size="sm" variant="outline" className="gap-1" onClick={exportLog}>
              <Download className="h-3.5 w-3.5" />
              Export
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-end gap-2">
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Retention days</label>
              <input type="number" min={1} value={retentionDays} onChange={e => setRetentionDays(parseInt(e.target.value || '30', 10))} className="rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm" />
            </div>
            <Button size="sm" onClick={saveSettings} className="gap-1 bg-[#390d58] text-white hover:bg-[#4a1170]">
              <Save className="h-3.5 w-3.5" />
              Save
            </Button>
          </div>

          {switchLog.length === 0 ? (
            <p className="text-sm text-muted-foreground">No switch events logged yet.</p>
          ) : (
            <div className="space-y-2">
              {switchLog.slice(0, 12).map((entry, index) => (
                <div key={`${entry.created_at}-${index}`} className="rounded-lg border border-[#390d58]/10 bg-[#390d58]/5 p-3 text-sm">
                  <p className="font-medium text-[#390d58]">{entry.actor_name} {entry.event === 'switch_back' ? 'switched back to' : 'switched into'} {entry.target_name}</p>
                  <p className="text-xs text-muted-foreground">{entry.created_at}</p>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
