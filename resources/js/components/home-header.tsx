import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

export function HomeBrand() {
    return (
        <span className="inline-flex items-center gap-3 text-lg font-semibold tracking-tight text-[#242a28]">
            <span
                className="flex size-8 items-center justify-center rounded-sm bg-[#244d3e] text-white"
                aria-hidden="true"
            >
                <ArrowUpRight className="size-5" strokeWidth={1.75} />
            </span>
            CRM <span className="font-normal text-[#6c736d]">SaaS</span>
        </span>
    );
}

export function HomeNavigation({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    return (
        <nav aria-label={label} className={className}>
            {[
                ['Recursos', '#recursos'],
                ['Planos', '#planos'],
                ['Como funciona', '#como-funciona'],
            ].map(([title, href]) => (
                <a
                    key={href}
                    href={href}
                    className="inline-flex min-h-11 items-center rounded-sm underline-offset-4 hover:text-[#244d3e] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#244d3e]"
                >
                    {title}
                </a>
            ))}
        </nav>
    );
}

export function HomeHeader() {
    return (
        <header className="border-b border-[#e0e4dc] bg-[#fafaf7]">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-y-4 px-6 py-5 sm:px-10 lg:py-6">
                <HomeBrand />
                <HomeNavigation
                    label="Navegação principal"
                    className="order-3 flex w-full items-center gap-6 border-t border-[#e0e4dc] pt-3 text-sm text-[#626963] sm:order-none sm:w-auto sm:border-0 sm:pt-0 lg:gap-9"
                />
                <div className="flex items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 rounded-md border-[#cbd5c8] bg-transparent px-5 text-[#44564a] shadow-none motion-reduce:transition-none"
                    >
                        <Link href={login()}>Entrar</Link>
                    </Button>
                </div>
            </div>
        </header>
    );
}
