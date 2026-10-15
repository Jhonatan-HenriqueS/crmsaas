import { Head } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowRight,
    ChartNoAxesCombined,
    FolderKanban,
    Megaphone,
    UsersRound,
} from 'lucide-react';
import {
    HomeBrand,
    HomeHeader,
    HomeNavigation,
} from '@/components/home-header';
import { HomePlanCard } from '@/components/home-plan-card';
import { Button } from '@/components/ui/button';
import { homePlans } from '@/lib/home-plans';

const resources = [
    {
        icon: FolderKanban,
        label: 'Organização comercial',
        title: 'Cada oportunidade no seu lugar.',
        description:
            'Leads, categorias e etapas do funil para acompanhar uma negociação do primeiro contato ao fechamento.',
    },
    {
        icon: Megaphone,
        label: 'Gestão de campanhas',
        title: 'O investimento encontra o resultado.',
        description:
            'Campanhas, mídia e custos de ferramentas reunidos para entender de onde vêm os leads e o que cada ação entrega.',
    },
    {
        icon: ChartNoAxesCombined,
        label: 'Visibilidade de resultados',
        title: 'Mais contexto para decidir.',
        description:
            'Indicadores de receita, resultado e custo por lead para acompanhar sua operação com informações centralizadas.',
    },
];

const operation = [
    {
        icon: UsersRound,
        title: 'Oportunidades',
        detail: 'Leads · Categorias · Funil',
    },
    {
        icon: Megaphone,
        title: 'Campanhas',
        detail: 'Mídia · Investimentos · Custos',
    },
    {
        icon: ChartNoAxesCombined,
        title: 'Resultados',
        detail: 'Receita · CPL · Indicadores',
    },
];

const steps = [
    {
        title: 'Organize',
        description: 'Reúna leads e clientes. Dê contexto a cada oportunidade.',
    },
    {
        title: 'Acompanhe',
        description: 'Conecte campanhas, investimentos e custos da operação.',
    },
    {
        title: 'Visualize',
        description: 'Leia os indicadores e entenda os resultados por período.',
    },
    {
        title: 'Decida',
        description:
            'Use uma visão centralizada para definir os próximos passos.',
    },
];

export default function Welcome() {
    return (
        <div
            lang="pt-BR"
            className="min-h-screen bg-[#fafaf7] text-[#242a28] selection:bg-[#d4e8dd] selection:text-[#183e33]"
        >
            <Head title="CRM SaaS — Leads, campanhas e resultados">
                <meta
                    name="description"
                    content="Conheça a proposta do CRM SaaS e os planos Empresa e Agência para organizar leads, acompanhar campanhas e entender resultados comerciais."
                />
            </Head>
            <a
                href="#conteudo"
                className="sr-only z-50 rounded bg-white p-4 text-[#244d3e] focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#244d3e]"
            >
                Ir para o conteúdo
            </a>
            <HomeHeader />
            <main id="conteudo" tabIndex={-1}>
                <section
                    aria-labelledby="hero-title"
                    className="mx-auto grid max-w-7xl gap-12 px-6 pt-16 pb-16 sm:px-10 sm:pt-24 sm:pb-20 lg:grid-cols-[1.45fr_1fr] lg:items-center lg:gap-20 lg:pt-28 lg:pb-24"
                >
                    <div>
                        <p className="mb-6 flex items-center gap-3 text-xs font-semibold tracking-[0.16em] text-[#496256] uppercase">
                            <span className="h-px w-8 bg-[#496256]" />
                            Gestão comercial, com clareza
                        </p>
                        <h1
                            id="hero-title"
                            className="max-w-3xl text-[2.65rem] leading-[1.08] font-semibold tracking-[-0.055em] sm:text-6xl lg:text-[4.25rem]"
                        >
                            Leads organizados.
                            <br />
                            Campanhas sob controle.
                            <br />
                            <span className="text-[#35624e]">
                                Decisões com dados.
                            </span>
                        </h1>
                        <p className="mt-7 max-w-lg text-base leading-7 text-[#626963] sm:text-lg sm:leading-8">
                            Uma plataforma para empresas e agências acompanharem
                            oportunidades comerciais, investimentos e resultados
                            em um só lugar.
                        </p>
                        <div className="mt-9 flex flex-wrap items-center gap-x-7 gap-y-5">
                            <Button
                                asChild
                                className="h-12 rounded-md bg-[#244d3e] px-6 text-white shadow-none transition-colors hover:bg-[#183a2e] focus-visible:ring-[#244d3e]/40 motion-reduce:transition-none"
                            >
                                <a href="#planos">
                                    Conhecer os planos
                                    <ArrowRight aria-hidden="true" />
                                </a>
                            </Button>
                            <a
                                href="#como-funciona"
                                className="inline-flex min-h-11 items-center gap-2 rounded-sm text-sm font-medium text-[#44564a] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#244d3e]"
                            >
                                Entenda a proposta
                                <ArrowDown
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>
                        <p className="mt-5 text-xs leading-5 text-[#6c736d]">
                            Produto em desenvolvimento. Conheça os recursos
                            planejados para a primeira versão.
                        </p>
                    </div>
                    <div className="border border-[#dce2d9] bg-[#f0f3ec] p-6 sm:p-8">
                        <p className="text-xs font-semibold tracking-[0.14em] text-[#5b695c] uppercase">
                            Uma operação. Uma visão conectada.
                        </p>
                        <div className="mt-8">
                            {operation.map(
                                ({ icon: Icon, title, detail }, index) => (
                                    <div key={title}>
                                        {index > 0 && (
                                            <div
                                                className="ml-6 h-7 w-px bg-[#cad5c8]"
                                                aria-hidden="true"
                                            />
                                        )}
                                        <div className="flex items-center gap-4 border border-[#dce2d9] bg-[#fafbf8] p-4 sm:p-5">
                                            <Icon
                                                className="size-6 shrink-0 text-[#35624e]"
                                                strokeWidth={1.5}
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <p className="text-lg font-semibold tracking-tight">
                                                    {title}
                                                </p>
                                                <p className="mt-1 text-xs leading-5 text-[#667163] sm:text-sm">
                                                    {detail}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                        <p className="mt-6 border-t border-[#d5ded1] pt-5 text-sm leading-6 text-[#596654]">
                            Da primeira conversa à análise da campanha, cada
                            informação tem seu lugar.
                        </p>
                    </div>
                </section>
                <section
                    id="recursos"
                    aria-labelledby="resources-title"
                    className="scroll-mt-6 border-y border-[#e0e4dc] bg-white"
                >
                    <div className="mx-auto max-w-7xl px-6 py-16 sm:px-10 sm:py-20">
                        <div className="grid gap-5 lg:grid-cols-[1fr_1.2fr] lg:gap-16">
                            <p className="text-xs font-semibold tracking-[0.16em] text-[#526450] uppercase">
                                Recursos planejados
                            </p>
                            <h2
                                id="resources-title"
                                className="max-w-xl text-3xl leading-tight font-semibold tracking-[-0.04em] sm:text-4xl"
                            >
                                Menos informações espalhadas.
                                <br />
                                Mais visão da sua operação.
                            </h2>
                        </div>
                        <div className="mt-12 grid gap-9 md:grid-cols-3 md:gap-8">
                            {resources.map(
                                ({ icon: Icon, title, description, label }) => (
                                    <article
                                        key={title}
                                        className="border-t border-[#dfe5dc] pt-6"
                                    >
                                        <div className="mb-7 flex items-center gap-3 text-[#526450]">
                                            <Icon
                                                className="size-5"
                                                strokeWidth={1.5}
                                                aria-hidden="true"
                                            />
                                            <p className="text-sm">{label}</p>
                                        </div>
                                        <h3 className="max-w-xs text-xl leading-7 font-semibold tracking-tight">
                                            {title}
                                        </h3>
                                        <p className="mt-3 text-sm leading-7 text-[#656b65]">
                                            {description}
                                        </p>
                                    </article>
                                ),
                            )}
                        </div>
                    </div>
                </section>
                <section
                    id="planos"
                    aria-labelledby="plans-title"
                    className="mx-auto max-w-7xl scroll-mt-6 px-6 py-16 sm:px-10 sm:py-24"
                >
                    <div className="mb-10 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p className="mb-4 text-xs font-semibold tracking-[0.16em] text-[#526450] uppercase">
                                Empresa ou agência
                            </p>
                            <h2
                                id="plans-title"
                                className="text-3xl leading-tight font-semibold tracking-[-0.04em] sm:text-5xl"
                            >
                                Duas formas de organizar.
                                <br />A mesma clareza para crescer.
                            </h2>
                        </div>
                        <p className="max-w-sm text-sm leading-7 text-[#626963]">
                            Uma conta para sua empresa ou uma estrutura para
                            acompanhar vários clientes. Conheça o que está
                            previsto para cada plano.
                        </p>
                    </div>
                    <div className="grid gap-6 md:grid-cols-2">
                        {homePlans.map((plan) => (
                            <HomePlanCard key={plan.id} plan={plan} />
                        ))}
                    </div>
                    <p className="mt-6 text-center text-xs leading-6 text-[#697167]">
                        Os planos apresentam a proposta da primeira versão.
                        Escolha um plano para criar sua conta. Sem pagamento
                        nesta etapa.
                    </p>
                </section>
                <section
                    id="como-funciona"
                    aria-labelledby="how-title"
                    className="scroll-mt-6 border-t border-[#dce2d9] bg-[#edf1e9]"
                >
                    <div className="mx-auto max-w-7xl px-6 py-16 sm:px-10 sm:py-20">
                        <div className="grid gap-5 lg:grid-cols-[1fr_1.2fr] lg:gap-16">
                            <p className="text-xs font-semibold tracking-[0.16em] text-[#526450] uppercase">
                                Como funciona a proposta
                            </p>
                            <h2
                                id="how-title"
                                className="max-w-xl text-3xl leading-tight font-semibold tracking-[-0.04em] sm:text-4xl"
                            >
                                Um caminho claro, do contato à decisão.
                            </h2>
                        </div>
                        <ol className="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            {steps.map(({ title, description }, index) => (
                                <li
                                    key={title}
                                    className="border-t border-[#cbd5c5] pt-5"
                                >
                                    <span
                                        className="font-mono text-xs text-[#526450]"
                                        aria-hidden="true"
                                    >
                                        0{index + 1}
                                    </span>
                                    <h3 className="mt-5 text-xl font-semibold tracking-tight">
                                        {title}
                                    </h3>
                                    <p className="mt-3 max-w-xs text-sm leading-7 text-[#626b5d]">
                                        {description}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>
            </main>
            <footer className="border-t border-[#dce2d9] bg-[#fafaf7]">
                <div className="mx-auto flex max-w-7xl flex-col justify-between gap-7 px-6 py-9 sm:px-10 md:flex-row md:items-center">
                    <div>
                        <HomeBrand />
                        <p className="mt-3 text-xs leading-5 text-[#6c736d]">
                            Leads, campanhas e resultados em um só lugar.
                        </p>
                    </div>
                    <HomeNavigation
                        label="Navegação do rodapé"
                        className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-[#626963]"
                    />
                </div>
            </footer>
        </div>
    );
}
