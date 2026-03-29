import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { PenLine, Search, FileSearch, FileText, Lock, ArrowRight, Sparkles } from "lucide-react"

interface Props {
  onOpen: (toolId: string) => void
}

interface Tool {
  id: string
  title: string
  description: string
  icon: React.ReactNode
  available: boolean
}

const tools: Tool[] = [
  {
    id: "content-generator",
    title: "Content Generator",
    description: "Generate high-quality, SEO-optimized content using AI. Create blog posts, landing pages, and marketing copy tailored to your brand voice.",
    icon: <PenLine className="h-6 w-6" />,
    available: true,
  },
  {
    id: "meta-generator",
    title: "Meta Generator",
    description: "Automatically generate optimized meta titles and descriptions for all your pages and posts.",
    icon: <FileText className="h-6 w-6" />,
    available: false,
  },
  {
    id: "content-gap",
    title: "Content Gap Analysis",
    description: "Identify missing content opportunities by analyzing your site against competitors and industry trends.",
    icon: <Search className="h-6 w-6" />,
    available: false,
  },
  {
    id: "page-brief",
    title: "Page Brief Generator",
    description: "Create comprehensive content briefs with keyword targets, structure recommendations, and competitor insights.",
    icon: <FileSearch className="h-6 w-6" />,
    available: false,
  },
]

export function Tools({ onOpen }: Props) {
  return (
    <div className="space-y-8">
      <div className="flex items-start justify-between">
        <div>
          <h2 className="text-xl font-semibold tracking-tight text-[#390d58]">AI Tools</h2>
          <p className="text-sm text-muted-foreground mt-1">
            Powerful AI-powered tools to enhance your content workflow
          </p>
        </div>
        <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#390d58]/10 text-[#390d58]">
          <Sparkles className="h-4 w-4" />
          <span className="text-sm font-medium">1 of 4 available</span>
        </div>
      </div>

      <div className="grid gap-5 md:grid-cols-2">
        {tools.map((tool) => (
          <Card
            key={tool.id}
            className={`group transition-all ${
              tool.available
                ? "hover:shadow-lg hover:shadow-[#390d58]/10 hover:border-[#390d58]/30 border-[#390d58]/20"
                : "border-dashed opacity-75"
            }`}
          >
            <CardHeader className="pb-4">
              <div className="flex items-start justify-between">
                <div className={`rounded-xl p-3.5 ${
                  tool.available
                    ? "bg-[#390d58] text-white shadow-md shadow-[#390d58]/20"
                    : "bg-muted text-muted-foreground"
                }`}>
                  {tool.available ? tool.icon : <Lock className="h-6 w-6" />}
                </div>
                {!tool.available && (
                  <Badge variant="outline" className="text-xs bg-muted/50 text-muted-foreground border-muted-foreground/20">
                    Coming Soon
                  </Badge>
                )}
                {tool.available && (
                  <Badge className="text-xs bg-[#390d58] text-white">
                    Active
                  </Badge>
                )}
              </div>
              <CardTitle className={`text-lg mt-4 ${tool.available ? "text-[#390d58]" : "text-muted-foreground"}`}>
                {tool.title}
              </CardTitle>
              <CardDescription className={`text-sm leading-relaxed ${!tool.available && "text-muted-foreground/70"}`}>
                {tool.description}
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-0">
              {tool.available ? (
                <Button onClick={() => onOpen(tool.id)}
                  className="w-full bg-[#390d58] hover:bg-[#4a1170] text-white gap-2 group-hover:gap-3 transition-all shadow-md shadow-[#390d58]/20">
                  Open Tool
                  <ArrowRight className="h-4 w-4" />
                </Button>
              ) : (
                <Button variant="outline" className="w-full border-dashed" disabled>
                  <Lock className="h-4 w-4 mr-2" />
                  Locked
                </Button>
              )}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
