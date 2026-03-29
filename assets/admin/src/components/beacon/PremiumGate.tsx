import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Sparkles, ArrowRight } from 'lucide-react'

interface Props {
  feature:     string        // e.g. "Automations"
  description: string        // what they'll unlock
  icon:        React.ReactNode
  gradient:    string        // Tailwind bg-gradient-to-br classes
}

export function PremiumGate({ feature, description, icon, gradient }: Props) {
  const navigate = useNavigate()

  return (
    <div className="flex flex-col items-center justify-center py-24 text-center px-6">
      {/* Icon area */}
      <div className={`rounded-2xl bg-gradient-to-br ${gradient} p-5 mb-6 shadow-lg`}>
        <span className="text-white">{icon}</span>
      </div>

      <h2 className="text-xl font-bold text-[#390d58] mb-2">{feature} requires a Beacon API key</h2>
      <p className="text-sm text-muted-foreground max-w-sm leading-relaxed mb-8">
        {description}
      </p>

      <div className="flex items-center gap-3">
        <Button
          onClick={() => navigate('/configuration')}
          className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2 px-6"
        >
          <Sparkles className="h-4 w-4" />
          Connect API Key
          <ArrowRight className="h-4 w-4" />
        </Button>
      </div>

      <p className="text-xs text-muted-foreground mt-6">
        Don't have a key?{' '}
        <a
          href="https://digitalroyalty.co.uk/beacon/premium"
          target="_blank"
          rel="noreferrer"
          className="text-[#390d58] hover:underline"
        >
          Get Beacon Premium →
        </a>
      </p>
    </div>
  )
}
