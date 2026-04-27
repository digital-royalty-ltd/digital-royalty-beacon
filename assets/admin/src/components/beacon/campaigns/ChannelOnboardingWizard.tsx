import { useEffect, useMemo, useState } from 'react'
import { Loader2, AlertTriangle, X, CheckCircle2, Info } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import type { ChannelEntry } from './ChannelSidebar'

/**
 * Channel onboarding questionnaire.
 *
 * The schema is fetched from Laravel — this component renders dynamically
 * from whatever the backend returns. Adding a new question is a registry
 * edit, not a UI rewrite.
 *
 * Two modes:
 *   - first-time: shown when channel.billing.status === 'awaiting_onboarding'.
 *     All required fields must be filled before the agent can run.
 *   - edit: re-opened from the setup form to revise answers later.
 *
 * Answers route into the right column (foundation fields) or into
 * channel_setup JSON (channel-specific) on the backend.
 */

interface QuestionOption {
  value: string
  label: string
}

interface Question {
  key:         string
  target:      string
  label:       string
  type:        'text' | 'textarea' | 'number' | 'select' | 'multiselect'
  placeholder?: string
  options?:    QuestionOption[]
  required?:   boolean
  why?:        string
}

interface Section {
  title:    string
  intro?:   string | null
  questions: Question[]
}

interface Schema {
  channel:  string
  label:    string
  sections: Section[]
}

interface SchemaResponse {
  ok:              boolean
  schema:          Schema
  answers:         Record<string, unknown>
  onboarded_at:    string | null
  channel_status:  string | null
}

interface SubmitErrorPayload {
  ok:      false
  message: string
  errors?: Record<string, string>
}

interface Props {
  channel:     ChannelEntry
  /** First-time onboarding has slightly different framing than re-edits. */
  mode:        'first-time' | 'edit'
  /** Fired with the fresh channels list once the wizard has saved. */
  onComplete:  (channels: ChannelEntry[]) => void
  /** First-time mode: clicking "skip for now" is disallowed (exit to overview only available after submit). Edit mode: allowed. */
  onCancel?:   () => void
}

export function ChannelOnboardingWizard({ channel, mode, onComplete, onCancel }: Props) {
  const [schema, setSchema]     = useState<Schema | null>(null)
  const [values, setValues]     = useState<Record<string, unknown>>({})
  const [errors, setErrors]     = useState<Record<string, string>>({})
  const [topError, setTopError] = useState<string | null>(null)
  const [loading, setLoading]   = useState(true)
  const [saving, setSaving]     = useState(false)

  // Fetch schema + current answers on mount and whenever the channel changes.
  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setErrors({})
    setTopError(null)
    api.get<SchemaResponse>(`/campaigns/channels/${channel.key}/onboarding`)
      .then(res => {
        if (cancelled) return
        setSchema(res.schema)
        setValues(res.answers ?? {})
      })
      .catch(e => {
        if (cancelled) return
        setTopError(e instanceof Error ? e.message : 'Could not load onboarding form.')
      })
      .finally(() => { if (!cancelled) setLoading(false) })

    return () => { cancelled = true }
  }, [channel.key])

  const requiredKeys = useMemo(() => {
    const keys: string[] = []
    schema?.sections.forEach(s => s.questions.forEach(q => { if (q.required) keys.push(q.key) }))
    return keys
  }, [schema])

  const requiredCompleteCount = useMemo(() => {
    return requiredKeys.filter(k => {
      const v = values[k]
      if (v === undefined || v === null || v === '') return false
      if (Array.isArray(v) && v.length === 0) return false
      return true
    }).length
  }, [requiredKeys, values])

  const setValue = (key: string, value: unknown) => {
    setValues(prev => ({ ...prev, [key]: value }))
    if (errors[key]) {
      setErrors(prev => {
        const next = { ...prev }
        delete next[key]
        return next
      })
    }
  }

  const submit = async () => {
    setSaving(true)
    setErrors({})
    setTopError(null)
    try {
      const res = await api.post<{ channels: ChannelEntry[] }>(
        `/campaigns/channels/${channel.key}/onboarding`,
        { answers: values }
      )
      onComplete(res.channels)
    } catch (e) {
      // The api helper rejects with the raw response body when status is 4xx;
      // surface field errors when present.
      const payload = (e as { response?: SubmitErrorPayload })?.response
      if (payload?.errors) {
        setErrors(payload.errors)
        setTopError(payload.message ?? 'Some required fields are missing.')
        // Scroll to first error.
        const firstKey = Object.keys(payload.errors)[0]
        if (firstKey) {
          requestAnimationFrame(() => {
            document.getElementById(`onboarding-q-${firstKey}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
          })
        }
      } else {
        setTopError(e instanceof Error ? e.message : 'Could not save onboarding.')
      }
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="flex-1 min-w-0 flex items-center justify-center py-16">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (!schema) {
    return (
      <div className="flex-1 min-w-0 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-900">
        {topError ?? 'Could not load the onboarding form for this channel.'}
      </div>
    )
  }

  const requiredTotal = requiredKeys.length
  const allRequiredAnswered = requiredCompleteCount === requiredTotal

  return (
    <div className="flex-1 min-w-0 space-y-5">
      {/* Header */}
      <div className="rounded-2xl border-2 border-[#390d58]/15 bg-[#390d58]/5 p-5">
        <div className="flex items-start gap-3">
          <Info className="h-5 w-5 text-[#390d58] shrink-0 mt-0.5" />
          <div className="flex-1 min-w-0">
            <h3 className="text-lg font-bold text-[#390d58]">
              {mode === 'first-time' ? `Onboard ${schema.label}` : `Edit ${schema.label} onboarding`}
            </h3>
            <p className="text-xs text-muted-foreground mt-1">
              {mode === 'first-time'
                ? `Answer the questions below to give ${channel.agent?.label ?? 'the agent'} the foundational context it needs. The agent won't run sessions until this is complete.`
                : `Update the foundations and brand context the agent reads on every session.`}
            </p>
            <p className="text-[11px] text-muted-foreground mt-2">
              {requiredCompleteCount}/{requiredTotal} required fields complete
            </p>
          </div>
        </div>
      </div>

      {topError && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900 flex items-start gap-2">
          <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
          <span>{topError}</span>
        </div>
      )}

      {schema.sections.map((section, i) => (
        <SectionBlock
          key={i}
          section={section}
          values={values}
          errors={errors}
          onChange={setValue}
        />
      ))}

      {/* Footer */}
      <div className="flex items-center justify-between gap-2 pt-2 sticky bottom-0 bg-background/95 backdrop-blur py-3 border-t">
        {mode === 'edit' && onCancel ? (
          <Button variant="outline" size="sm" onClick={onCancel} disabled={saving}>
            <X className="h-3.5 w-3.5 mr-1.5" />
            Cancel
          </Button>
        ) : <span />}

        <Button
          size="sm"
          onClick={submit}
          disabled={saving || !allRequiredAnswered}
          className="bg-[#390d58] hover:bg-[#2d0a47] text-white"
        >
          {saving
            ? <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />
            : <CheckCircle2 className="h-3.5 w-3.5 mr-1.5" />}
          {mode === 'first-time' ? 'Complete onboarding' : 'Save changes'}
        </Button>
      </div>
    </div>
  )
}

// ── Section + question rendering ────────────────────────────────────────────

function SectionBlock({
  section,
  values,
  errors,
  onChange,
}: {
  section: Section
  values:  Record<string, unknown>
  errors:  Record<string, string>
  onChange: (key: string, value: unknown) => void
}) {
  return (
    <section className="rounded-xl border bg-white p-5 space-y-4">
      <header>
        <h4 className="text-sm font-semibold text-[#390d58]">{section.title}</h4>
        {section.intro && (
          <p className="text-[11px] text-muted-foreground mt-1">{section.intro}</p>
        )}
      </header>

      <div className="space-y-4">
        {section.questions.map(q => (
          <QuestionField
            key={q.key}
            question={q}
            value={values[q.key]}
            error={errors[q.key]}
            onChange={v => onChange(q.key, v)}
          />
        ))}
      </div>
    </section>
  )
}

function QuestionField({
  question,
  value,
  error,
  onChange,
}: {
  question: Question
  value:    unknown
  error?:   string
  onChange: (value: unknown) => void
}) {
  const inputId = `onboarding-q-${question.key}`
  const baseClass = 'w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30'
  const errorClass = error ? 'border-red-300 bg-red-50' : ''

  return (
    <div id={inputId}>
      <div className="flex items-center gap-1.5 mb-1">
        <label htmlFor={inputId} className="text-xs font-medium text-[#390d58]">
          {question.label}
        </label>
        {question.required && <span className="text-red-500 text-xs">*</span>}
      </div>

      {question.type === 'textarea' && (
        <textarea
          id={inputId}
          rows={3}
          value={typeof value === 'string' ? value : ''}
          onChange={e => onChange(e.target.value)}
          placeholder={question.placeholder ?? ''}
          className={`${baseClass} ${errorClass}`}
        />
      )}

      {question.type === 'text' && (
        <input
          id={inputId}
          type="text"
          value={typeof value === 'string' ? value : ''}
          onChange={e => onChange(e.target.value)}
          placeholder={question.placeholder ?? ''}
          className={`${baseClass} ${errorClass}`}
        />
      )}

      {question.type === 'number' && (
        <input
          id={inputId}
          type="number"
          value={value === null || value === undefined ? '' : String(value)}
          onChange={e => onChange(e.target.value === '' ? null : Number(e.target.value))}
          placeholder={question.placeholder ?? ''}
          className={`${baseClass} ${errorClass}`}
        />
      )}

      {question.type === 'select' && (
        <select
          id={inputId}
          value={typeof value === 'string' ? value : ''}
          onChange={e => onChange(e.target.value)}
          className={`${baseClass} bg-white ${errorClass}`}
        >
          <option value="">— Pick one —</option>
          {(question.options ?? []).map(opt => (
            <option key={opt.value} value={opt.value}>{opt.label}</option>
          ))}
        </select>
      )}

      {question.type === 'multiselect' && (
        <div className={`rounded-md border p-2 space-y-1 ${errorClass || ''}`}>
          {(question.options ?? []).map(opt => {
            const selected = Array.isArray(value) && value.includes(opt.value)
            return (
              <label
                key={opt.value}
                className="flex items-center gap-2 text-sm cursor-pointer hover:bg-muted/40 rounded px-2 py-1"
              >
                <input
                  type="checkbox"
                  checked={selected}
                  onChange={() => {
                    const current = Array.isArray(value) ? (value as string[]) : []
                    onChange(selected
                      ? current.filter(v => v !== opt.value)
                      : [...current, opt.value])
                  }}
                />
                <span>{opt.label}</span>
              </label>
            )
          })}
        </div>
      )}

      {question.why && (
        <p className="text-[11px] text-muted-foreground mt-1.5">{question.why}</p>
      )}

      {error && (
        <p className="text-[11px] text-red-600 mt-1">{error}</p>
      )}
    </div>
  )
}
