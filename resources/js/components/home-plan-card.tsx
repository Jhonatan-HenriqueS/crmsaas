import { Link } from '@inertiajs/react';
import { Building2, Check, Network } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { HomePlan } from '@/lib/home-plans';
import { cn } from '@/lib/utils';
import { register } from '@/routes';

function PlanBenefit({ children }: { children: string }) {
    return (
        <li className="flex items-start gap-3 text-sm leading-6 text-[#515c52]">
            <Check
                className="mt-1 size-4 shrink-0 text-[#35624e]"
                strokeWidth={2}
                aria-hidden="true"
            />
            <span>{children}</span>
        </li>
    );
}

export function HomePlanCard({ plan }: { plan: HomePlan }) {
    const isAgency = plan.id === 'agencia';
    const Icon = isAgency ? Network : Building2;

    return (
        <article
            aria-labelledby={`plan-${plan.id}-title`}
            className={cn(
                'flex flex-col overflow-hidden rounded-lg border border-[#d6ded1]',
                isAgency ? 'bg-[#f0f4ec]' : 'bg-white',
            )}
        >
            <div className="border-b border-[#d6ded1] p-6 sm:p-8">
                <div className="mb-7 flex items-center justify-between gap-3">
                    <p className="text-xs font-medium text-[#596b56]">
                        {plan.audience}
                    </p>
                    <Icon
                        className="size-6 shrink-0 text-[#35624e]"
                        strokeWidth={1.5}
                        aria-hidden="true"
                    />
                </div>
                <h3
                    id={`plan-${plan.id}-title`}
                    className="text-4xl font-semibold tracking-[-0.04em]"
                >
                    {plan.name}
                </h3>
                <p className="mt-4 min-h-18 max-w-md text-base leading-6 text-[#626963]">
                    {plan.description}
                </p>
                <p className="mt-6 border-l-2 border-[#739174] pl-3 text-sm leading-6 font-medium text-[#39543d]">
                    {plan.objective}
                </p>
            </div>
            <div className="flex flex-1 flex-col p-6 sm:p-8">
                <p className="mb-6 text-xs font-medium text-[#687163]">
                    Recursos previstos para a primeira versão
                </p>
                <div className="space-y-7">
                    {plan.groups.map(({ title, benefits }) => (
                        <div key={title}>
                            <h4 className="mb-3 text-sm font-semibold">
                                {title}
                            </h4>
                            <ul className="space-y-2.5">
                                {benefits.map((benefit) => (
                                    <PlanBenefit key={benefit}>
                                        {benefit}
                                    </PlanBenefit>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
                <div className="mt-auto pt-8">
                    <p className="min-h-18 border-t border-[#d6ded1] pt-5 text-xs leading-6 text-[#626e5d]">
                        {plan.accessNote}
                    </p>
                    <Button
                        asChild
                        aria-describedby={`plan-${plan.id}-availability`}
                        className="mt-6 h-12 w-full rounded-md bg-[#244d3e] text-white shadow-none disabled:opacity-100 motion-reduce:transition-none"
                    >
                        <Link href={register({ query: { plan: plan.id } })}>
                            Escolher {plan.name}
                        </Link>
                    </Button>
                    <p
                        id={`plan-${plan.id}-availability`}
                        className="mt-3 text-center text-xs text-[#626e5d]"
                    >
                        Crie sua conta para começar
                    </p>
                </div>
            </div>
        </article>
    );
}
