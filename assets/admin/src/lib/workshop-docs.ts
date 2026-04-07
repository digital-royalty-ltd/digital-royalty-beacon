export interface WorkshopTool {
  slug: string
  label: string
  group: string
}

export const WORKSHOP_TOOLS: WorkshopTool[] = [
  // Security
  { slug: 'svg-support', label: 'SVG Upload Support', group: 'Security' },
  { slug: 'disable-comments', label: 'Disable Comments', group: 'Security' },
  { slug: 'disable-xmlrpc', label: 'Disable XML-RPC', group: 'Security' },
  { slug: 'disable-file-editing', label: 'Disable File Editing', group: 'Security' },
  { slug: 'sanitise-filenames', label: 'Sanitise Filenames', group: 'Security' },
  { slug: 'custom-login-url', label: 'Custom Login URL', group: 'Security' },
  { slug: 'maintenance-mode', label: 'Maintenance Mode', group: 'Security' },

  // Content
  { slug: 'header-footer-code', label: 'Header & Footer Code', group: 'Content' },
  { slug: 'custom-admin-css', label: 'Custom Admin CSS', group: 'Content' },
  { slug: 'robots-editor', label: 'Robots.txt Editor', group: 'Content' },
  { slug: 'htaccess-editor', label: '.htaccess Editor', group: 'Content' },
  { slug: 'login-branding', label: 'Login Page Branding', group: 'Content' },
  { slug: 'announcement-bar', label: 'Announcement Bar', group: 'Content' },
  { slug: 'redirects', label: 'Redirects Manager', group: 'Content' },

  // Email
  { slug: 'smtp-config', label: 'SMTP Configuration', group: 'Email' },

  // Utilities
  { slug: 'database-cleanup', label: 'Database Cleanup', group: 'Utilities' },
  { slug: 'permalink-flush', label: 'Flush Permalinks', group: 'Utilities' },
  { slug: 'post-expiry', label: 'Post Expiry', group: 'Utilities' },
  { slug: '404-monitor', label: '404 Monitor', group: 'Utilities' },
  { slug: 'post-type-switcher', label: 'Post Type Switcher', group: 'Utilities' },
  { slug: 'clone-post', label: 'Clone Post', group: 'Utilities' },
  { slug: 'find-replace', label: 'Find & Replace', group: 'Utilities' },
  { slug: 'media-replace', label: 'Media Replace', group: 'Utilities' },
  { slug: 'user-switcher', label: 'User Switcher', group: 'Utilities' },

  // Audits
  { slug: 'meta-auditor', label: 'Meta Auditor', group: 'Audits' },
  { slug: 'heading-structure', label: 'Heading Structure', group: 'Audits' },
  { slug: 'orphaned-content', label: 'Orphaned Content', group: 'Audits' },
  { slug: 'duplicate-titles', label: 'Duplicate Titles', group: 'Audits' },
  { slug: 'image-alt-auditor', label: 'Image Alt Auditor', group: 'Audits' },
  { slug: 'unused-media', label: 'Unused Media', group: 'Audits' },
  { slug: 'broken-links', label: 'Broken Links', group: 'Audits' },
  { slug: 'redirect-chains', label: 'Redirect Chains', group: 'Audits' },
  { slug: 'noindex-checker', label: 'Noindex Checker', group: 'Audits' },
]

export const WORKSHOP_GROUPS = Array.from(new Set(WORKSHOP_TOOLS.map(tool => tool.group)))
