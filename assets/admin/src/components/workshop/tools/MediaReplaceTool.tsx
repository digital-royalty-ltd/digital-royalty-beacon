import { useRef, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Loader2, Search, Upload } from 'lucide-react'
import { api } from '@/lib/api'

interface AttachmentResult {
  ID:         number
  post_title: string
  post_type:  string
  guid:       string
}

interface ReplaceResult {
  attachment_id: number
  url:           string
}

export function MediaReplaceTool() {
  const [query,     setQuery]     = useState('')
  const [results,   setResults]   = useState<AttachmentResult[]>([])
  const [selected,  setSelected]  = useState<AttachmentResult | null>(null)
  const [searching, setSearching] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [done,      setDone]      = useState<ReplaceResult | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  const handleSearch = async () => {
    if (!query.trim()) return
    setSearching(true)
    setSelected(null)
    setDone(null)
    try {
      const data = await api.get<AttachmentResult[]>(
        `/workshop/post-search?q=${encodeURIComponent(query)}&post_type=attachment`
      )
      setResults(data)
    } finally {
      setSearching(false)
    }
  }

  const handleUpload = async () => {
    if (!selected || !fileRef.current?.files?.[0]) return

    const formData = new FormData()
    formData.append('file', fileRef.current.files[0])
    formData.append('attachment_id', String(selected.ID))

    setUploading(true)
    try {
      // Use raw fetch since api.post sends JSON
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
      setResults([])
      setQuery('')
      if (fileRef.current) fileRef.current.value = ''
    } finally {
      setUploading(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Media Replace</CardTitle>
        <CardDescription>Replace an existing media file while keeping its URL and post associations.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">

        {done && (
          <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
            <p className="text-green-700 font-medium">File replaced successfully.</p>
            <a href={done.url} target="_blank" rel="noreferrer"
              className="text-green-600 underline underline-offset-2 text-xs mt-1 inline-block">
              View new file
            </a>
          </div>
        )}

        <div>
          <p className="text-sm font-medium text-[#390d58] mb-2">Step 1: Find the media file</p>
          <div className="flex gap-2">
            <input type="text" value={query} onChange={e => setQuery(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && handleSearch()}
              placeholder="Search by filename…"
              className="flex-1 text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
            <Button size="sm" onClick={handleSearch} disabled={searching}
              className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
              {searching ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
            </Button>
          </div>

          {results.length > 0 && (
            <div className="mt-2 rounded-xl border border-[#390d58]/10 overflow-hidden">
              {results.map(r => (
                <button key={r.ID} onClick={() => { setSelected(r); setResults([]) }}
                  className="w-full flex items-center justify-between px-3 py-2 text-sm text-left hover:bg-[#390d58]/5 transition-colors">
                  <span className="text-[#390d58]">{r.post_title || r.guid}</span>
                  <span className="text-[10px] text-muted-foreground">#{r.ID}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        {selected && (
          <div>
            <p className="text-sm font-medium text-[#390d58] mb-2">
              Step 2: Upload replacement for "{selected.post_title || selected.guid}"
            </p>
            <div className="flex gap-2 items-center">
              <input ref={fileRef} type="file"
                className="flex-1 text-sm border border-[#390d58]/20 rounded-lg px-3 py-2 focus:outline-none file:mr-2 file:text-xs file:border-0 file:bg-[#390d58]/10 file:text-[#390d58] file:rounded file:px-2 file:py-0.5" />
              <Button size="sm" onClick={handleUpload} disabled={uploading}
                className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                {uploading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
                Replace
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
