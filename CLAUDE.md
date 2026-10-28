# CLAUDE.md — Cérebro do projeto HING CRM SaaS

> **Arquivo comprimido e vivo: é o cérebro único do projeto. Ao atualizar (protocolo 0.4), mantenha o
> estilo curto, não perca contexto, preserve todas as seções e não crie outros arquivos de contexto.**
> Fonte única de contexto: ler inteiro antes de agir.

---

## 0. INSTRUÇÕES OBRIGATÓRIAS PARA O CLAUDE (ler antes de qualquer coisa)

### 0.1 Ordem de leitura e entendimento

1. Ler **este arquivo inteiro** antes de planejar, editar ou criar qualquer arquivo.
2. Seguir também:
   @AGENTS.md
   (Laravel Boost: Pint, Pest, Wayfinder, Inertia v3, `search-docs`, skills de domínio). Regras de lá continuam valendo.
3. Camadas:
    - **Seções 1–9**: como o sistema **está hoje** (verificado no código em 2026-10-27).
    - **Seção 10**: o que o documento de arquitetura exige e **o que falta** (gap analysis).
    - **Seção 12 (Anexo)**: **especificação-alvo viva** (nascida do PDF "Arquitetura e Segurança do
      CRM SaaS em Laravel"). Dúvida sobre "como deveria ser": **o anexo manda**. Sobre "como está":
      **o código manda**. Divergência entre os dois: corrigir este CLAUDE.md.
    - **Seção 14**: pendências, decisões e próximos passos (retomar daqui).
    - **Seção 0.6**: retomada, documentação e encerramento.
4. Abrir código só quando precisar do detalhe exato. Mapa na seção 3.
5. Ao iniciar a sessão, ler 14.1 e 14.3 antes de agir (ver 14.6).

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
- **Mass assignment**: `#[Fillable]` restrito e `#[Hidden]` para `account_id`, `system_key`,
  segredos. Não ampliar `Fillable` com campos de autoridade.
- **Integridade no banco**: FKs compostas `(account_id, category_id)` impedem relação entre tenants
  mesmo com bug na aplicação. Novas tabelas filhas repetem o padrão.
- **Escritas críticas em transação** com `lockForUpdate()` e `attempts: 3`; `saveOrFail()`/`deleteOrFail()`
  com `abort_unless(..., 409)`.
- **Data minimization**: backend envia só campos/agregados necessários
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
5. Changelog (seção 13): uma linha com data absoluta (AAAA-MM-DD), resumo e hash do commit base
   (`git rev-parse --short HEAD` no momento da edição, formato `base abc1234`).
6. Nunca criar arquivo separado para isso.
7. Ao terminar a tarefa, informar no chat, em 2–3 linhas, quais seções do CLAUDE.md foram atualizadas.
8. **Um fato, um lugar.** Antes de escrever algo novo, procurar se o fato já existe. Se existir,
   editar ali. Em outros pontos, só referência curta ("ver 7", "ver 11.1").
9. **Changelog completo.** O changelog não é rotacionado nem resumido automaticamente. O histórico é
   do usuário. Só condensar com pedido explícito dele.
10. **O CLAUDE.md não tem limite de tamanho que justifique cortar.** O tamanho é só informativo.
    PROIBIDO apagar, resumir, condensar ou deduplicar conteúdo para reduzir o arquivo, salvo pedido
    explícito do usuário. O arquivo pode crescer o quanto o projeto exigir. Ao fim da tarefa, se ele
    cresceu muito (mais de 15% desde a última medição registrada em 14.4), apenas informar o `wc -w`
    no chat. Quem decide qualquer redução é o usuário.
11. **O contexto nunca é sacrificado.** Proibido apagar fato, regra, decisão, status de matriz,
    ferramenta (atual, parcial ou futura), rota, arquivo, coluna, comando ou critério do MVP por
    qualquer motivo, inclusive tamanho. Corrigir informação errada é permitido (registrar no
    changelog); apagar informação verdadeira, não. A regra "um fato, um lugar" (item 8) vale para
    NOVAS informações; não autoriza remover repetições já existentes.
12. Manter o aviso do topo e o estilo telegráfico em português do Brasil.
13. **Seção 14 (memória entre sessões)**: atualizar ao fim de TODA tarefa e antes de encerrar a
    sessão (pendências, decisões, próximos passos), seguindo as regras em 14.6.

### 0.5 Comandos do dia a dia

| Ação                                              | Comando                                                                 |
| ------------------------------------------------- | ----------------------------------------------------------------------- |
| Ambiente dev completo                             | `composer run dev`                                                      |
| Testes (narrow)                                   | `php artisan test --compact --filter=NomeDoTeste` ou caminho do arquivo |
| Suíte completa (pedir ao usuário)                 | `php artisan test --compact`                                            |
| Formatação PHP (obrigatório após editar PHP)      | `vendor/bin/pint --dirty --format agent`                                |
| Análise estática                                  | `composer types:check` (Larastan)                                       |
| Front: lint/format/types                          | `npm run check` · `npm run types:check`                                 |
| Build front                                       | `npm run build`                                                         |
| CI local                                          | `composer ci:check`                                                     |
| Rotas                                             | `php artisan route:list --except-vendor`                                |
| Preparar categoria "Finalizado" em contas legadas | `php artisan accounts:ensure-sale-category`                             |

### 0.6 Retomada, documentação e encerramento

**Início de sessão (antes de qualquer tarefa):**

1. `git status` e `git log -10 --oneline`. Se houver commits depois do último hash `base` do changelog
   (13) que alterem arquivos além do CLAUDE.md, o arquivo está atrasado: reconciliar com o código e
   atualizar ANTES de agir.
2. Ler 14.1 e 14.3. Se 14.1 tiver tarefa em andamento, perguntar se é para retomar.
3. Se `composer.lock` ou `package-lock.json` mudaram desde a data da seção 2, reverificar versões e
   atualizar a seção 2.

**Documentação das ferramentas (obrigatório):**

1. Antes de usar ou alterar qualquer ferramenta, consultar a documentação da VERSÃO INSTALADA
   (seção 2). Ecossistema Laravel: `search-docs` (Boost). Demais: documentação oficial na web (coluna
   "Doc" da seção 2). Nunca confiar na memória para API, sintaxe ou configuração.
2. Se a documentação contradisser este arquivo: não decidir sozinho; avisar o usuário, resolver com
   ele e registrar em 14.4.
3. Ferramenta nova ou fora da seção 2: perguntar antes (0.3) e, se aprovada, registrar na seção 2 com
   versão e fonte da doc.

**Tarefa concluída = todos verdadeiros:** testes relevantes passando · `vendor/bin/pint --dirty --format agent` ·
`composer types:check` (se PHP) · `npm run check` e `npm run types:check` (se front) · seções afetadas,
matrizes 10, seção 11, anexo (se a arquitetura-alvo mudou), 13 (com hash base) e 14 atualizadas ·
`npx vp fmt CLAUDE.md` após editar este arquivo (`vp check` no CI reprova Markdown não formatado) ·
resumo de 2–3 linhas no chat.

**Antes de encerrar a sessão:** confirmar que 14.1 e 14.2 refletem o estado real e que toda decisão
tomada na sessão está em 14.4 com o porquê.

---

## 1. Visão geral do produto (estado atual — MVP v1)

**HING CRM**: CRM SaaS multi-tenant. Contas **Empresa** (opera a própria operação comercial) e
**Agência** (futuro: gerencia múltiplos clientes com permissões concedidas pela Empresa).

MVP hoje:

- **Home pública** (`/`): proposta + cards dos planos Empresa e Agência (`resources/js/lib/home-plans.ts`).
- **Cadastro** com plano (`/register?plan=empresa|agencia`), aceite de Termos, criação atômica de
  **User + Account + Membership(owner) + categoria permanente "Finalizado"** (ver 6.1).
- **Login / logout / recuperação de senha / verificação de e-mail / 2FA TOTP** via Fortify (ver 6.2).
- **Categorias de leads** (CRUD): nome + cor HEX única por conta, contagem e %, "Finalizado"
  protegida, exclusão com confirmação de impacto que **apaga em cascata os leads** (ver 6.3).
- **Leads** (CRUD): nome, descrição, categoria (etapa), primeiro/próximo contato, valor potencial,
  valor cobrado; **"Finalizar venda"** e reabertura com confirmação; exclusão; filtro; paginação (ver 6.4).
- **Dashboard** com dados reais: total, novos por período (semana / 2 semanas / mês), vendidos e %,
  barras da **semana atual (Seg–Dom)**, donut **por categoria** (ver 6.6).
- **Configurações**: perfil (nome/e-mail), exclusão do usuário, segurança (senha, 2FA), aparência.

**Não existe ainda**: campanhas, métricas de campanha, investimentos/CPL/receita/margem, clientes de
agência, vínculo Agência↔Empresa, convites, grants, checkout simulado, uploads/OCR, audit log, Redis,
S3, Sanctum, PWA. Ver 10.

---

## 2. Stack real instalada (verificado em 2026-10-27)

| Camada               | Versão instalada                                                                                                                              | Documento recomenda                 | Status                 | Doc                                                                                                                                                                                                                                                                                                            |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------- | ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP                  | 8.3 (`composer: ^8.3`, CLI 8.3.36, Docker `serversideup/php:8.3-fpm-nginx`)                                                                   | PHP 8.4/8.5                         | 🟡 divergente          | https://www.php.net/manual/en/                                                                                                                                                                                                                                                                                 |
| Laravel              | `laravel/framework` v13.36.0                                                                                                                  | Laravel 13                          | ✅                     | https://laravel.com/docs/13.x (`search-docs`)                                                                                                                                                                                                                                                                  |
| Auth web             | `laravel/fortify` v1.41.0, guard `session`                                                                                                    | Session + Fortify                   | ✅                     | https://laravel.com/docs/13.x/fortify (`search-docs`)                                                                                                                                                                                                                                                          |
| Frontend             | Inertia v3 (`inertiajs/inertia-laravel` v3.5.1, `@inertiajs/react` ^3), React 19, TypeScript 5, Tailwind 4, Radix UI, Recharts 3.10.1, Sonner | Inertia + React + TS                | ✅                     | Inertia https://inertiajs.com/docs/v3 (`search-docs`) · React https://react.dev · TS https://www.typescriptlang.org/docs/ · Tailwind https://tailwindcss.com/docs (`search-docs`) · Radix https://www.radix-ui.com/primitives/docs · Recharts https://recharts.github.io · Sonner https://sonner.emilkowal.ski |
| Mapa                 | `leaflet` ^1.9.4 + `react-leaflet` ^5.0.0 (`@types/leaflet` dev), tiles OpenStreetMap, chunk lazy                                             | —                                   | ✅                     | Leaflet https://leafletjs.com/reference.html · react-leaflet https://react-leaflet.js.org · tiles https://operations.osmfoundation.org/policies/tiles/                                                                                                                                                         |
| Rotas tipadas        | `laravel/wayfinder` v0.1.21 (`@/actions`, `@/routes`)                                                                                         | —                                   | ✅                     | https://github.com/laravel/wayfinder (`search-docs`)                                                                                                                                                                                                                                                           |
| Build                | Vite 8 via `vite-plus` (`vp build/dev/check`), React Compiler (babel)                                                                         | —                                   | ✅                     | Vite https://vite.dev/guide/ · `vite-plus`: a confirmar · React Compiler https://react.dev/learn/react-compiler                                                                                                                                                                                                |
| Banco                | **SQLite** local (`DB_CONNECTION=sqlite`); **PostgreSQL** em produção (Docker instala `pdo_pgsql`)                                            | PostgreSQL 18.6+                    | 🟡 local ≠ prod        | https://www.sqlite.org/docs.html · https://www.postgresql.org/docs/current/ (versão de produção a confirmar)                                                                                                                                                                                                   |
| Sessão               | driver `database`                                                                                                                             | Redis                               | 🟡                     | https://laravel.com/docs/13.x/session                                                                                                                                                                                                                                                                          |
| Cache / rate limiter | `database`                                                                                                                                    | Redis                               | 🟡                     | https://laravel.com/docs/13.x/cache · https://laravel.com/docs/13.x/rate-limiting                                                                                                                                                                                                                              |
| Fila                 | `database`                                                                                                                                    | Redis/Horizon                       | 🟡                     | https://laravel.com/docs/13.x/queues                                                                                                                                                                                                                                                                           |
| Arquivos             | disco `local`                                                                                                                                 | S3 privado                          | ❌ (sem uploads ainda) | https://laravel.com/docs/13.x/filesystem                                                                                                                                                                                                                                                                       |
| API/mobile           | —                                                                                                                                             | Sanctum                             | ❌                     | —                                                                                                                                                                                                                                                                                                              |
| E-mail               | mailer padrão `array` (`MAIL_MAILER=log` é convertido para `array`: conteúdo nunca vai a log)                                                 | —                                   | 🟡 sem envio real      | https://laravel.com/docs/13.x/mail                                                                                                                                                                                                                                                                             |
| Testes               | Pest v4.7.8 + pest-plugin-laravel, SQLite `:memory:`; testes JS em `tests/Unit/*.test.mjs`                                                    | —                                   | ✅                     | https://pestphp.com/docs (`search-docs`)                                                                                                                                                                                                                                                                       |
| Qualidade            | Pint, Larastan v3.13, `vp check` (lint type-aware)                                                                                            | —                                   | ✅                     | Pint https://laravel.com/docs/13.x/pint · Larastan https://github.com/larastan/larastan · `vp check`: a confirmar                                                                                                                                                                                              |
| Dev tooling          | Laravel Boost (MCP), Pail, Sail (não usado), Chisel                                                                                           | —                                   | —                      | Boost https://laravel.com/docs/13.x/boost · Pail https://laravel.com/docs/13.x/logging#tailing-log-messages-using-pail · Sail https://laravel.com/docs/13.x/sail · Chisel: a confirmar                                                                                                                         |
| Deploy               | `Dockerfile` (serversideup php-fpm-nginx, Node 22 para build, `AUTORUN_ENABLED` roda migrations) — alvo Render                                | AWS (CloudFront/WAF/ALB/RDS) futuro | 🟡                     | https://serversideup.net/open-source/docker-php/docs · https://render.com/docs/docker                                                                                                                                                                                                                          |
| Locale               | `pt_BR` (app, fallback, faker); `app.timezone=UTC`; `REPORTING_TIMEZONE=America/Porto_Velho`                                                  | —                                   | ✅                     | —                                                                                                                                                                                                                                                                                                              |

---

## 3. Mapa do código

### 3.1 Backend (`app/`)

| Arquivo                                                                                    | Responsabilidade                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| ------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Actions/Fortify/CreateNewUser.php`                                                        | Registro (fluxo em 6.1): valida perfil, senha (`min 8 + uncompromised`, max 128), `terms`, `plan ∈ Account::TYPES`; cria User+Account+Membership(owner) em transação; trata corrida de e-mail único.                                                                                                                                                                                                                                                                              |
| `Actions/Fortify/ResetUserPassword.php`                                                    | Redefinição de senha (`passwordRules()`), `Hash::make`.                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `Actions/ParseGoogleMapsLink.php`                                                          | Extrai lat/lng de link do Google Maps (`!3dlat!4dlng`, `@lat,lng`, `/maps/search                                                                                                                                                                                                                                                                                                                                                                                                  | place/lat,+lng`(pin),`?q=`/`ll=`); sem coordenadas (links do app: `/maps/place/<nome e endereço>/data=...`) geocodifica o endereço no Nominatim/OSM (até 3 tentativas: texto inteiro, a partir do 1º número, após a 1ª vírgula; `Sleep`1s entre elas; User-Agent`APP_NAME (APP_URL)`); `allows()` = allowlist de hosts (`HOSTS`); link curto (`goo.gl`, `maps.app.goo.gl`, só https) resolvido no servidor com `Http::withoutRedirecting()`(connect 3s, timeout 5s), lendo`Location`, máx. 3 saltos, cada salto revalidado na allowlist (anti-SSRF). |
| `Actions/SaveLead.php`                                                                     | **Único ponto de escrita de lead** (create/update/complete); regras em 6.4, incl. valor cobrado > 0 em "Finalizado".                                                                                                                                                                                                                                                                                                                                                              |
| `Actions/EnsureSaleCompletedCategory.php`                                                  | Cria idempotente a categoria de sistema `sale_completed` ("Finalizado", `#00ff0a`) com lock na conta. Falha se o verde já estiver em uso por categoria comum.                                                                                                                                                                                                                                                                                                                     |
| `Actions/FlashCrudSuccess.php`                                                             | Toasts de sucesso via `Inertia::flash('toast', ...)` em pt-BR.                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `Concerns/PasswordValidationRules.php`                                                     | Registro `Password::min(8)->uncompromised()->max(128)`; demais `Password::default()->max(128)` + `confirmed` (política em 7).                                                                                                                                                                                                                                                                                                                                                     |
| `Concerns/ProfileValidationRules.php`                                                      | `name` (≤255) e `email` (email, ≤255, unique).                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `Console/Commands/EnsureSaleCompletedCategories.php`                                       | `accounts:ensure-sale-category` — provisiona "Finalizado" em contas legadas.                                                                                                                                                                                                                                                                                                                                                                                                      |
| `Http/CurrentAccount.php`                                                                  | Lê conta resolvida dos atributos do request; `abort 404` se ausente.                                                                                                                                                                                                                                                                                                                                                                                                              |
| `Http/Middleware/ResolveCurrentAccount.php`                                                | Exige usuário autenticado (401) e **exatamente 1 conta com role owner** (senão 404); injeta e limpa contexto por request.                                                                                                                                                                                                                                                                                                                                                         |
| `Http/Middleware/EnsureEmailVerification.php`                                              | Alias `verified`; exige verificação só se `auth.email_verification_required` (regra em 6.2).                                                                                                                                                                                                                                                                                                                                                                                      |
| `Http/Middleware/EnsureSafeAuthenticationRedirect.php`                                     | Sanitiza `url.intended` (bloqueia open redirect: host/esquema/porta externos, `//`, `\`, caracteres de controle, credenciais na URL).                                                                                                                                                                                                                                                                                                                                             |
| `Http/Middleware/ValidateAuthenticationInput.php`                                          | Valida e normaliza (lowercase) e-mail/token/senha antes do Fortify em register, reset, confirm.                                                                                                                                                                                                                                                                                                                                                                                   |
| `Http/Middleware/HandleInertiaRequests.php`                                                | Share só `auth.user` com `id,name,email,email_verified_at,created_at,updated_at`; `Inertia::encryptHistory()` e `Cache-Control: no-store, private` quando autenticado.                                                                                                                                                                                                                                                                                                            |
| `Http/Middleware/HandleAppearance.php`                                                     | Cookie `appearance` (light/dark/system).                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `Http/Controllers/DashboardController.php`                                                 | Métricas agregadas da conta (ver 6.6).                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `Http/Controllers/LeadController.php`                                                      | index (paginado, filtro), `categoryOptions` (cursor), store, update, complete, `location` (só `maps_url`, via `SaveLead` com a categoria atual), destroy. `show()` existe **sem rota** (ver 11.5).                                                                                                                                                                                                                                                                                |
| `Http/Controllers/LeadCategoryController.php`                                              | index (paginado + programadas + cor sugerida), store, update, destroy com confirmação criptografada (ver 6.3).                                                                                                                                                                                                                                                                                                                                                                    |
| `Http/Controllers/Settings/ProfileController.php`                                          | Editar perfil, excluir usuário (ver 6.5).                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `Http/Controllers/Settings/SecurityController.php`                                         | Página de segurança (exige confirmação de senha), troca de senha (rotaciona `remember_token`, regenera sessão).                                                                                                                                                                                                                                                                                                                                                                   |
| `Http/Requests/StoreLeadRequest.php` / `MutateLeadRequest.php` / `CompleteLeadRequest.php` | Validação/autorização de leads; `MutateLeadRequest::lead()` resolve o lead **pela relação da conta**. `StoreLeadRequest::after()` chama `ParseGoogleMapsLink` e expõe `mapsLocation()` (lat/lng derivados no servidor); na edição reaproveita coordenadas se `maps_url` não mudou (`currentLead()`). Regras de `maps_url` em `mapsUrlRules()` (reusadas por `UpdateLeadLocationRequest`, que estende `MutateLeadRequest` e aceita só `maps_url` obrigatório + `page`/`category`). |
| `Http/Requests/StoreLeadCategoryRequest.php` / `MutateLeadCategoryRequest.php`             | Validação/autorização de categorias; proíbe DELETE da categoria de sistema (403).                                                                                                                                                                                                                                                                                                                                                                                                 |
| `Http/Requests/Auth/LoginRequest.php`                                                      | Login: email ≤255, senha ≤128, `remember` boolean.                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `Http/Requests/Settings/*`                                                                 | Perfil (troca de e-mail exige `current_password`), senha, exclusão, 2FA.                                                                                                                                                                                                                                                                                                                                                                                                          |
| `Http/Responses/LogoutResponse.php`                                                        | `Inertia::clearHistory()` + redirect `/login` (204 em JSON).                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `Http/Responses/PasswordResetLinkResponse.php`                                             | Resposta **idêntica** para e-mail existente ou não (anti-enumeração).                                                                                                                                                                                                                                                                                                                                                                                                             |
| `Models/User.php`                                                                          | `MustVerifyEmail`, `TwoFactorAuthenticatable`; e-mail sempre lowercase; cast `password => hashed`; `memberships()`, `accounts()`, `ownedAccounts()`.                                                                                                                                                                                                                                                                                                                              |
| `Models/Account.php`                                                                       | `TYPES = ['empresa','agencia']`; `save()` de conta nova provisiona "Finalizado" na mesma transação.                                                                                                                                                                                                                                                                                                                                                                               |
| `Models/Membership.php`                                                                    | user ↔ account com `role` (hoje só `owner`).                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `Models/LeadCategory.php`                                                                  | `SALE_COMPLETED`, `SALE_COMPLETED_COLOR`; `system_key` imutável; deleção da categoria de sistema vetada no evento `deleting`; `is_sale_completed` appended.                                                                                                                                                                                                                                                                                                                       |
| `Models/Lead.php`                                                                          | Casts de data civil e `decimal:2`; `#[Hidden(account_id)]`.                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `Policies/AccountPolicy.php`                                                               | `view`/`update`: usuário precisa ser owner da conta, senão `denyAsNotFound`.                                                                                                                                                                                                                                                                                                                                                                                                      |
| `Policies/LeadPolicy.php`                                                                  | `viewAny/view/create/update/delete` sempre exigem `lead.account_id === account.id` + AccountPolicy.                                                                                                                                                                                                                                                                                                                                                                               |
| `Providers/AppServiceProvider.php`                                                         | `CarbonImmutable`, `DB::prohibitDestructiveCommands` em produção, força origem/esquema do `APP_URL` em produção, `Password::defaults` de produção.                                                                                                                                                                                                                                                                                                                                |
| `Providers/FortifyServiceProvider.php`                                                     | Views Inertia do Fortify, bindings de responses, middlewares extras nas rotas do Fortify e **rate limiters** (valores em 7).                                                                                                                                                                                                                                                                                                                                                      |
| `bootstrap/app.php`                                                                        | `trustProxies`, `trustHosts`, cookies criptografados, `AuthenticateSession`, `dontFlash(token, code, recovery_code)` (detalhes em 7).                                                                                                                                                                                                                                                                                                                                             |

### 3.2 Config relevantes (`config/`)

`auth.php` (regra de verificação de e-mail), `fortify.php` (features), `session.php`, `hashing.php`
(argon2id), `reporting.php` (fuso dos relatórios), `trustedproxy.php` (túnel local opt-in), `mail.php`
(log→array).

### 3.3 Frontend (`resources/js/`)

| Caminho                                                                                                    | Conteúdo                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| ---------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app.tsx`                                                                                                  | `createInertiaApp`; sem layout: welcome, auth/register, auth/login, dashboard, categories/index, leads/index; `auth/*` → AuthLayout; `settings/*` → AppLayout+SettingsLayout.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `pages/welcome.tsx`                                                                                        | Home + planos (`components/home-plan-card.tsx`, `lib/home-plans.ts`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `pages/auth/*`                                                                                             | login, register (plano + termos), forgot/reset password, verify-email, two-factor-challenge, confirm-password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `pages/dashboard.tsx` + `components/dashboard/*`                                                           | `dashboard-metrics` (4 cards; "Em acompanhamento" = "em breve"; "Leads vendidos" destaca `soldRevenue` via `formatLeadMoney`, texto secundário "N% do total de leads · N lead(s) vendido(s)", valor em `var(--dashboard-green)` (mesmo verde do ícone check), fonte `clamp(1.25rem,11.5cqw,2.1rem)` com `@container` para caber em 320px), `dashboard-charts` (barras semanais + donut por categoria, Recharts), `dashboard-records` (placeholder "Em breve"), `dashboard-sidebar` (menu Dashboard / Categorias / Leads), `dashboard-data.ts` (tipos e formatadores).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `pages/leads/index.tsx` + `components/leads/*`                                                             | `create-lead-dialog` (criar/editar), `lead-card`, `lead-category-filter`, `lead-category-selector` (cursor paginado), `complete-lead-dialog` (sem valor cobrado salvo > 0: input "Valor cobrado" obrigatório no modal, "Confirmar venda" desabilitado até valor > 0), `sale-transition-dialog` (confirmar finalização/reabertura; aceita `children` e `confirmDisabled`), `lead-money-field` (input BRL compartilhado: form de lead e modal de finalização), `delete-lead-dialog`, `lead-map-dialog` (botão `MapPin` em **todo** card; com lat/lng: modal com "Abrir no Google Maps" + `lead-map` via `lazy`; sem lat/lng: estado de cadastro no centro com `LeadMapsUrlField`, envia ao colar (botão ou Ctrl+V), Enter ou blur para `leads.location` com `only: ['leads']`, `LoaderCircle` + campo desabilitado durante envio, erro do servidor no campo, mapa carrega no mesmo modal), `lead-maps-url-field` (input do link + botão colar + erro; compartilhado entre form de lead e modal do mapa), `lead-map` (react-leaflet: OSM, marcador, `invalidateSize()` ao fim da animação, `isolate`). Form tem "Link do Google Maps" (`LeadMapsUrlField`) com botão `ClipboardPaste` (`navigator.clipboard.readText()`, falha silenciosa). |
| `pages/categories/index.tsx` + `components/categories/*`                                                   | `category-card`, `category-dialog` (criar/editar, color picker `react-colorful`), `delete-category-dialog` (mostra impacto, envia `confirmation`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `pages/settings/*`                                                                                         | profile, security (senha + 2FA), appearance.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| `lib/lead-format.ts`                                                                                       | Datas civis, prazo do próximo contato ("Hoje", "Atrasado há N dias"), parse/format BRL sem float, `categoryColor()` (sanitiza cor antes do CSS), `mapsLinkFrom()` (texto colado → 1º link `http(s)://`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `lib/lead-transition.ts`                                                                                   | Decide se mudança de categoria é `complete`/`reopen` **pelo `is_sale_completed` do servidor**, nunca por nome/cor.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `hooks/use-flash-toast.ts`, `use-lead-category-options.ts`, `use-two-factor-auth.ts`, `use-appearance.tsx` | Hooks.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `actions/`, `routes/`, `wayfinder/`                                                                        | **Gerados pelo Wayfinder** — não editar à mão.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `components/ui/*`                                                                                          | shadcn/Radix — não lintado/formatado.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |

### 3.4 Testes (`tests/`)

Padrão: ~336 testes Pest em `Feature` com `RefreshDatabase`; factories com estados (`Account::factory()->agency()`,
`User::factory()->withTwoFactor()`, `->unverified()`); JS em `.test.mjs` (`lead-format`, `lead-transition`).
Temas/arquivos: tenancy (`AccountAuthorizationTest`, `CurrentAccountTest`, `TenantFoundationTest`); leads
(`LeadFoundationTest`, `LeadMutationTest`, `LeadIntegrityTest`, `LeadPageTest`, `LeadMapsLocationTest` (com `Http::fake`/`preventStrayRequests`; inclui `leads.location`: grava link, 422 em link inválido/ausente/coords do browser, 404 em lead de outra conta)); categorias
(`LeadCategory*Test`, `CategoryDeletion*Test`, `SystemLeadCategoryTest`); venda (`SaleCompletion*Test`,
`SaleMetricsTest`); dashboard (`Dashboard*Test`, incl. `DashboardTest`, `DashboardMetricsTest`); sessão
(`LogoutSecurityTest`, `SessionReplayTest`); registro (`RegistrationSecurityTest`, `RegistrationCsrfTest`);
`SecurityConfigurationTest`; `Auth/*` (Login CSRF/Security, `AuthenticationTest`, `LoginSecurityTest`,
Origin, Recovery, SessionRevocation, TrustedProxy, 2FA…); `Settings/*` (IdentitySecurity, OwnerDeletion…).

---

## 4. Banco de dados (schema atual)

```
users ─┬─< memberships >─┬─ accounts ─┬─< lead_categories ─┬─< leads
       │   (role=owner)  │            │  (account_id,id)   │  FK composta
sessions (database)                    └──────────────< leads (account_id)
password_reset_tokens · cache · cache_locks · jobs · job_batches · failed_jobs
```

| Tabela            | Colunas                                                                                                                                                                                                                                                                                                   | Restrições                                                                                                                                           |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users`           | id, name, email (unique), email_verified_at, password (hash), two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, remember_token, timestamps                                                                                                                                           | segredos 2FA criptografados pelo Fortify; `#[Hidden]`                                                                                                |
| `accounts`        | id, `type enum('empresa','agencia')`, name nullable, timestamps                                                                                                                                                                                                                                           | —                                                                                                                                                    |
| `memberships`     | id, user_id FK cascade, account_id FK cascade, `role enum('owner')`, timestamps                                                                                                                                                                                                                           | unique(user_id, account_id)                                                                                                                          |
| `lead_categories` | id, account_id FK cascade, name(120), color(7), system_key(32) nullable, timestamps                                                                                                                                                                                                                       | unique(account_id, color); unique(account_id, id); unique(account_id, system_key); index(account_id, id)                                             |
| `leads`           | id, account_id FK cascade, category_id, name(120), description text null, first_contact_date date, next_contact_date date null, potential_amount decimal(12,2) null, charged_amount decimal(12,2) null, maps_url string(2048) null, latitude decimal(10,7) null, longitude decimal(10,7) null, timestamps | **FK composta (account_id, category_id) → lead_categories(account_id, id) ON DELETE CASCADE**; index(account_id, id); index(category_id, account_id) |
| `sessions`        | id, user_id, ip_address, user_agent, payload, last_activity                                                                                                                                                                                                                                               | driver `database`                                                                                                                                    |

Cascatas: excluir **categoria** apaga seus leads; **conta** apaga categorias, leads e memberships;
**usuário** apaga memberships (UI bloqueia se último owner).

---

## 5. Rotas

Públicas: `GET /` (home), `GET /up` (health), Fortify: `GET|POST /login`, `POST /logout`,
`GET|POST /register` (GET exige `?plan=empresa|agencia`, senão redireciona para `/#planos`),
`/forgot-password`, `/reset-password/{token}`, `/email/verify*`, `/two-factor-challenge`,
`/user/confirm-password`, `/user/two-factor-*`.

Autenticadas — middleware `auth` + `verified` + `ResolveCurrentAccount` (`routes/web.php`):

| Método | URI                      | Nome                   | Ação                                                                         |
| ------ | ------------------------ | ---------------------- | ---------------------------------------------------------------------------- |
| GET    | `/dashboard`             | dashboard              | DashboardController@index (`?period=week                                     | fortnight | month`) |
| GET    | `/leads`                 | leads.index            | lista paginada (12), `?category=`, `?page=`; JSON se `wantsJson`             |
| POST   | `/leads`                 | leads.store            | cria lead                                                                    |
| GET    | `/leads/etapas`          | leads.category-options | categorias da conta, cursor (30)                                             |
| PATCH  | `/leads/{lead}`          | leads.update           | edita lead                                                                   |
| POST   | `/leads/{lead}/complete` | leads.complete         | finaliza venda (`confirm_completion` obrigatório; `charged_amount` opcional) |
| PATCH  | `/leads/{lead}/location` | leads.location         | grava só `maps_url` (+ lat/lng derivados); `maps_url` obrigatório            |
| DELETE | `/leads/{lead}`          | leads.destroy          | exclui lead                                                                  |
| GET    | `/categorias`            | categories.index       | lista (12, simplePaginate) + programadas                                     |
| POST   | `/categorias`            | categories.store       | cria categoria                                                               |
| PATCH  | `/categorias/{category}` | categories.update      | edita nome/cor                                                               |
| DELETE | `/categorias/{category}` | categories.destroy     | exclui com `confirmation`                                                    |

Settings (`routes/settings.php`): `GET /settings` → perfil; `GET|PATCH /settings/profile` (auth;
PATCH throttle 6/min); `DELETE /settings/profile`, `GET /settings/security` (RequirePassword),
`PUT /settings/password` (throttle 6/min), `GET /settings/appearance` (auth + verified).

IDs de rota usam `whereNumber`.

---

## 6. Fluxos funcionais (como o MVP funciona hoje)

Rate limits de todos os fluxos: ver 7.

### 6.1 Home → Plano → Cadastro

1. `/` mostra planos; CTA leva a `/register?plan=empresa|agencia`.
2. `registerView` valida o plano (senão volta para `/#planos`).
3. `POST /register` (limiter `registration` + `ValidateAuthenticationInput` +
   `EnsureSafeAuthenticationRedirect`) → `CreateNewUser`:
    - valida nome, e-mail único (lowercase), senha (ver 7), `terms` aceito, `plan`;
    - transação: `User` (senha `Hash::make` → argon2id) → `Account(type=plan)` (provisiona
      "Finalizado") → `Membership(role=owner)`.
4. Fortify loga o usuário e redireciona para `/dashboard` (ou verificação de e-mail, se exigida).

> Sem checkout simulado nem "ordem/intenção" no servidor: plano vem do query param, só validado
> contra a allowlist. Ver 11.2.

### 6.2 Login / 2FA / Logout / Recuperação

- `POST /login`: valida (`LoginRequest`), erro genérico (não revela se e-mail existe), **regenera
  sessão e CSRF**, respeita `url.intended` sanitizado. `rehash_on_login` atualiza hashes legados
  (bcrypt → argon2id).
- 2FA TOTP opcional por usuário (Fortify: `confirm` + `confirmPassword`); códigos de recuperação.
- `POST /logout`: Fortify faz `Auth::logout` + `invalidate` + `regenerateToken`; `LogoutResponse`
  limpa histórico Inertia criptografado e redireciona para `/login`. Sessão antiga e cookie remember
  não restauram acesso (testado).
- Recuperação: resposta idêntica para e-mail existente ou não; token expira em 60 min, uso único.
- Verificação de e-mail: obrigatória por padrão. Desligada só com `AUTH_EMAIL_VERIFICATION_REQUIRED=false`
  em `APP_ENV=local`; em outros ambientes **somente** com também `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION=true`.

### 6.3 Categorias

- Lista: de sistema ("programadas") separadas + comuns paginadas (12), cada uma com `leads_count`,
  `percentage` sobre o total da conta e, nas comuns, token
  `deletion_confirmation = Crypt::encryptString(json([account_id, category_id, name, count]))`.
- Criar/editar: só `name` (trim, ≤120, sem caracteres de controle) e `color` (`#rrggbb` minúsculo,
  **única por conta** — validação + unique no banco + tratamento de corrida). Campos extras rejeitados.
  Cor sugerida: `#6d54b5` ou a primeira HEX livre.
- Sistema **"Finalizado"** (`system_key=sale_completed`, cor inicial `#00ff0a`): criada com a conta;
  pode ser renomeada/recolorida; **não pode ser excluída** (403 + veto no model); `system_key`
  imutável, nunca aceito do browser.
- **Exclusão**: diálogo mostra quantos leads serão apagados; servidor, em transação com lock,
  recalcula contagem, descriptografa confirmação, compara com `hash_equals`. Nome ou quantidade
  mudaram → 422, nada apagado. Válido → **apaga categoria e, por FK em cascata, seus leads**.

### 6.4 Leads

Campos: `name` (obrigatório, ≤120), `description` (≤5000, opcional), `category_id` (obrigatório,
existe **na conta**), `first_contact_date` (obrigatória, `Y-m-d`), `next_contact_date` (opcional,
≥ primeiro contato), `potential_amount` e `charged_amount` (opcionais, 0–9.999.999.999,99, 2 casas),
`maps_url` (opcional, trim, `''` → null, texto colado com nome + link vira só o 1º `http(s)://` (servidor e botão colar), `url:http,https`, ≤2048, host na allowlist de `ParseGoogleMapsLink`).
Nulo continua nulo (não vira zero).

- Localização: `latitude`/`longitude` (`decimal:7`) **nunca vêm do browser** (campo extra → erro `form`);
  derivados de `maps_url` no `after()` do FormRequest, fora da transação. Sem coordenadas → erro em
  `maps_url`: "Não foi possível localizar a posição. Abra o local no Google Maps, toque em Compartilhar e
  cole o link copiado." `maps_url` vazio limpa lat/lng; ausente na requisição não altera. Card mostra
  botão de mapa em todo lead; com lat/lng o modal abre mapa Leaflet + link original em nova aba (`noopener noreferrer`);
  sem lat/lng o modal mostra cadastro do link (`PATCH /leads/{id}/location`, `UpdateLeadLocationRequest` → `SaveLead`,
  mesmas regras/mensagens do form; toast "Localização salva com sucesso!"; reload parcial `only: ['leads']`).
  Lead legado em "Finalizado" sem valor cobrado recebe 422 em `charged_amount` (mostrado no campo do link).

- Lista: 12 por página, ordem `id desc`, filtro por categoria validado e escopado à conta (categoria
  alheia → 404), página além do fim redireciona para a última, eager load de categoria (sem N+1).
- Criar/editar: `SaveLead` em transação com `lockForUpdate`, valida categoria da conta. Entrar em
  "Finalizado" (lead não finalizado) exige `confirm_completion`; sair dela exige `confirm_reopening`.
  Edição sem mudança não altera timestamps nem emite toast.
- **Finalizar venda** (`POST /leads/{id}/complete`): aceita só `confirm_completion`, `charged_amount`
  (mesmas regras do form), `page`, `category`; move para "Finalizado" e grava o valor na mesma
  transação; idempotente.
- **Valor cobrado obrigatório em "Finalizado"**: `SaveLead` rejeita (422 em `charged_amount`) qualquer
  gravação que deixe lead em "Finalizado" com `charged_amount` nulo ou 0 — cobre criação, edição,
  seletor de etapa e check. Lead finalizado não pode limpar/zerar o valor; para limpar, reabrir antes.
  Front espelha: form de lead marca "(obrigatório)" e barra antes do diálogo de transição; modal do
  check mostra input só se o lead não tem valor > 0 (`isPositiveLeadMoney`, sem float).
- Excluir: remove só o lead (categoria permanece), preserva página/filtro no redirect.
- Frontend: BRL convertido para string decimal sem float; prazo do próximo contato relativo à data local.

### 6.5 Configurações

- Perfil: nome livre; troca de e-mail exige `current_password` na mesma requisição, normaliza para
  minúsculas e zera `email_verified_at`.
- Excluir usuário: exige senha; bloqueado se for o **último owner** de qualquer conta (lock nas
  contas); depois invalida sessão.
- Segurança: confirmação de senha recente (`password_timeout` 3h); troca de senha regenera sessão e
  `remember_token` → `AuthenticateSession` derruba outros navegadores.

### 6.6 Dashboard

Props Inertia (closures, permitem _partial reload_):

- `overview`: `totalLeads`; `soldLeads` (= leads em "Finalizado") e `soldPercentage`;
  `soldRevenue` (string decimal `"1234.56"`: soma de `charged_amount` dos leads em "Finalizado", no
  banco em centavos inteiros `SUM(CAST(ROUND(charged_amount * 100) AS BIGINT))`, nulos somam 0, sem float);
  `inProgressLeads` **fixo em 0** (card "Em acompanhamento — em breve"); `categories` (todas, com
  contagem e % arredondado a 1 casa, sem forçar soma 100%; ordenadas no backend por `leads_count` desc, empate `id` desc); `weeklyIntake` = 7 dias Seg–Dom da
  **semana civil atual** no fuso `REPORTING_TIMEZONE`, contando `created_at` com limites semiabertos
  (uma query com `COUNT(CASE ...)`); `weeklyTotal`.
- `newLeads`: leads criados nos últimos 7/14/30 dias civis (`period` em allowlist).
- Card "Prévia de registros": placeholder "Em breve".
- Tudo calculado no backend, escopado por `account_id`; nenhuma lista de registros enviada.

---

## 7. Segurança implementada (camada por camada)

| Camada                          | Implementação atual                                                                                                                                                                                                                                                                                   |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Transporte**                  | Produção força esquema/origem do `APP_URL` (`URL::forceScheme/useOrigin`); cookie de sessão `secure` em produção. HTTPS depende do provedor (Render).                                                                                                                                                 |
| **Hosts / proxies**             | `trustHosts` só host do `APP_URL` (sem subdomínios); `trustProxies` só `X-Forwarded-Proto`, lista de proxies vazia, exceto opt-in `TRUST_LOCAL_TUNNEL_PROXY=true` em local (loopback, Cloudflare Tunnel). Impede poisoning de URLs de e-mail.                                                         |
| **Sessão**                      | Guard `session`; driver `database`; cookie `HttpOnly`, `SameSite=lax`, `Secure` em produção; lifetime 120 min; ID regenerado no login, troca de senha e logout; `AuthenticateSession` invalida outras sessões ao mudar senha; `serialization=json`.                                                   |
| **Cookies**                     | Todos criptografados (AES-256-CBC, `APP_KEY`) exceto `appearance` e `sidebar_state` (não sensíveis).                                                                                                                                                                                                  |
| **CSRF**                        | Middleware nativo do Laravel 13 (origem/Sec-Fetch + token / `X-XSRF-TOKEN`) em todas as mutações, inclusive login, registro, logout. GET logout não muda estado.                                                                                                                                      |
| **Senhas**                      | Hash **Argon2id** (memory 64 MB, time 4, threads 1), `rehash_on_login`, cast `hashed`; máx. 128; registro: mín. 8 + vazamento (HIBP `uncompromised`); troca/reset em produção: mín. 12 + maiúscula/minúscula + número + símbolo + uncompromised; fora de produção: mín. 8. Divergência do alvo: 11.1. |
| **2FA**                         | TOTP Fortify com confirmação e confirmação de senha; segredo e recovery codes criptografados; opcional (não obrigatório).                                                                                                                                                                             |
| **Brute force / rate limit**    | `login` 5/min (email\|ip); `two-factor` 5/min por `login.id`; `registration` 5/min/IP; `password.email` 30/min/IP + 5/min por hash(email)+IP; `password.update` 10/min; perfil (PATCH) e senha 6/min. Store = cache `database`.                                                                       |
| **Anti-enumeração**             | Login e recuperação com respostas genéricas; recurso de outro tenant → 404.                                                                                                                                                                                                                           |
| **Open redirect**               | `EnsureSafeAuthenticationRedirect` em login, 2FA, confirm, register, verify.                                                                                                                                                                                                                          |
| **Tenancy**                     | `ResolveCurrentAccount` (conta da sessão/membership, exatamente 1 owner) + `CurrentAccount`; queries pela relação da conta; contexto limpo após cada request.                                                                                                                                         |
| **Autorização**                 | `AccountPolicy` + `LeadPolicy` (`denyAsNotFound`); FormRequests chamam `Gate::authorize`; categorias usam `AccountPolicy@update`.                                                                                                                                                                     |
| **Requisições de saída (SSRF)** | Só `ParseGoogleMapsLink`: geocoder fixo `https://nominatim.openstreetmap.org/search`; https, hosts `goo.gl`/`maps.app.goo.gl`, sem seguir redirect automático, cada `Location` revalidado na allowlist, máx. 3 saltos, timeouts curtos; `ConnectionException` → erro de validação.                    |
| **Validação**                   | Dupla (front para UX, back como autoridade); allowlist de campos (rejeita extras); regex sem caracteres de controle; limites de tamanho; datas `Y-m-d`; dinheiro `decimal:0,2`.                                                                                                                       |
| **Mass assignment**             | `#[Fillable]` mínimos; `account_id` via relação; `system_key` só por código.                                                                                                                                                                                                                          |
| **Integridade no banco**        | FKs com cascade; **FK composta (account_id, category_id)**; uniques por conta; `DB::prohibitDestructiveCommands` em produção.                                                                                                                                                                         |
| **Concorrência**                | Transações com `lockForUpdate`, retries (`attempts: 3`), tratamento de `UniqueConstraintViolationException` (testes de concorrência em SQLite).                                                                                                                                                       |
| **Exposição de dados**          | Share Inertia só com campos públicos do usuário; colunas explícitas nas queries; `#[Hidden]` em segredos e `account_id`; JSON de registro/login sem dados sensíveis.                                                                                                                                  |
| **Histórico/cache do browser**  | `Inertia::encryptHistory()` em páginas autenticadas, `clearHistory()` no logout, `Cache-Control: no-store, private`.                                                                                                                                                                                  |
| **Logs**                        | Mailer `log` convertido para `array` (conteúdo de e-mail de auth nunca vai a log); `dontFlash` de `token`, `code`, `recovery_code`.                                                                                                                                                                   |
| **Frontend**                    | `categoryColor()` sanitiza cores antes do CSS; nenhum token de auth em `localStorage` (só preferência de aparência).                                                                                                                                                                                  |
| **Produção**                    | `Dockerfile` roda como `www-data`, `--no-dev`, opcache, remove `node_modules`; migrations automáticas (`AUTORUN`).                                                                                                                                                                                    |

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
- Mensagens de validação customizadas em pt-BR no próprio FormRequest. Toasts via `FlashCrudSuccess`.
- Rotas em português no path (`/categorias`, `/leads/etapas`), nomes em inglês (`categories.index`).
- Frontend: Wayfinder para URLs (`@/routes`, `@/actions`), componentes por domínio
  (`components/leads`, `components/categories`, `components/dashboard`), tipos em `types/`,
  helpers puros em `lib/` com teste `.test.mjs`.
- Testes Pest descritivos em inglês, foco em fronteiras de segurança e regressão.

---

## 10. Documento de arquitetura × implementação (gap analysis)

Legenda: ✅ implementado · 🟡 parcial/divergente · ❌ não implementado

### 10.1 Arquitetura e produto

| Item do documento                                                                              | Status | Observação                                                                                                                                                                                                             |
| ---------------------------------------------------------------------------------------------- | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel 13 + Inertia/React/TS                                                                  | ✅     |                                                                                                                                                                                                                        |
| PHP 8.4/8.5                                                                                    | 🟡     | PHP 8.3 (ver 2).                                                                                                                                                                                                       |
| PostgreSQL 18.6+                                                                               | 🟡     | Ver 2 e 11.9. Migrations compiladas para PG testadas sem servidor.                                                                                                                                                     |
| Redis para sessão/cache/rate limit                                                             | ❌     | Tudo em `database` (ver 2; plano em 10.2).                                                                                                                                                                             |
| Fortify (login, registro, reset, verificação, 2FA)                                             | ✅     |                                                                                                                                                                                                                        |
| Sanctum para API/mobile                                                                        | ❌     | Não instalado (OK para MVP web).                                                                                                                                                                                       |
| S3 privado                                                                                     | ❌     | Sem uploads.                                                                                                                                                                                                           |
| Queue + Redis/Horizon                                                                          | 🟡     | Fila `database`; nenhum job de domínio.                                                                                                                                                                                |
| Infra CloudFront/WAF/ALB/RDS                                                                   | ❌     | Deploy em container (Render).                                                                                                                                                                                          |
| PWA (manifest/service worker)                                                                  | ❌     | Sem `manifest.webmanifest`.                                                                                                                                                                                            |
| Capacitor                                                                                      | ❌     | Futuro.                                                                                                                                                                                                                |
| Fluxo HOME → PLANO → CHECKOUT SIMULADO → CADASTRO                                              | 🟡     | Home e plano ✅; **checkout simulado/ordem no servidor não existe** (ver 11.2).                                                                                                                                        |
| Tenancy por `account_id` (USER → membership → ACCOUNT)                                         | ✅     |                                                                                                                                                                                                                        |
| Múltiplas contas por usuário / slug de contexto                                                | ❌     | Ver 11.4.                                                                                                                                                                                                              |
| `users`                                                                                        | ✅     |                                                                                                                                                                                                                        |
| `accounts`                                                                                     | ✅     | `type empresa                                                                                                                                                                                                          | agencia`. |
| `memberships`                                                                                  | 🟡     | Só `role=owner`; sem papéis de colaborador.                                                                                                                                                                            |
| `plans/subscriptions`                                                                          | ❌     |                                                                                                                                                                                                                        |
| `lead_categories`                                                                              | ✅     | + categoria de sistema "Finalizado".                                                                                                                                                                                   |
| `leads`                                                                                        | 🟡     | Atuais em 4. **Faltam**: `client_id`, `phone`, `website`, `instagram`, `quoted_amount` (≈ potential_amount), `tool_cost`, `status` (hoje status = categoria). Extra implementado: `maps_url` + `latitude`/`longitude`. |
| `clients`                                                                                      | ❌     |                                                                                                                                                                                                                        |
| `campaigns` / `campaign_metrics` (histórico)                                                   | ❌     |                                                                                                                                                                                                                        |
| `agency_links` / `access_grants` / `invitations`                                               | ❌     |                                                                                                                                                                                                                        |
| `attachments`                                                                                  | ❌     |                                                                                                                                                                                                                        |
| `audit_logs`                                                                                   | ❌     |                                                                                                                                                                                                                        |
| RLS PostgreSQL (2ª barreira)                                                                   | ❌     | Opcional no documento; hoje 2ª barreira = FK composta.                                                                                                                                                                 |
| Policies em todos os recursos                                                                  | ✅     | Account e Lead; categorias via AccountPolicy.                                                                                                                                                                          |
| `PATCH /leads/ABC` passa por sessão → membership → lead da conta → policy → validação → UPDATE | ✅     |                                                                                                                                                                                                                        |
| `/dashboard` sem user_id na rota                                                               | ✅     |                                                                                                                                                                                                                        |
| Data minimization                                                                              | ✅     |                                                                                                                                                                                                                        |
| Dashboard: donut por categoria                                                                 | ✅     |                                                                                                                                                                                                                        |
| Dashboard: campanhas ativas, receita, investimento, ferramentas, resultado, margem             | 🟡     | Faturamento de leads finalizados (`soldRevenue`) no card "Leads vendidos"; demais dependem de campanhas.                                                                                                               |
| Cookie Secure/HttpOnly/SameSite, sem token em localStorage                                     | ✅     |                                                                                                                                                                                                                        |
| CSRF                                                                                           | ✅     |                                                                                                                                                                                                                        |
| Senha mínimo 15 sem MFA (ou 8 com 2FA obrigatório), sem regras de composição, bloquear vazadas | 🟡     | Ver 11.1.                                                                                                                                                                                                              |
| Hash bcrypt/Argon2id                                                                           | ✅     | Argon2id.                                                                                                                                                                                                              |
| Criptografia em repouso/TLS no banco/rede privada/IAM                                          | ❌     | Depende da infra; nada no código (ex.: `sslmode`).                                                                                                                                                                     |
| Criptografia de campo seletiva                                                                 | 🟡     | Só segredos 2FA (Fortify) e tokens de confirmação; correto para MVP segundo o documento.                                                                                                                               |
| Validação tripla (front, back, constraints)                                                    | ✅     |                                                                                                                                                                                                                        |
| Bloqueio de mass assignment de campos de autoridade                                            | ✅     |                                                                                                                                                                                                                        |
| Deslogado → /login; 401 API; 403 ação conhecida; 404 tenant alheio                             | ✅     |                                                                                                                                                                                                                        |
| Logout: logout → invalidate → regenerate CSRF → redirect                                       | ✅     |                                                                                                                                                                                                                        |
| Convites Agência↔Empresa com aceite/recusa, grants granulares, revogação                       | ❌     | Plano em 10.2.                                                                                                                                                                                                         |
| Pipeline de print/OCR com revisão humana                                                       | ❌     | Plano em 10.2.                                                                                                                                                                                                         |
| Meta Marketing API                                                                             | ❌     |                                                                                                                                                                                                                        |
| App stateless (sessão fora do servidor)                                                        | 🟡     | Sessão no banco já compartilhável entre instâncias; Redis recomendado.                                                                                                                                                 |
| Docker sem segredos, usuário não privilegiado                                                  | ✅     | Roda como `www-data`; `.dockerignore` exclui `.env`, `.git`, `node_modules`, `vendor`, `tests`, logs e `public/build`.                                                                                                 |
| LGPD: aviso de privacidade, retenção, acesso, cookies, incidentes                              | 🟡     | Só checkbox de Termos no cadastro.                                                                                                                                                                                     |
| Backup e restore testado                                                                       | ❌     |                                                                                                                                                                                                                        |
| Observabilidade                                                                                | ❌     |                                                                                                                                                                                                                        |

### 10.2 Escalabilidade futura (plano; encaixar sem quebrar a arquitetura)

Fluxos e decisões-alvo correspondentes: anexo (12).

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
- **Uploads/OCR**: disco S3 privado, validação MIME/tamanho (sem SVG), scan de malware (GuardDuty
  Malware Protection for S3), job em fila, OCR (AWS Textract, com confiança), tela de revisão,
  gravação em transação. Depois: Meta Marketing API/Insights como fonte de verdade.
- **Infra**: Redis (sessão, cache, rate limit, fila/Horizon), PostgreSQL gerenciado com TLS, backups
  testados, `audit_logs` para eventos de segurança.
- **API/mobile**: Sanctum; PWA (manifest + service worker); Capacitor reutilizando o mesmo backend.

### 10.3 Critérios para liberar o MVP (do documento) × situação atual

| Teste exigido                                         | Situação                                                          |
| ----------------------------------------------------- | ----------------------------------------------------------------- |
| Usuário A lista leads de B → impossível               | ✅ coberto (`LeadFoundationTest`, `DashboardMetricsTest`)         |
| A abre ID de B → 404/negado                           | ✅ (`LeadPolicy`, `CurrentAccountTest`)                           |
| A faz PATCH em B → impossível                         | ✅ (`LeadMutationTest`)                                           |
| A tenta DELETE em B → impossível                      | ✅ (`LeadMutationTest`, `LeadIntegrityTest`)                      |
| `account_id` alterado no request → ignorado/rejeitado | ✅ (campos extras rejeitados)                                     |
| Deslogado abre dashboard → login                      | ✅ (`DashboardTest`)                                              |
| Cookie/sessão antigo após logout → inválido           | ✅ (`LogoutSecurityTest`, `SessionReplayTest`)                    |
| POST sem CSRF → rejeitado                             | ✅ (`*CsrfTest`)                                                  |
| Brute force no login → rate limited                   | ✅ (`AuthenticationTest`, `LoginSecurityTest`)                    |
| Agência sem convite aceito → zero acesso              | ⏳ não aplicável ainda (sem vínculo; hoje acesso zero por padrão) |
| Agência com read tenta editar → negado                | ❌ funcionalidade inexistente                                     |
| Permissão revogada → próxima operação negada          | 🟡 membership revogada testada; grants inexistentes               |
| Upload com MIME falso → rejeitado                     | ❌ sem uploads                                                    |
| Arquivo malicioso → não processado                    | ❌ sem uploads                                                    |
| OCR baixa confiança → não gravado                     | ❌ sem OCR                                                        |
| Dashboard Empresa A → zero dado de B                  | ✅ (`DashboardMetricsTest`, `TenantFoundationTest`)               |
| Exclusão de lead → dependências em transação          | ✅ (`LeadIntegrityTest`)                                          |
| Restore do backup comprovadamente funcional           | ❌ não há processo de backup                                      |

---

## 11. Divergências, riscos e débitos conhecidos (não alterar sem pedido)

1. **Política de senha** diverge do alvo (anexo, "Senha"): registro 8; troca/reset em produção 12 +
   composição (alvo desaconselha); inconsistência registro × troca; 2FA não obrigatório. UI do
   registro cita 8 caracteres. Detalhe atual em 7.
2. **Plano escolhido pelo browser** (`?plan=`) sem ordem/checkout no servidor — hoje inofensivo (sem
   entitlement diferente), mas precisa mudar antes de existir diferença de recursos/cobrança.
3. **Conta Agência = conta Empresa** em funcionalidades; nada específico de agência existe.
4. `ResolveCurrentAccount` exige **exatamente uma** conta owner: convidado/colaborador ou usuário com
   2 contas recebe 404 — evoluir para seleção de conta ativa (10.2).
5. `LeadController::show()` implementado mas **sem rota** (código morto ou pendente).
6. Card "Em acompanhamento" fixo em 0; "Prévia de registros" placeholder.
7. Sessão/cache/rate limit/fila em `database` (ver 2); fila sem worker definido no Docker.
8. Mailer padrão `array`: e-mails de verificação/reset **não são enviados** até configurar `MAIL_MAILER`.
   Verificação exigida em produção sem mailer real impede usuários de verificar (por isso existe o
   opt-out explícito `AUTH_ALLOW_UNVERIFIED_EMAIL_IN_PRODUCTION`).
9. Dev em SQLite × produção em PostgreSQL: locks/enum diferem (há testes de compilação de constraints
   PG, sem execução contra PG real).
10. `trustProxies` com lista vazia em produção: atrás do proxy do Render, HTTPS garantido por
    `URL::forceScheme` a partir do `APP_URL`; IP do cliente para rate limit pode ser o do proxy —
    avaliar `proxies` corretos antes de escalar.
11. Sem audit log, backup/restore, observabilidade, política de privacidade/LGPD.
12. Mapa usa o servidor público de tiles do OpenStreetMap (política de uso justo, sem SLA); com volume
    alto, trocar por provedor de tiles próprio/pago.
13. Extração de coordenadas depende do formato de URL/redirect do Google Maps; mudança do Google faz
    links novos falharem com erro de validação (dados salvos não são afetados). Link curto exige saída
    HTTP do servidor para `maps.app.goo.gl`.
14. Links do app (sem coordenadas) dependem do Nominatim público: política de uso (máx. 1 req/s, sem
    uso massivo, User-Agent identificável), sem SLA; precisão depende do endereço (pode cair para
    bairro/cidade ou falhar). Volume alto: geocoder próprio/pago. Google Geocoding descartado (termos
    proíbem exibir em mapa não-Google).
15. Leads legados em "Finalizado" sem valor cobrado (0 no banco local em 2026-10-27; produção não
    verificada) não salvam edição até receber valor > 0 ou ser reabertos. Sem migration de dados.

---

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

| Componente            | Backend calcula                          |
| --------------------- | ---------------------------------------- |
| Donut/pizza de leads  | total + count e percentual por categoria |
| Campanhas ativas      | count                                    |
| Receita bruta         | soma do período                          |
| Investimento em mídia | soma                                     |
| Ferramentas           | soma                                     |
| Resultado             | receita − mídia − ferramentas            |
| Margem                | resultado ÷ receita × 100                |

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

---

## 13. Changelog deste arquivo

| Data       | Mudança                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-27 | Criação do CLAUDE.md: instruções para o Claude, mapeamento completo do MVP v1 (commits até `aa06919`), segurança implementada, gap analysis contra o documento de arquitetura e anexo integral do PDF. Nenhum código alterado.                                                                                                                                                                                                                                                                                                                             |
| 2026-10-27 | `/CLAUDE.md` removido do `.gitignore`: este arquivo passa a ser versionado com o projeto.                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2026-10-27 | CLAUDE.md comprimido; anexo (seção 12) passa a ser especificação viva e atualizável (regra 0.4 alterada). Nenhum código alterado.                                                                                                                                                                                                                                                                                                                                                                                                                          |
| 2026-10-27 | Segunda passada: deduplicação, remoção de prosa/referências numeradas, regras 8–12 (um fato um lugar, rotação do changelog, orçamento de 650 linhas que nunca vence o contexto). Nenhum código alterado.                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-27 | Seção 14 (pendências, decisões e próximos passos — memória entre sessões) criada; regra 0.4 item 13 e leitura de 14.1/14.3 em 0.1. Nenhum código alterado.                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-27 | Seção 0.6 (retomada, documentação das ferramentas, tarefa concluída); `@AGENTS.md`; coluna Doc na seção 2; regras 0.4 itens 5, 9, 10 e 11 (tamanho só informativo, nunca corta contexto; changelog completo; hash base). Nenhum código alterado. base 8f675ce                                                                                                                                                                                                                                                                                              |
| 2026-10-27 | CI quebrado por formatação do CLAUDE.md (`vp check`: "Formatting issues found"); corrigido com `npx vp fmt CLAUDE.md` (só espaços, alinhamento de tabelas, `*x*` → `_x_`); 0.6 passa a exigir o fmt. Arquivos Wayfinder locais regenerados (`--with-form`, gitignored). `composer ci:check` local verde (677 testes). Nenhum código alterado. base 487f6a0                                                                                                                                                                                                 |
| 2026-10-27 | Lead: campo "Link do Google Maps" (`maps_url`) + `latitude`/`longitude` derivados no servidor (`ParseGoogleMapsLink`, links curtos resolvidos com allowlist anti-SSRF); botão colar (`ClipboardPaste`); botão `MapPin` no card abre modal com mapa Leaflet/OSM (lazy) e "Abrir no Google Maps". Deps: `leaflet`, `react-leaflet`, `@types/leaflet`. Migration `2026_10_28_005604_add_maps_location_to_leads_table`; `LeadMapsLocationTest`; `LeadIntegrityTest` (rollback) inclui a nova migration. Seções 2, 3, 4, 6.4, 7, 10.1, 11, 12, 14. base 66af1ac |
| 2026-10-27 | Links compartilhados pelo app do Google Maps passam a funcionar: redirect do app traz só nome/endereço, agora geocodificado no Nominatim/OSM; padrão de pin `/maps/search/lat,+lng`; texto colado com nome + link extrai a URL. Testes em `LeadMapsLocationTest`. Seções 3.1, 6.4, 7, 11, 14. base 2e8db42                                                                                                                                                                                                                                                 |
| 2026-10-27 | Valor cobrado > 0 obrigatório para lead em "Finalizado" (regra em `SaveLead`; check aceita `charged_amount`; modal com input; `LeadMoneyField` extraído; `isPositiveLeadMoney`). Testes de venda atualizados + novos em `SaleCompletionTest`. Seções 3.1, 3.3, 5, 6.4, 11, 12, 14. base 4642076                                                                                                                                                                                                                                                            |
| 2026-10-27 | Card "Leads vendidos" do dashboard passa a destacar o faturamento (`soldRevenue`, soma exata em centavos no banco) com % e quantidade como texto secundário. Bloco verde do painel "Leads por categoria" inalterado. Orçamento de queries do dashboard em `DashboardMetricsTest` 4 → 5. Novo teste em `SaleMetricsTest`. Seções 3.3, 6.6, 10.1, 14. base 4642076                                                                                                                                                                                           |
| 2026-10-28 | Valor do faturamento no card "Leads vendidos" na cor `var(--dashboard-green)`. Seção 3.3. base 4642076                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| 2026-10-28 | Botão de mapa em todos os leads; lead sem localização cadastra o link dentro do modal (envio ao colar/Enter/blur, spinner, erro no campo, mapa carrega sem fechar). Rota `PATCH /leads/{lead}/location` (`leads.location`) + `UpdateLeadLocationRequest` (estende `MutateLeadRequest`, regras via `StoreLeadRequest::mapsUrlRules()`) → `SaveLead`. `LeadMapsUrlField` extraído do form; `mapsLinkFrom()` em `lead-format.ts` (+ teste JS). Testes novos em `LeadMapsLocationTest`. Seções 3.1, 3.3, 3.4, 5, 6.4, 13, 14. base 91721df                     |
| 2026-10-28 | Dashboard: `overview.categories` (donut + lista "Leads por categoria") ordenadas no backend da categoria com mais leads para a com menos (empate: mais nova primeiro). Teste em `DashboardMetricsTest`. Seção 6.6. base 4642076                                                                                                                                                                                                                                                                                                                            |

---

## 14. Pendências, decisões e próximos passos (memória entre sessões)

> Serve para a próxima sessão retomar de onde esta parou. Só o que NÃO está em outro lugar:
> riscos/débitos ficam em 11, histórico do que foi feito fica em 13, status de arquitetura fica em 10.
> Aqui: o que falta fazer, o que foi decidido e por quê, o que depende do usuário.

### 14.1 Em andamento

Tarefa iniciada e não concluída, com o ponto exato onde parou (arquivo, etapa, o que falta). Vazio = nada em andamento.

- (nada em andamento)

### 14.2 Próximos passos (ordem de prioridade definida pelo usuário)

Formato: `N. [área] o que fazer — critério de pronto — dependência (se houver)`.
Só o usuário define prioridade; o Claude sugere e registra como "sugerido".

- (a definir pelo usuário; candidatos vêm de 10.1, 10.3 e 11)

### 14.3 Decisões pendentes do usuário

Perguntas que bloqueiam trabalho. O Claude NÃO decide sozinho; pergunta ao iniciar a tarefa relacionada.

- (nenhuma)

### 14.4 Decisões tomadas (com o porquê)

Formato: `AAAA-MM-DD | decisão | motivo | alternativa descartada`.
Evita rediscutir o que já foi resolvido. Decisão de arquitetura-alvo também entra no anexo (12).

- 2026-10-27 | CLAUDE.md é o cérebro único; anexo (12) é especificação viva | sessão nova não herda a conversa, só o arquivo | arquivo de contexto separado
- 2026-10-27 | Limite de tamanho do CLAUDE.md não pode cortar contexto; tamanho é só informativo | o arquivo é o cérebro único e a prioridade do usuário é nunca perder contexto | limite de 650 linhas com condensação automática (arquivo real ~1210 linhas)
- 2026-10-27 | Terceira passada de compressão não aplicada | o usuário prioriza contexto completo sobre economia de tokens | deduplicar tabelas e diagramas do anexo
- 2026-10-27 | Mapa com `leaflet` + `react-leaflet` + `@types/leaflet` (3 pacotes) | prompt pedia react-leaflet e "no máximo 1 dependência"; react-leaflet exige leaflet (peer) e TS exige tipos; usuário escolheu os 3 | só leaflet com wrapper próprio; leaflet sem tipos
- 2026-10-27 | Parser de link do Maps em `app/Actions/ParseGoogleMapsLink.php` (não `GoogleMapsLinkParser`/`app/Support`) | seguir convenção de `app/Actions` e não criar pasta-base (0.3) | classe em pasta nova
- 2026-10-27 | Allowlist de hosts inclui `www.google.com` e `www.google.com.br` além dos pedidos | links longos do Google Maps usam `www.`; sem isso seriam rejeitados | allowlist literal do pedido
- 2026-10-27 | Coordenadas extraídas no `after()` do FormRequest (fora da transação de `SaveLead`) | não segurar lock de banco durante requisição HTTP | chamar o parser dentro de `SaveLead`
- 2026-10-27 | Links do app sem coordenadas geocodificados via Nominatim/OSM | grátis, sem chave, mesmo ecossistema do mapa; usuário escolheu | Google Geocoding API (chave + cobrança; termos proíbem mapa não-Google); raspar HTML do Google (frágil, viola termos)
- 2026-10-27 | Regra "valor cobrado > 0 em Finalizado" em `SaveLead`, não em FormRequest/Rule | `SaveLead` é o único ponto de escrita e já conhece a categoria-alvo e o valor salvo (com lock); cobre create/edit/check de uma vez | regra por FormRequest (precisaria repetir em 3 requests e consultar lead fora do lock)
- 2026-10-27 | Legados finalizados sem valor não migrados; ficam bloqueados para edição até receber valor ou reabrir | pedido do usuário (sem preencher retroativo) | migration de dados
- 2026-10-27 | `soldRevenue` somado em centavos inteiros no banco (`CAST(ROUND(x*100) AS BIGINT)`) e formatado em PHP | `SUM` de decimal no SQLite volta float; centavos inteiros são exatos em SQLite e PostgreSQL | `->sum('charged_amount')` (float no SQLite)
- 2026-10-27 | Orçamento fixo de queries `"leads"` do dashboard sobe de 4 para 5 | soma do faturamento é 1 query constante, escopada por `account_id`; continua sem crescer com categorias/leads | fundir a soma na query de contagem total (alteraria o cálculo existente da contagem)
- 2026-10-28 | Link do mapa salvo por endpoint dedicado `PATCH /leads/{lead}/location` | `leads.update` exige nome, categoria e primeiro contato; endpoint enxuto reusa `MutateLeadRequest` (tenancy, policy, allowlist de campos, parser) e `SaveLead` | mandar o form completo do lead a partir do modal
- 2026-10-28 | Sem link, "Abrir no Google Maps" fica oculto (aparece após salvar) | não há destino para o link | botão desabilitado
- 2026-10-27 | Medição de referência: 8983 palavras (wc -w) | base para o aviso de crescimento >15% (0.4 item 10) | —

### 14.5 Ideias e descartes

Ideia citada e não priorizada, ou descartada com motivo, para não ser reproposta sem novidade.

- (nenhuma)

### 14.6 Regras desta seção

1. Atualizar ao fim de TODA tarefa (protocolo 0.4, item 13) e antes de encerrar a sessão.
2. Ao concluir um passo: remover de 14.1/14.2 e registrar em 13 (changelog) e nas seções afetadas.
3. Ao interromper uma tarefa no meio: registrar em 14.1 o ponto exato e o que falta.
4. Não duplicar: risco/débito → 11; feito → 13; status de arquitetura → 10. Aqui só referência ("ver 11.4").
5. Sem datas relativas ("amanhã", "semana que vem"): sempre AAAA-MM-DD.
6. O Claude nunca apaga item de 14.3, 14.4 ou 14.5 sem pedido do usuário. Item resolvido de 14.3 migra para 14.4.
7. Ao iniciar uma sessão, ler 14.1 e 14.3 antes de agir; se 14.1 tiver tarefa em andamento, perguntar ao usuário se é para retomá-la.
8. Esta seção e o changelog nunca são condensados sem pedido explícito do usuário.
