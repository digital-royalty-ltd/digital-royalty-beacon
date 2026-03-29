import { useState, useMemo } from 'react'
import { X, ChevronRight, ChevronLeft, CheckCircle2, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { AiCharacter } from './AiCharacterCard'
import { api } from '@/lib/api'

// ── Types ─────────────────────────────────────────────────────────────────────

type FieldType  = 'multi-select' | 'single-select' | 'textarea'
type FormValues = Record<string, string | string[]>
type FormData   = Record<string, FormValues>

interface FieldOption { value: string; label: string }

interface StepField {
  key:          string
  label:        string
  type:         FieldType
  options?:     FieldOption[]
  placeholder?: string
}

interface StepConfig {
  id:       string
  title:    string
  subtitle: string
  fields:   StepField[]
}

interface Props {
  character:    AiCharacter
  characterKey: string
  onComplete:   () => void
  onDismiss:    () => void
}

// ── Step definitions ──────────────────────────────────────────────────────────

const GENERAL: StepConfig = {
  id:       'general',
  title:    "Let's get to know your business",
  subtitle: "I'll use these details to guide every campaign decision I make.",
  fields: [
    {
      key:   'goals',
      label: 'What are your primary marketing goals?',
      type:  'multi-select',
      options: [
        { value: 'brand_awareness',   label: 'Brand Awareness'    },
        { value: 'lead_generation',   label: 'Lead Generation'    },
        { value: 'sales_revenue',     label: 'Sales & Revenue'    },
        { value: 'retention',         label: 'Customer Retention' },
        { value: 'market_expansion',  label: 'Market Expansion'   },
      ],
    },
    {
      key:   'budget',
      label: "What's your monthly marketing budget?",
      type:  'single-select',
      options: [
        { value: 'under_1k', label: 'Under £1,000'   },
        { value: '1k_5k',    label: '£1,000–£5,000'  },
        { value: '5k_10k',   label: '£5,000–£10,000' },
        { value: '10k_25k',  label: '£10,000–£25,000'},
        { value: 'over_25k', label: '£25,000+'        },
      ],
    },
    {
      key:         'audience',
      label:       'Describe your target audience',
      type:        'textarea',
      placeholder: 'e.g. Small business owners in the UK looking for affordable digital marketing…',
    },
    {
      key:   'campaign_types',
      label: 'Which campaign types would you like me to manage?',
      type:  'multi-select',
      options: [
        { value: 'content', label: 'Content Marketing' },
        { value: 'seo',     label: 'SEO'               },
        { value: 'ppc',     label: 'PPC Advertising'   },
        { value: 'social',  label: 'Social Media'      },
      ],
    },
  ],
}

const CAMPAIGN_STEPS: Record<string, StepConfig> = {
  content: {
    id:       'content',
    title:    'Content Marketing',
    subtitle: 'Tell me about your content strategy and publishing goals.',
    fields: [
      {
        key:   'frequency',
        label: 'How often should we publish content?',
        type:  'single-select',
        options: [
          { value: 'daily',    label: 'Daily'         },
          { value: '3_4_week', label: '3–4× per week' },
          { value: 'weekly',   label: 'Weekly'        },
          { value: 'biweekly', label: 'Bi-weekly'     },
          { value: 'monthly',  label: 'Monthly'       },
        ],
      },
      {
        key:   'formats',
        label: 'What content formats should we focus on?',
        type:  'multi-select',
        options: [
          { value: 'blog_posts',        label: 'Blog Posts'        },
          { value: 'long_form_guides',  label: 'Long-form Guides'  },
          { value: 'case_studies',      label: 'Case Studies'      },
          { value: 'video_scripts',     label: 'Video Scripts'     },
          { value: 'infographics',      label: 'Infographics'      },
          { value: 'email_newsletters', label: 'Email Newsletters' },
        ],
      },
      {
        key:   'content_goals',
        label: "What's the primary goal of your content?",
        type:  'multi-select',
        options: [
          { value: 'organic_traffic',    label: 'Organic Traffic'    },
          { value: 'lead_generation',    label: 'Lead Generation'    },
          { value: 'brand_authority',    label: 'Brand Authority'    },
          { value: 'customer_education', label: 'Customer Education' },
        ],
      },
    ],
  },

  seo: {
    id:       'seo',
    title:    'SEO',
    subtitle: "Help me understand your current SEO position and where we're headed.",
    fields: [
      {
        key:   'maturity',
        label: 'How would you describe your current SEO maturity?',
        type:  'single-select',
        options: [
          { value: 'starting_out',     label: 'Just Starting Out'     },
          { value: 'some_foundation',  label: 'Some Foundation Built' },
          { value: 'well_established', label: 'Well Established'      },
        ],
      },
      {
        key:   'keyword_focus',
        label: 'What types of keywords should we prioritise?',
        type:  'multi-select',
        options: [
          { value: 'branded',       label: 'Branded Terms'               },
          { value: 'commercial',    label: 'Commercial Intent'            },
          { value: 'informational', label: 'Informational / Educational'  },
          { value: 'local',         label: 'Local Keywords'               },
        ],
      },
      {
        key:         'competitors',
        label:       'Are there specific competitors you want us to monitor?',
        type:        'textarea',
        placeholder: 'e.g. competitor1.com, competitor2.com',
      },
      {
        key:   'focus_areas',
        label: 'Which SEO areas need the most attention?',
        type:  'multi-select',
        options: [
          { value: 'technical_seo', label: 'Technical SEO'        },
          { value: 'on_page',       label: 'On-Page Optimisation' },
          { value: 'link_building', label: 'Link Building'        },
          { value: 'local_seo',     label: 'Local SEO'            },
        ],
      },
    ],
  },

  ppc: {
    id:       'ppc',
    title:    'PPC Advertising',
    subtitle: "Let's align on platforms, objectives, and what success looks like.",
    fields: [
      {
        key:   'platforms',
        label: 'Which advertising platforms are you using or want to use?',
        type:  'multi-select',
        options: [
          { value: 'google_ads',    label: 'Google Ads'    },
          { value: 'microsoft_ads', label: 'Microsoft Ads' },
          { value: 'meta_ads',      label: 'Meta Ads'      },
          { value: 'linkedin_ads',  label: 'LinkedIn Ads'  },
          { value: 'tiktok_ads',    label: 'TikTok Ads'    },
        ],
      },
      {
        key:   'objective',
        label: "What's your primary PPC objective?",
        type:  'single-select',
        options: [
          { value: 'drive_traffic',  label: 'Drive Website Traffic'     },
          { value: 'generate_leads', label: 'Generate Leads'            },
          { value: 'increase_sales', label: 'Increase Sales'            },
          { value: 'remarketing',    label: 'Retarget Existing Visitors' },
        ],
      },
      {
        key:         'success_metric',
        label:       'What does success look like? (e.g. target CPA, ROAS)',
        type:        'textarea',
        placeholder: 'e.g. Target cost per lead under £30, or 4× ROAS on product campaigns…',
      },
    ],
  },

  social: {
    id:       'social',
    title:    'Social Media',
    subtitle: "Tell me which platforms matter and how you want to show up.",
    fields: [
      {
        key:   'platforms',
        label: 'Which social platforms should we manage?',
        type:  'multi-select',
        options: [
          { value: 'facebook',  label: 'Facebook'    },
          { value: 'instagram', label: 'Instagram'   },
          { value: 'linkedin',  label: 'LinkedIn'    },
          { value: 'twitter_x', label: 'X (Twitter)' },
          { value: 'tiktok',    label: 'TikTok'      },
          { value: 'youtube',   label: 'YouTube'     },
        ],
      },
      {
        key:   'posting_frequency',
        label: 'How often should we post?',
        type:  'single-select',
        options: [
          { value: 'daily',    label: 'Daily'         },
          { value: '3_5_week', label: '3–5× per week' },
          { value: 'weekly',   label: 'Weekly'        },
        ],
      },
      {
        key:   'social_goals',
        label: 'What are your social media goals?',
        type:  'multi-select',
        options: [
          { value: 'brand_awareness',    label: 'Brand Awareness'    },
          { value: 'community_building', label: 'Community Building' },
          { value: 'lead_gen',           label: 'Lead Generation'    },
          { value: 'engagement',         label: 'Customer Engagement'},
        ],
      },
      {
        key:   'tone',
        label: 'What tone of voice should we use?',
        type:  'single-select',
        options: [
          { value: 'professional',   label: 'Professional'   },
          { value: 'conversational', label: 'Conversational' },
          { value: 'bold',           label: 'Bold & Direct'  },
          { value: 'educational',    label: 'Educational'    },
          { value: 'playful',        label: 'Playful'        },
        ],
      },
    ],
  },
}

// ── Avatar panel ──────────────────────────────────────────────────────────────

function AvatarPanel({ character }: { character: AiCharacter }) {
  const { label, emoji, tagline, color, image_url } = character
  return (
    <div
      className="hidden md:flex w-52 lg:w-60 shrink-0 flex-col relative overflow-hidden"
      style={{ background: `linear-gradient(160deg, ${color}ee 0%, ${color}88 100%)` }}
    >
      {image_url ? (
        <img
          src={image_url}
          alt={label}
          className="w-full flex-1 object-cover object-top"
        />
      ) : (
        <div className="flex-1 flex items-center justify-center">
          <div className="absolute -top-6 -right-6 w-32 h-32 rounded-full bg-white/10" />
          <div className="absolute top-4 right-16 w-14 h-14 rounded-full bg-white/10" />
          <span className="text-8xl relative z-10">{emoji}</span>
        </div>
      )}
      <div
        className="absolute bottom-0 left-0 right-0 p-5"
        style={{ background: `linear-gradient(to top, ${color}f5 0%, transparent 100%)` }}
      >
        <p className="text-white font-bold text-base leading-tight">{label}</p>
        <p className="text-white/70 text-xs mt-0.5 leading-snug">{tagline}</p>
      </div>
    </div>
  )
}

// ── Field renderers ───────────────────────────────────────────────────────────

function MultiSelectField({
  field, values, color, toggle,
}: {
  field:  StepField
  values: string[]
  color:  string
  toggle: (v: string) => void
}) {
  return (
    <div className="flex flex-wrap gap-2">
      {field.options!.map(opt => {
        const active = values.includes(opt.value)
        return (
          <button
            key={opt.value}
            type="button"
            onClick={() => toggle(opt.value)}
            className="px-3.5 py-1.5 rounded-full text-sm font-medium border-2 transition-all"
            style={{
              borderColor:     active ? color : `${color}40`,
              backgroundColor: active ? color : 'transparent',
              color:           active ? '#ffffff' : color,
            }}
          >
            {active && <span className="mr-1 text-[10px]">✓</span>}
            {opt.label}
          </button>
        )
      })}
    </div>
  )
}

function SingleSelectField({
  field, value, color, select,
}: {
  field:  StepField
  value:  string
  color:  string
  select: (v: string) => void
}) {
  return (
    <div className="flex flex-wrap gap-2">
      {field.options!.map(opt => {
        const active = value === opt.value
        return (
          <button
            key={opt.value}
            type="button"
            onClick={() => select(opt.value)}
            className="px-3.5 py-1.5 rounded-full text-sm font-medium border-2 transition-all"
            style={{
              borderColor:     active ? color : `${color}40`,
              backgroundColor: active ? color : 'transparent',
              color:           active ? '#ffffff' : color,
            }}
          >
            {opt.label}
          </button>
        )
      })}
    </div>
  )
}

// ── Done screen ───────────────────────────────────────────────────────────────

function DoneScreen({ character, onClose }: { character: AiCharacter; onClose: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center flex-1 p-10 text-center">
      <CheckCircle2 className="h-10 w-10 mb-4" style={{ color: character.color }} />
      <h2 className="text-2xl font-bold mb-2" style={{ color: character.color }}>
        {character.label} is ready.
      </h2>
      <p className="text-sm text-muted-foreground max-w-xs mb-8 leading-relaxed">
        Your answers are now woven into the {character.label} strategy framework. Digital Royalty's methodology will shape every campaign decision going forward.
      </p>
      <Button
        onClick={onClose}
        className="text-white px-8"
        style={{ backgroundColor: character.color }}
      >
        Let's go
      </Button>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────

export function CampaignOnboarding({ character, characterKey, onComplete, onDismiss }: Props) {
  const [stepIndex,  setStepIndex]  = useState(0)
  const [formData,   setFormData]   = useState<FormData>({})
  const [submitting, setSubmitting] = useState(false)
  const [done,       setDone]       = useState(false)

  const { color } = character

  // Derive steps: general always first, then one per selected campaign type
  const steps = useMemo<StepConfig[]>(() => {
    const selected = (formData.general?.campaign_types as string[]) ?? []
    const extra    = selected
      .filter(t => t in CAMPAIGN_STEPS)
      .map(t => CAMPAIGN_STEPS[t])
    return [GENERAL, ...extra]
  }, [formData.general?.campaign_types])

  const step     = steps[stepIndex]
  const stepData = formData[step.id] ?? {}
  const isLast   = stepIndex === steps.length - 1

  function setValue(key: string, value: string | string[]) {
    setFormData(prev => ({
      ...prev,
      [step.id]: { ...(prev[step.id] ?? {}), [key]: value },
    }))
  }

  function toggleMulti(key: string, value: string) {
    const cur  = (stepData[key] as string[]) ?? []
    const next = cur.includes(value) ? cur.filter(v => v !== value) : [...cur, value]
    setValue(key, next)
  }

  function canAdvance(): boolean {
    for (const field of step.fields) {
      if (field.type === 'multi-select') {
        const val = stepData[field.key] as string[] | undefined
        if (!val || val.length === 0) return false
      }
      if (field.type === 'single-select') {
        if (!stepData[field.key]) return false
      }
    }
    return true
  }

  async function handleNext() {
    if (!isLast) {
      setStepIndex(i => i + 1)
      return
    }
    setSubmitting(true)
    try {
      await api.post('/campaigns/ai', { key: characterKey })
      await api.post('/campaigns/onboarding', formData)
      setDone(true)
    } catch {
      // silent — done screen still shows, data may not have saved
      setDone(true)
    } finally {
      setSubmitting(false)
    }
  }

  // Step indicator label (e.g. "Step 2 of 4")
  const campaignStepLabels: Record<string, string> = {
    content: 'Content', seo: 'SEO', ppc: 'PPC', social: 'Social',
  }

  return (
    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div
        className="bg-white rounded-3xl overflow-hidden w-full max-w-3xl shadow-2xl flex"
        style={{ maxHeight: '90vh' }}
      >
        {/* Left: avatar */}
        <AvatarPanel character={character} />

        {/* Right: form */}
        <div className="flex flex-col flex-1 overflow-hidden">

          {/* Top bar — progress + close */}
          <div className="flex items-center justify-between px-7 pt-6 pb-2 shrink-0">
            <div className="flex items-center gap-2">
              <div className="flex gap-1.5 items-center">
                {steps.map((s, i) => (
                  <span
                    key={s.id}
                    className="h-1.5 rounded-full transition-all duration-300"
                    style={{
                      width:           i === stepIndex ? '20px' : '6px',
                      backgroundColor: i <= stepIndex ? color : `${color}25`,
                    }}
                  />
                ))}
              </div>
              <span className="text-xs text-muted-foreground ml-1">
                {stepIndex + 1} of {steps.length}
              </span>
            </div>
            <button
              onClick={onDismiss}
              className="text-muted-foreground hover:text-foreground transition-colors p-1 rounded-lg hover:bg-muted"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Content area */}
          {done ? (
            <DoneScreen character={character} onClose={onComplete} />
          ) : (
            <>
              {/* Scrollable form body */}
              <div className="flex-1 overflow-y-auto px-7 py-4">
                {/* Step header */}
                <div className="mb-5">
                  {step.id !== 'general' && (
                    <span
                      className="inline-block text-[10px] font-bold uppercase tracking-widest px-2.5 py-0.5 rounded-full mb-2"
                      style={{ backgroundColor: `${color}18`, color }}
                    >
                      {campaignStepLabels[step.id] ?? step.id}
                    </span>
                  )}
                  <h2 className="text-lg font-bold text-foreground leading-tight">{step.title}</h2>
                  <p className="text-sm text-muted-foreground mt-0.5">{step.subtitle}</p>
                </div>

                {/* Fields */}
                <div className="space-y-6">
                  {step.fields.map(field => (
                    <div key={field.key}>
                      <p className="text-sm font-semibold text-foreground mb-2.5">{field.label}</p>

                      {field.type === 'multi-select' && (
                        <MultiSelectField
                          field={field}
                          values={(stepData[field.key] as string[]) ?? []}
                          color={color}
                          toggle={v => toggleMulti(field.key, v)}
                        />
                      )}

                      {field.type === 'single-select' && (
                        <SingleSelectField
                          field={field}
                          value={(stepData[field.key] as string) ?? ''}
                          color={color}
                          select={v => setValue(field.key, v)}
                        />
                      )}

                      {field.type === 'textarea' && (
                        <textarea
                          rows={3}
                          placeholder={field.placeholder}
                          value={(stepData[field.key] as string) ?? ''}
                          onChange={e => setValue(field.key, e.target.value)}
                          className="w-full rounded-xl border border-input bg-background px-4 py-3 text-sm resize-none focus:outline-none transition-shadow"
                          onFocus={e  => { e.currentTarget.style.boxShadow = `0 0 0 2px ${color}50` }}
                          onBlur={e   => { e.currentTarget.style.boxShadow = '' }}
                        />
                      )}
                    </div>
                  ))}
                </div>
              </div>

              {/* Footer nav */}
              <div className="flex items-center justify-between px-7 py-4 border-t shrink-0">
                {stepIndex > 0 ? (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setStepIndex(i => i - 1)}
                    className="text-muted-foreground"
                  >
                    <ChevronLeft className="h-4 w-4 mr-1" /> Back
                  </Button>
                ) : (
                  <div />
                )}

                <Button
                  size="sm"
                  disabled={!canAdvance() || submitting}
                  onClick={handleNext}
                  className="text-white px-6 disabled:opacity-40"
                  style={{ backgroundColor: color }}
                >
                  {submitting ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : isLast ? (
                    <> Finish <CheckCircle2 className="h-3.5 w-3.5 ml-1.5" /> </>
                  ) : (
                    <> Next <ChevronRight className="h-3.5 w-3.5 ml-1" /> </>
                  )}
                </Button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
