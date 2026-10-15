export type HomePlan = {
    id: 'empresa' | 'agencia';
    name: string;
    audience: string;
    description: string;
    objective: string;
    groups: { title: string; benefits: string[] }[];
    accessNote: string;
};

export const homePlans: HomePlan[] = [
    {
        id: 'empresa',
        name: 'Empresa',
        audience: 'Para quem cuida da própria operação',
        description:
            'Tenha visibilidade sobre seus leads, negociações, campanhas e resultados comerciais.',
        objective:
            'Sua operação comercial organizada em uma conta empresarial.',
        groups: [
            {
                title: 'Do contato à negociação',
                benefits: [
                    'Gestão de leads e oportunidades',
                    'Organização de leads por categorias',
                    'Acompanhamento do funil comercial',
                ],
            },
            {
                title: 'Campanhas e resultados',
                benefits: [
                    'Registro e acompanhamento de campanhas',
                    'Controle de investimentos em mídia',
                    'Registro de custos de ferramentas',
                    'Receita e resultado das campanhas',
                    'Indicadores de custo por lead (CPL)',
                    'Dashboard com indicadores comerciais',
                ],
            },
            {
                title: 'Sua empresa, sua equipe',
                benefits: [
                    'Informações reunidas em uma conta empresarial',
                    'Acesso controlado para usuários autorizados',
                ],
            },
        ],
        accessNote:
            'Foco na sua empresa: oportunidades, investimentos e resultados da própria operação.',
    },
    {
        id: 'agencia',
        name: 'Agência',
        audience: 'Para quem acompanha múltiplos clientes',
        description:
            'Centralize sua operação e acompanhe diferentes clientes com organização e controle de acesso.',
        objective:
            'Uma visão por cliente, com cada informação no seu contexto.',
        groups: [
            {
                title: 'Sua agência e seus clientes',
                benefits: [
                    'Gestão comercial da própria agência',
                    'Organização de múltiplos clientes',
                    'Leads e campanhas acompanhados por cliente',
                ],
            },
            {
                title: 'Visibilidade por operação',
                benefits: [
                    'Indicadores e dashboards segmentados',
                    'Investimentos e resultados por cliente',
                    'Gestão de categorias e funis',
                ],
            },
            {
                title: 'Conexões com autorização',
                benefits: [
                    'Convites para conectar Agência e Empresa',
                    'Compartilhamento mediante autorização',
                    'Permissões específicas de leitura e edição',
                    'Possibilidade de revogação dos acessos',
                    'Separação das informações entre clientes',
                ],
            },
        ],
        accessNote:
            'O plano não concede acesso automático às empresas. Cada conexão depende de aceite e permissões explícitas.',
    },
];
