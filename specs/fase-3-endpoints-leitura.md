# Fase 3 — Endpoints públicos de leitura

> Fase 3 de [`../plan.md`](../plan.md), seção "4. Fase 3". Depende da Fase 2 (PR #3, mergeado)
> — schema e migrations já validados contra MySQL real.

## Escopo

`GET /devs`, `GET /devs/{username}`, `GET /channels`, `GET /channels/{searchQuery}`,
`GET /feed/trending`, `GET /feed/channel`, `GET /video/{idYoutubeWatch}`,
`GET /description/feed`, `GET /description/category` — só leitura pública/opcionalmente
autenticada, sem a parte de **personalização** que depende de saber quem está logado (isso é
Fase 4: JWT/Filters). A personalização mencionada no contrato (`x-auth: optional`) já está
com o comentário `// A partir da Fase 4` nos casos de aceite correspondentes — implementação
real fica pra lá.

## Achado 0 — os casos de aceite herdados da Fase 0 estavam errados pra esta stack

Antes de implementar, achei um problema em `specs/acceptance/*.http`: os 9 arquivos desta
fase ainda carregavam pressupostos do **design DynamoDB** do projeto irmão — `channel._id`
como slug (não aqui: MySQL tem PK numérica nativa, decisão já registrada em
`fase-1-data-model.md`), referências a `specs/fase-3-endpoints-leitura.md`/dump real que só
existem em `../../serverless`, e números de contagem amarrados a um dump de produção que nem
temos aqui (gitignored no projeto irmão por LGPD). Corrigidos todos os 9 arquivos antes de
implementar — spec/teste antes de código, não depois.

## Fixture sintética (sem dado real)

Sem o dump real disponível, criei uma fixture sintética e determinística —
`app/Database/Seeds/Acceptance*.php` (`AcceptanceSeeder` chama os outros 4 em ordem):

| Seeder | Conteúdo |
|---|---|
| `AcceptanceDevSeeder` | 35 devs (`dev01`..`dev35`) — testa paginação de `GET /devs` em 2 páginas |
| `AcceptanceChannelSeeder` | 2 canais ("Canal Alpha", "Canal Beta") + 3 tags via `channel_tag` |
| `AcceptanceVideoSeeder` | 20 vídeos em Alpha + 35 em Beta (55 total) — testa paginação de `feed/trending` e `feed/channel` |
| `AcceptanceReactionSeeder` | `dev01` com 1 like, 1 dislike, 1 follow, 1 ignore — testa que `GET /devs/dev01` devolve arrays não-vazios |

Rodar: `php spark migrate --all && php spark db:seed AcceptanceSeeder` num banco vazio.

## Implementação

- **Models**: `DevModel`, `ChannelModel` (+ `tagsFor()`, `findByNameOrLink()`), `VideoModel`
  (+ `trendingQuery()`, `byChannelQuery()`, `findByYoutubeId()` — todos com `JOIN channels`
  pra reconstruir `channel`/`channel_url`), `DevReactionModel`, `ChannelReactionModel` (+
  `targetIdsFor()`, usado pra montar `likes`/`deslikes`/`follow`/`ignore`).
- **Controllers**: `DevController`, `ChannelController`, `VideoController`,
  `DescriptionController` — cada um com um método `present()` privado mapeando a linha do
  banco pro shape público exato de `fase-0-openapi.yaml` (`_id`, `user`/`username`, etc.).
- **Helper** `to_iso8601()` (`app/Helpers/format_helper.php`, autoload global) — formata
  `DATETIME` do MySQL como ISO 8601 pros campos `createdAt`/`updatedAt`/`date`.
- **Rotas**: grupo `v1` em `app/Config/Routes.php`.

## Achados reais (rodando de verdade, não previstos em nenhuma doc lida antes)

### 1. Tipo inconsistente em `likes`/`follow`/etc — MySQLi devolve string

`array_column()` sobre resultado do MySQLi devolve os ids como **string**, não int — mesmo a
coluna sendo `BIGINT`. Como `_id` do Dev/Channel é serializado como int, e
`devfinder-next` compara `user.follow.includes(channel._id)` (`===` estrito em JS), a
inconsistência de tipo quebraria a comparação silenciosamente. Corrigido com
`array_map('intval', ...)` em `DevReactionModel::targetIdsFor()` /
`ChannelReactionModel::targetIdsFor()`.

### 2. `(:any)` não cruza `/` por padrão no CI4 4.7

Rota `channels/(:any)` só capturava até o primeiro `/` decodificado — a doc oficial descreve
`(:any)` como "qualquer caractere até o fim da URI, incluindo múltiplos segmentos", mas isso
mudou: desde a 4.5.0 existe `Config\Routing::$multipleSegmentsOneParam` (default `false`) que
precisa ser ligado pra esse comportamento antigo voltar. Em vez de ligar essa flag global
(afetaria todas as rotas do projeto), usei regex bruta (`channels/(.*)`) só nessa rota
específica.

### 3. `%2F` no path não sobrevive como parte de um parâmetro, mesmo com regex bruta

Mesmo com `(.*)`, tentar passar um link inteiro (`https%3A%2F%2Fyoutube.com%2Fbeta`) como
segmento de path chegou truncado (`https:`) no controller. Isolado: o CI4 decodifica o path
**antes** de rodar o matching de rota, e o `Router` reconstrói a lista de segmentos a partir
do path já decodificado — nesse ponto, `%2F` e `/` literal já são indistinguíveis. Diferença
de plataforma vs. o Express original (decodifica só depois de capturar o parâmetro).
**Não é um bug a corrigir**: confirmado lendo `devfinder-next/src/pages/channel/[slug].tsx`
que o frontend real **nunca** chama `GET /channels/{searchQuery}` com um link, só com
`channel.name` exato (vem de `router.query.slug`, que vem de um link já montado com o nome).
`ChannelModel::findByNameOrLink()` continua casando por `link` na query SQL — só não é
exercitável via este path específico, documentado em `specs/acceptance/channel-show.http`.

### 4. `Pager` do CI4 faz *clamp* pra última página, não devolve vazio além do fim

`?page=99` com só 2 páginas de dado devolveu os mesmos itens da página 2, não um array vazio
— comportamento documentado desde o CHANGELOG 4.0.3 do framework ("correctly handle cases
where the open page exceeds the page count, defaulting to the last page"), não é bug.
**Decisão**: manter o comportamento nativo (prioridade "seguir os padrões do CodeIgniter") —
a UI real (`devfinder-next/src/components/Paginate`) nunca constrói link pra uma página além
do total calculado, então esse caso só existe pra edição manual de URL.

### 5. Debug Toolbar injeta `<script>` em toda resposta `text/html`

`GET /description/feed`/`category` devolvem texto puro pensado pra colar numa descrição do
YouTube — o Toolbar (ligado por padrão em `ENVIRONMENT=development`) decorava o corpo com
script de debug, corrompendo o contrato. Corrigido movendo `toolbar` de
`Filters::$required['after']` pra `Filters::$globals['after']` com
`'except' => ['v1/description/*']` — padrão documentado oficialmente pra excluir o Toolbar de
rotas de API (`$this->assertNotFilter('api/v1/widgets', 'after', 'toolbar')` no guia de
testes do CI4).

## Validação — execução real dos `.http`, não só `curl` solto

Primeira rodada foi só `curl` ad hoc pra checar rápido — sem registro persistido, uma lacuna
de rigor apontada em revisão humana. Corrigido: os 9 arquivos `.http` foram executados de
verdade (`npx httpyac send *.http --env local --all`), saída completa salva em
[`acceptance/execucao-fase-3.log`](./acceptance/execucao-fase-3.log). **19/19 requests
executáveis retornaram HTTP 200** (as seções sem request no `.http` são notas/casos adiados
pra Fase 4 — não contam). Cobre paginação (`/devs`, `/feed/trending`, `/feed/channel`),
lookup exato (`/devs/{username}`, `/channels/{searchQuery}`, `/video/{id}`) com `null` pra
não-encontrado, canal inexistente em `/feed/channel` sem erro, `/description/*` com texto
limpo (sem o Debug Toolbar, achado 5 abaixo).

**PHPUnit é escopo da Fase 7, não desta fase** — registrado aqui de propósito pra não virar
dúvida recorrente: `plan.md`, Fase 7 ("Observabilidade, testes e corte"), é quem introduz a
suíte CIUnit/`FeatureTestTrait` rodando no CI a cada PR. Até lá, `execucao-fase-3.log` é a
evidência que existe — uma rodada manual pontual, real (não uma alegação em prosa), mas
**não** uma regressão automática: se algo quebrar depois desta fase, nada aqui detecta
sozinho. Mesma régua registrada em `../CLAUDE.md`, "Regra do projeto".

## Revisão humana — 2026-08-24

Verificado pelo usuário: a existência de `acceptance/execucao-fase-3.log` como evidência real
(saída de `httpyac`, não `curl` solto/alegação em prosa) — ver pergunta "onde registrou
chamada das request? no phpunit? em arquivos .http?" e a correção que ela gerou. Ponto
confirmado: o `.http` rodado de verdade é o padrão daqui pra frente pra qualquer fase que
tenha caso de aceite — não repetir a lacuna de só narrar "validado manualmente" sem log.

## Critério de aceite da Fase 3 (de `../plan.md`)

- [x] Casos de aceite escritos/corrigidos em `specs/acceptance/` antes da implementação.
- [x] Todos os 9 endpoints implementados e validados contra o ambiente local (Docker
  Compose) — execução real dos `.http` (não só `curl` solto), log em
  `acceptance/execucao-fase-3.log`: 19/19 requests com HTTP 200. **Existência do log
  verificada por revisão humana** (ver seção acima).
- [ ] Validados contra o deploy real — não aplicável ainda (host real não provisionado, ver
  `specs/infra-pending.md`).
- [x] Revisão humana (do processo de validação — ver seção acima). Revisão do código/design
  em si continua em aberto.

## Próximo passo

Fase 4 (Autenticação — GitHub OAuth + JWT via Filters CI4), que desbloqueia a personalização
já prevista no contrato (`x-auth: optional`) para `/devs`, `/feed/trending`, e o restante dos
endpoints ainda não implementados (`/feed/subscriptions`, `/me`, `likes`/`dislikes`).
