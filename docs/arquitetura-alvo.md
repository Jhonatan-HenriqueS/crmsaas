## 12. ANEXO — Especificação-alvo (arquitetura e segurança)

> Especificação-alvo viva, nascida do PDF 'Arquitetura e Segurança do CRM SaaS em Laravel' (versão
> condensada). Em dúvida sobre 'como deveria ser', o anexo manda; sobre 'como está', o código manda.
> Mantenha atualizado. Plano de implementação dos itens futuros: 10.2. Status: 10.1 e 10.3.

# Arquitetura e Segurança do CRM SaaS em Laravel

## Conclusão executiva

- Base: Laravel com auth por sessão/cookie, CSRF, Policies, PostgreSQL relacional, isolamento dos
  registros, logout com invalidação da sessão.
- Estruturar por conta/workspace (`account_id`), não só `user_id`: Empresa com usuários próprios;
  Agência com acesso controlado à conta da Empresa, sem misturar dados entre clientes.
- Navegador: sem JWT por login, sem token de auth no JavaScript. Sanctum só para API, SPA separada ou
  mobile (em SPA first-party, Sanctum também usa cookie de sessão).

Stack-alvo do MVP:

| Camada                   | Escolha                                           |
| ------------------------ | ------------------------------------------------- |
| Backend                  | Laravel 13 + PHP 8.4/8.5                          |
| Frontend                 | Inertia + React + TypeScript                      |
| Banco                    | PostgreSQL 18.6 ou posterior corrigido            |
| Sessões/cache/rate limit | Redis                                             |
| Autenticação web         | Laravel Session + Fortify                         |
| API/mobile futuro        | Laravel Sanctum                                   |
| Arquivos/prints          | S3 privado                                        |
| Jobs/OCR/e-mails         | Laravel Queue + Redis/Horizon                     |
| Infra futura             | CloudFront + WAF + ALB + containers + RDS + Redis |
| Web instalável           | PWA                                               |
| App de loja futuro       | Capacitor, mantendo Laravel como backend          |

- Fortify (Starter Kits Laravel 13): login, registro, recuperação, verificação de e-mail, 2FA + rate
  limiting oficial.
- PostgreSQL com RLS: 18.6+ ou versão explicitamente corrigida — CVE-2026-14666 (cache de planos e
  políticas RLS) afetou versões anteriores; correção em 13 de agosto de 2026.
  https://www.postgresql.org/support/security/CVE-2026-14666/

> **Decisão central:** navegador identifica o usuário pela sessão; `account_id` define o
> tenant; Policy define o que ele pode fazer; consulta SQL limita quais registros podem
> aparecer. Um ID ou URL nunca concede permissão.

## Arquitetura do produto e experiência do usuário

Referência Hostinger (só fluxo): planos separados, destaque de uma opção, CTA objetivo, checkout com resumo.

```
HOME → PLANO → CHECKOUT SIMULADO → CADASTRO → SESSÃO → DASHBOARD → CRM
```

### Home e planos

Plano Empresa:

> “Controle sua operação comercial e descubra exatamente onde seus leads e campanhas
> estão gerando resultado.”

Funções centrais: leads, categorias, funil, campanhas, investimentos, CPL, receita, margem e dashboard.

Plano Agência:

> “Gerencie sua própria operação e acompanhe múltiplos clientes em uma única estrutura.”

Acrescenta: clientes gerenciados, campanhas por cliente, permissões Empresa↔Agência e
compartilhamento controlado de dados.

Home sem dezenas de funcionalidades: para quem é o plano, o que resolve, preço e CTA.

### Checkout simulado

Mesmo simulado, servidor nunca acredita no frontend enviando:

```
plan = agency
success = true
```

Fluxo correto:

```
Usuário escolhe plano
↓
Backend cria intenção/ordem simulada
↓
Checkout mostra dados dessa ordem
↓
Usuário finaliza
↓
Backend marca a ordem como concluída
↓
Servidor libera /register para aquela intenção
↓
Conta + workspace + membership são criados
```

Gateway real depois: troca só o mecanismo de confirmação, não a arquitetura.

### Cadastro e login

Fortify define `GET /login`, `POST /login`, `POST /logout`, registro, recuperação e verificação de
e-mail. `GET /login` só entrega o formulário. `POST /login` envia credenciais por HTTPS; backend
verifica senha e inicia/regenera a sessão. Nunca mandar todos os dados da conta ao frontend após login.

```
POST /login
↓
credenciais válidas?
↓ sim
regenera session ID
↓
cookie de sessão
↓
GET /dashboard
↓
backend identifica usuário + account
↓
consulta SOMENTE dados desse account
```

## Banco de dados, CRM e isolamento entre contas

### Não use apenas user_id como dono do CRM

```
users
leads.user_id
campaigns.user_id
```

Funciona para sistema individual; quebra com: Agência com vários colaboradores; Empresa com vários
funcionários; Agência administrando Empresa; mudança de proprietário; permissões diferentes;
integração futura com equipes. Hierarquia (conta = fronteira de segurança):

```
USER
↓ membership
ACCOUNT / WORKSPACE
↓
LEADS
CAMPAIGNS
CATEGORIES
CLIENTS
METRICS
ATTACHMENTS
```

### Estrutura mínima recomendada

| Entidade            | Função                                 |
| ------------------- | -------------------------------------- |
| users               | Pessoa autenticada                     |
| accounts            | Empresa ou Agência                     |
| memberships         | Liga usuários às contas e papéis       |
| plans/subscriptions | Plano e entitlement                    |
| lead_categories     | Categorias próprias da conta           |
| leads               | Leads daquela conta                    |
| clients             | Clientes administrados por Agência     |
| campaigns           | Campanhas                              |
| campaign_metrics    | Histórico de métricas                  |
| agency_links        | Relação aceita Agência↔Empresa         |
| access_grants       | Permissões desse vínculo               |
| invitations         | Convites pendentes                     |
| attachments         | Prints/arquivos                        |
| audit_logs          | Eventos de segurança/ações importantes |

RLS PostgreSQL: com RLS ativo e nenhuma política permitindo, padrão = negar; mas dono da tabela e
papéis privilegiados podem contornar. Logo, RLS = segunda barreira, nunca substitui Policies nem
escopo da aplicação.

### Leads

Lead não vira:

```
user_123/lead.json
```

É linha relacional:

```
lead
├─ id
├─ account_id
├─ client_id?
├─ category_id
├─ name
├─ phone
├─ website?
├─ instagram?
├─ quoted_amount
├─ charged_amount?
├─ tool_cost?
├─ status
├─ maps_url? / latitude? / longitude?   [atualizado 2026-10-27]
└─ timestamps
```

Lead em "Finalizado" exige `charged_amount` > 0; regra no ponto único de escrita, nunca só no front.
[atualizado 2026-10-27]

Browser não escolhe o proprietário: backend deriva `account_id` da sessão/membership. Policies
autorizam ver, criar, atualizar e excluir. Tentativa de:

```
PATCH /leads/ABC
```

passa por:

```
Sessão válida?
↓
Usuário pertence ao account?
↓
Lead pertence ao account?
↓
Policy permite update?
↓
Payload validado?
↓
UPDATE
```

Conhecer o ID não basta. OWASP IDOR/BOLA: verificar autorização no objeto em cada requisição; UUID no
lugar de ID numérico só dificulta enumeração, não substitui autorização.
https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html

### Dashboard

Sem `user_id` na rota. Use:

```
/dashboard
```

Backend sabe:

```
session
→ user
→ active membership
→ account
→ dados daquele account
```

Várias contas depois:

```
/app/minha-empresa/dashboard
/app/cliente-alfa/dashboard
```

Slug só indica contexto, nunca prova autorização.

Data minimization (não "nenhum dado no frontend", que impediria o dashboard): backend entrega só
campos e agregados necessários à tela, nunca registros/propriedades desnecessários. OWASP API1:2023
(autorização por objeto) e API3:2023 (exposição excessiva/autorização por propriedade) são riscos
distintos. https://api-security.owasp.org/editions/2023/en/0xa3-broken-object-property-level-authorization/

### Dashboard inicial

| Componente            | Backend calcula                                                                                                |
| --------------------- | -------------------------------------------------------------------------------------------------------------- |
| Donut/pizza de leads  | total + count e percentual por categoria                                                                       |
| Campanhas ativas      | count                                                                                                          |
| Receita bruta         | soma do período                                                                                                |
| Investimento em mídia | soma                                                                                                           |
| Ferramentas           | soma                                                                                                           |
| Resultado             | receita − mídia − ferramentas                                                                                  |
| Margem                | resultado ÷ receita × 100                                                                                      |
| Meta da conta         | restante = meta − faturamento finalizado (pode ser negativo); prazo guardado como data [atualizado 2026-10-28] |

Exemplo:

```
20 leads
5 reunião     25%
5 contrato    25%
5 vendido     25%
5 pendente    25%
```

- Não chamar resultado de “lucro líquido” enquanto impostos, mão de obra e demais custos não forem
  contemplados; no MVP: resultado da campanha ou margem de contribuição.
- `campaign_metrics` histórico (append-only), nunca alterar coluna única: preserva valores por dia/
  semana; permite comparar períodos e auditar alterações.

## Autenticação, cookies, formulários e proteção dos dados

### Cookie e sessão

Credencial persistente = cookie de sessão protegido, nunca token exposto ao JavaScript. OWASP Session
Management: `Secure`, `HttpOnly`, `SameSite`, HTTPS em toda a sessão, regenerar ID ao mudar nível de
autenticação, nada de credencial de sessão em `localStorage`. Redis: sessão disponível para qualquer
container + rate limiter.

```
COOKIE
Secure
HttpOnly
SameSite
↓
SESSION ID
↓
Redis
↓
user/account no servidor
```

### CSRF

CSRF não é “token de login”. Token ligado à sessão, verificado em requisições mutáveis; Laravel 13 usa
também informação de origem do navegador antes da comparação tradicional, quando aplicável.

```
SESSION COOKIE  = "quem está autenticado"
CSRF TOKEN      = "esta requisição veio do contexto esperado"
POLICY          = "esse usuário pode fazer esta ação?"
```

Os três são necessários.

### Senha

Decisão-alvo (substitui o requisito original de 8), base NIST SP 800-63B
(https://pages.nist.gov/800-63-4/sp800-63b.html): 15 mínimo quando senha é o único fator; 8 só com
MFA; sem regras de composição arbitrárias (“uma maiúscula + uma minúscula + símbolo”); bloquear senhas
comuns/comprometidas. Estado atual: 7; divergência: 11.1.

> mínimo de 15 caracteres sem MFA, permitindo frases-senha.

Ou:

> mínimo de 8 somente quando 2FA estiver sendo exigido.

2FA via Fortify TOTP. Senha é hasheada (bcrypt ou Argon2id), não “criptografada”:

```
senha original
↓
Hash::make()
↓
hash irreversível
↓
PostgreSQL
```

Ninguém (você, administrador, dashboard) recupera a senha original.

### E-mail, telefone e dados comerciais

Não “criptografar todos os campos” sem projeto. Camadas do MVP:

```
HTTPS/TLS em trânsito
+
PostgreSQL/RDS criptografado em repouso
+
rede privada
+
IAM mínimo
+
controle Laravel
+
logs sem PII desnecessária
+
criptografia de campo seletiva
```

- RDS PostgreSQL: `rds.force_ssl` rejeita conexões sem SSL/TLS.
- Criptografia de campo do Laravel: valores autenticados (protegidos contra alteração).
- E-mail de login (unique, pesquisa): criptografia exige desenho adicional; sem criptografia caseira.
  V1: banco/backup criptografados em repouso, rede privada, autorização rigorosa; field encryption só
  para campos com necessidade e estratégia de pesquisa definidas.

### Validação do formulário

```
Frontend
UX imediata
↓
Backend
autoridade real
↓
Database constraints
última barreira de integridade
```

Regra de imagem do Laravel não aceita SVG por padrão (só se habilitado). Nunca mass assignment, vindo
do browser, de:

```
user_id
account_id
role
is_admin
owner_id
permissions
plan_status
```

### Comportamento para rotas não autorizadas

Deslogado em `/dashboard` (após login volta à rota pretendida):

```
→ redireciona para /login
```

API deslogada:

```
→ 401
```

Autenticado sem permissão para ação conhecida:

```
→ 403
```

Usuário A tentando recurso privado do tenant B (não revela existência; não substitui autorização):

```
→ 404
```

### Logout

```
Auth::logout()
↓
invalidate session
↓
regenerate CSRF token
↓
redirect
```

Depois, sessão antiga não abre `/dashboard`.

## Agência, compartilhamento, campanhas e app futuro

### Permissão Agência → Empresa

Modelo GitHub (convite aceito antes de virar colaborador; níveis Read, Triage, Write, Maintain, Admin;
aceitar e recusar distintos). https://docs.github.com/en/rest/collaborators/invitations

```
AGÊNCIA
│
├── solicita acesso
↓
EMPRESA recebe convite
│
├── Recusar
│
└── Aceitar
      ↓
   escolher permissões
      ↓
┌──────────────────────────┐
│ leads.read               │
│ leads.write              │
│ campaigns.read           │
│ campaigns.write          │
│ categories.write         │
└──────────────────────────┘
```

Sem bearer token permanente que “dá acesso à empresa”. Token só comprova/consome o convite; após
aceite, autorização = dado relacional:

```
agency_links
agency_account_id
company_account_id
status = accepted
```

```
access_grants
link_id
permission
```

Revogação:

```
status = revoked
```

Próxima requisição da Agência falha.

### Print da campanha do Facebook

IA nunca grava silenciosamente valores financeiros. AWS Textract extrai texto/formulários/tabelas com
níveis de confiança. GuardDuty Malware Protection for S3 analisa objetos novos em ambiente isolado e
gera finding se houver malware. Meta Marketing API/Insights (impressões, cliques, gasto, alcance,
ações), com autorização do cliente, é a fonte de verdade superior; print = conveniência de entrada.

```
PRINT
↓
validar MIME/tamanho
↓
S3 PRIVADO
↓
MALWARE SCAN
↓
QUEUE
↓
OCR / VISÃO
↓
JSON estruturado
↓
VALIDAÇÃO BACKEND
↓
TELA DE REVISÃO
↓
USUÁRIO CONFIRMA
↓
TRANSACTION
↓
campaign_metrics
```

### PWA e aplicativo futuro

Primeiro lançamento (Web App Manifest + service worker; instalação varia por plataforma/navegador):

```
Laravel + React
↓
responsivo
↓
Web App Manifest
↓
PWA
↓
instalável / adicionar à tela inicial
```

Depois (Capacitor: Web, iOS, Android; cliente mobile separado usa Sanctum):

```
        MESMO BACKEND LARAVEL
                 │
          ┌──────┴──────┐
          │             │
       WEB/PWA    APP iOS/Android
                        │
                    Capacitor
```

**Não precisamos reescrever PostgreSQL, campanhas, leads, Policies ou regras de negócio para criar o
app.** Troca-se/adiciona-se o cliente; Laravel continua backend.

## Escala, mercado e plano real de execução

- Nenhuma arquitetura garante zero queda/vazamento; defesa em profundidade (CDN/WAF/mitigação DDoS,
  rate limit, criptografia, observabilidade).
- MVP não paga infra pesada, mas o código nasce preparado para chegar nela.

Progressão (plano do MVP e além):

| Fase          | O que precisa existir                                       |
| ------------- | ----------------------------------------------------------- |
| Fundação      | Laravel, React/Inertia, PostgreSQL, Redis, Docker           |
| Identidade    | register/login/logout, sessão, CSRF, Fortify, 2FA preparado |
| Tenancy       | accounts + memberships + Policies                           |
| CRM           | categorias, leads, funil e CRUD                             |
| Campanhas     | campanhas + métricas históricas                             |
| Dashboard     | agregações por account_id                                   |
| Agência       | convites + grants + revogação                               |
| Imagens       | S3 + malware scan + OCR + confirmação                       |
| Produção      | backup, observabilidade, HTTPS, secrets                     |
| Crescimento   | containers horizontais + managed PostgreSQL/Redis           |
| Grande escala | CDN/WAF/autoscaling/filas/monitoramento avançado            |

- App stateless fora de Redis/PostgreSQL/S3: novas instâncias sem quebrar sessão. OCR, importação e
  e-mail em fila (Redis, SQS etc.), fora da requisição HTTP.
- Docker reproduz ambiente e empacota; não é sistema de segurança. Reduzir privilégios/capabilities,
  processo não privilegiado, sem segredos na imagem.
- RLS, se adotado, é cinto adicional; nunca justifica remover camada:

```
Policy Laravel
+
query account-scoped
+
PostgreSQL constraints
+
opcionalmente RLS
```

### Critério para liberar o MVP

Primeira versão não sai enquanto estes testes não passarem (situação atual: 10.3). Cada endpoint com
identificador de objeto verifica autorização de nível de objeto (OWASP API1:2023
https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization/).

| Teste                            | Resultado obrigatório                        |
| -------------------------------- | -------------------------------------------- |
| Usuário A lista leads de B       | impossível                                   |
| Usuário A abre ID/UUID de B      | 404/negado                                   |
| Usuário A faz PATCH em B         | impossível                                   |
| Usuário A tenta DELETE em B      | impossível                                   |
| account_id alterado no request   | ignorado/rejeitado                           |
| usuário deslogado abre dashboard | login obrigatório                            |
| cookie/sessão antigo após logout | inválido                                     |
| POST sem CSRF                    | rejeitado                                    |
| brute force no login             | rate limited                                 |
| Agência sem convite aceito       | zero acesso                                  |
| Agência com read tenta editar    | negado                                       |
| permissão revogada               | próxima operação negada                      |
| upload com MIME falso            | rejeitado                                    |
| arquivo malicioso                | não processado                               |
| OCR com baixa confiança          | não gravado automaticamente                  |
| dashboard Empresa A              | zero dado de Empresa B                       |
| exclusão de lead                 | dependências previstas tratadas em transação |
| restore do backup                | comprovadamente funcional                    |

LGPD: MVP nasce com aviso de privacidade, finalidade e retenção, mecanismos de acesso, política de
cookies aplicável e procedimento de incidentes. ANPD (guia de segurança + checklist): incidente com
risco/dano relevante exige avaliação e comunicação conforme regras da autoridade, prazo de 3 dias
úteis nos casos comunicáveis.
https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-sobre-seguranca-da-informacao-para-agentes-de-tratamento-de-pequeno-porte

Base aprovada:

> **Laravel 13 + Inertia/React + PostgreSQL + Redis, tenancy por `account_id`, Fortify/
> session cookies para web, Sanctum preparado para API/mobile, Policies em todos os
> recursos, nenhuma autoridade vinda de user_id/account_id do frontend,
> métricas históricas, convites granulares Agência↔Empresa, uploads privados e jobs
> assíncronos.**
