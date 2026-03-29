import { useParams } from 'react-router-dom'
import { Construction } from 'lucide-react'
import { ToggleTool }           from '@/components/workshop/tools/ToggleTool'
import { CodeInjectionTool }    from '@/components/workshop/tools/CodeInjectionTool'
import { AdminCssTool }         from '@/components/workshop/tools/AdminCssTool'
import { SmtpTool }             from '@/components/workshop/tools/SmtpTool'
import { SiteFilesTool }        from '@/components/workshop/tools/SiteFilesTool'
import { DatabaseCleanupTool }  from '@/components/workshop/tools/DatabaseCleanupTool'
import { PermalinkFlushTool }   from '@/components/workshop/tools/PermalinkFlushTool'
import { MaintenanceModeTool }  from '@/components/workshop/tools/MaintenanceModeTool'
import { LoginUrlTool }         from '@/components/workshop/tools/LoginUrlTool'
import { LoginBrandingTool }    from '@/components/workshop/tools/LoginBrandingTool'
import { AnnouncementBarTool }  from '@/components/workshop/tools/AnnouncementBarTool'
import { PostExpiryTool }       from '@/components/workshop/tools/PostExpiryTool'
import { FourOhFourTool }       from '@/components/workshop/tools/FourOhFourTool'
import { AuditTool }            from '@/components/workshop/tools/AuditTool'
import { RedirectsTool }        from '@/components/workshop/tools/RedirectsTool'
import { PostTypeSwitcherTool } from '@/components/workshop/tools/PostTypeSwitcherTool'
import { ClonePostTool }        from '@/components/workshop/tools/ClonePostTool'
import { FindReplaceTool }      from '@/components/workshop/tools/FindReplaceTool'
import { MediaReplaceTool }     from '@/components/workshop/tools/MediaReplaceTool'
import { UserSwitcherTool }     from '@/components/workshop/tools/UserSwitcherTool'

const TOGGLE_SLUGS = new Set([
  'svg-support', 'disable-comments', 'disable-xmlrpc', 'disable-file-editing', 'sanitise-filenames',
])

const AUDIT_SLUGS = new Set([
  'meta-auditor', 'heading-structure', 'orphaned-content', 'duplicate-titles',
  'image-alt-auditor', 'unused-media', 'noindex-checker', 'redirect-chains', 'broken-links',
])

function ComingSoon({ label }: { label: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="rounded-2xl bg-[#390d58]/10 p-5 mb-6">
        <Construction className="h-10 w-10 text-[#390d58]" />
      </div>
      <h2 className="text-xl font-semibold text-[#390d58] mb-2">{label}</h2>
      <p className="text-sm text-muted-foreground max-w-sm">This tool is coming soon.</p>
    </div>
  )
}

export function WorkshopToolPage() {
  const { slug = '' } = useParams<{ slug: string }>()

  if (TOGGLE_SLUGS.has(slug)) return <ToggleTool slug={slug} />
  if (AUDIT_SLUGS.has(slug))  return <AuditTool toolSlug={slug} />

  switch (slug) {
    case 'header-footer-code':   return <CodeInjectionTool />
    case 'custom-admin-css':     return <AdminCssTool />
    case 'smtp-config':
    case 'test-email':           return <SmtpTool />
    case 'robots-editor':        return <SiteFilesTool slug="robots-editor" />
    case 'htaccess-editor':      return <SiteFilesTool slug="htaccess-editor" />
    case 'database-cleanup':     return <DatabaseCleanupTool />
    case 'permalink-flush':      return <PermalinkFlushTool />
    case 'maintenance-mode':     return <MaintenanceModeTool />
    case 'custom-login-url':     return <LoginUrlTool />
    case 'login-branding':       return <LoginBrandingTool />
    case 'announcement-bar':     return <AnnouncementBarTool />
    case 'post-expiry':          return <PostExpiryTool />
    case '404-monitor':          return <FourOhFourTool />
    case 'redirects':            return <RedirectsTool />
    case 'post-type-switcher':   return <PostTypeSwitcherTool />
    case 'clone-post':           return <ClonePostTool />
    case 'find-replace':         return <FindReplaceTool />
    case 'media-replace':        return <MediaReplaceTool />
    case 'user-switcher':        return <UserSwitcherTool />

    default:
      return <ComingSoon label={slug.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())} />
  }
}
