import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Loader2, Trash2, RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'

interface LogRow { id: string; path: string; referrer: string | null; hit_count: string; first_seen_at: string; last_seen_at: string }

export function FourOhFourTool() {
  const [rows,    setRows]    = useState<LogRow[]>([])
  const [loading, setLoading] = useState(true)
  const [clearing, setClearing] = useState(false)

  const fetchRows = () => {
    setLoading(true)
    api.get<LogRow[]>('/workshop/404-logs').then(setRows).finally(() => setLoading(false))
  }

  useEffect(() => { fetchRows() }, [])

  const handleClear = async () => {
    if (!confirm('Clear all 404 log entries?')) return
    setClearing(true)
    try { await api.delete('/workshop/404-logs'); setRows([]) }
    finally { setClearing(false) }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex items-start justify-between">
          <div>
            <CardTitle className="text-lg text-[#390d58]">404 Monitor</CardTitle>
            <CardDescription>Track 404 errors across your site. {rows.length > 0 && `${rows.length} unique path${rows.length !== 1 ? 's' : ''} recorded.`}</CardDescription>
          </div>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" onClick={fetchRows} disabled={loading} className="gap-1">
              <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
            </Button>
            <Button size="sm" variant="outline" onClick={handleClear} disabled={clearing || rows.length === 0} className="gap-1 text-red-600 hover:bg-red-50 hover:border-red-300">
              {clearing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" />}
              Clear
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="flex justify-center py-8"><Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /></div>
        ) : rows.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-8">No 404 errors recorded yet.</p>
        ) : (
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                  <TableHead className="text-[#390d58] font-semibold">Path</TableHead>
                  <TableHead className="w-16 text-[#390d58] font-semibold text-right">Hits</TableHead>
                  <TableHead className="w-40 text-[#390d58] font-semibold">Last Seen</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row, idx) => (
                  <TableRow key={row.id} className={`font-mono text-sm ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                    <TableCell className="text-[#390d58]">{row.path}</TableCell>
                    <TableCell className="text-right">
                      <Badge variant="outline" className={parseInt(row.hit_count) > 10 ? 'bg-red-50 text-red-600 border-red-200' : 'bg-[#390d58]/5 text-[#390d58] border-[#390d58]/20'}>
                        {row.hit_count}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{row.last_seen_at}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
