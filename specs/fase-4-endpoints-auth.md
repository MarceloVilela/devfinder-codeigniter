# Fase 4 — Autenticação (GitHub OAuth + JWT via Filters)

> Fase 4 de [`../plan.md`](../plan.md), seção "5. Fase 4". Depende da Fase 3 (PR #4,
> mergeado). Reaproveita decisões já resolvidas em
> [`../../serverless/specs/fase-4-auth.md`](../../serverless/specs/fase-4-auth.md) (payload
> do JWT, achados de leitura do `devfinder-api`/`devfinder-next` originais), adaptadas pra
> CI4 — sem os problemas específicos de API Gateway Authorizer que o projeto irmão teve
> (`identitySource`, dois authorizers pra simular "opcional").

## O que entra

- `app/Config/Auth.php` — secreto do JWT, expiração, credenciais do GitHub OAuth App, URL do
  `devfinder-next`. Tudo via `.env` (`auth.*`) — **sem** Secrets Manager/Parameter Store
  equivalente, decisão já registrada em `plan.md`: "sem custo fixo mensal a evitar, ao
  contrário do caso AWS".
- `app/Libraries/Jwt.php` — `encode`/`decode` (`firebase/php-jwt`), payload `{ username, iat,
  exp }`.
- `app/Libraries/GithubOAuth.php` — troca `code`↔`access_token`, busca perfil público.
- `app/Libraries/AuthContext.php` + Service (`app/Config/Services.php`) — guarda o Dev
  identificado na request atual, populado pelos Filters, lido pelos Controllers.
- `app/Filters/{RequiredAuthFilter,OptionalAuthFilter}.php` — substituem
  `middlewares/{auth,optionalAuth}.ts`.
- `app/Controllers/AuthController.php` (`github`, `callback`), `app/Controllers/MeController.php`.
- `app/Presenters/DevPresenter.php` — serialização de Dev extraída de `DevController` (Fase
  3) pra ser reaproveitada por `MeController`.
- `DevModel::findOrCreate()` — substitui `findOrCreateDev.ts`, com o mesmo fallback (busca
  perfil público do GitHub quando `name`/`bio`/`avatar` vêm vazios).
- Personalização reativada: `DevController::index()` (exclui o próprio Dev + likes/dislikes),
  `VideoController::trending()` (exclui vídeos de canais ignorados, via JWT ou `?user=`).

## Decisões reaproveitadas do projeto irmão (evidência já levantada lá)

1. **Payload do JWT = `{ username }`**, não `{ id }` do Mongo original. Nesta stack MySQL
   existe um `id` numérico real (diferente do DynamoDB, que forçou essa decisão por não ter
   equivalente a ObjectId) — mesmo assim, mantido `{ username }`: é o que `plan.md` já
   esperava, é estável/legível, e o payload é opaco pro frontend de qualquer forma
   (`devfinder-next` nunca decodifica o JWT, só repassa e lê `_id` do corpo de `GET /me`).
2. **`?user=<username>` preservado** em `GET /feed/trending` como alternativa ao JWT — não é
   autenticação, é identificação explícita sem token (`TrendingController.index` original
   aceita os dois). Confirmado sem uso conflitante.
3. **`id=` dropado do redirect final** (`?id=${id}&token=${token}` → só `?token=`) — confirmado
   lendo `devfinder-next/src/hooks/auth.tsx`/`pages/login/index.tsx`: só `router.query.token`
   é lido.
4. **Bug do `failureRedirect: '/login'` corrigido, não replicado** — original redirecionava
   pra um `/login` relativo ao domínio da própria API (sem consumidor real); aqui vai sempre
   pra `auth.webURL/login`.

## Achados novos desta execução (CI4/MySQL, não no projeto irmão)

### 1. `hex2bin:` não é um padrão genérico do CI4 pra qualquer config

Tentei usar o mesmo truque de `encryption.key = hex2bin:...` pra `auth.jwtSecret`, assumindo
que fosse uma convenção geral do framework pra "valor deve ser decodificado de hex". Não é —
essa decodificação é específica de `Config\Encryption`. Usando o mesmo prefixo em
`Config\Auth::$jwtSecret`, o valor lido era a **string literal** `"hex2bin:4795..."`, não os
bytes decodificados — `Jwt::decode()` falhava pra todo token assinado com o valor real.
Corrigido usando o valor puro (a string hex funciona como segredo HMAC igual — HS256 não
exige que a chave seja binária).

### 2. CI4 Filters não têm o problema de `identitySource` do API Gateway

O projeto irmão precisou de dois Lambda Authorizers (`REQUEST`, sem `identitySource` no
"opcional") pra contornar o fato de o API Gateway rejeitar a request **antes** de invocar a
function se `identitySource` estiver declarado e ausente — mesmo a Lambda sempre fazendo
`Allow`. Um `Filter::before()` do CI4 roda como código PHP normal dentro do próprio request
lifecycle: `OptionalAuthFilter` simplesmente não retorna nada (deixa passar) quando o header
está ausente/inválido — não existe uma camada de infraestrutura separada que possa rejeitar
antes. "Opcional" aqui é trivial, sem gambiarra nenhuma.

### 3. Divergência deliberada: `GET /me` com token de username inexistente é 401, não 400

Original: `authMiddleware` só valida a assinatura do JWT; `ProfileController.show` faz
`Dev.findById` separado e devolve 400 `{error: 'DevProfile not exists'}` se não achar.
Aqui: `RequiredAuthFilter` já resolve o Dev pelo `username` do token pra popular o
`AuthContext` (usado também por `DevController`/`VideoController`, não só `/me`) — se o Dev
não existe, trata como token inválido (401), não deixa passar pro Controller pra um segundo
tipo de erro. Registrado como divergência aceita, não um bug: `devfinder-next` só reage a 401
no interceptor de "sessão expirada" (`services/api.ts`); um 400 aqui ficaria como rejection
não tratada — efeito prático igual ou melhor.

### 4. `scope` do GitHub OAuth — corrigido antes de virar divergência

Implementei inicialmente com `scope=read:user` na URL de autorização; o original
(`passport-github`) não configurava nenhum scope. Corrigido pra paridade: perfil público
(login/name/bio/avatar_url) não precisa de escopo nenhum, `read:user` só seria necessário pra
dado privado (ex. email), que este projeto não usa.

## Validação — execução real, não só leitura de código

`vendor/bin/phpunit`: `OK (5 tests, 7 assertions)`, exit 0 (conferido explicitamente, não só
o resumo visual — ver `CLAUDE.md`).

Todos os `.http` relevantes rodados de verdade via `httpyac` contra o Docker Compose local —
saída completa em [`acceptance/execucao-fase-4.log`](./acceptance/execucao-fase-4.log):

- `GET /auth/github` — 302 pra `github.com/login/oauth/authorize`, sem `scope`, `redirect_uri`
  montado corretamente a partir de `base_url()`.
- `GET /auth/github/callback` sem `code` — 302 pra `auth.webURL/login` (não um `/login`
  relativo à API).
- `GET /me` — 401 sem token, 401 com token malformado, **200 com token válido** (JWT gerado
  localmente com o mesmo `auth.jwtSecret`, payload `{username: "dev01", ...}` — corpo bate
  com `DevPresenter::present`), 401 com token de username inexistente (divergência #3 acima).
- `GET /devs` autenticado como `dev01` (fixture: like em dev02, dislike em dev03) — total cai
  de 35 pra **32** (exclui dev01 + dev02 + dev03), nenhum dos 3 aparece nos `docs`.
- `GET /devs` com token **inválido** — 200 (não bloqueia), total 35 — confirma que
  `optionalAuth` nunca nega.
- `GET /feed/trending` personalizado via `?user=dev01` **e** via Bearer token — os dois dão o
  mesmo resultado: total cai de 55 pra **20** (`dev01` ignora "Canal Beta", 35 vídeos).
- `DevModel::findOrCreate()` testado isolado (Command `spark` temporário, removido depois):
  cria o Dev com username normalizado pra minúsculas (`NovoDev` → `novodev`), é idempotente
  (segunda chamada com `name`/`bio`/`avatar` diferentes devolve a linha já existente, não
  duplica nem sobrescreve) — mesma verificação que o projeto irmão fez pro
  `findOrCreateDev` equivalente dele.

**Exercitado depois, pelo usuário** (não nesta execução automatizada — exigia login de
verdade num navegador, sessão sem acesso a browser): a troca real
`code`→`access_token`→profile via API do GitHub. Ver "Verificação humana" abaixo — concluída
em 2026-08-24, `GET /me` confirmado com dado real do GitHub.

## Verificação humana — GitHub OAuth App real

Passo a passo pra fechar a única parte que não dá pra automatizar (login de verdade no
GitHub). Estado: app cadastrado e `.env` preenchido (2026-08-24) — falta só o passo 3
(browser).

1. **Cadastrar o app no GitHub** — [github.com/settings/developers](https://github.com/settings/developers)
   → "New OAuth App":
   - Homepage URL: `http://localhost:3000`
   - Authorization callback URL: `http://localhost:8081/v1/auth/github/callback`
2. **Preencher `.env`** (nunca `env` sem ponto nem `.env.example` — ambos versionados, aviso
   na primeira linha dos dois):
   ```
   auth.githubClientId = <Client ID do app>
   auth.githubClientSecret = <Client Secret do app>
   ```
   `docker compose restart app` depois de editar. **Confirmado mecanicamente** (2026-08-24):
   `GET /auth/github` já redireciona com o `client_id` real —
   `Location: https://github.com/login/oauth/authorize?client_id=Ov23liUM...&redirect_uri=...`.
3. **Login de verdade** (só o usuário, precisa de navegador): abrir
   `http://localhost:8081/v1/auth/github`, autorizar no GitHub, cair em
   `http://localhost:3000/login?token=<jwt>` (a página não existe — `devfinder-next` não
   precisa estar rodando, o `token` já aparece na URL do navegador mesmo assim). Copiar esse
   `token`.
4. **`GET /me` com o token real**: colar em `authToken` de
   `specs/acceptance/http-client.private.env.json` (local, gitignored) e rodar o caso
   "token válido" de [`me.http`](./acceptance/me.http) — já existe, é o mesmo arquivo usado
   pros testes com JWT sintético, só troca o token. Esperado: 200, corpo com dado real do
   GitHub (nome, avatar, bio), não mais os valores de `dev01` da fixture.
5. Resultado real vai pra `specs/acceptance/execucao-fase-4.log`, mesmo padrão de evidência
   já usado nos outros casos deste documento.

**Concluído em 2026-08-24.** Fluxo completo executado de ponta a ponta pelo usuário: login
real no GitHub → `AuthController::callback` trocou `code`→`access_token`→profile de verdade
→ `DevModel::findOrCreate` criou o Dev real (`marcelovilela`, `_id: 37`) → JWT emitido →
redirect com token real → `GET /me` com esse token confirmado, **200**:

```json
{
    "_id": 37,
    "name": "Marcelo Vilela",
    "user": "marcelovilela",
    "bio": "",
    "avatar": "https://avatars.githubusercontent.com/u/32023347?v=4",
    "likes": [], "deslikes": [], "follow": [], "ignore": [],
    "createdAt": "2026-08-24T16:52:41+00:00",
    "updatedAt": "2026-08-24T16:52:41+00:00"
}
```

Fecha a única lacuna que faltava: `GithubOAuth::exchangeCodeForToken`/`fetchProfile`,
antes verificados só por leitura de código, agora confirmados contra a API real do GitHub.

## Critério de aceite da Fase 4 (de `../plan.md`)

- [x] Login via GitHub end-to-end (API) — confirmado em 2026-08-24: navegador → GitHub →
  callback real → `findOrCreate` → JWT → `GET /me` com dado real (ver "Verificação humana").
  **Parcial**: contra `devfinder-next` local especificamente, não — o frontend não estava
  rodando durante o teste (só a URL de redirect com `?token=` foi capturada manualmente do
  navegador); a página `/login` de fato consumindo o token via `GET /me` segue não
  verificada na prática, só por leitura de código (`hooks/auth.tsx`).
- [x] Token aceito pelo filtro — `RequiredAuthFilter`/`OptionalAuthFilter` validados com JWTs
  sintéticos e com o JWT real emitido pelo fluxo OAuth.
- [x] `GET /me` retorna o Dev autenticado — confirmado com JWT sintético e com token real do
  fluxo OAuth (dado real do GitHub: nome, avatar).
- [ ] Revisão humana.

## Próximo passo

Fase 5 (endpoints autenticados de escrita) reaproveita `RequiredAuthFilter` +
`DevModel::findOrCreate()` pra `POST /devs`, e os pares like/dislike/follow/ignore via
`dev_reactions`/`channel_reactions`.
