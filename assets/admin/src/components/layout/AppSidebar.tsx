import { Sparkles, ExternalLink, CheckCircle2 } from 'lucide-react'

const PREMIUM_FEATURES = [
  'Agency-proven campaign strategies, not generic AI prompts',
  'Competitor gap targeting — the same analysis our team runs for clients',
  'Content calendars built on Digital Royalty methodology, automated',
  'Priority processing — your jobs run at the front of the queue',
]

export function AppSidebar() {
  return (
    <aside className="w-full lg:w-80 shrink-0 border-t lg:border-t-0 lg:border-l border-[#390d58]/10 lg:overflow-y-auto bg-white flex flex-col gap-4 px-4 sm:px-5 py-5 sm:py-6">

      {/* Premium upsell */}
      <div className="rounded-xl bg-gradient-to-b from-[#390d58] to-[#2d0a47] p-4 text-white">
        <div className="flex items-center gap-2 mb-3">
          <div className="rounded-lg bg-white/15 p-1.5">
            <Sparkles className="h-4 w-4 text-white" />
          </div>
          <p className="text-sm font-bold tracking-tight">Go Premium</p>
        </div>

        <p className="text-xs text-white/75 leading-relaxed mb-4">
          Beacon isn't generic AI — it's Digital Royalty's agency expertise committed to software. Every strategy is built on what we've proven works for real clients.
        </p>

        <ul className="space-y-2 mb-5">
          {PREMIUM_FEATURES.map(f => (
            <li key={f} className="flex items-start gap-2">
              <CheckCircle2 className="h-3.5 w-3.5 text-white/60 mt-0.5 shrink-0" />
              <span className="text-xs text-white/80 leading-snug">{f}</span>
            </li>
          ))}
        </ul>

        <a
          href="https://digitalroyalty.co.uk/beacon/premium"
          target="_blank"
          rel="noreferrer"
          className="block w-full text-center rounded-lg bg-white text-[#390d58] text-xs font-semibold py-2 hover:bg-white/90 transition-colors"
        >
          Explore Premium →
        </a>
      </div>

      {/* Human services */}
      <div className="rounded-xl border border-[#390d58]/15 bg-white p-4">
        <p className="text-sm font-semibold text-[#390d58] mb-2">Want us to run it for you?</p>
        <p className="text-xs text-muted-foreground leading-relaxed mb-3">
          Beacon is built on Digital Royalty's agency expertise. If you'd rather hand it off entirely, our team handles strategy, content, SEO, and development end to end.
        </p>
        <a
          href="https://digitalroyalty.co.uk"
          target="_blank"
          rel="noreferrer"
          className="inline-flex items-center gap-1.5 text-xs font-medium text-[#390d58] hover:text-[#4a1170] transition-colors"
        >
          Visit digitalroyalty.co.uk
          <ExternalLink className="h-3 w-3" />
        </a>
      </div>

    </aside>
  )
}
