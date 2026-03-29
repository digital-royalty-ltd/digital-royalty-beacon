import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Loader2, Plus, Pencil, Trash2, Check, X } from 'lucide-react'
import { api } from '@/lib/api'

interface Redirect {
  id:           number
  source_path:  string
  target_url:   string
  redirect_type: number
  is_active:    number
  hit_count:    number
  created_at:   string
}

interface EditState {
  source_path:  string
  target_url:   string
  redirect_type: number
  is_active:    boolean
}

const emptyEdit = (): EditState => ({ source_path: '', target_url: '', redirect_type: 301, is_active: true })

export function RedirectsTool() {
  const [rows,    setRows]    = useState<Redirect[]>([])
  const [loading, setLoading] = useState(true)
  const [saving,  setSaving]  = useState(false)
  const [editId,  setEditId]  = useState<number | 'new' | null>(null)
  const [form,    setForm]    = useState<EditState>(emptyEdit())

  const fetchRows = () => {
    setLoading(true)
    api.get<Redirect[]>('/workshop/redirects').then(setRows).finally(() => setLoading(false))
  }

  useEffect(() => { fetchRows() }, [])

  const startNew = () => { setEditId('new'); setForm(emptyEdit()) }
  const startEdit = (r: Redirect) => {
    setEditId(r.id)
    setForm({ source_path: r.source_path, target_url: r.target_url, redirect_type: r.redirect_type, is_active: r.is_active === 1 })
  }
  const cancelEdit = () => { setEditId(null) }

  const handleSave = async () => {
    setSaving(true)
    try {
      if (editId === 'new') {
        const created = await api.post<Redirect>('/workshop/redirects', form)
        setRows(prev => [created, ...prev])
      } else {
        const updated = await api.put<Redirect>(`/workshop/redirects/${editId}`, form)
        setRows(prev => prev.map(r => r.id === editId ? updated : r))
      }
      setEditId(null)
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this redirect?')) return
    await api.delete(`/workshop/redirects/${id}`)
    setRows(prev => prev.filter(r => r.id !== id))
  }

  const f = (key: keyof EditState, val: unknown) => setForm(prev => ({ ...prev, [key]: val }))

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex items-start justify-between">
          <div>
            <CardTitle className="text-lg text-[#390d58]">Redirects Manager</CardTitle>
            <CardDescription>{rows.length} redirect{rows.length !== 1 ? 's' : ''} configured.</CardDescription>
          </div>
          {editId === null && (
            <Button size="sm" onClick={startNew} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
              <Plus className="h-3.5 w-3.5" /> Add Redirect
            </Button>
          )}
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        {/* Inline form for new/edit */}
        {editId !== null && (
          <div className="rounded-xl border border-[#390d58]/20 p-4 space-y-3 bg-[#390d58]/[0.02]">
            <p className="text-sm font-medium text-[#390d58]">{editId === 'new' ? 'New Redirect' : 'Edit Redirect'}</p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label className="text-xs text-muted-foreground mb-1 block">Source path</label>
                <input
                  type="text"
                  value={form.source_path}
                  onChange={e => f('source_path', e.target.value)}
                  placeholder="/old-url"
                  className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
              </div>
              <div>
                <label className="text-xs text-muted-foreground mb-1 block">Target URL</label>
                <input
                  type="text"
                  value={form.target_url}
                  onChange={e => f('target_url', e.target.value)}
                  placeholder="/new-url or https://..."
                  className="w-full text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
              </div>
            </div>
            <div className="flex items-center gap-4">
              <div>
                <label className="text-xs text-muted-foreground mb-1 block">Type</label>
                <select
                  value={form.redirect_type}
                  onChange={e => f('redirect_type', parseInt(e.target.value))}
                  className="text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                >
                  <option value={301}>301 Permanent</option>
                  <option value={302}>302 Temporary</option>
                </select>
              </div>
              {editId !== 'new' && (
                <div className="flex items-center gap-2 mt-4">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={form.is_active}
                    onChange={e => f('is_active', e.target.checked)}
                    className="rounded"
                  />
                  <label htmlFor="is_active" className="text-sm text-muted-foreground">Active</label>
                </div>
              )}
            </div>
            <div className="flex gap-2">
              <Button size="sm" onClick={handleSave} disabled={saving}
                className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                {saving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                Save
              </Button>
              <Button size="sm" variant="outline" onClick={cancelEdit} className="gap-1">
                <X className="h-3.5 w-3.5" /> Cancel
              </Button>
            </div>
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-8"><Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /></div>
        ) : rows.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-8">No redirects configured yet.</p>
        ) : (
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                  <TableHead className="text-[#390d58] font-semibold">Source</TableHead>
                  <TableHead className="text-[#390d58] font-semibold">Target</TableHead>
                  <TableHead className="w-20 text-[#390d58] font-semibold">Type</TableHead>
                  <TableHead className="w-16 text-[#390d58] font-semibold text-right">Hits</TableHead>
                  <TableHead className="w-20" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row, idx) => (
                  <TableRow key={row.id}
                    className={`font-mono text-xs ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'} ${row.is_active === 0 ? 'opacity-50' : ''}`}>
                    <TableCell className="text-[#390d58]">{row.source_path}</TableCell>
                    <TableCell className="text-muted-foreground max-w-[200px] truncate">{row.target_url}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className="text-[10px]">{row.redirect_type}</Badge>
                    </TableCell>
                    <TableCell className="text-right text-muted-foreground">{row.hit_count}</TableCell>
                    <TableCell>
                      <div className="flex gap-1 justify-end">
                        <button onClick={() => startEdit(row)}
                          className="p-1 rounded text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10">
                          <Pencil className="h-3.5 w-3.5" />
                        </button>
                        <button onClick={() => handleDelete(row.id)}
                          className="p-1 rounded text-muted-foreground hover:text-red-600 hover:bg-red-50">
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </TableCell>
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
