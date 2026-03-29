import { useEffect, useRef, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ArrowLeft, Send, Loader2, CheckCircle2, AlertCircle } from 'lucide-react'
import { api } from '@/lib/api'
import { JsonEditor } from './JsonEditor'
import type { Content, ContentErrors, OnChangeStatus } from 'vanilla-jsoneditor'

interface ReportDetail {
  type:         string
  label:        string
  version:      number
  status:       string
  generated_at: string | null
  submitted_at: string | null
  payload:      unknown
}

interface Props {
  reportType: string
  onBack:     () => void
}

function dateLabel(d: string | null): string {
  if (!d) return '—'
  const dt = new Date(d.endsWith('Z') ? d : d + 'Z')
  return dt.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}

export function ReportEditor({ reportType, onBack }: Props) {
  const [report,      setReport]      = useState<ReportDetail | null>(null)
  const [loading,     setLoading]     = useState(true)
  const [loadError,   setLoadError]   = useState<string | null>(null)

  const [content,     setContent]     = useState<Content>({ json: {} })
  const [hasErrors,   setHasErrors]   = useState(false)
  const [isDirty,     setIsDirty]     = useState(false)

  const [submitting,  setSubmitting]  = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [submitted,   setSubmitted]   = useState(false)

  // Keep a ref to current content so the submit handler always has latest value
  const contentRef = useRef<Content>({ json: {} })

  useEffect(() => {
    setLoading(true)
    api.get<ReportDetail>(`/reports/${reportType}`)
      .then(data => {
        setReport(data)
        const initial: Content = { json: data.payload ?? {} }
        setContent(initial)
        contentRef.current = initial
      })
      .catch(() => setLoadError('Could not load report.'))
      .finally(() => setLoading(false))
  }, [reportType])

  const handleChange = (
    newContent: Content,
    _prev: Content,
    { contentErrors }: OnChangeStatus,
  ) => {
    setContent(newContent)
    contentRef.current = newContent
    const errs = contentErrors as ContentErrors | undefined
    setHasErrors(
      !!(errs && 'parseError' in errs && errs.parseError) ||
      !!(errs && 'validationErrors' in errs && errs.validationErrors?.length)
    )
    setIsDirty(true)
    setSubmitted(false)
  }

  const handleResubmit = async () => {
    if (hasErrors) return

    // Extract JSON from current editor content
    let payload: unknown
    const c = contentRef.current
    if ('json' in c) {
      payload = c.json
    } else {
      try {
        payload = JSON.parse(c.text)
      } catch {
        setSubmitError('Invalid JSON — fix errors before resubmitting.')
        return
      }
    }

    setSubmitting(true)
    setSubmitError(null)
    try {
      await api.post(`/reports/${reportType}/resubmit`, { payload })
      setIsDirty(false)
      setSubmitted(true)
      // Update local status display
      setReport(r => r ? { ...r, status: 'submitted', submitted_at: new Date().toISOString() } : r)
    } catch (e: unknown) {
      setSubmitError(e instanceof Error ? e.message : 'Resubmission failed.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Back nav */}
      <div className="flex items-center gap-3">
        <button
          onClick={onBack}
          className="p-1.5 rounded-lg text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-xl font-semibold text-[#390d58]">
            {report?.label ?? 'Report Editor'}
          </h2>
          <p className="text-sm text-muted-foreground">
            Edit the data Beacon holds for this report, then resubmit to update its knowledge.
          </p>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-48 text-muted-foreground gap-2">
          <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" />
          <span className="text-sm">Loading report…</span>
        </div>
      ) : loadError ? (
        <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{loadError}</p>
      ) : report ? (
        <Card className="border-[#390d58]/20 overflow-hidden">
          <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
          <CardHeader>
            <div className="flex items-start justify-between gap-4">
              <div>
                <CardTitle className="text-lg text-[#390d58]">{report.label}</CardTitle>
                <CardDescription className="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs">
                  <span>Version {report.version}</span>
                  <span>Generated: {dateLabel(report.generated_at)}</span>
                  {report.submitted_at && <span>Last submitted: {dateLabel(report.submitted_at)}</span>}
                </CardDescription>
              </div>
              <Badge
                variant="outline"
                className={
                  report.status === 'submitted'
                    ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                    : report.status === 'failed'
                    ? 'bg-red-500/10 text-red-600 border-red-500/20'
                    : 'bg-amber-500/10 text-amber-600 border-amber-500/20'
                }
              >
                {report.status}
              </Badge>
            </div>
          </CardHeader>

          <CardContent className="space-y-4">
            {/* Info banner */}
            <div className="rounded-lg bg-[#390d58]/5 border border-[#390d58]/10 px-4 py-3 text-sm text-[#390d58]">
              <strong>Tip:</strong> Use tree view to expand and edit individual fields. Switch to the{' '}
              <code className="text-xs bg-[#390d58]/10 px-1 py-0.5 rounded">&#123;&#125;</code> code view for bulk edits.
              Changes are only saved when you click <strong>Save &amp; Resubmit</strong>.
            </div>

            {/* JSON editor */}
            <JsonEditor
              content={content}
              onChange={handleChange}
              height={520}
            />

            {/* Validation error */}
            {hasErrors && (
              <p className="flex items-center gap-2 text-sm text-red-600">
                <AlertCircle className="h-4 w-4 shrink-0" />
                Fix JSON errors before resubmitting.
              </p>
            )}

            {/* Submit error */}
            {submitError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {submitError}
              </p>
            )}

            {/* Success */}
            {submitted && !isDirty && (
              <p className="flex items-center gap-2 text-sm text-emerald-600">
                <CheckCircle2 className="h-4 w-4 shrink-0" />
                Report saved and resubmitted to Beacon successfully.
              </p>
            )}

            {/* Actions */}
            <div className="flex items-center justify-between pt-2">
              <Button
                variant="outline"
                onClick={onBack}
                className="border-[#390d58]/20 text-[#390d58]"
              >
                Back to configuration
              </Button>
              <Button
                onClick={handleResubmit}
                disabled={submitting || hasErrors || (!isDirty && submitted)}
                className="gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white"
              >
                {submitting
                  ? <><Loader2 className="h-4 w-4 animate-spin" /> Submitting…</>
                  : <><Send className="h-4 w-4" /> Save &amp; Resubmit</>}
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}
