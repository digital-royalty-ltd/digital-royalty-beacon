import { NavLink, Outlet, useParams } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { Wrench } from 'lucide-react'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'

interface ToolDef {
  slug: string
  label: string
  group: string
}

const TOOLS: ToolDef[] = [
  // Security & Performance
  { slug: 'svg-support',           label: 'SVG Upload Support',        group: 'Security' },
  { slug: 'disable-comments',      label: 'Disable Comments',          group: 'Security' },
  { slug: 'disable-xmlrpc',        label: 'Disable XML-RPC',           group: 'Security' },
  { slug: 'disable-file-editing',  label: 'Disable File Editing',      group: 'Security' },
  { slug: 'sanitise-filenames',    label: 'Sanitise Filenames',        group: 'Security' },
  { slug: 'custom-login-url',      label: 'Custom Login URL',          group: 'Security' },
  { slug: 'maintenance-mode',      label: 'Maintenance Mode',          group: 'Security' },

  // Content & Code
  { slug: 'header-footer-code',    label: 'Header & Footer Code',      group: 'Content' },
  { slug: 'custom-admin-css',      label: 'Custom Admin CSS',          group: 'Content' },
  { slug: 'robots-editor',         label: 'Robots.txt Editor',         group: 'Content' },
  { slug: 'htaccess-editor',       label: '.htaccess Editor',          group: 'Content' },
  { slug: 'login-branding',        label: 'Login Page Branding',       group: 'Content' },
  { slug: 'announcement-bar',      label: 'Announcement Bar',          group: 'Content' },

  // Email
  { slug: 'smtp-config',           label: 'SMTP Configuration',        group: 'Email' },

  // Database & Utilities
  { slug: 'database-cleanup',      label: 'Database Cleanup',          group: 'Utilities' },
  { slug: 'permalink-flush',       label: 'Flush Permalinks',          group: 'Utilities' },
  { slug: 'post-expiry',           label: 'Post Expiry',               group: 'Utilities' },
  { slug: '404-monitor',           label: '404 Monitor',               group: 'Utilities' },

  // Audits
  { slug: 'redirects',             label: 'Redirects Manager',         group: 'Content'   },
  { slug: 'meta-auditor',          label: 'Meta Auditor',              group: 'Audits'    },
  { slug: 'heading-structure',     label: 'Heading Structure',         group: 'Audits'    },
  { slug: 'orphaned-content',      label: 'Orphaned Content',          group: 'Audits'    },
  { slug: 'duplicate-titles',      label: 'Duplicate Titles',          group: 'Audits'    },
  { slug: 'image-alt-auditor',     label: 'Image Alt Auditor',         group: 'Audits'    },
  { slug: 'unused-media',          label: 'Unused Media',              group: 'Audits'    },
  { slug: 'broken-links',          label: 'Broken Links',              group: 'Audits'    },
  { slug: 'redirect-chains',       label: 'Redirect Chains',           group: 'Audits'    },
  { slug: 'noindex-checker',       label: 'Noindex Checker',           group: 'Audits'    },
  { slug: 'post-type-switcher',    label: 'Post Type Switcher',        group: 'Utilities' },
  { slug: 'clone-post',            label: 'Clone Post',                group: 'Utilities' },
  { slug: 'find-replace',          label: 'Find & Replace',            group: 'Utilities' },
  { slug: 'media-replace',         label: 'Media Replace',             group: 'Utilities' },
  { slug: 'user-switcher',         label: 'User Switcher',             group: 'Utilities' },
]

const GROUPS = Array.from(new Set(TOOLS.map(t => t.group)))

function WorkshopWelcome() {
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="rounded-2xl bg-[#390d58]/10 p-5 mb-6">
        <Wrench className="h-10 w-10 text-[#390d58]" />
      </div>
      <h2 className="text-xl font-semibold text-[#390d58] mb-2">Workshop</h2>
      <p className="text-sm text-muted-foreground max-w-sm">
        Select a tool from the left to get started.
      </p>
    </div>
  )
}

function WorkshopNav() {
  const { slug } = useParams<{ slug: string }>()

  return (
    <nav className="w-56 shrink-0 border-r border-[#390d58]/10 overflow-y-auto">
      {GROUPS.map(group => {
        const groupTools = TOOLS.filter(t => t.group === group)
        return (
          <div key={group} className="py-3">
            <p className="px-4 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">
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
                    ? 'bg-[#390d58] text-white font-medium'
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
    <div className="flex h-full -m-6 overflow-hidden">
      <OnboardingOverlay screen="workshop" />
      <WorkshopNav />
      <div className="flex-1 overflow-y-auto p-6">
        {slug ? <Outlet /> : <WorkshopWelcome />}
      </div>
    </div>
  )
}
