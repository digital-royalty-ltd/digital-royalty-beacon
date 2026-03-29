import {
  Zap, ArrowRight, ExternalLink,
  FileCheck, Puzzle, TrendingUp,
  Bot, LayoutDashboard, Cable, Server,
  X, Check,
  Target, Repeat,
  Shield, Users,
  Layers, Database, Cog, Sparkles,
  BarChart3, Workflow, Building2,
} from 'lucide-react'
import { Button } from '@/components/ui/button'

const DR_URL  = 'https://digitalroyalty.co.uk'
const APPLY   = 'https://digitalroyalty.co.uk/apply/'

// ─── Data ────────────────────────────────────────────────────────────────────

const beaconFeatures = [
  { icon: FileCheck,  text: 'We implement opportunities identified in your reports' },
  { icon: Puzzle,     text: 'We extend Beacon with custom functionality' },
  { icon: TrendingUp, text: 'We turn insights into systems across your business' },
]

const capabilities = [
  {
    icon: Bot,
    title: 'AI & Automation Systems',
    description: 'Custom AI agents and workflows designed around your business',
    features: [
      'Lead qualification and response systems',
      'Internal AI assistants',
      'Automated decision-making workflows',
      'AI integrated into your existing tools',
    ],
  },
  {
    icon: LayoutDashboard,
    title: 'Internal Tools & Dashboards',
    description: 'Systems your team actually uses to operate and grow',
    features: [
      'Custom admin dashboards',
      'Reporting platforms across marketing channels',
      'Workflow management tools',
      'Internal SaaS-style systems',
    ],
  },
  {
    icon: Cable,
    title: 'Integrations & Data Infrastructure',
    description: 'Make everything work together',
    features: [
      'CRM and third-party integrations',
      'API design and implementation',
      'Data pipelines and syncing',
      'Cross-platform automation',
    ],
  },
  {
    icon: Server,
    title: 'Backend & Infrastructure',
    description: 'Built for performance, reliability, and scale',
    features: [
      'Scalable backend systems',
      'Hosting and architecture optimisation',
      'Performance engineering',
      'High-volume data handling',
    ],
  },
]

const withoutExecution = [
  'Opportunities stay as ideas',
  'Implementation gets delayed',
  'Systems remain disconnected',
]

const withExecution = [
  'Ideas go live fast',
  'Everything integrates properly',
  'Your marketing runs on real infrastructure',
]

const engagementModels = [
  {
    icon: Target,
    title: 'Project-Based Builds',
    subtitle: 'For defined systems, features, or implementations',
    features: ['Clear scope', 'Fixed delivery', 'Ideal for immediate needs'],
    highlight: false,
  },
  {
    icon: Repeat,
    title: 'Ongoing Technical Partner',
    subtitle: 'For continuous growth and system development',
    features: [
      'Dedicated engineering time',
      'Ongoing improvements and iteration',
      'Priority execution of Beacon-driven opportunities',
    ],
    highlight: true,
  },
]

const challenges = [
  { icon: Layers,    text: "Systems that don't exist yet" },
  { icon: Database,  text: "Data that isn't connected" },
  { icon: Cog,       text: 'Processes that are still manual' },
  { icon: Sparkles,  text: "AI that isn't tailored to your business" },
]

const possibilities = [
  { icon: Bot,        text: 'A custom AI agent that handles inbound leads' },
  { icon: BarChart3,  text: 'A dashboard showing real ROI across channels' },
  { icon: Workflow,   text: 'Automated workflows connecting your marketing and CRM' },
  { icon: Building2,  text: 'Internal systems to run your operations' },
  { icon: TrendingUp, text: 'Scalable infrastructure to support growth' },
]

// ─── Helpers ─────────────────────────────────────────────────────────────────

function SectionDivider() {
  return <div className="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent" />
}

function PrimaryBtn({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <a href={href} target="_blank" rel="noreferrer">
      <Button size="lg" className="h-12 px-7 text-sm font-semibold rounded-xl bg-white text-[#390d58] hover:bg-white/90 transition-all duration-300 shadow-lg shadow-black/30">
        {children}
      </Button>
    </a>
  )
}

function OutlineBtn({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <a href={href} target="_blank" rel="noreferrer">
      <Button variant="outline" size="lg" className="h-12 px-7 text-sm font-semibold rounded-xl border-white/20 text-white bg-transparent hover:bg-white/10 hover:border-white/40 transition-all duration-300">
        {children}
      </Button>
    </a>
  )
}

// ─── Sections ────────────────────────────────────────────────────────────────

function HeroSection() {
  return (
    <section className="relative flex items-center justify-center px-6 py-16 overflow-hidden">
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-[#390d58]/30 rounded-full blur-[120px]" />
        <div className="absolute bottom-1/4 right-1/4 w-[300px] h-[300px] bg-[#5a1a8a]/20 rounded-full blur-[100px]" />
        <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:72px_72px]" />
      </div>

      <div className="relative z-10 max-w-4xl mx-auto text-center">
        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-white/15 bg-white/5 backdrop-blur-sm mb-8">
          <Zap className="w-4 h-4 text-[#c084fc]" />
          <span className="text-sm font-medium text-white/70">WP Beacon Technical Services</span>
        </div>

        <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-balance mb-8 text-white">
          Turn Beacon Into a
          <br />
          <span className="text-[#c084fc]">Complete Growth System</span>
        </h1>

        <div className="max-w-2xl mx-auto space-y-4 mb-12">
          <p className="text-lg sm:text-xl text-white/70 leading-relaxed">Beacon automates your marketing.</p>
          <p className="text-base sm:text-lg text-white/50 leading-relaxed">
            But real growth depends on more than campaigns. It requires systems, infrastructure, and execution across your entire business.
          </p>
          <p className="text-sm sm:text-base text-[#c084fc]/80 font-medium tracking-wide">
            Custom AI · Internal Tools · Connected Data · Scalable Architecture
          </p>
          <p className="text-lg sm:text-xl text-white font-medium">{"That's where we come in."}</p>
        </div>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
          <PrimaryBtn href={APPLY}>Plan Your Build <ArrowRight className="ml-2 w-4 h-4" /></PrimaryBtn>
          <OutlineBtn href={APPLY}>Work With Our Engineering Team</OutlineBtn>
        </div>

        <div className="mt-16 relative">
          <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-transparent to-transparent z-10 pointer-events-none" />
          <div className="relative mx-auto max-w-3xl">
            <div className="bg-white/[0.04] backdrop-blur-sm border border-white/10 rounded-2xl p-8 shadow-2xl">
              <div className="grid grid-cols-3 gap-4">
                {[...Array(3)].map((_, i) => (
                  <div key={i} className="space-y-3">
                    <div className="h-2 bg-white/10 rounded-full w-3/4" />
                    <div className="h-2 bg-white/6 rounded-full w-1/2" />
                    <div className="h-8 bg-[#390d58]/20 rounded-lg border border-[#390d58]/30" />
                  </div>
                ))}
              </div>
              <div className="mt-6 flex items-center gap-4">
                <div className="flex-1 h-2 bg-white/10 rounded-full overflow-hidden">
                  <div className="h-full w-2/3 bg-[#c084fc]/40 rounded-full" />
                </div>
                <div className="text-xs text-white/40 font-mono">System Active</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

function OpportunitySection() {
  return (
    <section className="relative px-6 py-16">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-px h-16 bg-gradient-to-b from-transparent via-white/15 to-transparent" />

      <div className="max-w-5xl mx-auto">
        <div className="grid lg:grid-cols-2 gap-14 items-center">
          <div className="space-y-7">
            <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-balance leading-tight text-white">
              Beacon Shows the Opportunity.
              <br />
              <span className="text-white/50">We Build What Makes It Real.</span>
            </h2>
            <p className="text-lg text-white/60 leading-relaxed">Beacon identifies what needs to happen to grow your business.</p>
            <p className="text-base text-white/40 leading-relaxed">But many of those opportunities require technical execution beyond your website:</p>

            <div className="space-y-3">
              {challenges.map((item, i) => (
                <div key={i} className="flex items-center gap-4 p-4 rounded-xl bg-white/[0.04] border border-white/8 hover:border-[#c084fc]/30 transition-colors duration-300">
                  <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-[#390d58]/30 flex items-center justify-center">
                    <item.icon className="w-5 h-5 text-[#c084fc]" />
                  </div>
                  <span className="text-white/80 font-medium">{item.text}</span>
                </div>
              ))}
            </div>

            <div className="space-y-2 pt-2">
              <p className="text-white/50">Most tools stop at insight.</p>
              <p className="text-xl font-semibold text-white">We turn it into working systems.</p>
            </div>

            <PrimaryBtn href={APPLY}>Request Implementation <ArrowRight className="ml-2 w-4 h-4" /></PrimaryBtn>
          </div>

          <div className="relative">
            <div className="absolute inset-0 bg-[#390d58]/15 rounded-3xl blur-3xl pointer-events-none" />
            <div className="relative bg-white/[0.04] border border-white/10 rounded-2xl p-8 shadow-2xl">
              <div className="space-y-6">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-mono text-white/40 uppercase tracking-wider">System Architecture</span>
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full bg-[#c084fc] animate-pulse" />
                    <span className="text-xs text-[#c084fc]">Live</span>
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="flex items-center justify-center gap-4">
                    <div className="w-20 h-12 rounded-lg bg-[#390d58]/30 border border-[#390d58]/50 flex items-center justify-center">
                      <span className="text-xs font-mono text-[#c084fc]">CRM</span>
                    </div>
                    <div className="w-8 h-px bg-white/20" />
                    <div className="w-24 h-14 rounded-lg bg-[#390d58]/40 border border-[#c084fc]/30 flex items-center justify-center">
                      <span className="text-xs font-mono text-white font-semibold">Beacon</span>
                    </div>
                    <div className="w-8 h-px bg-white/20" />
                    <div className="w-20 h-12 rounded-lg bg-[#390d58]/30 border border-[#390d58]/50 flex items-center justify-center">
                      <span className="text-xs font-mono text-[#c084fc]">API</span>
                    </div>
                  </div>
                  <div className="flex justify-center">
                    <div className="w-px h-8 bg-white/20" />
                  </div>
                  <div className="flex items-center justify-center gap-6">
                    {['AI', 'Data', 'Ops'].map(label => (
                      <div key={label} className="w-16 h-10 rounded bg-white/[0.06] border border-white/10 flex items-center justify-center">
                        <span className="text-[10px] font-mono text-white/40">{label}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="pt-4 border-t border-white/8">
                  <div className="flex items-center justify-between text-xs text-white/40">
                    <span>Connected systems: 12</span>
                    <span>Active workflows: 8</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

function ExecutionLayerSection() {
  return (
    <section className="relative px-6 py-16">
      <div className="absolute inset-0 bg-gradient-to-b from-[#390d58]/10 via-transparent to-transparent pointer-events-none" />

      <div className="relative max-w-5xl mx-auto">
        <div className="grid lg:grid-cols-2 gap-14 items-center">
          <div className="relative order-2 lg:order-1 space-y-4">
            {[
              { icon: Shield, title: 'Enterprise-Grade',  desc: 'Built for scale and reliability' },
              { icon: Zap,    title: 'Beacon-Integrated', desc: 'Deep understanding of your growth system' },
              { icon: Users,  title: 'Dedicated Team',    desc: 'Senior engineers, not freelancers' },
            ].map(({ icon: Icon, title, desc }) => (
              <div key={title} className="flex items-center gap-4 p-5 bg-white/[0.04] border border-white/8 rounded-2xl hover:border-[#c084fc]/25 transition-all duration-300">
                <div className="flex-shrink-0 w-14 h-14 rounded-xl bg-[#390d58]/30 flex items-center justify-center">
                  <Icon className="w-6 h-6 text-[#c084fc]" />
                </div>
                <div>
                  <h4 className="font-semibold text-white">{title}</h4>
                  <p className="text-sm text-white/50">{desc}</p>
                </div>
              </div>
            ))}
          </div>

          <div className="space-y-7 order-1 lg:order-2">
            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#c084fc]/25 bg-[#390d58]/20">
              <span className="text-sm font-medium text-[#c084fc]">Technical Partnership</span>
            </div>
            <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-balance leading-tight text-white">
              Your Technical
              <br />
              Execution Layer
            </h2>
            <div className="space-y-5 text-lg text-white/60 leading-relaxed">
              <p>We act as your in-house engineering team, without the cost or complexity of hiring one.</p>
              <div className="p-5 border-l-2 border-[#c084fc]/60 bg-[#390d58]/20 rounded-r-xl">
                <p className="text-white font-medium">Not freelancers. Not generic developers.</p>
              </div>
              <p>A team that understands Beacon, understands growth systems, and builds what your business actually needs.</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

function CapabilitiesSection() {
  return (
    <section className="relative px-6 py-16">
      <SectionDivider />

      <div className="max-w-5xl mx-auto">
        <div className="text-center mb-14">
          <h2 className="text-3xl sm:text-4xl font-bold tracking-tight mb-4 text-white">What We Build</h2>
          <p className="text-lg text-white/60 max-w-2xl mx-auto">End-to-end technical execution for every layer of your growth infrastructure</p>
        </div>

        <div className="grid md:grid-cols-2 gap-5 mb-12">
          {capabilities.map((cap, i) => (
            <div key={i} className="group relative bg-white/[0.03] border border-white/8 rounded-2xl p-7 hover:border-[#c084fc]/25 hover:bg-white/[0.06] transition-all duration-500">
              <div className="absolute inset-0 bg-[#390d58]/10 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none" />
              <div className="relative space-y-5">
                <div className="flex items-start gap-4">
                  <div className="flex-shrink-0 w-14 h-14 rounded-xl bg-[#390d58]/30 border border-[#390d58]/50 flex items-center justify-center group-hover:bg-[#390d58]/50 transition-colors duration-300">
                    <cap.icon className="w-7 h-7 text-[#c084fc]" />
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-bold text-white mb-1">{cap.title}</h3>
                    <p className="text-white/50 text-sm">{cap.description}</p>
                  </div>
                </div>
                <ul className="space-y-2.5 pl-1">
                  {cap.features.map((f, fi) => (
                    <li key={fi} className="flex items-start gap-3 text-sm text-white/50">
                      <span className="flex-shrink-0 w-1.5 h-1.5 rounded-full bg-[#c084fc]/50 mt-2" />
                      {f}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          ))}
        </div>

        <div className="text-center">
          <PrimaryBtn href={APPLY}>Work With Our Engineering Team <ArrowRight className="ml-2 w-4 h-4" /></PrimaryBtn>
        </div>
      </div>
    </section>
  )
}

function BeaconCenterSection() {
  return (
    <section className="relative px-6 py-16 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-[#390d58]/15 to-transparent pointer-events-none" />

      <div className="relative max-w-5xl mx-auto">
        <div className="text-center mb-14">
          <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#c084fc]/25 bg-[#390d58]/20 mb-8">
            <span className="text-sm font-medium text-[#c084fc]">Native Integration</span>
          </div>
          <h2 className="text-3xl sm:text-4xl font-bold tracking-tight mb-4 text-white">Built Around Beacon</h2>
          <p className="text-lg text-white/60 max-w-2xl mx-auto">Everything we build connects directly to your Beacon system.</p>
        </div>

        <div className="flex justify-center mb-12">
          <div className="relative">
            <div className="absolute inset-0 bg-[#390d58]/40 rounded-full blur-3xl scale-150 pointer-events-none" />
            <div className="relative w-28 h-28 rounded-full bg-gradient-to-br from-[#390d58]/60 to-[#390d58]/20 border border-[#c084fc]/30 flex items-center justify-center shadow-[0_0_60px_rgba(57,13,88,0.6)]">
              <div className="w-[4.5rem] h-[4.5rem] rounded-full bg-[#0a0a0a] border border-[#c084fc]/40 flex items-center justify-center">
                <span className="text-xl font-bold text-[#c084fc] tracking-tight">WP</span>
              </div>
            </div>
          </div>
        </div>

        <div className="grid md:grid-cols-3 gap-6">
          {beaconFeatures.map((feat, i) => (
            <div key={i} className="relative">
              <div className="hidden md:block absolute -top-12 left-1/2 w-px h-12 bg-gradient-to-b from-[#c084fc]/30 to-transparent" />
              <div className="bg-white/[0.04] border border-white/8 rounded-2xl p-6 hover:border-[#c084fc]/25 transition-all duration-300 text-center">
                <div className="w-12 h-12 rounded-xl bg-[#390d58]/30 border border-[#390d58]/50 flex items-center justify-center mx-auto mb-4">
                  <feat.icon className="w-6 h-6 text-[#c084fc]" />
                </div>
                <p className="text-white/70 font-medium text-sm leading-relaxed">{feat.text}</p>
              </div>
            </div>
          ))}
        </div>

        <div className="text-center mt-14">
          <p className="text-xl sm:text-2xl font-semibold text-white">
            Beacon becomes your{' '}
            <span className="text-[#c084fc]">operating system for growth.</span>
          </p>
        </div>
      </div>
    </section>
  )
}

function ComparisonSection() {
  return (
    <section className="relative px-6 py-16">
      <SectionDivider />

      <div className="max-w-5xl mx-auto">
        <div className="text-center mb-12">
          <h2 className="text-3xl sm:text-4xl font-bold tracking-tight mb-4 text-white">From Insight to Execution</h2>
          <p className="text-lg text-white/60 max-w-2xl mx-auto">The difference technical execution makes</p>
        </div>

        <div className="grid md:grid-cols-2 gap-7">
          <div className="relative bg-white/[0.03] border border-white/8 rounded-2xl p-7">
            <div className="space-y-7">
              <div>
                <span className="inline-block px-3 py-1.5 rounded-lg bg-white/8 text-white/40 text-xs font-medium mb-4">Without Technical Execution</span>
                <h3 className="text-xl font-bold text-white/30">Growth stalls</h3>
              </div>
              <ul className="space-y-5">
                {withoutExecution.map((item, i) => (
                  <li key={i} className="flex items-start gap-4">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center">
                      <X className="w-4 h-4 text-red-400/60" />
                    </div>
                    <span className="text-white/40 pt-1 text-sm">{item}</span>
                  </li>
                ))}
              </ul>
              <div className="pt-4 border-t border-white/8">
                <div className="flex items-center gap-3">
                  <div className="flex-1 h-2 bg-white/8 rounded-full overflow-hidden">
                    <div className="h-full w-1/4 bg-white/20 rounded-full" />
                  </div>
                  <span className="text-xs text-white/30">25%</span>
                </div>
                <p className="text-xs text-white/30 mt-2">Potential realized</p>
              </div>
            </div>
          </div>

          <div className="relative bg-white/[0.04] border-2 border-[#390d58]/60 rounded-2xl p-7 shadow-[0_0_40px_rgba(57,13,88,0.3)]">
            <div className="absolute inset-0 bg-[#390d58]/10 rounded-2xl pointer-events-none" />
            <div className="relative space-y-7">
              <div>
                <span className="inline-block px-3 py-1.5 rounded-lg bg-[#390d58]/40 text-[#c084fc] text-xs font-medium mb-4">With Our Engineering Team</span>
                <h3 className="text-xl font-bold text-white">Growth accelerates</h3>
              </div>
              <ul className="space-y-5">
                {withExecution.map((item, i) => (
                  <li key={i} className="flex items-start gap-4">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-[#390d58]/40 flex items-center justify-center">
                      <Check className="w-4 h-4 text-[#c084fc]" />
                    </div>
                    <span className="text-white/80 pt-1 text-sm">{item}</span>
                  </li>
                ))}
              </ul>
              <div className="pt-4 border-t border-[#390d58]/30">
                <div className="flex items-center gap-3">
                  <div className="flex-1 h-2 bg-[#390d58]/30 rounded-full overflow-hidden">
                    <div className="h-full w-full bg-[#c084fc]/50 rounded-full" />
                  </div>
                  <span className="text-xs text-[#c084fc] font-medium">100%</span>
                </div>
                <p className="text-xs text-[#c084fc]/60 mt-2">Full potential unlocked</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

function EngagementSection() {
  return (
    <section className="relative px-6 py-16">
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-[#390d58]/10 to-transparent pointer-events-none" />

      <div className="relative max-w-5xl mx-auto">
        <div className="text-center mb-12">
          <h2 className="text-3xl sm:text-4xl font-bold tracking-tight mb-4 text-white">How We Work</h2>
          <p className="text-lg text-white/60 max-w-2xl mx-auto">Flexible engagement models designed for your needs</p>
        </div>

        <div className="grid md:grid-cols-2 gap-7 mb-10">
          {engagementModels.map((model, i) => (
            <div
              key={i}
              className={`relative rounded-2xl p-7 transition-all duration-500 ${
                model.highlight
                  ? 'bg-white/[0.05] border-2 border-[#390d58]/60 shadow-[0_0_40px_rgba(57,13,88,0.35)]'
                  : 'bg-white/[0.03] border border-white/8 hover:border-[#c084fc]/20'
              }`}
            >
              {model.highlight && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <span className="px-4 py-1 rounded-full bg-[#390d58] text-white text-xs font-semibold">Most Popular</span>
                </div>
              )}
              <div className="space-y-5">
                <div className="text-center">
                  <div className={`w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 ${model.highlight ? 'bg-[#390d58]/40 border border-[#390d58]/60' : 'bg-white/[0.06] border border-white/10'}`}>
                    <model.icon className={`w-7 h-7 ${model.highlight ? 'text-[#c084fc]' : 'text-white/40'}`} />
                  </div>
                  <h3 className="text-xl font-bold text-white mb-2">{model.title}</h3>
                  <p className="text-white/50 text-sm">{model.subtitle}</p>
                </div>
                <ul className="space-y-3">
                  {model.features.map((feat, fi) => (
                    <li key={fi} className="flex items-center gap-3 text-sm">
                      <span className={`flex-shrink-0 w-1.5 h-1.5 rounded-full ${model.highlight ? 'bg-[#c084fc]' : 'bg-white/20'}`} />
                      <span className={model.highlight ? 'text-white/80' : 'text-white/40'}>{feat}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          ))}
        </div>

        <div className="text-center space-y-7">
          <p className="text-lg text-white/50">
            Most clients work with us as an{' '}
            <span className="text-white font-medium">ongoing technical partner.</span>
          </p>
          <PrimaryBtn href={APPLY}>Plan Your Build <ArrowRight className="ml-2 w-4 h-4" /></PrimaryBtn>
        </div>
      </div>
    </section>
  )
}

function PossibilitiesSection() {
  return (
    <section className="relative px-6 py-16">
      <SectionDivider />

      <div className="max-w-5xl mx-auto">
        <div className="text-center mb-12">
          <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-white">What Could We Build For You?</h2>
        </div>

        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-14">
          {possibilities.map((item, i) => (
            <div key={i} className="group relative bg-white/[0.03] border border-white/8 rounded-xl p-5 hover:border-[#c084fc]/30 hover:bg-white/[0.06] transition-all duration-300 cursor-default">
              <div className="absolute inset-0 bg-[#390d58]/10 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none" />
              <div className="relative flex items-start gap-4">
                <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-[#390d58]/30 border border-[#390d58]/50 flex items-center justify-center group-hover:bg-[#390d58]/50 transition-colors duration-300">
                  <item.icon className="w-5 h-5 text-[#c084fc]" />
                </div>
                <p className="text-white/70 font-medium text-sm leading-relaxed pt-1.5">{item.text}</p>
              </div>
            </div>
          ))}
        </div>

        <div className="text-center">
          <div className="inline-block p-7 rounded-2xl bg-gradient-to-r from-[#390d58]/20 via-[#390d58]/10 to-[#390d58]/20 border border-[#390d58]/40">
            <p className="text-xl sm:text-2xl font-semibold text-white">
              If Beacon can identify it,{' '}
              <span className="text-[#c084fc]">we can build it.</span>
            </p>
          </div>
        </div>
      </div>
    </section>
  )
}

function FinalCTASection() {
  return (
    <section className="relative px-6 py-16 overflow-hidden">
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-[#390d58]/25 rounded-full blur-[120px]" />
        <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.015)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.015)_1px,transparent_1px)] bg-[size:72px_72px]" />
      </div>
      <div className="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-[#c084fc]/30 to-transparent" />

      <div className="relative max-w-3xl mx-auto text-center">
        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#c084fc]/30 bg-[#390d58]/25 mb-8">
          <Zap className="w-4 h-4 text-[#c084fc]" />
          <span className="text-sm font-medium text-[#c084fc]">Start Building Today</span>
        </div>

        <h2 className="text-4xl sm:text-5xl font-bold tracking-tight mb-8 text-balance text-white">
          Turn Insight Into
          <br />
          <span className="text-[#c084fc]">Infrastructure</span>
        </h2>

        <div className="space-y-3 mb-12">
          <p className="text-lg sm:text-xl text-white/60 leading-relaxed">Take Beacon beyond automation and into full execution.</p>
          <p className="text-base sm:text-lg text-white font-medium">Build the systems your growth depends on.</p>
        </div>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
          <PrimaryBtn href={APPLY}>Start Your Build <ArrowRight className="ml-2 w-4 h-4" /></PrimaryBtn>
          <OutlineBtn href={APPLY}>Request Implementation</OutlineBtn>
        </div>

        <div className="mt-16">
          <div className="relative mx-auto max-w-xl">
            <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-transparent to-transparent z-10 pointer-events-none" />
            <div className="bg-white/[0.04] border border-white/10 rounded-2xl p-6 shadow-2xl">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 rounded-full bg-[#c084fc] animate-pulse" />
                  <span className="text-xs font-mono text-white/40">WP Beacon × Engineering</span>
                </div>
                <span className="text-xs text-[#c084fc] font-medium">Active</span>
              </div>
              <div className="grid grid-cols-4 gap-2">
                {[...Array(4)].map((_, i) => (
                  <div key={i} className="h-8 bg-[#390d58]/20 rounded border border-[#390d58]/30" />
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="mt-12 pt-7 border-t border-white/8">
          <a href={DR_URL} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm text-white/30 hover:text-[#c084fc] transition-colors">
            WP Beacon Technical Services — Digital Royalty
            <ExternalLink className="w-3 h-3" />
          </a>
        </div>
      </div>
    </section>
  )
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export function DevelopmentPage() {
  return (
    <div className="beacon-dark-page -mx-6 -mt-8 bg-[#0a0a0a]">
      <HeroSection />
      <OpportunitySection />
      <ExecutionLayerSection />
      <CapabilitiesSection />
      <BeaconCenterSection />
      <ComparisonSection />
      <EngagementSection />
      <PossibilitiesSection />
      <FinalCTASection />
    </div>
  )
}
