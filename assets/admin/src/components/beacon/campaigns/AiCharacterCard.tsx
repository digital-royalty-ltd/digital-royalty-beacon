import { CheckCircle2 } from 'lucide-react'

export interface AiCharacter {
  label:       string
  emoji:       string
  tagline:     string
  traits:      string[]
  description: string
  color:       string
  image_url:   string | null
}

interface Props {
  id:        string
  character: AiCharacter
  selected:  boolean
  onSelect:  (id: string) => void
  saving:    boolean
}

export function AiCharacterCard({ id, character, selected, onSelect, saving }: Props) {
  const { label, emoji, tagline, traits, description, color, image_url } = character

  return (
    <button
      onClick={() => !saving && onSelect(id)}
      disabled={saving}
      className="relative text-left rounded-2xl overflow-hidden w-full h-full flex flex-col transition-all focus-visible:outline-none focus-visible:ring-2 hover:shadow-xl hover:shadow-black/10 hover:-translate-y-0.5"
      style={{
        boxShadow: selected ? `0 0 0 3px ${color}, 0 8px 32px ${color}30` : undefined,
        transform: selected ? 'translateY(-2px)' : undefined,
      }}
    >
      {/* Header — image or gradient fallback */}
      <div className="relative aspect-square overflow-hidden">
        {image_url ? (
          <img
            src={image_url}
            alt={label}
            className="w-full h-full object-cover"
          />
        ) : (
          /* Fallback gradient with emoji when image not yet available */
          <div
            className="w-full h-full flex items-center justify-center relative"
            style={{ background: `linear-gradient(135deg, ${color}ee, ${color}99)` }}
          >
            <div className="absolute -top-6 -right-6 w-28 h-28 rounded-full bg-white/10" />
            <div className="absolute top-2 right-12 w-12 h-12 rounded-full bg-white/10" />
            <span className="text-6xl relative z-10">{emoji}</span>
          </div>
        )}

        {/* Selected badge */}
        {selected && (
          <span
            className="absolute top-3 right-3 flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold text-white shadow-lg"
            style={{ backgroundColor: color }}
          >
            <CheckCircle2 className="h-3 w-3" />
            Active
          </span>
        )}

        {/* Colour bar at bottom of image */}
        <div className="absolute bottom-0 left-0 right-0 h-1" style={{ backgroundColor: color }} />
      </div>

      {/* Body */}
      <div className="bg-white p-5 flex flex-col flex-1">
        <div className="mb-3">
          <h3 className="text-xl font-bold" style={{ color }}>{label}</h3>
          <p className="text-xs font-medium mt-0.5" style={{ color: `${color}cc` }}>{tagline}</p>
        </div>

        {/* Traits */}
        <div className="flex flex-wrap gap-1.5 mb-3 mt-0">
          {traits.map(t => (
            <span
              key={t}
              className="inline-block rounded-full px-2 py-0.5 text-[10px] font-medium"
              style={{ backgroundColor: `${color}18`, color }}
            >
              {t}
            </span>
          ))}
        </div>

        <p className="text-xs text-muted-foreground leading-relaxed flex-1">{description}</p>

        {/* CTA */}
        <div
          className="mt-4 w-full text-center rounded-lg py-2 text-xs font-semibold transition-all"
          style={{
            backgroundColor: selected ? color : `${color}15`,
            color:           selected ? '#ffffff' : color,
          }}
        >
          {selected ? 'Active AI Manager' : 'Onboard'}
        </div>
      </div>
    </button>
  )
}
