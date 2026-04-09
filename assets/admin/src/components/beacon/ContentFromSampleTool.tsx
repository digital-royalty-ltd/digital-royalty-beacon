import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ArrowLeft, CheckCircle2, FileText, Globe, Loader2 } from 'lucide-react'
import { api } from '@/lib/api'

interface Props {
  onBack: () => void
}

export function ContentFromSampleTool({ onBack }: Props) {
  const [url, setUrl] = useState('')
  const [bodyText, setBodyText] = useState('')
  const [generating, setGenerating] = useState(false)
  const [queued, setQueued] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const canSubmit = (url.trim() || bodyText.trim()) && !generating

  const handleGenerate = async () => {
    if (!canSubmit) return
    setGenerating(true)
    setError(null)
    try {
      await api.post('/content-from-sample/generate', {
        url: url.trim() || undefined,
        body_text: bodyText.trim() || undefined,
      })
      setQueued(true)
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Request failed.'
      setError(msg)
    } finally {
      setGenerating(false)
    }
  }

  const handleReset = () => {
    setQueued(false)
    setUrl('')
    setBodyText('')
    setError(null)
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={onBack}
          className="p-1.5 rounded-lg text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-xl font-semibold text-[#390d58]">Create Content From Sample</h2>
          <p className="text-sm text-muted-foreground">Provide a URL or paste content to generate a fresh rewritten draft.</p>
        </div>
      </div>

      {queued ? (
        <Card className="border-[#390d58]/20 overflow-hidden">
          <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
          <CardContent className="pt-8 pb-10 flex flex-col items-center text-center gap-4">
            <div className="rounded-2xl bg-green-50 p-4">
              <CheckCircle2 className="h-10 w-10 text-green-600" />
            </div>
            <div>
              <p className="text-lg font-semibold text-[#390d58]">Draft queued</p>
              <p className="text-sm text-muted-foreground mt-1 max-w-sm">
                Beacon is analysing the sample and writing a fresh draft. It will appear in
                <strong className="text-[#390d58]"> Posts &rarr; Drafts</strong> when ready.
              </p>
            </div>
            <div className="flex gap-3 mt-2">
              <Button onClick={handleReset} variant="outline" className="border-[#390d58]/20 text-[#390d58]">
                Generate another
              </Button>
              <Button
                onClick={() => window.open(
                  `${window.BeaconData?.adminUrl ?? '/wp-admin/'}edit.php?post_status=draft`,
                  '_blank',
                  'noopener,noreferrer'
                )}
                className="bg-[#390d58] hover:bg-[#4a1170] text-white"
              >
                View drafts
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="border-[#390d58]/20 overflow-hidden">
          <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
          <CardHeader>
            <CardTitle className="text-lg text-[#390d58]">Sample source</CardTitle>
            <CardDescription>
              Provide a URL to an existing page, paste the content directly, or both. Beacon will analyse the sample, extract the key themes, and produce a fresh rewritten draft.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">

            {error && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
            )}

            <div>
              <label className="text-sm font-medium text-[#390d58] mb-2 flex items-center gap-1.5">
                <Globe className="h-3.5 w-3.5" />
                Source URL
              </label>
              <Input
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="https://example.com/article-to-rewrite"
                className="border-[#390d58]/20 focus:border-[#390d58] focus:ring-[#390d58]/20"
              />
              <p className="text-xs text-muted-foreground mt-1">
                Optional if you paste content below.
              </p>
            </div>

            <div>
              <label className="text-sm font-medium text-[#390d58] mb-2 flex items-center gap-1.5">
                <FileText className="h-3.5 w-3.5" />
                Content
              </label>
              <textarea
                value={bodyText}
                onChange={(e) => setBodyText(e.target.value)}
                placeholder="Paste the page content here..."
                rows={8}
                className="flex w-full rounded-md border border-[#390d58]/20 bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#390d58]/20 focus-visible:border-[#390d58] disabled:cursor-not-allowed disabled:opacity-50 resize-none"
              />
              <p className="text-xs text-muted-foreground mt-1">
                Optional if you provided a URL above.
              </p>
            </div>

            <Button
              onClick={handleGenerate}
              disabled={!canSubmit}
              className="w-full gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white disabled:opacity-50"
            >
              {generating
                ? <><Loader2 className="h-4 w-4 animate-spin" /> Submitting…</>
                : 'Generate Draft'}
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
