import { useRef, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, Search, Upload, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'

interface AttachmentResult {
  ID: number
  post_title: string
  post_type: string
  guid: string
}

interface ReplacePreview {
  attachment_id: number
  title: string
  url: string
  file: string
  path: string
  mime_type: string
  filesize: number
  width: number
  height: number
  generated_sizes: string[]
  edit_url: string
}

interface ReplaceResult {
  attachment_id: number
  url: string
  mime_changed: boolean
  old_mime: string
  new_mime: string
  generated_sizes: number
}

export function MediaReplaceTool() {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<AttachmentResult[]>([])
  const [selected, setSelected] = useState<AttachmentResult | null>(null)
  const [preview, setPreview] = useState<ReplacePreview | null>(null)
  const [searching, setSearching] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [done, setDone] = useState<ReplaceResult | null>(null)
  const [preserveFilename, setPreserveFilename] = useState(true)
  const [pendingFile, setPendingFile] = useState<File | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  const handleSearch = async () => {
    if (!query.trim()) return
    setSearching(true)
    setSelected(null)
    setPreview(null)
    setDone(null)
    try {
      const data = await api.get<AttachmentResult[]>(`/workshop/post-search?q=${encodeURIComponent(query)}&post_type=attachment`)
      setResults(data)
    } finally {
      setSearching(false)
    }
  }

  const selectAttachment = async (attachment: AttachmentResult) => {
    setSelected(attachment)
    setResults([])
    const data = await api.get<ReplacePreview>(`/workshop/media-replace/preview?attachment_id=${attachment.ID}`)
    setPreview(data)
  }

  const handleUpload = async () => {
    if (!selected || !fileRef.current?.files?.[0]) return

    const formData = new FormData()
    formData.append('file', fileRef.current.files[0])
    formData.append('attachment_id', String(selected.ID))
    formData.append('preserve_filename', preserveFilename ? '1' : '0')

    setUploading(true)
    try {
      const base = window.BeaconData?.restBase ?? '/wp-json/'
      const nonce = window.BeaconData?.nonce ?? ''

      const res = await fetch(`${base}beacon/v1/admin/workshop/media-replace`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': nonce },
        body: formData,
      })

      if (!res.ok) {
        const err = await res.json()
        alert(err.error ?? 'Upload failed')
        return
      }

      const result: ReplaceResult = await res.json()
      setDone(result)
      setSelected(null)
      setPreview(null)
      setResults([])
      setQuery('')
      setPendingFile(null)
      if (fileRef.current) fileRef.current.value = ''
    } finally {
      setUploading(false)
    }
  }

  const newMime = pendingFile?.type || ''
  const mimeWillChange = Boolean(preview?.mime_type && newMime && preview.mime_type !== newMime)

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Media Replace</CardTitle>
        <CardDescription>Replace an existing media file while keeping its attachment identity, with a metadata review before replacement.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {done && (
          <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="font-medium text-green-700">File replaced successfully.</p>
            <p className="text-xs text-green-700">Generated {done.generated_sizes} image size(s). {done.mime_changed ? `MIME changed from ${done.old_mime} to ${done.new_mime}.` : `MIME remained ${done.new_mime || done.old_mime}.`}</p>
            <a href={done.url} target="_blank" rel="noreferrer" className="mt-1 inline-block text-xs text-green-600 underline underline-offset-2">
              View new file
            </a>
          </div>
        )}

        <div>
          <p className="mb-2 text-sm font-medium text-[#390d58]">Step 1: Find the media file</p>
          <div className="flex gap-2">
            <input type="text" value={query} onChange={e => setQuery(e.target.value)} onKeyDown={e => e.key === 'Enter' && handleSearch()} placeholder="Search by filename..." className="flex-1 rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
            <Button size="sm" onClick={handleSearch} disabled={searching} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
              {searching ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
            </Button>
          </div>

          {results.length > 0 && (
            <div className="mt-2 rounded-xl border border-[#390d58]/10 overflow-hidden">
              {results.map(r => (
                <button key={r.ID} onClick={() => selectAttachment(r)} className="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition-colors hover:bg-[#390d58]/5">
                  <span className="text-[#390d58]">{r.post_title || r.guid}</span>
                  <span className="text-[10px] text-muted-foreground">#{r.ID}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        {selected && preview && (
          <div className="space-y-4">
            <div className="rounded-xl border border-[#390d58]/10 p-4">
              <p className="mb-2 text-sm font-medium text-[#390d58]">Current attachment review</p>
              <div className="grid gap-3 sm:grid-cols-2 text-xs text-muted-foreground">
                <p>File: {preview.file}</p>
                <p>MIME: {preview.mime_type}</p>
                <p>Size: {preview.filesize} bytes</p>
                <p>Dimensions: {preview.width > 0 ? `${preview.width} x ${preview.height}` : 'Not an image'}</p>
                <p className="sm:col-span-2">Generated sizes: {preview.generated_sizes.length > 0 ? preview.generated_sizes.join(', ') : 'None'}</p>
              </div>
              <a href={preview.edit_url} target="_blank" rel="noreferrer" className="mt-2 inline-block text-xs text-[#390d58] underline underline-offset-2">Open attachment edit screen</a>
            </div>

            <div>
              <p className="mb-2 text-sm font-medium text-[#390d58]">Step 2: Upload replacement for "{selected.post_title || selected.guid}"</p>
              <div className="mb-2 flex items-center gap-2 text-sm text-muted-foreground">
                <input id="preserve_filename" type="checkbox" checked={preserveFilename} onChange={e => setPreserveFilename(e.target.checked)} />
                <label htmlFor="preserve_filename">Keep the original filename and URL where possible</label>
              </div>
              <div className="flex gap-2 items-center">
                <input
                  ref={fileRef}
                  type="file"
                  onChange={e => setPendingFile(e.target.files?.[0] ?? null)}
                  className="flex-1 rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none file:mr-2 file:rounded file:border-0 file:bg-[#390d58]/10 file:px-2 file:py-0.5 file:text-xs file:text-[#390d58]"
                />
                <Button size="sm" onClick={handleUpload} disabled={uploading} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                  {uploading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
                  Replace
                </Button>
              </div>
            </div>

            {pendingFile && (
              <div className={`flex items-start gap-2 rounded-lg px-3 py-2 text-xs ${mimeWillChange ? 'border border-amber-200 bg-amber-50 text-amber-700' : 'border border-[#390d58]/10 bg-[#390d58]/[0.03] text-muted-foreground'}`}>
                {mimeWillChange && <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />}
                <div>
                  <p>Incoming file: {pendingFile.name}</p>
                  <p>{newMime || 'Unknown MIME'}{mimeWillChange ? `, replacing ${preview.mime_type}` : ''}</p>
                </div>
              </div>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
