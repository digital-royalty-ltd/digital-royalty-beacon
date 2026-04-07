import { NavLink, Outlet, useParams } from 'react-router-dom'
import { Wrench } from 'lucide-react'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'
import { cn } from '@/lib/utils'
import { WORKSHOP_GROUPS, WORKSHOP_TOOLS } from '@/lib/workshop-docs'

function WorkshopWelcome() {
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="mb-6 rounded-2xl bg-[#390d58]/10 p-5">
        <Wrench className="h-10 w-10 text-[#390d58]" />
      </div>
      <h2 className="mb-2 text-xl font-semibold text-[#390d58]">Workshop</h2>
      <p className="max-w-sm text-sm text-muted-foreground">
        Select a tool from the left to get started.
      </p>
    </div>
  )
}

function WorkshopNav() {
  const { slug } = useParams<{ slug: string }>()

  return (
    <nav className="w-56 shrink-0 overflow-y-auto border-r border-[#390d58]/10">
      {WORKSHOP_GROUPS.map(group => {
        const groupTools = WORKSHOP_TOOLS.filter(tool => tool.group === group)

        return (
          <div key={group} className="py-3">
            <p className="mb-1 px-4 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
              {group}
            </p>
            {groupTools.map(tool => (
              <NavLink
                key={tool.slug}
                to={`/workshop/${tool.slug}`}
                data-active={slug === tool.slug ? '' : undefined}
                className={cn(
                  'flex items-center px-4 py-1.5 text-sm transition-colors',
                  slug === tool.slug
                    ? 'bg-[#390d58] font-medium text-white'
                    : 'text-muted-foreground hover:bg-[#390d58]/8 hover:text-[#390d58]',
                )}
              >
                {tool.label}
              </NavLink>
            ))}
          </div>
        )
      })}
    </nav>
  )
}

export function WorkshopPage() {
  const { slug } = useParams<{ slug: string }>()

  return (
    <div className="-m-6 flex h-full overflow-hidden">
      <OnboardingOverlay screen="workshop" />
      <WorkshopNav />
      <div className="flex-1 overflow-y-auto p-6">
        {slug ? <Outlet /> : <WorkshopWelcome />}
      </div>
    </div>
  )
}
