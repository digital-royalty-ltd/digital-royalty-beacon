/**
 * Thin React wrapper around vanilla-jsoneditor.
 *
 * The editor is created once on mount and kept in a ref; subsequent prop
 * changes are applied via updateProps() so the editor never re-mounts.
 */
import { useEffect, useRef } from 'react'
import { JSONEditor } from 'vanilla-jsoneditor'
import type { Content, OnChange } from 'vanilla-jsoneditor'
import { Loader2 } from 'lucide-react'

interface Props {
  content:   Content
  onChange?: OnChange
  readOnly?: boolean
  /** px height of the editor container — defaults to 480 */
  height?:   number
}

export function JsonEditor({ content, onChange, readOnly = false, height = 480 }: Props) {
  const containerRef = useRef<HTMLDivElement>(null)
  const editorRef    = useRef<JSONEditor | null>(null)
  const mountedRef   = useRef(false)

  // Create editor once
  useEffect(() => {
    if (!containerRef.current || mountedRef.current) return
    mountedRef.current = true

    editorRef.current = new JSONEditor({
      target: containerRef.current,
      props: {
        content,
        onChange,
        readOnly,
        mode:          'tree',
        mainMenuBar:   true,
        navigationBar: false,
        statusBar:     true,
        askToFormat:   false,
        indentation:   2,
      },
    })

    return () => {
      editorRef.current?.destroy()
      editorRef.current = null
      mountedRef.current = false
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Sync prop changes without remounting
  useEffect(() => {
    if (!mountedRef.current) return
    editorRef.current?.updateProps({ content, onChange, readOnly })
  }, [content, onChange, readOnly])

  return (
    <div style={{ height }} className="relative rounded-xl border border-[#390d58]/15 overflow-hidden">
      {!mountedRef.current && (
        <div className="absolute inset-0 flex items-center justify-center bg-[#390d58]/[0.02] pointer-events-none">
          <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" />
        </div>
      )}
      <div ref={containerRef} className="h-full [&_.jse-main]:rounded-none [&_.jse-menu]:bg-[#390d58]/5" />
    </div>
  )
}
