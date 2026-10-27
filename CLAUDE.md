# CLAUDE.md — Cérebro do projeto HING CRM SaaS

> Este arquivo é a **fonte única de contexto** do projeto. Ele existe para que o Claude não precise
> redescobrir a arquitetura a cada sessão. Leia-o inteiro antes de agir.

---

## 0. INSTRUÇÕES OBRIGATÓRIAS PARA O CLAUDE (ler antes de qualquer coisa)

### 0.1 Ordem de leitura e entendimento

1. Leia **este arquivo inteiro** antes de planejar, editar ou criar qualquer arquivo.
2. Em seguida, siga também o [AGENTS.md](AGENTS.md) (diretrizes Laravel Boost: Pint, Pest, Wayfinder,
   Inertia v3, `search-docs`, skills de domínio). As regras de lá continuam valendo.
3. Entenda as três camadas deste documento:
   - **Seções 1–9**: como o sistema **está hoje** (verificado no código em 2026-10-27).
   - **Seção 10**: o que o documento de arquitetura exige e **o que ainda falta** (gap analysis).
   - **Seção 12 (Anexo)**: o **conteúdo integral do PDF** "Arquitetura e Segurança do CRM SaaS em
     Laravel", que é a **especificação-alvo** do produto. Em caso de dúvida sobre "como deveria ser",
     o anexo manda; em caso de dúvida sobre "como está", o código manda (e este arquivo deve ser
     corrigido se divergir).
4. Só abra arquivos do código quando precisar do detalhe exato para a tarefa. O mapa da seção 3 diz
   onde cada coisa está.

### 0.2 Regras invioláveis de arquitetura e segurança

- **A conta (`accounts`) é a fronteira de segurança (tenant).** Todo dado de negócio pertence a um
  `account_id`. Nunca crie recursos de negócio "donos" por `user_id`.
- **O `account_id` é derivado da sessão no servidor**, via middleware `ResolveCurrentAccount` →
  `App\Http\CurrentAccount`. **Nunca** aceite `account_id`, `user_id`, `role`, `owner_id`,
  `is_admin`, `permissions`, `plan_status`, `system_key` vindos do browser.
- **Toda consulta de negócio parte da relação da conta**: `$currentAccount->get()->leads()`,
  `->leadCategories()`. Nunca `Lead::find($id)` solto.
- **Toda ação passa por Policy/Gate** (`Gate::authorize(...)` ou `authorize()` do FormRequest).
  Recurso de outro tenant responde **404** (`Response::denyAsNotFound()`), não 403.
- **FormRequests rejeitam campos extras** (hook `after()` que compara as chaves recebidas com a
  allowlist). Mantenha esse padrão em todo endpoint novo.
- **Mass assignment**: modelos usam `#[Fillable]` restrito e `#[Hidden]` para `account_id`,
  `system_key`, segredos. Não amplie `Fillable` com campos de autoridade.
- **Integridade no banco**: chaves estrangeiras compostas `(account_id, category_id)` impedem
  relacionamento entre tenants mesmo com bug na aplicação. Novas tabelas filhas devem repetir o padrão.
- **Escritas críticas em transação** com `lockForUpdate()` e `attempts: 3`; `saveOrFail()`/`deleteOrFail()`
  com `abort_unless(..., 409)`.
- **Data minimization**: o backend envia ao frontend somente os campos/agregados necessários
  (`paginate(12, [colunas explícitas])`, `->only([...])` no share do Inertia).
- **Sessão + CSRF + Policy** são três coisas diferentes e as três são obrigatórias.
- Senha é **hash** (Argon2id), nunca "criptografia" reversível.
- Mensagens ao usuário em **português do Brasil** (`lang/pt_BR`, mensagens customizadas nos FormRequests).
- Valores monetários: `decimal(12,2)` no banco, strings decimais no JSON, **sem float** no frontend
  (`parseLeadMoney` / `formatLeadMoney`).
- Datas civis (`first_contact_date`, `next_contact_date`) são `date` puras (`Y-m-d`); timestamps em UTC;
  relatórios usam `config('reporting.timezone')` (padrão `America/Porto_Velho`).

### 0.3 O que NÃO fazer sem pedido explícito do usuário

- Não alterar processos funcionais do CRM existentes (leads, categorias, finalização, dashboard, auth).
- Não adicionar/remover dependências (composer/npm).
- Não criar novas pastas-base nem documentação extra (além deste arquivo).
- Não remover testes.
- Não enfraquecer nenhuma barreira listada na seção 7 (rate limit, CSRF, policies, validação, FKs).

### 0.4 Protocolo de atualização deste arquivo (OBRIGATÓRIO)

**Sempre que uma nova funcionalidade, correção, migration, rota, policy, configuração de segurança
ou decisão arquitetural for implementada, o Claude DEVE atualizar este CLAUDE.md na mesma tarefa**:

1. Atualizar as seções afetadas (3 mapa de arquivos, 4 banco, 5 rotas, 6 fluxos, 7 segurança).
2. Atualizar a **matriz da seção 10** (mudar status ❌ → 🟡 → ✅) e a **matriz de critérios do MVP (10.3)**.
3. Remover/ajustar itens resolvidos da seção 11 (divergências e riscos).
4. Acrescentar uma linha no **Changelog (seção 13)** com data absoluta (AAAA-MM-DD) e resumo.
5. **Nunca editar o Anexo (seção 12)** — ele é a cópia fiel do PDF. Se o documento de arquitetura
   for revisado, substitua o anexo inteiro pela nova versão e registre no changelog.

### 0.5 Comandos do dia a dia

| Ação | Comando |
|---|---|
| Ambiente dev completo | `composer run dev` |
| Testes (narrow) | `php artisan test --compact --filter=NomeDoTeste` ou caminho do arquivo |
| Suíte completa (pedir ao usuário) | `php artisan test --compact` |
| Formatação PHP (obrigatório após editar PHP) | `vendor/bin/pint --dirty --format agent` |
| Análise estática | `composer types:check` (Larastan) |
| Front: lint/format/types | `npm run check` · `npm run types:check` |
| Build front | `npm run build` |
| CI local | `composer ci:check` |
| Rotas | `php artisan route:list --except-vendor` |
| Preparar categoria "Finalizado" em contas legadas | `php artisan accounts:ensure-sale-category` |

---

## 1. Visão geral do produto (estado atual — MVP v1)

**HING CRM** é um CRM SaaS multi-tenant para dois tipos de conta: **Empresa** (opera a própria
operação comercial) e **Agência** (no futuro, gerencia múltiplos clientes com permissões
concedidas pela Empresa).

Hoje o MVP entrega:

- **Home pública** (`/`) com proposta e cards dos planos Empresa e Agência (`resources/js/lib/home-plans.ts`).
- **Cadastro** com escolha de plano (`/register?plan=empresa|agencia`), aceite de Termos, criação atômica
  de **User + Account + Membership(owner) + categoria permanente "Finalizado"**.
- **Login / logout / recuperação de senha / verificação de e-mail / 2FA TOTP** via Laravel Fortify.
- **Categorias de leads** (CRUD) com nome + cor HEX única por conta, contagem e percentual de leads,
  categoria de sistema "Finalizado" protegida, exclusão com confirmação de impacto que **apaga em
  cascata todos os leads da categoria**.
- **Leads** (CRUD) com: nome, descrição, categoria (etapa), data do primeiro contato, data do próximo
  contato, valor potencial, valor cobrado; botão **"Finalizar venda"** (move para a categoria
  "Finalizado" com confirmação explícita), reabertura com confirmação, exclusão, filtro por categoria,
  paginação.
- **Dashboard** com dados reais da conta: total de leads, novos leads por período (semana / 2 semanas /
  mês), leads vendidos e % vendido, gráfico de barras de **entrada de leads na semana atual (Seg–Dom)**
  e gráfico donut de **leads por categoria** com percentuais.
- **Configurações**: perfil (nome/e-mail), exclusão do usuário, segurança (senha, 2FA), aparência.

Ainda **não existe**: campanhas, métricas de campanha, investimentos/CPL/receita/margem, clientes de
agência, vínculo Agência↔Empresa, convites, grants, checkout simulado, uploads/OCR, audit log, Redis,
S3, Sanctum, PWA. Ver seção 10.

---

## 2. Stack real instalada (verificado em 2026-10-27)

| Camada | Versão instalada | Documento recomenda | Status |
|---|---|---|---|
| PHP | 8.3 (`composer: ^8.3`, CLI 8.3.36, Docker `serversideup/php:8.3-fpm-nginx`) | PHP 8.4/8.5 | 🟡 divergente |
| Laravel | `laravel/framework` v13.36.0 | Laravel 13 | ✅ |
| Auth web | `laravel/fortify` v1.41.0, guard `session` | Session + Fortify | ✅ |
| Frontend | Inertia v3 (`inertiajs/inertia-laravel` v3.5.1, `@inertiajs/react` ^3), React 19, TypeScript 5, Tailwind 4, Radix UI, Recharts 3.10.1, Sonner | Inertia + React + TS | ✅ |
| Rotas tipadas | `laravel/wayfinder` v0.1.21 (`@/actions`, `@/routes`) | — | ✅ |
| Build | Vite 8 via `vite-plus` (`vp build/dev/check`), React Compiler (babel) | — | ✅ |
| Banco | **SQLite** local (`DB_CONNECTION=sqlite`); **PostgreSQL** em produção (Docker instala `pdo_pgsql`) | PostgreSQL 18.6+ | 🟡 local ≠ prod |
| Sessão | driver `database` | Redis | 🟡 |
| Cache / rate limiter | `database` | Redis | 🟡 |
| Fila | `database` | Redis/Horizon | 🟡 |
| Arquivos | disco `local` | S3 privado | ❌ (sem uploads ainda) |
| API/mobile | — | Sanctum | ❌ |
| E-mail | mailer padrão `array` (se `MAIL_MAILER=log`, é convertido para `array` para nunca logar conteúdo) | — | 🟡 sem envio real configurado |
| Testes | Pest v4.7.8 + pest-plugin-laravel, SQLite `:memory:`; testes JS em `tests/Unit/*.test.mjs` | — | ✅ |
| Qualidade | Pint, Larastan v3.13, `vp check` (lint type-aware) | — | ✅ |
| Dev tooling | Laravel Boost (MCP), Pail, Sail (não usado), Chisel | — | — |
| Deploy | `Dockerfile` (serversideup php-fpm-nginx, Node 22 para build, `AUTORUN_ENABLED` roda migrations) — alvo Render | AWS (CloudFront/WAF/ALB/RDS) futuro | 🟡 |
| Locale | `pt_BR` (app, fallback, faker); `app.timezone=UTC`; `REPORTING_TIMEZONE=America/Porto_Velho` | — | ✅ |

---

## 3. Mapa do código

### 3.1 Backend (`app/`)

| Arquivo | Responsabilidade |
|---|---|
| `Actions/Fortify/CreateNewUser.php` | Registro: valida perfil, senha (`min 8 + uncompromised`, max 128), `terms` aceito, `plan ∈ Account::TYPES`; cria User+Account+Membership(owner) em transação; trata corrida de e-mail único. |
| `Actions/Fortify/ResetUserPassword.php` | Redefinição de senha (regras `passwordRules()`), `Hash::make`. |
| `Actions/SaveLead.php` | **Único ponto de escrita de lead** (create/update/complete). Transação, `lockForUpdate`, valida categoria da conta, exige `confirm_completion` ao entrar em "Finalizado" e `confirm_reopening` ao sair dela. |
| `Actions/EnsureSaleCompletedCategory.php` | Cria idempotentemente a categoria de sistema `sale_completed` ("Finalizado", `#00ff0a`) com lock na conta. Falha se o verde já estiver em uso por categoria comum. |
| `Actions/FlashCrudSuccess.php` | Toasts de sucesso via `Inertia::flash('toast', ...)` em pt-BR. |
| `Concerns/PasswordValidationRules.php` | Regras de senha: registro `Password::min(8)->uncompromised()->max(128)`; demais `Password::default()->max(128)` + `confirmed`. |
| `Concerns/ProfileValidationRules.php` | `name` (≤255) e `email` (email, ≤255, unique). |
| `Console/Commands/EnsureSaleCompletedCategories.php` | `accounts:ensure-sale-category` — provisiona categoria "Finalizado" em contas legadas. |
| `Http/CurrentAccount.php` | Lê a conta resolvida dos atributos do request; `abort 404` se ausente. |
| `Http/Middleware/ResolveCurrentAccount.php` | Exige usuário autenticado (401) e **exatamente 1 conta com role owner** (senão 404); injeta e limpa o contexto por request. |
| `Http/Middleware/EnsureEmailVerification.php` | Alias `verified`; só exige verificação se `auth.email_verification_required`. |
| `Http/Middleware/EnsureSafeAuthenticationRedirect.php` | Sanitiza `url.intended` (bloqueia open redirect: host/esquema/porta externos, `//`, `\`, caracteres de controle, credenciais na URL). |
| `Http/Middleware/ValidateAuthenticationInput.php` | Valida e normaliza (lowercase) e-mail/token/senha antes do Fortify em register, reset, confirm. |
| `Http/Middleware/HandleInertiaRequests.php` | Compartilha apenas `auth.user` com campos públicos (`id,name,email,email_verified_at,created_at,updated_at`); `Inertia::encryptHistory()` quando autenticado; `Cache-Control: no-store, private` em páginas autenticadas. |
| `Http/Middleware/HandleAppearance.php` | Cookie `appearance` (light/dark/system). |
| `Http/Controllers/DashboardController.php` | Métricas agregadas da conta (ver 6.6). |
| `Http/Controllers/LeadController.php` | index (paginado, filtro), `categoryOptions` (cursor), store, update, complete, destroy. Obs.: método `show()` existe **sem rota registrada**. |
| `Http/Controllers/LeadCategoryController.php` | index (paginado + programadas + cor sugerida), store, update, destroy com confirmação criptografada. |
| `Http/Controllers/Settings/ProfileController.php` | Editar perfil, excluir usuário (bloqueado se for o último owner de alguma conta). |
| `Http/Controllers/Settings/SecurityController.php` | Página de segurança (exige confirmação de senha), troca de senha (rotaciona `remember_token`, regenera sessão). |
| `Http/Requests/StoreLeadRequest.php` / `MutateLeadRequest.php` / `CompleteLeadRequest.php` | Validação/autorização de leads; `MutateLeadRequest::lead()` resolve o lead **pela relação da conta**. |
| `Http/Requests/StoreLeadCategoryRequest.php` / `MutateLeadCategoryRequest.php` | Validação/autorização de categorias; proíbe DELETE da categoria de sistema (403). |
| `Http/Requests/Auth/LoginRequest.php` | Login: email ≤255, senha ≤128, `remember` boolean. |
| `Http/Requests/Settings/*` | Perfil (troca de e-mail exige `current_password`), senha, exclusão, 2FA. |
| `Http/Responses/LogoutResponse.php` | `Inertia::clearHistory()` + redirect `/login` (204 em JSON). |
| `Http/Responses/PasswordResetLinkResponse.php` | Resposta **idêntica** para e-mail existente ou não (anti-enumeração). |
| `Models/User.php` | `MustVerifyEmail`, `TwoFactorAuthenticatable`; e-mail sempre lowercase; cast `password => hashed`; `memberships()`, `accounts()`, `ownedAccounts()`. |
| `Models/Account.php` | `TYPES = ['empresa','agencia']`; `save()` de conta nova provisiona a categoria "Finalizado" na mesma transação. |
| `Models/Membership.php` | user ↔ account com `role` (hoje só `owner`). |
| `Models/LeadCategory.php` | `SALE_COMPLETED`, `SALE_COMPLETED_COLOR`; `system_key` imutável; deleção da categoria de sistema vetada no evento `deleting`; `is_sale_completed` appended. |
| `Models/Lead.php` | Casts de data civil e `decimal:2`; `#[Hidden(account_id)]`. |
| `Policies/AccountPolicy.php` | `view`/`update`: usuário precisa ser owner da conta, senão `denyAsNotFound`. |
| `Policies/LeadPolicy.php` | `viewAny/view/create/update/delete` sempre exigem `lead.account_id === account.id` + AccountPolicy. |
| `Providers/AppServiceProvider.php` | `CarbonImmutable`, `DB::prohibitDestructiveCommands` em produção, força origem/esquema do `APP_URL` em produção, `Password::defaults` de produção. |
| `Providers/FortifyServiceProvider.php` | Views Inertia do Fortify, bindings de responses, middlewares extras nas rotas do Fortify e **rate limiters**. |
| `bootstrap/app.php` | `trustProxies` (só `X-Forwarded-Proto`), `trustHosts` (host do `APP_URL`, sem subdomínios), cookies criptografados (exceto `appearance`, `sidebar_state`), `AuthenticateSession`, `dontFlash(token, code, recovery_code)`. |

### 3.2 Config relevantes (`config/`)

`auth.php` (regra de verificação de e-mail), `fortify.php` (features), `session.php`, `hashing.php`
(argon2id), `reporting.php` (fuso dos relatórios), `trustedproxy.php` (túnel local opt-in), `mail.php`
(log→array).

### 3.3 Frontend (`resources/js/`)

| Caminho | Conteúdo |
|---|---|
| `app.tsx` | `createInertiaApp`; páginas sem layout: welcome, auth/register, auth/login, dashboard, categories/index, leads/index; `auth/*` → AuthLayout; `settings/*` → AppLayout+SettingsLayout. |
| `pages/welcome.tsx` | Home + planos (`components/home-plan-card.tsx`, `lib/home-plans.ts`). |
| `pages/auth/*` | login, register (plano + termos), forgot/reset password, verify-email, two-factor-challenge, confirm-password. |
| `pages/dashboard.tsx` + `components/dashboard/*` | `dashboard-metrics` (4 cards; "Em acompanhamento" = "em breve"), `dashboard-charts` (barras semanais + donut por categoria, Recharts), `dashboard-records` (placeholder "Em breve"), `dashboard-sidebar` (menu Dashboard / Categorias / Leads), `dashboard-data.ts` (tipos e formatadores). |
| `pages/leads/index.tsx` + `components/leads/*` | `create-lead-dialog` (criar/editar), `lead-card`, `lead-category-filter`, `lead-category-selector` (cursor paginado), `complete-lead-dialog`, `sale-transition-dialog` (confirmar finalização/reabertura), `delete-lead-dialog`. |
| `pages/categories/index.tsx` + `components/categories/*` | `category-card`, `category-dialog` (criar/editar com color picker `react-colorful`), `delete-category-dialog` (mostra impacto e envia `confirmation`). |
| `pages/settings/*` | profile, security (senha + 2FA), appearance. |
| `lib/lead-format.ts` | Datas civis, prazo do próximo contato ("Hoje", "Atrasado há N dias"), parse/format BRL sem float, `categoryColor()` (sanitiza cor antes de usar em CSS). |
| `lib/lead-transition.ts` | Decide se a mudança de categoria é `complete`/`reopen` **pelo `is_sale_completed` vindo do servidor**, nunca por nome/cor. |
| `hooks/use-flash-toast.ts`, `use-lead-category-options.ts`, `use-two-factor-auth.ts`, `use-appearance.tsx` | Hooks. |
| `actions/`, `routes/`, `wayfinder/` | **Gerados pelo Wayfinder** — não editar à mão. |
| `components/ui/*` | shadcn/Radix — não lintado/formatado. |

### 3.4 Testes (`tests/`)

~336 testes de Feature (Pest) organizados por tema, mais testes JS de `lead-format` e `lead-transition`.
Arquivos principais: `AccountAuthorizationTest`, `CurrentAccountTest`, `TenantFoundationTest`,
`LeadFoundationTest`, `LeadMutationTest`, `LeadIntegrityTest`, `LeadPageTest`, `LeadCategory*Test`,
`CategoryDeletion*Test`, `SaleCompletion*Test`, `SaleMetricsTest`, `SystemLeadCategoryTest`,
`Dashboard*Test`, `LogoutSecurityTest`, `SessionReplayTest`, `RegistrationSecurityTest`,
`RegistrationCsrfTest`, `SecurityConfigurationTest`, `Auth/*` (Login CSRF/Security, Origin, Recovery,
SessionRevocation, TrustedProxy, 2FA…), `Settings/*` (IdentitySecurity, OwnerDeletion…).
Padrão: `RefreshDatabase` em `Feature`, factories (`Account::factory()->agency()`,
`User::factory()->withTwoFactor()`, `->unverified()`).

---

## 4. Banco de dados (schema atual)

```
users ─┬─< memberships >─┬─ accounts ─┬─< lead_categories ─┬─< leads
       │   (role=owner)  │            │  (account_id,id)   │  FK composta
sessions (database)                    └──────────────< leads (account_id)
password_reset_tokens · cache · cache_locks · jobs · job_batches · failed_jobs
```

| Tabela | Colunas | Restrições |
|---|---|---|
| `users` | id, name, email (unique), email_verified_at, password (hash), two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, remember_token, timestamps | segredos 2FA criptografados pelo Fortify; `#[Hidden]` |
| `accounts` | id, `type enum('empresa','agencia')`, name nullable, timestamps | — |
| `memberships` | id, user_id FK cascade, account_id FK cascade, `role enum('owner')`, timestamps | unique(user_id, account_id) |
| `lead_categories` | id, account_id FK cascade, name(120), color(7), system_key(32) nullable, timestamps | unique(account_id, color); unique(account_id, id); unique(account_id, system_key); index(account_id, id) |
| `leads` | id, account_id FK cascade, category_id, name(120), description text null, first_contact_date date, next_contact_date date null, potential_amount decimal(12,2) null, charged_amount decimal(12,2) null, timestamps | **FK composta (account_id, category_id) → lead_categories(account_id, id) ON DELETE CASCADE**; index(account_id, id); index(category_id, account_id) |
| `sessions` | id, user_id, ip_address, user_agent, payload, last_activity | driver `database` |

Efeitos em cascata: excluir **categoria** → exclui todos os leads dela; excluir **conta** → exclui
categorias, leads e memberships; excluir **usuário** → exclui memberships (mas o fluxo de UI bloqueia
se ele for o último owner).

---

## 5. Rotas

Públicas: `GET /` (home), `GET /up` (health), rotas Fortify: `GET|POST /login`, `POST /logout`,
`GET|POST /register` (GET exige `?plan=empresa|agencia`, senão redireciona para `/#planos`),
`/forgot-password`, `/reset-password/{token}`, `/email/verify*`, `/two-factor-challenge`,
`/user/confirm-password`, `/user/two-factor-*`.

Autenticadas — middleware `auth` + `verified` + `ResolveCurrentAccount` (`routes/web.php`):

| Método | URI | Nome | Ação |
|---|---|---|---|
| GET | `/dashboard` | dashboard | DashboardController@index (`?period=week|fortnight|month`) |
| GET | `/leads` | leads.index | lista paginada (12), `?category=`, `?page=`; JSON se `wantsJson` |
| POST | `/leads` | leads.store | cria lead |
| GET | `/leads/etapas` | leads.category-options | categorias da conta, cursor (30) |
| PATCH | `/leads/{lead}` | leads.update | edita lead |
| POST | `/leads/{lead}/complete` | leads.complete | finaliza venda (`confirm_completion` obrigatório) |
| DELETE | `/leads/{lead}` | leads.destroy | exclui lead |
| GET | `/categorias` | categories.index | lista (12, simplePaginate) + programadas |
| POST | `/categorias` | categories.store | cria categoria |
| PATCH | `/categorias/{category}` | categories.update | edita nome/cor |
| DELETE | `/categorias/{category}` | categories.destroy | exclui com `confirmation` |

Settings (`routes/settings.php`): `GET /settings` → perfil; `GET|PATCH /settings/profile` (auth;
PATCH throttle 6/min); `DELETE /settings/profile`, `GET /settings/security` (RequirePassword),
`PUT /settings/password` (throttle 6/min), `GET /settings/appearance` (auth + verified).

IDs de rota usam `whereNumber`.

---

## 6. Fluxos funcionais (como o MVP funciona hoje)

### 6.1 Home → Plano → Cadastro

1. `/` mostra planos Empresa e Agência; CTA leva a `/register?plan=empresa|agencia`.
2. `registerView` valida o plano (senão volta para `/#planos`).
3. `POST /register` (throttle `registration` 5/min/IP + `ValidateAuthenticationInput` +
   `EnsureSafeAuthenticationRedirect`) → `CreateNewUser`:
   - valida nome, e-mail único (lowercase), senha (8–128, não vazada — HIBP), `terms` aceito, `plan`;
   - transação: `User` (senha `Hash::make` → argon2id) → `Account(type=plan)` (provisiona categoria
     "Finalizado") → `Membership(role=owner)`.
4. Fortify loga o usuário e redireciona para `/dashboard` (ou verificação de e-mail, se exigida).

> Não existe checkout simulado nem "ordem/intenção" no servidor: o plano vem do query param e é
> apenas validado contra a allowlist. Ver gap 10.1.

### 6.2 Login / 2FA / Logout / Recuperação

- `POST /login`: valida (`LoginRequest`), rate limit `login` 5/min por `email|ip`, erro genérico
  (não revela se o e-mail existe), **regenera sessão e CSRF**, respeita `url.intended` sanitizado.
  `rehash_on_login` atualiza hashes legados (bcrypt → argon2id).
- 2FA TOTP opcional por usuário (Fortify: `confirm` + `confirmPassword`); desafio com limiter
  `two-factor` 5/min por `login.id`; códigos de recuperação.
- `POST /logout`: Fortify faz `Auth::logout` + `invalidate` + `regenerateToken`; `LogoutResponse`
  limpa o histórico Inertia criptografado e redireciona para `/login`. Sessão antiga e cookie
  remember não restauram acesso (testado).
- Recuperação: limiter `password.email` (30/min/IP + 5/min por hash(email)+IP), resposta idêntica
  para e-mails existentes ou não; token expira em 60 min, uso único; `password.update` 10/min.
- Verificação de e-mail: obrigatória por padrão; só é desligada com `AUTH_EMAIL_VERIFICATION_REQUIRED=false`
  em `APP_ENV=local`, ou em outros ambientes **somente** com também `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION=true`.

### 6.3 Categorias

- Lista: categorias de sistema ("programadas") separadas + comuns paginadas (12), cada uma com
  `leads_count`, `percentage` sobre o total da conta e, para comuns, um token
  `deletion_confirmation = Crypt::encryptString(json([account_id, category_id, name, count]))`.
- Criação/edição: apenas `name` (trim, ≤120, sem caracteres de controle) e `color` (`#rrggbb`
  minúsculo, **única por conta** — validação + unique no banco + tratamento de corrida). Campos extras
  são rejeitados. Cor inicial sugerida: `#6d54b5` ou a primeira HEX livre.
- Categoria de sistema **"Finalizado"** (`system_key=sale_completed`, cor inicial `#00ff0a`): criada
  automaticamente com a conta; pode ser renomeada/recolorida; **não pode ser excluída** (403 + veto
  no model); `system_key` imutável e nunca aceito do browser.
- **Exclusão**: o diálogo mostra quantos leads serão apagados; o servidor, em transação com lock,
  recalcula a contagem, descriptografa a confirmação e compara com `hash_equals`. Se nome ou
  quantidade mudaram → 422 e nada é apagado. Se válido → **apaga a categoria e, por FK em cascata,
  todos os seus leads**.

### 6.4 Leads

Campos: `name` (obrigatório, ≤120), `description` (≤5000, opcional), `category_id` (obrigatório,
precisa existir **na conta**), `first_contact_date` (obrigatória, `Y-m-d`), `next_contact_date`
(opcional, ≥ primeiro contato), `potential_amount` e `charged_amount` (opcionais, 0–9.999.999.999,99,
2 casas). Nulo continua nulo (não vira zero).

- Lista: 12 por página, ordem `id desc`, filtro por categoria validado e escopado à conta (categoria
  alheia → 404), página além do fim redireciona para a última, eager load de categoria (sem N+1).
- Criar/editar: `SaveLead` em transação com lock. Se a categoria escolhida for "Finalizado" e o lead
  não estava finalizado → exige `confirm_completion`. Se estava finalizado e vai para outra →
  exige `confirm_reopening`. Edição sem mudanças não altera timestamps nem emite toast.
- **Finalizar venda** (`POST /leads/{id}/complete`): só aceita `confirm_completion`, `page`,
  `category`; move para "Finalizado"; idempotente.
- Excluir: remove apenas o lead (categoria permanece), preserva página/filtro no redirect.
- Frontend: valores BRL convertidos para string decimal sem float; prazo do próximo contato exibido
  relativo à data local.

### 6.5 Configurações

- Perfil: nome livre; troca de e-mail exige `current_password` na mesma requisição, normaliza para
  minúsculas e zera `email_verified_at`; throttle 6/min.
- Excluir usuário: exige senha; bloqueado se for o **último owner** de qualquer conta (lock nas contas);
  depois invalida sessão.
- Segurança: requer confirmação de senha recente (`password_timeout` 3h); troca de senha regenera
  sessão e `remember_token` → `AuthenticateSession` derruba outros navegadores.

### 6.6 Dashboard

Props Inertia (closures, permitem *partial reload*):

- `overview`: `totalLeads`; `soldLeads` (= leads na categoria "Finalizado") e `soldPercentage`;
  `inProgressLeads` **fixo em 0** (card "Em acompanhamento — em breve"); `categories` (todas, com
  contagem e % arredondado a 1 casa, sem forçar soma 100%); `weeklyIntake` = 7 dias Seg–Dom da
  **semana civil atual** no fuso `REPORTING_TIMEZONE`, contando `created_at` com limites semiabertos
  (uma única query com `COUNT(CASE ...)`); `weeklyTotal`.
- `newLeads`: contagem de leads criados nos últimos 7/14/30 dias civis (`period` em allowlist).
- Card "Prévia de registros": placeholder "Em breve".
- Tudo é calculado no backend e escopado por `account_id`; nenhuma lista de registros é enviada.

---

## 7. Segurança implementada (camada por camada)

| Camada | Implementação atual |
|---|---|
| **Transporte** | Produção força esquema/origem do `APP_URL` (`URL::forceScheme/useOrigin`); cookie de sessão `secure` em produção. HTTPS depende do provedor (Render). |
| **Hosts / proxies** | `trustHosts` só o host do `APP_URL` (sem subdomínios); `trustProxies` só `X-Forwarded-Proto` e lista de proxies vazia, exceto opt-in `TRUST_LOCAL_TUNNEL_PROXY=true` em local (loopback, Cloudflare Tunnel). Impede poisoning de URLs de e-mail. |
| **Sessão** | Guard `session`; driver `database`; cookie `HttpOnly`, `SameSite=lax`, `Secure` em produção; lifetime 120 min; ID regenerado no login, troca de senha e logout; `AuthenticateSession` invalida outras sessões quando a senha muda; `serialization=json`. |
| **Cookies** | Todos criptografados (AES-256-CBC, `APP_KEY`) exceto `appearance` e `sidebar_state` (não sensíveis). |
| **CSRF** | Middleware nativo do Laravel 13 (verificação por origem/Sec-Fetch + token / `X-XSRF-TOKEN`) em todas as mutações, inclusive login, registro e logout. GET logout não muda estado. |
| **Senhas** | Hash **Argon2id** (memory 64 MB, time 4, threads 1), `rehash_on_login`, cast `hashed`; máx. 128 caracteres; registro: mín. 8 + checagem de vazamento (HIBP `uncompromised`); troca/reset em produção: mín. 12 + maiúscula/minúscula + número + símbolo + uncompromised; fora de produção: mín. 8. |
| **2FA** | TOTP Fortify com confirmação e confirmação de senha; segredo e recovery codes criptografados; opcional (não obrigatório). |
| **Brute force / rate limit** | login 5/min (email\|ip); 2FA 5/min; registro 5/min/IP; recuperação 30/min/IP + 5/min por identidade; reset 10/min; perfil e senha 6/min. Store = cache `database`. |
| **Anti-enumeração** | Login e recuperação com respostas genéricas; recurso de outro tenant → 404. |
| **Open redirect** | `EnsureSafeAuthenticationRedirect` em login, 2FA, confirm, register, verify. |
| **Tenancy** | `ResolveCurrentAccount` (conta derivada da sessão/membership, exatamente 1 owner) + `CurrentAccount`; queries pela relação da conta; contexto limpo após cada request. |
| **Autorização** | `AccountPolicy` + `LeadPolicy` (`denyAsNotFound`); FormRequests chamam `Gate::authorize`; categorias usam `AccountPolicy@update`. |
| **Validação** | Dupla (front para UX, back como autoridade); allowlist de campos (rejeita extras); regex sem caracteres de controle; limites de tamanho; datas `Y-m-d`; dinheiro `decimal:0,2`. |
| **Mass assignment** | `#[Fillable]` mínimos; `account_id` atribuído via relação; `system_key` só por código. |
| **Integridade no banco** | FKs com cascade; **FK composta (account_id, category_id)**; uniques por conta; `DB::prohibitDestructiveCommands` em produção. |
| **Concorrência** | Transações com `lockForUpdate`, retries (`attempts: 3`), tratamento de `UniqueConstraintViolationException` (testes de concorrência em SQLite). |
| **Exposição de dados** | Share Inertia só com campos públicos do usuário; colunas explícitas nas queries; `#[Hidden]` em segredos e `account_id`; JSON de registro/login sem dados sensíveis. |
| **Histórico/cache do browser** | `Inertia::encryptHistory()` em páginas autenticadas, `clearHistory()` no logout, `Cache-Control: no-store, private`. |
| **Logs** | Mailer `log` convertido para `array` (conteúdo de e-mail de auth nunca vai para log); `dontFlash` de `token`, `code`, `recovery_code`. |
| **Frontend** | `categoryColor()` sanitiza cores antes de ir para CSS; nenhum token de autenticação em `localStorage` (só preferência de aparência). |
| **Produção** | `Dockerfile` roda como `www-data`, `--no-dev`, opcache, remove `node_modules`; migrations automáticas (`AUTORUN`). |

---

## 8. Variáveis de ambiente relevantes

`APP_URL` (define host confiável e origem), `APP_ENV`, `APP_KEY`, `DB_CONNECTION` (`sqlite` local /
`pgsql` produção), `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`,
`CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_MAILER`, `HASH_DRIVER` (padrão argon2id), `ARGON_*`,
`REPORTING_TIMEZONE`, `AUTH_EMAIL_VERIFICATION_REQUIRED`, `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION`,
`TRUST_LOCAL_TUNNEL_PROXY`, `REDIS_*` e `AWS_*` (presentes no `.env.example`, ainda não usados).

---

## 9. Convenções de código observadas

- PHP 8 com atributos Eloquent (`#[Fillable]`, `#[Hidden]`), comandos com `#[Signature]`/`#[Description]`,
  tipos de retorno explícitos, PHPDoc com array shapes, chaves `{}` sempre.
- Ações de domínio em `app/Actions` (classe com `handle()`), resolução de recurso dentro do FormRequest
  (`lead()`, `category()`), controllers finos.
- Mensagens de validação customizadas em pt-BR no próprio FormRequest.
- Toasts via `FlashCrudSuccess`.
- Rotas em português no path (`/categorias`, `/leads/etapas`), nomes em inglês (`categories.index`).
- Frontend: Wayfinder para URLs (`@/routes`, `@/actions`), componentes por domínio
  (`components/leads`, `components/categories`, `components/dashboard`), tipos em `types/`,
  helpers puros em `lib/` com teste `.test.mjs`.
- Testes Pest descritivos em inglês, foco em fronteiras de segurança e regressão.

---

## 10. Documento de arquitetura × implementação (gap analysis)

Legenda: ✅ implementado · 🟡 parcial/divergente · ❌ não implementado

### 10.1 Arquitetura e produto

| Item do documento | Status | Observação |
|---|---|---|
| Laravel 13 + Inertia/React/TS | ✅ | |
| PHP 8.4/8.5 | 🟡 | Projeto em PHP 8.3 (composer, CLI e Docker). |
| PostgreSQL 18.6+ | 🟡 | Produção usa pgsql (Docker); local e testes em SQLite. Migrations compiladas para PG testadas sem servidor. |
| Redis para sessão/cache/rate limit | ❌ | Tudo em `database`. |
| Fortify (login, registro, reset, verificação, 2FA) | ✅ | |
| Sanctum para API/mobile | ❌ | Não instalado (OK para o MVP web). |
| S3 privado | ❌ | Sem uploads. |
| Queue + Redis/Horizon | 🟡 | Fila `database`; nenhum job de domínio ainda. |
| Infra CloudFront/WAF/ALB/RDS | ❌ | Deploy atual em container (Render). |
| PWA (manifest/service worker) | ❌ | Sem `manifest.webmanifest`. |
| Capacitor | ❌ | Futuro. |
| Fluxo HOME → PLANO → CHECKOUT SIMULADO → CADASTRO | 🟡 | Home e plano ✅; **checkout simulado/ordem no servidor não existe** — plano vem de query param validado. |
| Tenancy por `account_id` (USER → membership → ACCOUNT) | ✅ | |
| Múltiplas contas por usuário / slug de contexto | ❌ | Middleware exige exatamente 1 conta owner. |
| `users` | ✅ | |
| `accounts` | ✅ | `type empresa|agencia`. |
| `memberships` | 🟡 | Só `role=owner`; sem papéis de colaborador. |
| `plans/subscriptions` | ❌ | |
| `lead_categories` | ✅ | + categoria de sistema "Finalizado". |
| `leads` | 🟡 | Campos atuais: name, description, category, datas de contato, potential/charged amount. **Faltam** do documento: `client_id`, `phone`, `website`, `instagram`, `quoted_amount` (≈ potential_amount), `tool_cost`, `status` (status hoje = categoria). |
| `clients` | ❌ | |
| `campaigns` / `campaign_metrics` (histórico) | ❌ | |
| `agency_links` / `access_grants` / `invitations` | ❌ | |
| `attachments` | ❌ | |
| `audit_logs` | ❌ | |
| RLS PostgreSQL (2ª barreira) | ❌ | Opcional no documento; hoje a 2ª barreira é FK composta. |
| Policies em todos os recursos | ✅ | Account e Lead; categorias via AccountPolicy. |
| `PATCH /leads/ABC` passa por sessão → membership → lead da conta → policy → validação → UPDATE | ✅ | |
| `/dashboard` sem user_id na rota | ✅ | |
| Data minimization | ✅ | |
| Dashboard: donut por categoria | ✅ | |
| Dashboard: campanhas ativas, receita, investimento, ferramentas, resultado, margem | ❌ | Dependem de campanhas. |
| Cookie Secure/HttpOnly/SameSite, sem token em localStorage | ✅ | |
| CSRF | ✅ | |
| Senha mínimo 15 sem MFA (ou 8 com 2FA obrigatório), sem regras de composição, bloquear vazadas | 🟡 | Registro: 8 + vazadas; produção (troca/reset): 12 + **regras de composição** (o documento desaconselha). Inconsistência registro × troca. 2FA não é obrigatório. |
| Hash bcrypt/Argon2id | ✅ | Argon2id. |
| Criptografia em repouso/TLS no banco/rede privada/IAM | ❌ | Depende da infra; nada configurado no código (ex.: `sslmode`). |
| Criptografia de campo seletiva | 🟡 | Apenas segredos 2FA (Fortify) e tokens de confirmação; correto para o MVP segundo o documento. |
| Validação tripla (front, back, constraints) | ✅ | |
| Bloqueio de mass assignment de campos de autoridade | ✅ | |
| Deslogado → /login; 401 API; 403 ação conhecida; 404 tenant alheio | ✅ | |
| Logout: logout → invalidate → regenerate CSRF → redirect | ✅ | |
| Convites Agência↔Empresa com aceite/recusa, grants granulares, revogação | ❌ | |
| Pipeline de print/OCR com revisão humana | ❌ | |
| Meta Marketing API | ❌ | |
| App stateless (sessão fora do servidor) | 🟡 | Sessão no banco já é compartilhável entre instâncias; Redis recomendado. |
| Docker sem segredos, usuário não privilegiado | ✅ | Roda como `www-data`; `.dockerignore` exclui `.env`, `.git`, `node_modules`, `vendor`, `tests`, logs e `public/build`. |
| LGPD: aviso de privacidade, retenção, acesso, cookies, incidentes | 🟡 | Só checkbox de Termos no cadastro. |
| Backup e restore testado | ❌ | |
| Observabilidade | ❌ | |

### 10.2 Escalabilidade futura (como encaixar sem quebrar a arquitetura)

- **Agência controlando contas de Empresas**: criar `agency_links(agency_account_id, company_account_id,
  status pending|accepted|revoked)`, `access_grants(link_id, permission ∈ leads.read, leads.write,
  campaigns.read, campaigns.write, categories.write)` e `invitations` (token só para consumir convite,
  hash no banco, expiração). Evoluir `ResolveCurrentAccount` para **conta ativa escolhida** (slug
  `/app/{slug}/...` ou valor na sessão) validada contra membership **ou** grant aceito e não revogado;
  Policies passam a checar permissão do grant. Revogação deve falhar a próxima requisição.
- **Equipes**: expandir `memberships.role` (ex.: owner, admin, member, viewer) via migration de enum e
  Policies por papel.
- **Clientes da agência**: `clients(account_id, ...)` + `leads.client_id` nullable com FK composta
  `(account_id, client_id)`.
- **Campanhas**: `campaigns(account_id, client_id?, ...)` + `campaign_metrics` **append-only** por data;
  dashboard calcula receita, mídia, ferramentas, resultado = receita − mídia − ferramentas, margem =
  resultado ÷ receita × 100 (nunca chamar de "lucro líquido").
- **E-mail**: configurar mailer real (SMTP/SES/Postmark) e enviar notificações via fila; manter
  conteúdo fora de logs.
- **Planos/checkout**: `plans`, `subscriptions`, `checkout_intents` criados no servidor; `/register`
  liberado só para intenção concluída; trocar depois só o mecanismo de confirmação pelo gateway real.
- **Uploads/OCR**: disco S3 privado, validação MIME/tamanho (sem SVG), scan de malware, job em fila,
  tela de revisão, gravação em transação.
- **Infra**: Redis (sessão, cache, rate limit, fila/Horizon), PostgreSQL gerenciado com TLS, backups
  testados, `audit_logs` para eventos de segurança.
- **API/mobile**: Sanctum; PWA (manifest + service worker); Capacitor reutilizando o mesmo backend.

### 10.3 Critérios para liberar o MVP (do documento) × situação atual

| Teste exigido | Situação |
|---|---|
| Usuário A lista leads de B → impossível | ✅ coberto (`LeadFoundationTest`, `DashboardMetricsTest`) |
| A abre ID de B → 404/negado | ✅ (`LeadPolicy`, `CurrentAccountTest`) |
| A faz PATCH em B → impossível | ✅ (`LeadMutationTest`) |
| A tenta DELETE em B → impossível | ✅ (`LeadMutationTest`, `LeadIntegrityTest`) |
| `account_id` alterado no request → ignorado/rejeitado | ✅ (campos extras rejeitados) |
| Deslogado abre dashboard → login | ✅ (`DashboardTest`) |
| Cookie/sessão antigo após logout → inválido | ✅ (`LogoutSecurityTest`, `SessionReplayTest`) |
| POST sem CSRF → rejeitado | ✅ (`*CsrfTest`) |
| Brute force no login → rate limited | ✅ (`AuthenticationTest`, `LoginSecurityTest`) |
| Agência sem convite aceito → zero acesso | ⏳ não aplicável ainda (sem vínculo; hoje acesso zero por padrão) |
| Agência com read tenta editar → negado | ❌ funcionalidade inexistente |
| Permissão revogada → próxima operação negada | 🟡 membership revogada testada; grants inexistentes |
| Upload com MIME falso → rejeitado | ❌ sem uploads |
| Arquivo malicioso → não processado | ❌ sem uploads |
| OCR baixa confiança → não gravado | ❌ sem OCR |
| Dashboard Empresa A → zero dado de B | ✅ (`DashboardMetricsTest`, `TenantFoundationTest`) |
| Exclusão de lead → dependências em transação | ✅ (`LeadIntegrityTest`) |
| Restore do backup comprovadamente funcional | ❌ não há processo de backup |

---

## 11. Divergências, riscos e débitos conhecidos (não alterar sem pedido)

1. **Política de senha** inconsistente: registro aceita 8 caracteres; troca/reset em produção exige 12 +
   composição; documento pede 15 sem MFA (ou 8 com 2FA obrigatório) e **sem** regras de composição.
   A mensagem de UI do registro cita 8 caracteres.
2. **Plano escolhido pelo browser** (`?plan=`) sem ordem/checkout no servidor — hoje inofensivo (não há
   entitlement diferente), mas precisa mudar antes de existir diferença de recursos/cobrança.
3. **Conta Agência = conta Empresa** em funcionalidades; nada específico de agência existe.
4. `ResolveCurrentAccount` exige **exatamente uma** conta owner: usuário convidado/colaborador ou com
   2 contas recebe 404 — precisa evoluir para seleção de conta ativa.
5. `LeadController::show()` está implementado mas **sem rota** (código morto ou pendente).
6. Card "Em acompanhamento" fixo em 0 e "Prévia de registros" placeholder.
7. Sessão/cache/rate limit em `database` (sem Redis); fila `database` sem worker definido no Docker.
8. Mailer padrão `array`: e-mails de verificação/reset **não são enviados** até configurar `MAIL_MAILER`.
   Se a verificação de e-mail estiver exigida em produção sem mailer real, usuários não conseguem verificar
   (por isso existe o opt-out explícito `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION`).
9. Desenvolvimento em SQLite × produção em PostgreSQL: comportamento de locks/enum difere (há testes
   de compilação de constraints PG, mas não há execução contra PG real).
10. `trustProxies` com lista vazia em produção: atrás do proxy do Render o esquema HTTPS é garantido
    por `URL::forceScheme` a partir do `APP_URL`; IP do cliente para rate limit pode ser o do proxy —
    avaliar `proxies` corretos antes de escalar.
11. Sem audit log, backup/restore, observabilidade, política de privacidade/LGPD.

---

## 12. ANEXO — Conteúdo integral do PDF "Arquitetura e Segurança do CRM SaaS em Laravel"

> Cópia fiel do documento fornecido pelo usuário (18 páginas). Tabelas e diagramas convertidos para
> Markdown/blocos de código sem alterar o texto. Números sobrescritos são as referências listadas ao final.
> **Não editar** — substituir integralmente se o documento mudar.

# Arquitetura e Segurança do CRM SaaS em Laravel

## Conclusão executiva

A pesquisa confirma a direção do relatório anterior: Laravel com autenticação por sessão/cookie,
CSRF, Policies, PostgreSQL relacional, isolamento dos registros e logout com invalidação da sessão
continua sendo a base correta. fileciteturn0file0 A principal evolução que eu faria antes de
escrever o CRM é estruturar o sistema por conta/workspace ( `account_id` ), e não simplesmente por
`user_id` . Isso é o que permitirá que uma Empresa tenha usuários próprios e que uma Agência
receba acesso controlado à conta de uma Empresa sem misturar dados entre clientes.

Para o navegador, não há motivo para transformar cada login em JWT ou armazenar tokens de
autenticação no JavaScript. O Laravel 13 possui autenticação nativa baseada em sessão e cookie; a
própria documentação recomenda Sanctum para aplicações que também terão API, SPA separada ou
cliente mobile. Em uma SPA first-party, Sanctum também prioriza a autenticação por cookie de sessão. ¹

Minha arquitetura recomendada para o MVP é:

| Camada | Escolha |
|---|---|
| Backend | Laravel 13 + PHP 8.4/8.5 |
| Frontend | Inertia + React + TypeScript |
| Banco | PostgreSQL 18.6 ou posterior corrigido |
| Sessões/cache/rate limit | Redis |
| Autenticação web | Laravel Session + Fortify |
| API/mobile futuro | Laravel Sanctum |
| Arquivos/prints | S3 privado |
| Jobs/OCR/e-mails | Laravel Queue + Redis/Horizon |
| Infra futura | CloudFront + WAF + ALB + containers + RDS + Redis |
| Web instalável | PWA |
| App de loja futuro | Capacitor, mantendo Laravel como backend |

Laravel 13 fornece Fortify nos Starter Kits para login, registro, recuperação de senha, verificação de
email e 2FA, além de mecanismos oficiais de rate limiting. ²

Há um detalhe importante sobre o PostgreSQL: se utilizarmos Row-Level Security como camada
adicional, eu partiria de PostgreSQL 18.6 ou uma versão explicitamente corrigida, pois a
CVE-2026-14666 afetou versões anteriores e envolvia cache de planos e políticas RLS; a correção foi
publicada em 13 de agosto de 2026. ³

> **Decisão central:** navegador identifica o usuário pela sessão; `account_id` define o
> tenant; Policy define o que ele pode fazer; consulta SQL limita quais registros podem
> aparecer. Um ID ou URL nunca concede permissão.

## Arquitetura do produto e experiência do usuário

As referências visuais da Hostinger que você enviou são úteis principalmente pelo fluxo de decisão,
não por tecnologia interna: planos claramente separados, destaque visual de uma opção, CTA objetivo e
depois uma tela de checkout com resumo antes da criação/continuação da conta. A página oficial
também utiliza uma estrutura de seleção de plano e fluxo de compra/conta. ⁴

Eu montaria seu fluxo assim:

```
HOME → PLANO → CHECKOUT SIMULADO → CADASTRO → SESSÃO → DASHBOARD → CRM
```

### Home e planos

O plano Empresa deve ser vendido como:

> “Controle sua operação comercial e descubra exatamente onde seus leads e campanhas
> estão gerando resultado.”

Funções centrais: leads, categorias, funil, campanhas, investimentos, CPL, receita, margem e
dashboard.

O plano Agência deve ser:

> “Gerencie sua própria operação e acompanhe múltiplos clientes em uma única estrutura.”

Ele acrescenta clientes gerenciados, campanhas por cliente, permissões Empresa↔Agência e
compartilhamento controlado de dados.

Não criaria dezenas de funcionalidades na home. Para o MVP, o usuário precisa compreender
imediatamente para quem é o plano, o que resolve, preço e CTA.

### Checkout simulado

Mesmo sendo uma simulação, o frontend não deveria simplesmente enviar:

```
plan = agency
success = true
```

e fazer o servidor acreditar nisso.

O fluxo correto conceitualmente é:

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

Assim, quando um gateway real entrar, você troca o mecanismo de confirmação, não a arquitetura
inteira.

### Cadastro e login

Laravel Fortify já define oficialmente rotas e lógica para `GET /login` , `POST /login` , `POST /logout` ,
registro, recuperação de senha e verificação de e-mail. ⁵

O `GET /login` não busca secretamente todos os dados do usuário. Ele entrega o formulário. O
`POST /login` envia as credenciais por HTTPS; o backend verifica a senha e, quando a autenticação é
aceita, inicia/regenera a sessão. Laravel recomenda invalidar a sessão e regenerar o token CSRF no
logout. ⁶

Depois:

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

Isso é diferente de mandar todos os dados da conta para o frontend após o login.

## Banco de dados, CRM e isolamento entre contas

Aqui está a mudança arquitetural mais importante em relação ao seu primeiro conceito.

### Não use apenas user_id como dono do CRM

Um modelo simples como:

```
users
leads.user_id
campaigns.user_id
```

funciona para um sistema individual, mas começa a atrapalhar quando surge:

- Agência com vários colaboradores;
- Empresa com vários funcionários;
- Agência administrando Empresa;
- mudança de proprietário;
- permissões diferentes;
- integração futura com equipes.

Eu criaria esta hierarquia:

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

Isso transforma a conta na fronteira de segurança.

### Estrutura mínima recomendada

| Entidade | Função |
|---|---|
| users | Pessoa autenticada |
| accounts | Empresa ou Agência |
| memberships | Liga usuários às contas e papéis |
| plans/subscriptions | Plano e entitlement |
| lead_categories | Categorias próprias da conta |
| leads | Leads daquela conta |
| clients | Clientes administrados por Agência |
| campaigns | Campanhas |
| campaign_metrics | Histórico de métricas |
| agency_links | Relação aceita Agência↔Empresa |
| access_grants | Permissões desse vínculo |
| invitations | Convites pendentes |
| attachments | Prints/arquivos |
| audit_logs | Eventos de segurança/ações importantes |

PostgreSQL fornece constraints, chaves estrangeiras e Row-Level Security; quando RLS está habilitado e
nenhuma política permite o acesso, a política padrão é negar. RLS, contudo, tem exceções importantes
— como o proprietário da tabela normalmente poder contorná-la — portanto eu a considero segunda
barreira, não substituta das Policies e do escopo da aplicação. ⁷

### Leads

Um lead não deve virar:

```
user_123/lead.json
```

Ele deve ser uma linha relacional:

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
└─ timestamps
```

O browser não escolhe quem é o proprietário. O backend deriva `account_id` da sessão/membership.
Laravel recomenda Policies justamente para organizar autorização sobre recursos e ações como
visualizar, criar, atualizar e excluir. ⁸

Portanto, uma tentativa conceitual de:

```
PATCH /leads/ABC
```

passará por:

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

Conhecer o ID de outro lead não é suficiente. OWASP classifica exatamente esse tipo de falha como
IDOR/BOLA e recomenda verificar autorização no objeto em cada requisição; trocar IDs numéricos por
UUIDs só adiciona dificuldade de enumeração, não substitui autorização. ⁹

### Dashboard

Eu não colocaria `user_id` na rota apenas para descobrir qual dashboard carregar.

Use:

```
/dashboard
```

O backend sabe:

```
session
→ user
→ active membership
→ account
→ dados daquele account
```

Se posteriormente o usuário administrar várias contas:

```
/app/minha-empresa/dashboard
/app/cliente-alfa/dashboard
```

O slug apenas indica contexto. Ele nunca prova autorização.

Também é importante corrigir a frase “nenhum dado pode aparecer no frontend”. Isso literalmente
impediria o dashboard de funcionar. O princípio correto é data minimization: o backend entrega ao
frontend somente os campos e agregados necessários para aquela tela, nunca registros ou
propriedades desnecessárias. O OWASP trata exposição excessiva e autorização em propriedades como
riscos distintos de API. ¹⁰

### Dashboard inicial

Para o seu MVP eu manteria somente:

| Componente | Backend calcula |
|---|---|
| Donut/pizza de leads | total + count e percentual por categoria |
| Campanhas ativas | count |
| Receita bruta | soma do período |
| Investimento em mídia | soma |
| Ferramentas | soma |
| Resultado | receita − mídia − ferramentas |
| Margem | resultado ÷ receita × 100 |

Exemplo:

```
20 leads
5 reunião     25%
5 contrato    25%
5 vendido     25%
5 pendente    25%
```

E não chamaria o resultado de “lucro líquido” enquanto impostos, mão de obra e demais custos não
estiverem contemplados; no MVP é melhor nomeá-lo resultado da campanha ou margem de
contribuição.

Para campanhas, eu criaria registros históricos em `campaign_metrics` em vez de alterar
continuamente uma única coluna de métricas. Assim você preserva o valor de segunda-feira, terça-feira,
semana seguinte etc., tornando possível comparar períodos e auditar alterações.

## Autenticação, cookies, formulários e proteção dos dados

Seu relatório anterior acertou ao separar sessão, CSRF e autorização, que são três coisas diferentes.
fileciteturn0file0

### Cookie e sessão

No seu web app, a credencial persistente do navegador deve ser um cookie de sessão protegido, e não
um token exposto ao JavaScript. OWASP recomenda cookies com `Secure` , `HttpOnly` e política
`SameSite` , HTTPS em toda a sessão, regeneração do ID após mudança de nível de autenticação e
evitar armazenamento de credenciais de sessão em `localStorage` . ¹¹

A arquitetura será:

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

Quando a aplicação crescer para múltiplos containers, Redis permite que a sessão continue disponível
independentemente de qual instância Laravel atender a próxima requisição; o Laravel fornece driver
Redis e integração do rate limiter com Redis. ¹²

### CSRF

CSRF não é “token de login”.

Laravel gera um token CSRF associado à sessão e verifica requisições mutáveis; no Laravel 13, a
proteção também utiliza informações de origem disponibilizadas pelo navegador antes de recorrer à
comparação tradicional do token quando aplicável. ¹³

Portanto:

```
SESSION COOKIE  = "quem está autenticado"
CSRF TOKEN      = "esta requisição veio do contexto esperado"
POLICY          = "esse usuário pode fazer esta ação?"
```

Você precisa dos três.

### Senha

Aqui eu alteraria obrigatoriamente seu requisito original de 8 caracteres.

A edição atual do NIST SP 800-63B estabelece mínimo de 15 caracteres quando senha é o único fator;
8 pode ser permitido quando ela faz parte de autenticação multifator. O NIST também desaconselha
regras arbitrárias como “uma maiúscula + uma minúscula + símbolo” e recomenda bloquear senhas
comuns ou comprometidas. ¹⁴

Eu adotaria para o MVP:

> mínimo de 15 caracteres sem MFA, permitindo frases-senha.

Ou:

> mínimo de 8 somente quando 2FA estiver sendo exigido.

Laravel/Fortify já oferece suporte a autenticação de dois fatores baseada em TOTP. ⁵

A senha deve ser hasheada, não “criptografada”. Laravel suporta bcrypt e Argon2id. ¹⁵

Isso significa:

```
senha original
↓
Hash::make()
↓
hash irreversível
↓
PostgreSQL
```

Nem você, nem administrador, nem dashboard devem conseguir recuperar a senha original.

### E-mail, telefone e dados comerciais

Não é tecnicamente correto simplesmente “criptografar todos os campos” sem projeto.

A abordagem mais equilibrada para o MVP é:

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

RDS PostgreSQL pode obrigar conexões SSL/TLS; nas versões modernas, `rds.force_ssl` pode ser
imposto para rejeitar conexões sem SSL. ¹⁶

Laravel também possui serviço de criptografia para campos específicos; seus valores criptografados são
protegidos contra alteração por mecanismos de autenticação da própria implementação. ¹⁷

Um e-mail usado para login, unique e pesquisa é um caso no qual criptografia de campo exige
desenho adicional. Portanto, não inventaria criptografia caseira para ele. Para a primeira versão,
protegeria banco/backup com criptografia em repouso, rede privada e autorização rigorosa; field
encryption ficaria para campos em que sua necessidade e estratégia de pesquisa estejam definidas.

### Validação do formulário

A validação será dupla:

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

Laravel possui validação para tipo, limites e arquivos; sua regra de imagem inclusive não permite SVG
por padrão, a menos que isso seja explicitamente habilitado. ¹⁸

Nunca permita mass assignment de:

```
user_id
account_id
role
is_admin
owner_id
permissions
plan_status
```

a partir da autoridade do browser.

### Comportamento para rotas não autorizadas

Eu alteraria uma decisão do seu requisito.

Usuário deslogado tentando `/dashboard` :

```
→ redireciona para /login
```

e após autenticação pode retornar à rota pretendida; esse fluxo é suportado pela autenticação do
Laravel. ¹⁹

API deslogada:

```
→ 401
```

Usuário autenticado, mas sem permissão para ação conhecida:

```
→ 403
```

Usuário A tentando descobrir recurso privado do tenant B:

```
→ 404
```

Usar 404 neste último caso ajuda a não revelar nem a existência do objeto. Isso não substitui a
verificação real de autorização. ²⁰

### Logout

O procedimento documentado pelo Laravel é exatamente a ideia que definimos anteriormente:

```
Auth::logout()
↓
invalidate session
↓
regenerate CSRF token
↓
redirect
```
²¹

Após isso, a sessão antiga não deve abrir novamente `/dashboard` .

## Agência, compartilhamento, campanhas e app futuro

### Permissão Agência → Empresa

Eu usaria como referência conceitual o GitHub, porque é uma comparação melhor para o seu caso que
Instagram.

No GitHub, um proprietário pode enviar convite; o convidado aceita antes de se tornar colaborador, e
organizações possuem níveis granulares de acesso como Read, Triage, Write, Maintain e Admin. ²² A
API de convites também distingue aceitar e recusar convites. ²³

Seu CRM deve fazer algo semelhante:

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

Não criaria um bearer token permanente que “dá acesso à empresa”.

O token é apenas para comprovar/consumir o convite. Depois do aceite, a autorização deve existir
como dado relacional no banco.

Exemplo:

```
agency_links
agency_account_id
company_account_id
status = accepted
```

e:

```
access_grants
link_id
permission
```

Se a Empresa revogar:

```
status = revoked
```

a próxima requisição da Agência deve falhar.

### Print da campanha do Facebook

Esta ideia é tecnicamente viável e pode ser muito boa para UX, mas eu não deixaria inteligência
artificial gravar silenciosamente valores financeiros.

AWS Textract consegue extrair texto, formulários, tabelas e elementos estruturados de documentos e
imagens, retornando inclusive níveis de confiança. ²⁴

O pipeline correto seria:

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

GuardDuty Malware Protection for S3 pode verificar objetos recém-enviados e executar a análise em
ambiente isolado; em caso de malware pode gerar finding correspondente. ²⁵

Para a evolução comercial, eu consideraria superior buscar métricas pela Meta Marketing API/
Insights quando o cliente autorizar integração. A coleção oficial da Meta expõe métricas como
impressões, cliques, gasto, alcance e ações; dessa forma o print passa a ser conveniência de entrada, e
não a fonte de verdade. ²⁶

### PWA e aplicativo futuro

Sim: este CRM pode nascer como Laravel web e depois tornar-se um app.

Para o primeiro lançamento:

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

Um Web App Manifest fornece os metadados usados pelo navegador para tratar a aplicação como PWA
instalada; service workers permitem capacidades adicionais de aplicação web, embora o suporte e
experiência de instalação variem entre plataformas/navegadores. ²⁷

Depois:

```
        MESMO BACKEND LARAVEL
                 │
          ┌──────┴──────┐
          │             │
       WEB/PWA    APP iOS/Android
                        │
                    Capacitor
```

Capacitor é oficialmente projetado para ser adicionado a projetos web modernos e possui suporte a
Web, iOS e Android. ²⁸

Se mais tarde existir um cliente mobile separado, Laravel indica Sanctum como opção recomendada
para aplicações que combinam UI web, API e clientes móveis. ²⁹

O ponto importante é: **não precisamos reescrever PostgreSQL, campanhas, leads, Policies ou
regras de negócio para criar o app.** Troca-se/adiciona-se o cliente; o Laravel continua sendo o
backend.

## Escala, mercado e plano real de execução

Não existe arquitetura que garanta que “nunca cairá” ou “nunca vazará”. Nenhuma análise
tecnicamente responsável deveria prometer isso.

O que empresas de grande escala publicamente demonstram é defesa em profundidade. A Vercel
documenta uma camada de firewall que combina mitigação de DDoS e WAF, e possui checklist de
produção envolvendo headers, WAF, rate limiting, observabilidade e testes. ³⁰ A OpenAI publica
controles como criptografia em trânsito e em repouso e certificações de segurança; isso é evidência de
uma abordagem em camadas, não de invulnerabilidade. ³¹

Na AWS, CloudFront pode operar junto ao WAF e mecanismos de mitigação DDoS; a própria
documentação recomenda uma arquitetura resistente a DDoS, enquanto o AWS WAF pode controlar
tráfego HTTP e regras de proteção. ³²

Para seu MVP, contudo, eu não começaria pagando e configurando toda essa infraestrutura. Eu
faria o código nascer preparado para chegar nela.

A progressão seria:

| Fase | O que precisa existir |
|---|---|
| Fundação | Laravel, React/Inertia, PostgreSQL, Redis, Docker |
| Identidade | register/login/logout, sessão, CSRF, Fortify, 2FA preparado |
| Tenancy | accounts + memberships + Policies |
| CRM | categorias, leads, funil e CRUD |
| Campanhas | campanhas + métricas históricas |
| Dashboard | agregações por account_id |
| Agência | convites + grants + revogação |
| Imagens | S3 + malware scan + OCR + confirmação |
| Produção | backup, observabilidade, HTTPS, secrets |
| Crescimento | containers horizontais + managed PostgreSQL/Redis |
| Grande escala | CDN/WAF/autoscaling/filas/monitoramento avançado |

A aplicação deve permanecer stateless fora de Redis/PostgreSQL/S3 para que novas instâncias possam
ser acrescentadas sem quebrar a sessão. Laravel possui filas suportadas por Redis, SQS e outros
backends; tarefas pesadas como OCR, importação ou e-mail não precisam bloquear a requisição HTTP. ³³

Docker deve servir para reproduzir ambiente e empacotar a aplicação, mas não deve ser tratado como
um sistema de segurança completo. A documentação do Docker recomenda redução de privilégios/
capabilities, processos não privilegiados e cuidado para não incluir segredos nas imagens. ³⁴

Por fim, se RLS for adotado posteriormente, ele deve funcionar como cinto de segurança adicional:

```
Policy Laravel
+
query account-scoped
+
PostgreSQL constraints
+
opcionalmente RLS
```

e não como justificativa para remover qualquer uma das camadas anteriores. PostgreSQL documenta
que RLS restringe linhas em SELECT/INSERT/UPDATE/DELETE, mas também documenta condições em
que proprietários ou papéis privilegiados a contornam. ⁷

### Critério para liberar o MVP

A primeira versão não deve ser liberada enquanto estes testes não passarem:

| Teste | Resultado obrigatório |
|---|---|
| Usuário A lista leads de B | impossível |
| Usuário A abre ID/UUID de B | 404/negado |
| Usuário A faz PATCH em B | impossível |
| Usuário A tenta DELETE em B | impossível |
| account_id alterado no request | ignorado/rejeitado |
| usuário deslogado abre dashboard | login obrigatório |
| cookie/sessão antigo após logout | inválido |
| POST sem CSRF | rejeitado |
| brute force no login | rate limited |
| Agência sem convite aceito | zero acesso |
| Agência com read tenta editar | negado |
| permissão revogada | próxima operação negada |
| upload com MIME falso | rejeitado |
| arquivo malicioso | não processado |
| OCR com baixa confiança | não gravado automaticamente |
| dashboard Empresa A | zero dado de Empresa B |
| exclusão de lead | dependências previstas tratadas em transação |
| restore do backup | comprovadamente funcional |

Essa matriz é particularmente importante porque o OWASP ressalta que cada endpoint que recebe um
identificador de objeto precisa verificar autorização de nível de objeto. ³⁵

Quanto à LGPD, o MVP já deve nascer com aviso de privacidade, finalidade e retenção de dados,
mecanismos de acesso, política de cookies aplicável e procedimento para incidentes. A ANPD possui
guia específico de segurança e checklist; para incidentes com potencial de risco ou dano relevante, a
regulamentação vigente determina avaliação e comunicação conforme as regras da autoridade,
incluindo o prazo de três dias úteis aplicável aos casos comunicáveis. ³⁶

Portanto, eu aprovaria esta base para começar o projeto:

> **Laravel 13 + Inertia/React + PostgreSQL + Redis, tenancy por `account_id`, Fortify/
> session cookies para web, Sanctum preparado para API/mobile, Policies em todos os
> recursos, nenhuma autoridade vinda de user_id/account_id do frontend,
> métricas históricas, convites granulares Agência↔Empresa, uploads privados e jobs
> assíncronos.**

Essa arquitetura resolve o seu MVP atual sem transformar o projeto em uma infraestrutura
desnecessariamente complexa e, ao mesmo tempo, evita as decisões que normalmente obrigariam a
reescrever autenticação, banco e permissões quando o CRM começar a crescer.

## Relatório em PDF

O relatório técnico foi consolidado em cinco páginas, mantendo as decisões arquiteturais, modelo de
banco, autenticação, segurança, fluxo Agência↔Empresa, campanhas/OCR, PWA, infraestrutura, ordem
de desenvolvimento e critérios de aceite.

Baixar o relatório — Arquitetura Segura do CRM Laravel MVP

### Referências

1, 6, 19, 21, 29. Authentication | Laravel 13.x - The clean stack for Artisans and agents —
https://laravel.com/framework/docs/13.x/authentication?utm_source=chatgpt.com

2, 5. Starter Kits | Laravel 13.x - The clean stack for Artisans and agents —
https://laravel.com/framework/docs/starter-kits?trk=article-ssr-frontend-pulse_little-text-block&utm_source=chatgpt.com

3. PostgreSQL: CVE-2026-14666: PostgreSQL row security caching disregards role modifications —
https://www.postgresql.org/support/security/CVE-2026-14666/?utm_source=chatgpt.com

4. Hospedagem de sites a partir de R$ 5,99/mês —
https://www.hostinger.com/br/hospedagem-de-sites?no_head=1&utm_source=chatgpt.com

7. PostgreSQL: Documentation: 18: 5.9. Row Security Policies —
https://www.postgresql.org/docs/18/ddl-rowsecurity.html?utm_source=chatgpt.com

8. Authorization | Laravel 13.x - The clean stack for Artisans and agents —
https://laravel.com/framework/docs/13.x/authorization?utm_source=chatgpt.com

9, 20. Insecure Direct Object Reference Prevention - OWASP Cheat Sheet Series —
https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html?utm_source=chatgpt.com

10. API3:2023 Broken Object Property Level Authorization - OWASP API Security Top 10 —
https://api-security.owasp.org/editions/2023/en/0xa3-broken-object-property-level-authorization/?utm_source=chatgpt.com

11. Session Management - OWASP Cheat Sheet Series —
https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html?utm_source=chatgpt.com

12. Illuminate\Session\SessionManager | Laravel API —
https://api.laravel.com/docs/13.x/Illuminate/Session/SessionManager.html?utm_source=chatgpt.com

13. CSRF Protection | Laravel 13.x - The clean stack for Artisans and agents —
https://laravel.com/framework/docs/13.x/csrf?utm_source=chatgpt.com

14. NIST Special Publication 800-63B —
https://pages.nist.gov/800-63-4/sp800-63b.html?locale=en&utm_source=chatgpt.com

15. Hashing | Laravel 13.x - The clean stack for Artisans and agents —
https://laravel.com/framework/docs/hashing?utm_source=chatgpt.com

16. Using SSL with a PostgreSQL DB instance - Amazon Relational Database Service —
https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/PostgreSQL.Concepts.General.SSL.html?utm_source=chatgpt.com

17. Encryption | Laravel 12.x - The clean stack for Artisans and agents —
https://laravel.com/framework/docs/12.x/encryption?utm_source=chatgpt.com

18. Illuminate\Validation\Rules\ImageFile | Laravel API —
https://api.laravel.com/docs/13.x/Illuminate/Validation/Rules/ImageFile.html?utm_source=chatgpt.com

22. Inviting collaborators to a personal repository - GitHub Docs —
https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/repository-access-and-collaboration/inviting-collaborators-to-a-personal-repository?utm_source=chatgpt.com

23. REST API endpoints for repository invitations - GitHub Docs —
https://docs.github.com/en/rest/collaborators/invitations?utm_source=chatgpt.com

24. AnalyzeDocument - Amazon Textract —
https://docs.aws.amazon.com/pt_br/textract/latest/APIReference/API_AnalyzeDocument.html?utm_source=chatgpt.com

25. How does Malware Protection for S3 work? - Amazon GuardDuty —
https://docs.aws.amazon.com/guardduty/latest/ug/how-malware-protection-for-s3-gdu-works.html?utm_source=chatgpt.com

26. Get Ad Insights [L1] | Facebook Marketing API | Postman API Network —
https://www.postman.com/meta/facebook-marketing-api/request/u07tack/get-ad-insights-l1?utm_source=chatgpt.com

27. Installation | web.dev —
https://web.dev/learn/pwa/installation?hl=en&utm_source=chatgpt.com

28. Environment Setup | Capacitor Documentation —
https://capacitorjs.com/docs/getting-started/environment-setup?utm_source=chatgpt.com

30. Vercel Firewall —
https://vercel.com/docs/vercel-firewall?utm_source=chatgpt.com

31. Segurança e privacidade na OpenAI | OpenAI —
https://openai.com/pt-BR/security-and-privacy/?utm_source=chatgpt.com

32. Configure secure access and restrict access to content - Amazon CloudFront —
https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/SecurityAndPrivateContent.html?utm_source=chatgpt.com

33. Queues | Laravel 13.x - The clean stack for Artisans and agents —
https://laravel.com/framework/docs/13.x/queues.?utm_source=chatgpt.com

34. Docker Engine security | Docker Docs —
https://docs.docker.com/engine/security/?utm_source=chatgpt.com

35. API1:2023 Broken Object Level Authorization - OWASP API Security Top 10 —
https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization/?utm_source=chatgpt.com

36. Guia orientativo sobre segurança da informação para agentes de tratamento de pequeno porte —
https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-sobre-seguranca-da-informacao-para-agentes-de-tratamento-de-pequeno-porte?utm_source=chatgpt.com

---

## 13. Changelog deste arquivo

| Data | Mudança |
|---|---|
| 2026-10-27 | Criação do CLAUDE.md: instruções para o Claude, mapeamento completo do MVP v1 (commits até `aa06919`), segurança implementada, gap analysis contra o documento de arquitetura e anexo integral do PDF. Nenhum código alterado. |
| 2026-10-27 | `/CLAUDE.md` removido do `.gitignore`: este arquivo passa a ser versionado com o projeto. |
