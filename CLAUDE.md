# CLAUDE.md — Cérebro do projeto HING CRM SaaS

> **Arquivo comprimido e vivo: é o cérebro único do projeto. Ao atualizar (protocolo 0.4), mantenha o
> estilo curto, não perca contexto, preserve todas as seções e não crie outros arquivos de contexto.**
>
> Fonte única de contexto. Evita redescobrir a arquitetura a cada sessão. Ler inteiro antes de agir.

---

## 0. INSTRUÇÕES OBRIGATÓRIAS PARA O CLAUDE (ler antes de qualquer coisa)

### 0.1 Ordem de leitura e entendimento

1. Ler **este arquivo inteiro** antes de planejar, editar ou criar qualquer arquivo.
2. Seguir também [AGENTS.md](AGENTS.md) (Laravel Boost: Pint, Pest, Wayfinder, Inertia v3,
   `search-docs`, skills de domínio). Regras de lá continuam valendo.
3. Três camadas:
   - **Seções 1–9**: como o sistema **está hoje** (verificado no código em 2026-10-27).
   - **Seção 10**: o que o documento de arquitetura exige e **o que falta** (gap analysis).
   - **Seção 12 (Anexo)**: **especificação-alvo viva** do produto (nascida do PDF "Arquitetura e
     Segurança do CRM SaaS em Laravel"). Dúvida sobre "como deveria ser": **o anexo manda**. Dúvida
     sobre "como está": **o código manda**. Divergência entre os dois: corrigir este CLAUDE.md.
4. Abrir código só quando precisar do detalhe exato. Mapa da seção 3 diz onde cada coisa está.

### 0.2 Regras invioláveis de arquitetura e segurança

- **Conta (`accounts`) é a fronteira de segurança (tenant).** Todo dado de negócio pertence a um
  `account_id`. Nunca criar recurso de negócio "dono" por `user_id`.
- **`account_id` derivado da sessão no servidor**, via middleware `ResolveCurrentAccount` →
  `App\Http\CurrentAccount`. **Nunca** aceitar `account_id`, `user_id`, `role`, `owner_id`,
  `is_admin`, `permissions`, `plan_status`, `system_key` vindos do browser.
- **Toda consulta de negócio parte da relação da conta**: `$currentAccount->get()->leads()`,
  `->leadCategories()`. Nunca `Lead::find($id)` solto.
- **Toda ação passa por Policy/Gate** (`Gate::authorize(...)` ou `authorize()` do FormRequest).
  Recurso de outro tenant responde **404** (`Response::denyAsNotFound()`), não 403.
- **FormRequests rejeitam campos extras** (hook `after()` compara chaves recebidas com a allowlist).
  Manter em todo endpoint novo.
- **Mass assignment**: modelos com `#[Fillable]` restrito e `#[Hidden]` para `account_id`,
  `system_key`, segredos. Não ampliar `Fillable` com campos de autoridade.
- **Integridade no banco**: FKs compostas `(account_id, category_id)` impedem relação entre tenants
  mesmo com bug na aplicação. Novas tabelas filhas repetem o padrão.
- **Escritas críticas em transação** com `lockForUpdate()` e `attempts: 3`; `saveOrFail()`/`deleteOrFail()`
  com `abort_unless(..., 409)`.
- **Data minimization**: backend envia ao frontend só campos/agregados necessários
  (`paginate(12, [colunas explícitas])`, `->only([...])` no share do Inertia).
- **Sessão + CSRF + Policy**: três coisas diferentes, as três obrigatórias.
- Senha é **hash** (Argon2id), nunca "criptografia" reversível.
- Mensagens ao usuário em **português do Brasil** (`lang/pt_BR`, mensagens customizadas nos FormRequests).
- Dinheiro: `decimal(12,2)` no banco, strings decimais no JSON, **sem float** no frontend
  (`parseLeadMoney` / `formatLeadMoney`).
- Datas civis (`first_contact_date`, `next_contact_date`) são `date` puras (`Y-m-d`); timestamps em UTC;
  relatórios usam `config('reporting.timezone')` (padrão `America/Porto_Velho`).

### 0.3 O que NÃO fazer sem pedido explícito do usuário

- Não alterar processos funcionais existentes do CRM (leads, categorias, finalização, dashboard, auth).
- Não adicionar/remover dependências (composer/npm).
- Não criar novas pastas-base nem documentação extra (além deste arquivo).
- Não remover testes.
- Não enfraquecer nenhuma barreira da seção 7 (rate limit, CSRF, policies, validação, FKs).

### 0.4 Protocolo de atualização deste arquivo (OBRIGATÓRIO)

**Em toda tarefa concluída** (funcionalidade, correção, migration, rota, policy, config de segurança,
decisão arquitetural), o Claude **DEVE** atualizar **dentro deste CLAUDE.md**, na mesma tarefa:

1. Seções afetadas: 3 (mapa), 4 (banco), 5 (rotas), 6 (fluxos), 7 (segurança), 8 (env).
2. Matrizes da seção 10: status (❌ → 🟡 → ✅) e **critérios do MVP (10.3)**.
3. Seção 11: remover/ajustar itens resolvidos, incluir novos riscos.
4. **O Anexo (seção 12) é a especificação-alvo VIVA.** Atualize-o na mesma tarefa sempre que uma
   implementação, decisão ou pedido do usuário mudar a arquitetura-alvo: edite o trecho no lugar
   (sem duplicar), marque a mudança com [atualizado AAAA-MM-DD] e registre no changelog.
5. Changelog (seção 13): uma linha com data absoluta (AAAA-MM-DD) e resumo.
6. Nunca criar arquivo separado para isso.
7. Ao terminar a tarefa, informar no chat, em 2–3 linhas, quais seções do CLAUDE.md foram atualizadas.

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

**HING CRM**: CRM SaaS multi-tenant, dois tipos de conta: **Empresa** (opera a própria operação
comercial) e **Agência** (futuro: gerencia múltiplos clientes com permissões concedidas pela Empresa).

MVP hoje:

- **Home pública** (`/`): proposta + cards dos planos Empresa e Agência (`resources/js/lib/home-plans.ts`).
- **Cadastro** com plano (`/register?plan=empresa|agencia`), aceite de Termos, criação atômica de
  **User + Account + Membership(owner) + categoria permanente "Finalizado"**.
- **Login / logout / recuperação de senha / verificação de e-mail / 2FA TOTP** via Laravel Fortify.
- **Categorias de leads** (CRUD): nome + cor HEX única por conta, contagem e % de leads, categoria de
  sistema "Finalizado" protegida, exclusão com confirmação de impacto que **apaga em cascata todos os
  leads da categoria**.
- **Leads** (CRUD): nome, descrição, categoria (etapa), data do primeiro contato, data do próximo
  contato, valor potencial, valor cobrado; botão **"Finalizar venda"** (move para "Finalizado" com
  confirmação explícita), reabertura com confirmação, exclusão, filtro por categoria, paginação.
- **Dashboard** com dados reais da conta: total de leads, novos leads por período (semana / 2 semanas /
  mês), leads vendidos e % vendido, barras de **entrada de leads na semana atual (Seg–Dom)**, donut de
  **leads por categoria** com percentuais.
- **Configurações**: perfil (nome/e-mail), exclusão do usuário, segurança (senha, 2FA), aparência.

**Não existe ainda**: campanhas, métricas de campanha, investimentos/CPL/receita/margem, clientes de
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
| E-mail | mailer padrão `array` (`MAIL_MAILER=log` é convertido para `array`: conteúdo nunca vai a log) | — | 🟡 sem envio real |
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
| `Actions/Fortify/ResetUserPassword.php` | Redefinição de senha (`passwordRules()`), `Hash::make`. |
| `Actions/SaveLead.php` | **Único ponto de escrita de lead** (create/update/complete). Transação, `lockForUpdate`, valida categoria da conta, exige `confirm_completion` ao entrar em "Finalizado" e `confirm_reopening` ao sair. |
| `Actions/EnsureSaleCompletedCategory.php` | Cria idempotente a categoria de sistema `sale_completed` ("Finalizado", `#00ff0a`) com lock na conta. Falha se o verde já estiver em uso por categoria comum. |
| `Actions/FlashCrudSuccess.php` | Toasts de sucesso via `Inertia::flash('toast', ...)` em pt-BR. |
| `Concerns/PasswordValidationRules.php` | Senha: registro `Password::min(8)->uncompromised()->max(128)`; demais `Password::default()->max(128)` + `confirmed`. |
| `Concerns/ProfileValidationRules.php` | `name` (≤255) e `email` (email, ≤255, unique). |
| `Console/Commands/EnsureSaleCompletedCategories.php` | `accounts:ensure-sale-category` — provisiona "Finalizado" em contas legadas. |
| `Http/CurrentAccount.php` | Lê conta resolvida dos atributos do request; `abort 404` se ausente. |
| `Http/Middleware/ResolveCurrentAccount.php` | Exige usuário autenticado (401) e **exatamente 1 conta com role owner** (senão 404); injeta e limpa contexto por request. |
| `Http/Middleware/EnsureEmailVerification.php` | Alias `verified`; exige verificação só se `auth.email_verification_required`. |
| `Http/Middleware/EnsureSafeAuthenticationRedirect.php` | Sanitiza `url.intended` (bloqueia open redirect: host/esquema/porta externos, `//`, `\`, caracteres de controle, credenciais na URL). |
| `Http/Middleware/ValidateAuthenticationInput.php` | Valida e normaliza (lowercase) e-mail/token/senha antes do Fortify em register, reset, confirm. |
| `Http/Middleware/HandleInertiaRequests.php` | Share só `auth.user` com campos públicos (`id,name,email,email_verified_at,created_at,updated_at`); `Inertia::encryptHistory()` quando autenticado; `Cache-Control: no-store, private` em páginas autenticadas. |
| `Http/Middleware/HandleAppearance.php` | Cookie `appearance` (light/dark/system). |
| `Http/Controllers/DashboardController.php` | Métricas agregadas da conta (ver 6.6). |
| `Http/Controllers/LeadController.php` | index (paginado, filtro), `categoryOptions` (cursor), store, update, complete, destroy. Obs.: `show()` existe **sem rota registrada**. |
| `Http/Controllers/LeadCategoryController.php` | index (paginado + programadas + cor sugerida), store, update, destroy com confirmação criptografada. |
| `Http/Controllers/Settings/ProfileController.php` | Editar perfil, excluir usuário (bloqueado se último owner de alguma conta). |
| `Http/Controllers/Settings/SecurityController.php` | Página de segurança (exige confirmação de senha), troca de senha (rotaciona `remember_token`, regenera sessão). |
| `Http/Requests/StoreLeadRequest.php` / `MutateLeadRequest.php` / `CompleteLeadRequest.php` | Validação/autorização de leads; `MutateLeadRequest::lead()` resolve o lead **pela relação da conta**. |
| `Http/Requests/StoreLeadCategoryRequest.php` / `MutateLeadCategoryRequest.php` | Validação/autorização de categorias; proíbe DELETE da categoria de sistema (403). |
| `Http/Requests/Auth/LoginRequest.php` | Login: email ≤255, senha ≤128, `remember` boolean. |
| `Http/Requests/Settings/*` | Perfil (troca de e-mail exige `current_password`), senha, exclusão, 2FA. |
| `Http/Responses/LogoutResponse.php` | `Inertia::clearHistory()` + redirect `/login` (204 em JSON). |
| `Http/Responses/PasswordResetLinkResponse.php` | Resposta **idêntica** para e-mail existente ou não (anti-enumeração). |
| `Models/User.php` | `MustVerifyEmail`, `TwoFactorAuthenticatable`; e-mail sempre lowercase; cast `password => hashed`; `memberships()`, `accounts()`, `ownedAccounts()`. |
| `Models/Account.php` | `TYPES = ['empresa','agencia']`; `save()` de conta nova provisiona "Finalizado" na mesma transação. |
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
| `app.tsx` | `createInertiaApp`; sem layout: welcome, auth/register, auth/login, dashboard, categories/index, leads/index; `auth/*` → AuthLayout; `settings/*` → AppLayout+SettingsLayout. |
| `pages/welcome.tsx` | Home + planos (`components/home-plan-card.tsx`, `lib/home-plans.ts`). |
| `pages/auth/*` | login, register (plano + termos), forgot/reset password, verify-email, two-factor-challenge, confirm-password. |
| `pages/dashboard.tsx` + `components/dashboard/*` | `dashboard-metrics` (4 cards; "Em acompanhamento" = "em breve"), `dashboard-charts` (barras semanais + donut por categoria, Recharts), `dashboard-records` (placeholder "Em breve"), `dashboard-sidebar` (menu Dashboard / Categorias / Leads), `dashboard-data.ts` (tipos e formatadores). |
| `pages/leads/index.tsx` + `components/leads/*` | `create-lead-dialog` (criar/editar), `lead-card`, `lead-category-filter`, `lead-category-selector` (cursor paginado), `complete-lead-dialog`, `sale-transition-dialog` (confirmar finalização/reabertura), `delete-lead-dialog`. |
| `pages/categories/index.tsx` + `components/categories/*` | `category-card`, `category-dialog` (criar/editar, color picker `react-colorful`), `delete-category-dialog` (mostra impacto, envia `confirmation`). |
| `pages/settings/*` | profile, security (senha + 2FA), appearance. |
| `lib/lead-format.ts` | Datas civis, prazo do próximo contato ("Hoje", "Atrasado há N dias"), parse/format BRL sem float, `categoryColor()` (sanitiza cor antes do CSS). |
| `lib/lead-transition.ts` | Decide se mudança de categoria é `complete`/`reopen` **pelo `is_sale_completed` do servidor**, nunca por nome/cor. |
| `hooks/use-flash-toast.ts`, `use-lead-category-options.ts`, `use-two-factor-auth.ts`, `use-appearance.tsx` | Hooks. |
| `actions/`, `routes/`, `wayfinder/` | **Gerados pelo Wayfinder** — não editar à mão. |
| `components/ui/*` | shadcn/Radix — não lintado/formatado. |

### 3.4 Testes (`tests/`)

~336 testes de Feature (Pest) por tema + testes JS de `lead-format` e `lead-transition`.
Principais: `AccountAuthorizationTest`, `CurrentAccountTest`, `TenantFoundationTest`,
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

Cascatas: excluir **categoria** apaga todos os leads dela; excluir **conta** apaga categorias, leads e
memberships; excluir **usuário** apaga memberships (UI bloqueia se for o último owner).

---

## 5. Rotas

Públicas: `GET /` (home), `GET /up` (health), Fortify: `GET|POST /login`, `POST /logout`,
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
   - transação: `User` (senha `Hash::make` → argon2id) → `Account(type=plan)` (provisiona
     "Finalizado") → `Membership(role=owner)`.
4. Fortify loga o usuário e redireciona para `/dashboard` (ou verificação de e-mail, se exigida).

> Sem checkout simulado nem "ordem/intenção" no servidor: plano vem do query param, só validado
> contra a allowlist. Ver gap 10.1.

### 6.2 Login / 2FA / Logout / Recuperação

- `POST /login`: valida (`LoginRequest`), rate limit `login` 5/min por `email|ip`, erro genérico
  (não revela se e-mail existe), **regenera sessão e CSRF**, respeita `url.intended` sanitizado.
  `rehash_on_login` atualiza hashes legados (bcrypt → argon2id).
- 2FA TOTP opcional por usuário (Fortify: `confirm` + `confirmPassword`); desafio com limiter
  `two-factor` 5/min por `login.id`; códigos de recuperação.
- `POST /logout`: Fortify faz `Auth::logout` + `invalidate` + `regenerateToken`; `LogoutResponse`
  limpa histórico Inertia criptografado e redireciona para `/login`. Sessão antiga e cookie remember
  não restauram acesso (testado).
- Recuperação: limiter `password.email` (30/min/IP + 5/min por hash(email)+IP), resposta idêntica
  para e-mail existente ou não; token expira em 60 min, uso único; `password.update` 10/min.
- Verificação de e-mail: obrigatória por padrão. Desligada só com `AUTH_EMAIL_VERIFICATION_REQUIRED=false`
  em `APP_ENV=local`; em outros ambientes **somente** com também `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION=true`.

### 6.3 Categorias

- Lista: categorias de sistema ("programadas") separadas + comuns paginadas (12), cada uma com
  `leads_count`, `percentage` sobre o total da conta e, nas comuns, token
  `deletion_confirmation = Crypt::encryptString(json([account_id, category_id, name, count]))`.
- Criar/editar: só `name` (trim, ≤120, sem caracteres de controle) e `color` (`#rrggbb` minúsculo,
  **única por conta** — validação + unique no banco + tratamento de corrida). Campos extras rejeitados.
  Cor sugerida: `#6d54b5` ou a primeira HEX livre.
- Sistema **"Finalizado"** (`system_key=sale_completed`, cor inicial `#00ff0a`): criada com a conta;
  pode ser renomeada/recolorida; **não pode ser excluída** (403 + veto no model); `system_key`
  imutável, nunca aceito do browser.
- **Exclusão**: diálogo mostra quantos leads serão apagados; servidor, em transação com lock,
  recalcula contagem, descriptografa confirmação, compara com `hash_equals`. Nome ou quantidade
  mudaram → 422, nada apagado. Válido → **apaga categoria e, por FK em cascata, todos os seus leads**.

### 6.4 Leads

Campos: `name` (obrigatório, ≤120), `description` (≤5000, opcional), `category_id` (obrigatório,
existe **na conta**), `first_contact_date` (obrigatória, `Y-m-d`), `next_contact_date` (opcional,
≥ primeiro contato), `potential_amount` e `charged_amount` (opcionais, 0–9.999.999.999,99, 2 casas).
Nulo continua nulo (não vira zero).

- Lista: 12 por página, ordem `id desc`, filtro por categoria validado e escopado à conta (categoria
  alheia → 404), página além do fim redireciona para a última, eager load de categoria (sem N+1).
- Criar/editar: `SaveLead` em transação com lock. Categoria escolhida "Finalizado" e lead não
  finalizado → exige `confirm_completion`. Finalizado indo para outra → exige `confirm_reopening`.
  Edição sem mudança não altera timestamps nem emite toast.
- **Finalizar venda** (`POST /leads/{id}/complete`): aceita só `confirm_completion`, `page`,
  `category`; move para "Finalizado"; idempotente.
- Excluir: remove só o lead (categoria permanece), preserva página/filtro no redirect.
- Frontend: BRL convertido para string decimal sem float; prazo do próximo contato relativo à data local.

### 6.5 Configurações

- Perfil: nome livre; troca de e-mail exige `current_password` na mesma requisição, normaliza para
  minúsculas e zera `email_verified_at`; throttle 6/min.
- Excluir usuário: exige senha; bloqueado se for o **último owner** de qualquer conta (lock nas
  contas); depois invalida sessão.
- Segurança: confirmação de senha recente (`password_timeout` 3h); troca de senha regenera sessão e
  `remember_token` → `AuthenticateSession` derruba outros navegadores.

### 6.6 Dashboard

Props Inertia (closures, permitem *partial reload*):

- `overview`: `totalLeads`; `soldLeads` (= leads em "Finalizado") e `soldPercentage`;
  `inProgressLeads` **fixo em 0** (card "Em acompanhamento — em breve"); `categories` (todas, com
  contagem e % arredondado a 1 casa, sem forçar soma 100%); `weeklyIntake` = 7 dias Seg–Dom da
  **semana civil atual** no fuso `REPORTING_TIMEZONE`, contando `created_at` com limites semiabertos
  (uma query com `COUNT(CASE ...)`); `weeklyTotal`.
- `newLeads`: leads criados nos últimos 7/14/30 dias civis (`period` em allowlist).
- Card "Prévia de registros": placeholder "Em breve".
- Tudo calculado no backend, escopado por `account_id`; nenhuma lista de registros enviada.

---

## 7. Segurança implementada (camada por camada)

| Camada | Implementação atual |
|---|---|
| **Transporte** | Produção força esquema/origem do `APP_URL` (`URL::forceScheme/useOrigin`); cookie de sessão `secure` em produção. HTTPS depende do provedor (Render). |
| **Hosts / proxies** | `trustHosts` só host do `APP_URL` (sem subdomínios); `trustProxies` só `X-Forwarded-Proto`, lista de proxies vazia, exceto opt-in `TRUST_LOCAL_TUNNEL_PROXY=true` em local (loopback, Cloudflare Tunnel). Impede poisoning de URLs de e-mail. |
| **Sessão** | Guard `session`; driver `database`; cookie `HttpOnly`, `SameSite=lax`, `Secure` em produção; lifetime 120 min; ID regenerado no login, troca de senha e logout; `AuthenticateSession` invalida outras sessões ao mudar senha; `serialization=json`. |
| **Cookies** | Todos criptografados (AES-256-CBC, `APP_KEY`) exceto `appearance` e `sidebar_state` (não sensíveis). |
| **CSRF** | Middleware nativo do Laravel 13 (origem/Sec-Fetch + token / `X-XSRF-TOKEN`) em todas as mutações, inclusive login, registro, logout. GET logout não muda estado. |
| **Senhas** | Hash **Argon2id** (memory 64 MB, time 4, threads 1), `rehash_on_login`, cast `hashed`; máx. 128; registro: mín. 8 + vazamento (HIBP `uncompromised`); troca/reset em produção: mín. 12 + maiúscula/minúscula + número + símbolo + uncompromised; fora de produção: mín. 8. |
| **2FA** | TOTP Fortify com confirmação e confirmação de senha; segredo e recovery codes criptografados; opcional (não obrigatório). |
| **Brute force / rate limit** | login 5/min (email\|ip); 2FA 5/min; registro 5/min/IP; recuperação 30/min/IP + 5/min por identidade; reset 10/min; perfil e senha 6/min. Store = cache `database`. |
| **Anti-enumeração** | Login e recuperação com respostas genéricas; recurso de outro tenant → 404. |
| **Open redirect** | `EnsureSafeAuthenticationRedirect` em login, 2FA, confirm, register, verify. |
| **Tenancy** | `ResolveCurrentAccount` (conta da sessão/membership, exatamente 1 owner) + `CurrentAccount`; queries pela relação da conta; contexto limpo após cada request. |
| **Autorização** | `AccountPolicy` + `LeadPolicy` (`denyAsNotFound`); FormRequests chamam `Gate::authorize`; categorias usam `AccountPolicy@update`. |
| **Validação** | Dupla (front para UX, back como autoridade); allowlist de campos (rejeita extras); regex sem caracteres de controle; limites de tamanho; datas `Y-m-d`; dinheiro `decimal:0,2`. |
| **Mass assignment** | `#[Fillable]` mínimos; `account_id` via relação; `system_key` só por código. |
| **Integridade no banco** | FKs com cascade; **FK composta (account_id, category_id)**; uniques por conta; `DB::prohibitDestructiveCommands` em produção. |
| **Concorrência** | Transações com `lockForUpdate`, retries (`attempts: 3`), tratamento de `UniqueConstraintViolationException` (testes de concorrência em SQLite). |
| **Exposição de dados** | Share Inertia só com campos públicos do usuário; colunas explícitas nas queries; `#[Hidden]` em segredos e `account_id`; JSON de registro/login sem dados sensíveis. |
| **Histórico/cache do browser** | `Inertia::encryptHistory()` em páginas autenticadas, `clearHistory()` no logout, `Cache-Control: no-store, private`. |
| **Logs** | Mailer `log` convertido para `array` (conteúdo de e-mail de auth nunca vai a log); `dontFlash` de `token`, `code`, `recovery_code`. |
| **Frontend** | `categoryColor()` sanitiza cores antes do CSS; nenhum token de auth em `localStorage` (só preferência de aparência). |
| **Produção** | `Dockerfile` roda como `www-data`, `--no-dev`, opcache, remove `node_modules`; migrations automáticas (`AUTORUN`). |

---

## 8. Variáveis de ambiente relevantes

`APP_URL` (host confiável e origem), `APP_ENV`, `APP_KEY`, `DB_CONNECTION` (`sqlite` local /
`pgsql` produção), `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`,
`CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_MAILER`, `HASH_DRIVER` (padrão argon2id), `ARGON_*`,
`REPORTING_TIMEZONE`, `AUTH_EMAIL_VERIFICATION_REQUIRED`, `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION`,
`TRUST_LOCAL_TUNNEL_PROXY`, `REDIS_*` e `AWS_*` (no `.env.example`, ainda não usados).

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
| PostgreSQL 18.6+ | 🟡 | Produção pgsql (Docker); local e testes SQLite. Migrations compiladas para PG testadas sem servidor. |
| Redis para sessão/cache/rate limit | ❌ | Tudo em `database`. |
| Fortify (login, registro, reset, verificação, 2FA) | ✅ | |
| Sanctum para API/mobile | ❌ | Não instalado (OK para MVP web). |
| S3 privado | ❌ | Sem uploads. |
| Queue + Redis/Horizon | 🟡 | Fila `database`; nenhum job de domínio. |
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
| `leads` | 🟡 | Atuais: name, description, category, datas de contato, potential/charged amount. **Faltam**: `client_id`, `phone`, `website`, `instagram`, `quoted_amount` (≈ potential_amount), `tool_cost`, `status` (hoje status = categoria). |
| `clients` | ❌ | |
| `campaigns` / `campaign_metrics` (histórico) | ❌ | |
| `agency_links` / `access_grants` / `invitations` | ❌ | |
| `attachments` | ❌ | |
| `audit_logs` | ❌ | |
| RLS PostgreSQL (2ª barreira) | ❌ | Opcional no documento; hoje 2ª barreira = FK composta. |
| Policies em todos os recursos | ✅ | Account e Lead; categorias via AccountPolicy. |
| `PATCH /leads/ABC` passa por sessão → membership → lead da conta → policy → validação → UPDATE | ✅ | |
| `/dashboard` sem user_id na rota | ✅ | |
| Data minimization | ✅ | |
| Dashboard: donut por categoria | ✅ | |
| Dashboard: campanhas ativas, receita, investimento, ferramentas, resultado, margem | ❌ | Dependem de campanhas. |
| Cookie Secure/HttpOnly/SameSite, sem token em localStorage | ✅ | |
| CSRF | ✅ | |
| Senha mínimo 15 sem MFA (ou 8 com 2FA obrigatório), sem regras de composição, bloquear vazadas | 🟡 | Registro: 8 + vazadas; produção (troca/reset): 12 + **regras de composição** (documento desaconselha). Inconsistência registro × troca. 2FA não obrigatório. |
| Hash bcrypt/Argon2id | ✅ | Argon2id. |
| Criptografia em repouso/TLS no banco/rede privada/IAM | ❌ | Depende da infra; nada no código (ex.: `sslmode`). |
| Criptografia de campo seletiva | 🟡 | Só segredos 2FA (Fortify) e tokens de confirmação; correto para MVP segundo o documento. |
| Validação tripla (front, back, constraints) | ✅ | |
| Bloqueio de mass assignment de campos de autoridade | ✅ | |
| Deslogado → /login; 401 API; 403 ação conhecida; 404 tenant alheio | ✅ | |
| Logout: logout → invalidate → regenerate CSRF → redirect | ✅ | |
| Convites Agência↔Empresa com aceite/recusa, grants granulares, revogação | ❌ | |
| Pipeline de print/OCR com revisão humana | ❌ | |
| Meta Marketing API | ❌ | |
| App stateless (sessão fora do servidor) | 🟡 | Sessão no banco já compartilhável entre instâncias; Redis recomendado. |
| Docker sem segredos, usuário não privilegiado | ✅ | Roda como `www-data`; `.dockerignore` exclui `.env`, `.git`, `node_modules`, `vendor`, `tests`, logs e `public/build`. |
| LGPD: aviso de privacidade, retenção, acesso, cookies, incidentes | 🟡 | Só checkbox de Termos no cadastro. |
| Backup e restore testado | ❌ | |
| Observabilidade | ❌ | |

### 10.2 Escalabilidade futura (encaixar sem quebrar a arquitetura)

- **Agência controlando contas de Empresas**: `agency_links(agency_account_id, company_account_id,
  status pending|accepted|revoked)`, `access_grants(link_id, permission ∈ leads.read, leads.write,
  campaigns.read, campaigns.write, categories.write)` e `invitations` (token só para consumir convite,
  hash no banco, expiração). Evoluir `ResolveCurrentAccount` para **conta ativa escolhida** (slug
  `/app/{slug}/...` ou valor na sessão) validada contra membership **ou** grant aceito e não revogado;
  Policies checam permissão do grant. Revogação falha a próxima requisição.
- **Equipes**: expandir `memberships.role` (ex.: owner, admin, member, viewer) via migration de enum +
  Policies por papel.
- **Clientes da agência**: `clients(account_id, ...)` + `leads.client_id` nullable com FK composta
  `(account_id, client_id)`.
- **Campanhas**: `campaigns(account_id, client_id?, ...)` + `campaign_metrics` **append-only** por data;
  dashboard calcula receita, mídia, ferramentas, resultado = receita − mídia − ferramentas, margem =
  resultado ÷ receita × 100 (nunca chamar de "lucro líquido").
- **E-mail**: mailer real (SMTP/SES/Postmark), notificações via fila; conteúdo fora de logs.
- **Planos/checkout**: `plans`, `subscriptions`, `checkout_intents` criados no servidor; `/register`
  liberado só para intenção concluída; depois trocar só o mecanismo de confirmação pelo gateway real.
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

1. **Política de senha** inconsistente: registro aceita 8; troca/reset em produção exige 12 +
   composição; documento pede 15 sem MFA (ou 8 com 2FA obrigatório) e **sem** regras de composição.
   UI do registro cita 8 caracteres.
2. **Plano escolhido pelo browser** (`?plan=`) sem ordem/checkout no servidor — hoje inofensivo (sem
   entitlement diferente), mas precisa mudar antes de existir diferença de recursos/cobrança.
3. **Conta Agência = conta Empresa** em funcionalidades; nada específico de agência existe.
4. `ResolveCurrentAccount` exige **exatamente uma** conta owner: convidado/colaborador ou usuário com
   2 contas recebe 404 — evoluir para seleção de conta ativa.
5. `LeadController::show()` implementado mas **sem rota** (código morto ou pendente).
6. Card "Em acompanhamento" fixo em 0; "Prévia de registros" placeholder.
7. Sessão/cache/rate limit em `database` (sem Redis); fila `database` sem worker definido no Docker.
8. Mailer padrão `array`: e-mails de verificação/reset **não são enviados** até configurar `MAIL_MAILER`.
   Verificação exigida em produção sem mailer real impede usuários de verificar (por isso existe o
   opt-out explícito `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION`).
9. Dev em SQLite × produção em PostgreSQL: locks/enum diferem (há testes de compilação de constraints
   PG, sem execução contra PG real).
10. `trustProxies` com lista vazia em produção: atrás do proxy do Render, HTTPS garantido por
    `URL::forceScheme` a partir do `APP_URL`; IP do cliente para rate limit pode ser o do proxy —
    avaliar `proxies` corretos antes de escalar.
11. Sem audit log, backup/restore, observabilidade, política de privacidade/LGPD.

---

## 12. ANEXO — Especificação-alvo (arquitetura e segurança)

> Especificação-alvo viva, nascida do PDF 'Arquitetura e Segurança do CRM SaaS em Laravel' (versão
> condensada). Em dúvida sobre 'como deveria ser', o anexo manda; sobre 'como está', o código manda.
> Mantenha atualizado. Números sobrescritos = referências no final.

# Arquitetura e Segurança do CRM SaaS em Laravel

## Conclusão executiva

- Confirma relatório anterior: Laravel com auth por sessão/cookie, CSRF, Policies, PostgreSQL
  relacional, isolamento dos registros e logout com invalidação da sessão = base correta.
- Evolução principal antes de escrever o CRM: estruturar por conta/workspace (`account_id`), não só
  `user_id`. Permite Empresa com usuários próprios e Agência com acesso controlado à conta da Empresa,
  sem misturar dados entre clientes.
- Navegador: sem JWT por login, sem token de auth no JavaScript. Laravel 13 tem auth nativa por
  sessão/cookie; doc recomenda Sanctum para API, SPA separada ou mobile. Em SPA first-party, Sanctum
  também prioriza cookie de sessão. ¹

Arquitetura recomendada para o MVP:

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

- Laravel 13: Fortify nos Starter Kits (login, registro, recuperação de senha, verificação de e-mail,
  2FA) + rate limiting oficial. ²
- PostgreSQL com RLS: partir de 18.6 ou versão explicitamente corrigida. CVE-2026-14666 afetou versões
  anteriores (cache de planos e políticas RLS); correção publicada em 13 de agosto de 2026. ³

> **Decisão central:** navegador identifica o usuário pela sessão; `account_id` define o
> tenant; Policy define o que ele pode fazer; consulta SQL limita quais registros podem
> aparecer. Um ID ou URL nunca concede permissão.

## Arquitetura do produto e experiência do usuário

Referências visuais da Hostinger: úteis pelo fluxo de decisão, não pela tecnologia — planos separados,
destaque visual de uma opção, CTA objetivo, checkout com resumo antes de criar/continuar a conta.
Página oficial usa seleção de plano + fluxo de compra/conta. ⁴

Fluxo:

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

Sem dezenas de funcionalidades na home. MVP: usuário entende na hora para quem é o plano, o que
resolve, preço e CTA.

### Checkout simulado

Mesmo simulado, frontend não envia isto e servidor não acredita:

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

Gateway real depois: troca-se o mecanismo de confirmação, não a arquitetura inteira.

### Cadastro e login

- Fortify define rotas/lógica de `GET /login`, `POST /login`, `POST /logout`, registro, recuperação
  de senha e verificação de e-mail. ⁵
- `GET /login` só entrega o formulário (não busca dados do usuário). `POST /login` envia credenciais
  por HTTPS; backend verifica senha e, se aceita, inicia/regenera a sessão. Laravel recomenda
  invalidar sessão e regenerar CSRF no logout. ⁶

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

Diferente de mandar todos os dados da conta ao frontend após o login.

## Banco de dados, CRM e isolamento entre contas

Mudança arquitetural mais importante em relação ao primeiro conceito.

### Não use apenas user_id como dono do CRM

```
users
leads.user_id
campaigns.user_id
```

Funciona para sistema individual; atrapalha quando surge:

- Agência com vários colaboradores;
- Empresa com vários funcionários;
- Agência administrando Empresa;
- mudança de proprietário;
- permissões diferentes;
- integração futura com equipes.

Hierarquia:

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

Conta = fronteira de segurança.

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

PostgreSQL: constraints, FKs e RLS; com RLS ativo e nenhuma política permitindo, padrão = negar. RLS
tem exceções (dono da tabela normalmente contorna), então é segunda barreira, não substitui Policies
nem escopo da aplicação. ⁷

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
└─ timestamps
```

Browser não escolhe o proprietário. Backend deriva `account_id` da sessão/membership. Laravel
recomenda Policies para autorizar ver, criar, atualizar e excluir. ⁸

Tentativa de:

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

Conhecer o ID de outro lead não basta. OWASP: IDOR/BOLA; verificar autorização no objeto em cada
requisição; UUID no lugar de ID numérico só dificulta enumeração, não substitui autorização. ⁹

### Dashboard

Sem `user_id` na rota para descobrir qual dashboard carregar. Use:

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

Usuário com várias contas depois:

```
/app/minha-empresa/dashboard
/app/cliente-alfa/dashboard
```

Slug só indica contexto. Nunca prova autorização.

Corrigir a frase “nenhum dado pode aparecer no frontend” (impediria o dashboard). Princípio correto:
data minimization — backend entrega só campos e agregados necessários para a tela, nunca registros ou
propriedades desnecessárias. OWASP trata exposição excessiva e autorização em propriedades como riscos
distintos de API. ¹⁰

### Dashboard inicial

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

Não chamar resultado de “lucro líquido” enquanto impostos, mão de obra e demais custos não estiverem
contemplados; no MVP: resultado da campanha ou margem de contribuição.

Campanhas: registros históricos em `campaign_metrics`, não alterar continuamente uma coluna única.
Preserva valor de segunda, terça, semana seguinte etc.; permite comparar períodos e auditar alterações.

## Autenticação, cookies, formulários e proteção dos dados

Relatório anterior acertou ao separar sessão, CSRF e autorização: três coisas diferentes.

### Cookie e sessão

Credencial persistente do navegador = cookie de sessão protegido, não token exposto ao JavaScript.
OWASP: cookies `Secure`, `HttpOnly` e `SameSite`, HTTPS em toda a sessão, regenerar ID após mudança de
nível de autenticação, não guardar credenciais de sessão em `localStorage`. ¹¹

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

Múltiplos containers: Redis mantém sessão disponível qualquer que seja a instância Laravel; Laravel
fornece driver Redis e rate limiter com Redis. ¹²

### CSRF

CSRF não é “token de login”. Laravel gera token CSRF ligado à sessão e verifica requisições mutáveis;
no Laravel 13 usa também informações de origem do navegador antes da comparação tradicional do token,
quando aplicável. ¹³

```
SESSION COOKIE  = "quem está autenticado"
CSRF TOKEN      = "esta requisição veio do contexto esperado"
POLICY          = "esse usuário pode fazer esta ação?"
```

Os três são necessários.

### Senha

Alterar obrigatoriamente o requisito original de 8 caracteres. NIST SP 800-63B atual: mínimo 15
quando senha é o único fator; 8 permitido quando parte de autenticação multifator. NIST desaconselha
regras arbitrárias (“uma maiúscula + uma minúscula + símbolo”) e recomenda bloquear senhas comuns ou
comprometidas. ¹⁴

MVP adota:

> mínimo de 15 caracteres sem MFA, permitindo frases-senha.

Ou:

> mínimo de 8 somente quando 2FA estiver sendo exigido.

Fortify suporta 2FA TOTP. ⁵ Senha é hasheada, não “criptografada”; Laravel suporta bcrypt e
Argon2id. ¹⁵

```
senha original
↓
Hash::make()
↓
hash irreversível
↓
PostgreSQL
```

Nem você, nem administrador, nem dashboard recuperam a senha original.

### E-mail, telefone e dados comerciais

Não é correto “criptografar todos os campos” sem projeto. Abordagem do MVP:

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

- RDS PostgreSQL pode obrigar SSL/TLS; nas versões modernas `rds.force_ssl` rejeita conexões sem SSL. ¹⁶
- Laravel tem criptografia de campos específicos; valores protegidos contra alteração pela própria
  implementação. ¹⁷
- E-mail de login (unique, pesquisa): criptografia de campo exige desenho adicional; sem criptografia
  caseira. V1: banco/backup criptografados em repouso, rede privada, autorização rigorosa. Field
  encryption só para campos com necessidade e estratégia de pesquisa definidas.

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

Laravel valida tipo, limites e arquivos; regra de imagem não permite SVG por padrão, só se habilitado
explicitamente. ¹⁸

Nunca permitir mass assignment, vindo do browser, de:

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

Alteração de decisão do requisito original.

Usuário deslogado tentando `/dashboard` (após autenticar pode voltar à rota pretendida; suportado pelo
Laravel ¹⁹):

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

Usuário A tentando descobrir recurso privado do tenant B:

```
→ 404
```

404 não revela nem a existência do objeto. Não substitui a verificação real de autorização. ²⁰

### Logout

Procedimento documentado pelo Laravel:

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

Depois disso, sessão antiga não abre `/dashboard`.

## Agência, compartilhamento, campanhas e app futuro

### Permissão Agência → Empresa

Referência conceitual: GitHub (melhor comparação que Instagram). Proprietário envia convite;
convidado aceita antes de virar colaborador; organizações têm níveis granulares Read, Triage, Write,
Maintain e Admin. ²² API de convites distingue aceitar e recusar. ²³

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

Sem bearer token permanente que “dá acesso à empresa”. Token só comprova/consome o convite. Após
aceite, autorização existe como dado relacional no banco:

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

Empresa revoga:

```
status = revoked
```

Próxima requisição da Agência falha.

### Print da campanha do Facebook

Viável e bom para UX, mas IA nunca grava silenciosamente valores financeiros. AWS Textract extrai
texto, formulários, tabelas e elementos estruturados de documentos/imagens, com níveis de confiança. ²⁴

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

- GuardDuty Malware Protection for S3 verifica objetos recém-enviados em ambiente isolado; malware gera
  finding. ²⁵
- Evolução comercial superior: Meta Marketing API/Insights quando o cliente autorizar integração
  (impressões, cliques, gasto, alcance, ações). Print vira conveniência de entrada, não fonte de
  verdade. ²⁶

### PWA e aplicativo futuro

CRM nasce Laravel web, depois vira app. Primeiro lançamento:

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

Web App Manifest = metadados para o navegador tratar a app como PWA instalada; service workers dão
capacidades extras; suporte e instalação variam por plataforma/navegador. ²⁷

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

Capacitor: feito para projetos web modernos; suporta Web, iOS e Android. ²⁸ Cliente mobile separado:
Laravel indica Sanctum (web UI + API + mobile). ²⁹

**Não precisamos reescrever PostgreSQL, campanhas, leads, Policies ou regras de negócio para criar o
app.** Troca-se/adiciona-se o cliente; Laravel continua backend.

## Escala, mercado e plano real de execução

- Nenhuma arquitetura garante “nunca cairá” ou “nunca vazará”; análise responsável não promete isso.
- Grande escala = defesa em profundidade. Vercel: firewall com mitigação de DDoS + WAF; checklist de
  produção (headers, WAF, rate limiting, observabilidade, testes). ³⁰ OpenAI: criptografia em trânsito
  e em repouso, certificações — evidência de camadas, não invulnerabilidade. ³¹ AWS: CloudFront com WAF
  e mitigação DDoS; doc recomenda arquitetura resistente a DDoS; AWS WAF controla tráfego HTTP e
  regras de proteção. ³²
- MVP: não pagar/configurar toda essa infra agora; código nasce preparado para chegar nela.

Progressão (plano do MVP e além):

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

- App stateless fora de Redis/PostgreSQL/S3: novas instâncias sem quebrar sessão. Filas Laravel com
  Redis, SQS e outros; OCR, importação e e-mail não bloqueiam a requisição HTTP. ³³
- Docker reproduz ambiente e empacota; não é sistema de segurança completo. Docker recomenda reduzir
  privilégios/capabilities, processos não privilegiados, sem segredos nas imagens. ³⁴
- RLS, se adotado depois, é cinto de segurança adicional:

```
Policy Laravel
+
query account-scoped
+
PostgreSQL constraints
+
opcionalmente RLS
```

  Não justifica remover nenhuma camada anterior. RLS restringe linhas em SELECT/INSERT/UPDATE/DELETE,
  mas proprietários ou papéis privilegiados podem contorná-la. ⁷

### Critério para liberar o MVP

Primeira versão não é liberada enquanto estes testes não passarem:

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

OWASP: cada endpoint que recebe identificador de objeto verifica autorização de nível de objeto. ³⁵

LGPD: MVP nasce com aviso de privacidade, finalidade e retenção de dados, mecanismos de acesso,
política de cookies aplicável e procedimento para incidentes. ANPD tem guia de segurança e checklist;
incidentes com potencial de risco ou dano relevante: avaliação e comunicação conforme regras da
autoridade, prazo de três dias úteis nos casos comunicáveis. ³⁶

Base aprovada:

> **Laravel 13 + Inertia/React + PostgreSQL + Redis, tenancy por `account_id`, Fortify/
> session cookies para web, Sanctum preparado para API/mobile, Policies em todos os
> recursos, nenhuma autoridade vinda de user_id/account_id do frontend,
> métricas históricas, convites granulares Agência↔Empresa, uploads privados e jobs
> assíncronos.**

Resolve o MVP sem infra desnecessariamente complexa e evita decisões que obrigariam reescrever
autenticação, banco e permissões quando o CRM crescer.

## Relatório em PDF

Relatório técnico consolidado em cinco páginas: decisões arquiteturais, modelo de banco, autenticação,
segurança, fluxo Agência↔Empresa, campanhas/OCR, PWA, infraestrutura, ordem de desenvolvimento e
critérios de aceite. Título: “Arquitetura Segura do CRM Laravel MVP”.

### Referências

1, 6, 19, 21, 29. Authentication | Laravel 13.x — https://laravel.com/framework/docs/13.x/authentication
2, 5. Starter Kits | Laravel 13.x — https://laravel.com/framework/docs/starter-kits
3. PostgreSQL: CVE-2026-14666: PostgreSQL row security caching disregards role modifications — https://www.postgresql.org/support/security/CVE-2026-14666/
4. Hospedagem de sites a partir de R$ 5,99/mês — https://www.hostinger.com/br/hospedagem-de-sites?no_head=1
7. PostgreSQL: Documentation: 18: 5.9. Row Security Policies — https://www.postgresql.org/docs/18/ddl-rowsecurity.html
8. Authorization | Laravel 13.x — https://laravel.com/framework/docs/13.x/authorization
9, 20. Insecure Direct Object Reference Prevention - OWASP Cheat Sheet Series — https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html
10. API3:2023 Broken Object Property Level Authorization - OWASP API Security Top 10 — https://api-security.owasp.org/editions/2023/en/0xa3-broken-object-property-level-authorization/
11. Session Management - OWASP Cheat Sheet Series — https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html
12. Illuminate\Session\SessionManager | Laravel API — https://api.laravel.com/docs/13.x/Illuminate/Session/SessionManager.html
13. CSRF Protection | Laravel 13.x — https://laravel.com/framework/docs/13.x/csrf
14. NIST Special Publication 800-63B — https://pages.nist.gov/800-63-4/sp800-63b.html?locale=en
15. Hashing | Laravel 13.x — https://laravel.com/framework/docs/hashing
16. Using SSL with a PostgreSQL DB instance - Amazon Relational Database Service — https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/PostgreSQL.Concepts.General.SSL.html
17. Encryption | Laravel 12.x — https://laravel.com/framework/docs/12.x/encryption
18. Illuminate\Validation\Rules\ImageFile | Laravel API — https://api.laravel.com/docs/13.x/Illuminate/Validation/Rules/ImageFile.html
22. Inviting collaborators to a personal repository - GitHub Docs — https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/repository-access-and-collaboration/inviting-collaborators-to-a-personal-repository
23. REST API endpoints for repository invitations - GitHub Docs — https://docs.github.com/en/rest/collaborators/invitations
24. AnalyzeDocument - Amazon Textract — https://docs.aws.amazon.com/pt_br/textract/latest/APIReference/API_AnalyzeDocument.html
25. How does Malware Protection for S3 work? - Amazon GuardDuty — https://docs.aws.amazon.com/guardduty/latest/ug/how-malware-protection-for-s3-gdu-works.html
26. Get Ad Insights [L1] | Facebook Marketing API | Postman API Network — https://www.postman.com/meta/facebook-marketing-api/request/u07tack/get-ad-insights-l1
27. Installation | web.dev — https://web.dev/learn/pwa/installation?hl=en
28. Environment Setup | Capacitor Documentation — https://capacitorjs.com/docs/getting-started/environment-setup
30. Vercel Firewall — https://vercel.com/docs/vercel-firewall
31. Segurança e privacidade na OpenAI | OpenAI — https://openai.com/pt-BR/security-and-privacy/
32. Configure secure access and restrict access to content - Amazon CloudFront — https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/SecurityAndPrivateContent.html
33. Queues | Laravel 13.x — https://laravel.com/framework/docs/13.x/queues
34. Docker Engine security | Docker Docs — https://docs.docker.com/engine/security/
35. API1:2023 Broken Object Level Authorization - OWASP API Security Top 10 — https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization/
36. Guia orientativo sobre segurança da informação para agentes de tratamento de pequeno porte — https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-sobre-seguranca-da-informacao-para-agentes-de-tratamento-de-pequeno-porte

---

## 13. Changelog deste arquivo

| Data | Mudança |
|---|---|
| 2026-10-27 | Criação do CLAUDE.md: instruções para o Claude, mapeamento completo do MVP v1 (commits até `aa06919`), segurança implementada, gap analysis contra o documento de arquitetura e anexo integral do PDF. Nenhum código alterado. |
| 2026-10-27 | `/CLAUDE.md` removido do `.gitignore`: este arquivo passa a ser versionado com o projeto. |
| 2026-10-27 | CLAUDE.md comprimido; anexo (seção 12) passa a ser especificação viva e atualizável (regra 0.4 alterada). Nenhum código alterado. |
