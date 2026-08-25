# Plano de Implementação (Spec-Driven Design) — DevFinder API CodeIgniter

> Replicação de `devfinder-api` (Express + MongoDB) para um monolito **PHP / CodeIgniter 4**
> (MVC + Query Builder sobre MySQL/MariaDB), com Docker Compose para desenvolvimento local e
> deploy real em host sempre-gratuito (ver `CLAUDE.md`, "Regra de custo").
>
> Metodologia: **spec-driven design**, igual ao projeto irmão `../serverless` — cada fase
> começa escrevendo a especificação antes de qualquer código, a spec vira a fonte de verdade
> para gerar testes e implementação, e nenhuma fase avança sem a spec anterior aprovada e
> validada por teste. **Diferença deliberada em relação a `../serverless`**: aqui cada fase é
> isolada num **Pull Request** (ver "Metodologia" abaixo), não implementada direto em `main`.

## Layout do workspace

Este plano vive em `./php-codei/` (ou, em caminho absoluto,
`/home/marcelo/Desktop/coding/published/php-codei/`). Todos os repositórios referenciados
neste documento são **diretórios irmãos** de `./php-codei`, não filhos dele:

```
/home/marcelo/Desktop/coding/published/
├── php-codei/        ← você está aqui (portfolio.md, plan.md, specs/)
├── devfinder-api/     ← fonte da replicação (Express + MongoDB)
├── devfinder-next/    ← frontend consumidor (Next.js) — contrato público não pode quebrar
└── serverless/        ← projeto irmão, mesmo domínio em AWS Serverless — não alterar
```

Ou seja, qualquer caminho citado abaixo como `devfinder-api/src/...` deve ser lido como
`../devfinder-api/src/...` a partir de dentro de `./php-codei`, ou pelo caminho absoluto acima.

**Repositório remoto**: `git@github.com:MarceloVilela/devfinder-codeigniter.git` (privado, já
criado). Git local ainda não inicializado neste diretório — ver `CLAUDE.md`, "Repositório
remoto". Os branches/PRs de "Metodologia" abaixo são contra esse remote, a partir do momento
em que a Fase 0 começar a ser executada.

## Metodologia — spec-driven + PR por fase

Cada fase segue sempre a mesma sequência:

1. **Spec**: escrever/atualizar `specs/fase-N-nome.md` (ou `.yaml` para o contrato) descrevendo
   o comportamento esperado antes de qualquer código.
2. **Branch**: criar `fase-N-nome` a partir de `main`.
3. **Implementar**: código + testes até o critério de aceite da fase bater — nesta ordem
   (spec → teste → implementação), nunca implementação improvisando o que a spec deixou em
   aberto.
4. **Pull Request**: abrir PR `fase-N-nome → main`, descrição linkando a spec correspondente e
   listando o que o critério de aceite verificou. **Só é aberto quando o critério de aceite já
   bate localmente** — o PR é o registro da fase pronta, não um rascunho em progresso.
5. **Merge e avanço**: a fase seguinte só começa depois do PR mergeado (revisão humana, mesmo
   que rápida). Isso é o que garante o histórico granular por fase — cada PR do GitHub vira,
   sozinho, uma peça de portfólio revisável ("aqui está o PR que fecha a Fase 3").

CI mínimo (GitHub Actions, criado na Fase 2): a cada PR, rodar `php spark migrate` contra um
MySQL de serviço + suíte de testes (PHPUnit/CIUnit). PR não é mergeável com CI vermelho.

## 0. Objetivo e critérios de sucesso

**Objetivo**: ter a API do DevFinder rodando em CodeIgniter 4 + MySQL, com paridade funcional
com `devfinder-api` atual, consumível pelo mesmo frontend (`devfinder-next`) sem alterações no
contrato público, com deploy real fora do Docker local.

**Critérios de sucesso**:
- Todos os endpoints listados em `portfolio.md` respondem com o mesmo shape de request/response
  hoje consumido por `devfinder-next`.
- `devfinder-next` funcionaria apontando para a nova API sem alterar código do frontend —
  critério de **compatibilidade de contrato**, verificado byte a byte contra
  `fase-0-openapi.yaml`, não um cutover de fato (ver Fase 7, "decisão de escopo": o frontend
  não é alterado nem apontado para esta API, decisão do usuário de 2026-08-24 — este projeto é
  portfólio de backend, mesmo objetivo já adotado em `../serverless`).
- Ambiente local via Docker Compose (PHP-FPM + Nginx + MySQL) é o mesmo conjunto de containers
  usado no deploy real — sem divergência entre "como roda aqui" e "como roda em produção".
- Deploy real dentro de camada genuinamente sempre-gratuita (ver `CLAUDE.md`, "Regra de
  custo", e `specs/infra-pending.md`).
- Cada fase corresponde a um PR mergeado, com CI verde, referenciando sua spec.

**Fora de escopo (v1)**: reescrever o frontend; apontar/alterar `devfinder-next` para esta API
(ver Fase 7); scraping automatizado de novas fontes; autenticação além de GitHub OAuth;
multi-região/alta disponibilidade.

---

## 1. Fase 0 — Especificação (contrato + ferramentas de contexto)

Antes de qualquer infraestrutura, produzir os artefatos de especificação e confirmar as
ferramentas de apoio de IA para CodeIgniter. Tudo em `./php-codei/specs/`.

| Artefato | Conteúdo | Fonte |
|---|---|---|
| `specs/fase-0-openapi.yaml` | Contrato OpenAPI completo dos endpoints | Adaptar de `../serverless/specs/fase-0-openapi.yaml` (mesmo domínio, já cobre as 30 operações) — não re-derivar do zero do Swagger de `devfinder-api`. Ajustar apenas o que muda por ser SQL (ex.: paginação por `page`/`limit` nativa em vez do cursor base64 do DynamoDB). |
| `specs/fase-1-data-model.md` | Modelagem relacional (tabelas + relações), ver Fase 1 | `devfinder-api/src/models/*.ts` |
| `specs/infra-pending.md` | Já existe (ver abaixo) — confirmar/atualizar a decisão de host real nesta fase, antes do scaffold da Fase 2 | — |
| `specs/acceptance/*.http` | Casos de teste por endpoint (request → response esperado) | `devfinder-api/client.http`, ou reaproveitar `../serverless/specs/acceptance/*.http` como ponto de partida |

**Ferramentas de contexto CodeIgniter** (ver `CLAUDE.md`, "Ferramentas de contexto
CodeIgniter"): nesta fase, rodar `resolve-library-id` no Context7 MCP para confirmar cobertura
real de CodeIgniter 4 (não assumir), e instalar `npx skills add
yasserstudio/codeigniter-skills`. Registrar o resultado em `specs/fase-0-especificacao.md`.

**Critério de aceite da Fase 0**: specs revisadas e aprovadas; toda rota de
`devfinder-api/src/routes/index.ts` tem entrada em `fase-0-openapi.yaml` e pelo menos um caso
de aceite; Context7/skill CodeIgniter avaliados e decisão registrada. PR `fase-0-especificacao
→ main` mergeado.

---

## 2. Fase 1 — Especificação de dados (MySQL relacional)

Mongo usa 3 coleções com referências (`ObjectId`) e arrays embutidos (`likes[]`,
`deslikes[]`, `follow[]`, `ignore[]`). MySQL normaliza isso em tabelas de junção — mais direto
de modelar do que o single-table DynamoDB da versão serverless, mas exige decidir chaves e
índices antes de implementar.

### Entidades e tabelas (proposta a validar na spec)

| Tabela | Colunas principais | Notas |
|---|---|---|
| `devs` | `id`, `username` (unique), `name`, `bio`, `avatar`, `created_at` | |
| `channels` | `id`, `name` (slug/unique), `link`, `category`, `avatar`, `created_at` | `tags` como tabela `channel_tags` (many-to-many) ou coluna JSON — decidir na spec |
| `videos` | `id`, `youtube_id` (unique), `title`, `url`, `channel_id` (FK), `thumbnail`, `date` | campos desnormalizados `channel`/`channel_url` do Mongoose viram JOIN, não coluna duplicada |
| `dev_dev_reactions` | `dev_id` (FK), `target_dev_id` (FK), `type` (`like`/`dislike`), chave composta única | substitui `likes[]`/`deslikes[]` de Dev |
| `dev_channel_reactions` | `dev_id` (FK), `channel_id` (FK), `type` (`follow`/`ignore`), chave composta única | substitui `follow[]`/`ignore[]` de Dev |

**Pontos a decidir na spec (não improvisar durante a implementação)**:
- Paginação: `LIMIT`/`OFFSET` nativo do SQL é suficiente (ao contrário do cursor exigido pelo
  DynamoDB da versão serverless) — mas confirmar contra o uso real de `devfinder-next`
  (mesma investigação que `../serverless/specs/fase-1-data-model.md` já fez, reaproveitar a
  evidência de lá em vez de re-investigar do zero).
- Busca textual (`SearchController`) — MySQL tem `FULLTEXT INDEX` nativo (ao contrário do
  DynamoDB); decidir se cobre o caso de uso ou se `LIKE`/`%termo%` já é suficiente na escala
  deste projeto.
- Contadores de likes/follows: `COUNT` via JOIN/subquery vs. coluna contador desnormalizada
  mantida por trigger/transação — decidir considerando volume real (baixo, não precisa
  otimizar prematuramente).

**Critério de aceite da Fase 1**: `specs/fase-1-data-model.md` aprovado, migrations CI4
(`php spark make:migration`) escritas para cada tabela, toda operação de leitura do OpenAPI
mapeada a uma query concreta sem N+1 não documentado. PR mergeado.

---

## 3. Fase 2 — Infraestrutura base (scaffold + Docker + deploy real)

- `composer create-project codeigniter4/appstarter` **na raiz de `./php-codei`**, não numa
  subpasta `api/` — decisão revista na execução da Fase 2: o próprio template
  `codeigniter4/appstarter` já vem com `app/`, `public/`, `tests/`, `writable/`,
  `composer.json` soltos na sua raiz (conferido em `codeigniter4/appstarter` real, via `gh
  api`), ou seja, é desenhado pra *virar* a raiz do projeto onde é instalado — não pra ocupar
  uma subpasta. Um `api/` extra (herdado sem verificação da estrutura do `../serverless`,
  onde faz sentido por outros motivos) seria menos idiomático aqui sem trazer benefício real.
- Docker Compose: PHP-FPM + Nginx + MySQL — **mesmo conjunto de containers usado no deploy
  real** (ver `specs/infra-pending.md`), não um ambiente local divergente.
- Migrations da Fase 1 aplicadas via `php spark migrate` dentro do container.
- CI (GitHub Actions): a cada PR, sobe MySQL de serviço, roda migrations + testes.
- Escolher e provisionar o host real (decisão ainda em aberto, ver `specs/infra-pending.md` —
  critério fixo é a Regra de custo do `CLAUDE.md`, o provedor específico é decidido nesta
  fase, não antes) — documentar os passos manuais de provisionamento (conta, VM/serviço, rede)
  num runbook, mesmo padrão de runbook humano já usado no projeto irmão `../serverless`.

**Critério de aceite**: `docker compose up` sobe API + MySQL localmente, migrations aplicadas,
endpoint de health-check responde; CI verde num PR de teste; host real provisionado (ou
runbook pronto, se a execução ficar para quando o usuário tiver a conta pronta). PR mergeado.

---

## 4. Fase 3 — Endpoints públicos de leitura

Ordem: começar pelo que não exige auth nem escrita, para validar o padrão
request→Controller→Model→response antes de lidar com autenticação.

Endpoints desta fase (Controller CodeIgniter por recurso, seguindo o roteamento de
`app/Config/Routes.php`):

`GET /devs`, `GET /devs/:username`, `GET /channels`, `GET /channels/:searchQuery`,
`GET /feed/trending`, `GET /feed/channel`, `GET /video/:idYoutubeWatch`,
`GET /description/feed`, `GET /description/category`.

Para cada endpoint: escrever/atualizar o caso de aceite em `specs/acceptance/`, então
implementar até o teste passar (spec → teste → implementação, nessa ordem).

**Critério de aceite**: todos os casos de aceite dessas rotas passam contra o ambiente local
(Docker Compose) e contra o deploy real. PR mergeado.

---

## 5. Fase 4 — Autenticação (GitHub OAuth + JWT)

- Fluxo GitHub OAuth (troca `code` por token do GitHub, busca perfil, upsert de `Dev`) —
  implementado direto (sem depender de um pacote de sessão), mesmo espírito do que
  `../serverless/specs/fase-4-auth.md` já validou para o fluxo real.
- Emissão de JWT (`firebase/php-jwt`, mesmo payload `{ username }` já adotado em
  `../serverless/specs/fase-4-auth.md`).
- **Filters** do CodeIgniter 4 (`app/Filters/`) substituindo `middlewares/auth.ts`:
  `RequiredAuthFilter` e `OptionalAuthFilter` (paridade: rota pública que personaliza
  resultado se houver token válido).
- Secret do JWT: variável de ambiente (`.env`, `app/Config/Boot/*.php`) — sem equivalente a
  Secrets Manager necessário aqui (sem custo fixo mensal a evitar, ao contrário do caso AWS).

**Critério de aceite**: login via GitHub end-to-end contra `devfinder-next` local, token
aceito pelo filtro, `GET /me` retorna o Dev autenticado. PR mergeado.

---

## 6. Fase 5 — Endpoints autenticados de escrita e relacionamento

`POST /devs`, `POST /channels`, `POST /video`, e os pares simétricos de
likes/dislikes/follow/ignore — implementar sempre os dois lados do par juntos, preservando a
simetria já documentada no `CLAUDE.md` do projeto original (`devfinder-api`).

Ao contrário do array mutado via `.push()`/`.splice()` no Mongoose (ou do item independente do
DynamoDB), aqui é `INSERT`/`DELETE` numa tabela de junção com chave composta única — mais
simples, mas atenção a idempotência (curtir duas vezes não deve duplicar linha nem estourar
erro de unicidade sem tratamento).

**Critério de aceite**: casos de aceite cobrindo criar → listar → remover para cada par
(like/dislike, follow/ignore) passam. PR mergeado.

---

## 7. Fase 6 — Ingestão em lote (`*RefreshController`, `task.ts`)

Reimplementar como **Command CodeIgniter** (`php spark`) agendado via `crontab` na VM do
deploy real — equivalente funcional ao EventBridge Scheduler da versão serverless, mas usando
o mecanismo de agendamento nativo do SO em vez de um serviço gerenciado (não há equivalente
"Always Free" a decidir aqui: cron na própria VM Always Free não tem custo adicional).
Especificar se mantém também a rota HTTP autenticada (`POST /channels/refresh`,
`POST /video/refresh`) como gatilho manual, mesmo padrão de paridade adotado em
`../serverless/specs/fase-6-ingestao-lote.md`.

**Critério de aceite**: `videosAdded`/`videosFounded`/`errors` no mesmo shape do
`VideoRefreshController` atual, tanto via `spark` local quanto via cron no deploy real. PR
mergeado.

---

## 8. Fase 7 — Observabilidade, testes e corte (cutover)

> **Atualização 2026-08-24 — decisão de escopo**: o item de apontar `devfinder-next` pra nova
> API (e qualquer cutover de fato) foi descartado por decisão do usuário — mesma decisão já
> tomada no projeto irmão `../serverless` (`ssr.md`, Fase 7: "o frontend fica intocado,
> apontando pra sempre pro `devfinder-api` original"). O objetivo deste projeto é portfólio de
> backend — demonstrar o domínio replicado num monolito CI4/MySQL funcional de verdade, não
> substituir a produção real do `devfinder-next`/`devfinder-api`. Isso não é uma pendência em
> aberto, é escopo fechado conscientemente: `devfinder-next` **não é alterado nem apontado**
> para esta API, nem em staging nem definitivamente.
>
> A vantagem que sobra é a mesma do projeto irmão: como o contrato público
> (`fase-0-openapi.yaml`) foi preservado byte a byte em relação ao `devfinder-api` original, o
> cutover **seria** possível a qualquer momento sem tocar uma linha do frontend — não precisar
> fazer é a prova da paridade, não a ausência dela.
>
> Só o primeiro bullet (observabilidade) e o segundo (testes CIUnit) seguem valendo como
> escopo real desta fase; testes de carga ficam opcionais (material de comparação de
> portfólio, não critério de aceite); o cutover propriamente dito está fora de escopo.

- [x] **7.1** Logs estruturados (JSON) via handler de log do CodeIgniter 4.
- [x] **7.2** Suíte de testes CIUnit cobrindo os casos de aceite críticos, rodando no CI a
  cada PR (não só na fase em que foram escritos).
- [x] **7.3** Testes de carga leves (`autocannon`) documentando latência real do monolito
  PHP — material de comparação de portfólio (opcional, não bloqueia o critério de aceite).

Execução dos 3 itens acima, com achados reais (N+1 em `GET /devs`, vazamento de
`AuthContext` entre requests simuladas do harness de teste): ver
[`fase-7-observabilidade-testes.md`](./fase-7-observabilidade-testes.md).

- **7.4** ~~Apontar `devfinder-next` (via variável de ambiente) para a nova API em staging,
  validar manualmente os fluxos críticos~~ — **confirmado fora de escopo pelo usuário em
  2026-08-24** (mesma decisão do projeto irmão `../serverless`, ver atualização acima).
- **7.5** Deploy real (host sempre-gratuito genuíno, ver `specs/infra-pending.md`) — **decisão
  do usuário em 2026-08-24**: quer o deploy real acontecer (a API rodando de fato fora do
  Docker local), mas **sem apontar `devfinder-next` pra ela** — mesma distinção já feita no
  item 7.4/na decisão de escopo acima: deploy real é demonstração funcional de portfólio, não
  vira produção do frontend. Escolha do provedor (Oracle Cloud Always Free vs. Google Cloud
  e2-micro Always Free, ver `specs/infra-pending.md`, item 1) **adiada pelo usuário** ("decido
  depois") — nenhuma execução/provisionamento ainda, só a intenção registrada aqui.

**Critério de aceite final (revisado)**: logs estruturados implementados; suíte CIUnit
cobrindo os casos de aceite críticos das Fases 3/5/6, rodando no CI a cada PR; todos os casos
de aceite de `specs/acceptance/` verdes contra o deploy real (quando o host real existir) ou
contra Docker Compose local (evidência já registrada nas Fases 3/5/6, ver
`specs/acceptance/execucao-fase-*.log`). O checklist de paridade funcional contra produção do
frontend real não se aplica (ver decisão de escopo acima). PR mergeado.

---

## Riscos e trade-offs a documentar durante a execução

- **Busca textual**: decidir entre `FULLTEXT INDEX` do MySQL e `LIKE` simples na Fase 1 — não
  adiar para durante a implementação.
- **Idempotência de like/follow**: `INSERT` numa tabela com chave única composta precisa de
  tratamento explícito de conflito (`INSERT ... ON DUPLICATE KEY` ou verificação prévia) —
  especificar antes de implementar a Fase 5.
- **Escolha do host real**: ainda em aberto, decisão adiada para a Fase 2 (ver
  `specs/infra-pending.md`) — não travar essa escolha durante o planejamento nem assumir um
  provedor específico nas fases anteriores.
- **Migração de dados**: script único de ETL Mongo → MySQL (fora do escopo deste plano de
  implementação de código, necessário antes do cutover — especificar à parte, mesma ressalva
  já feita em `../serverless/ssr.md`).

## Ordem de execução resumida

```
Fase 0 (specs + ferramentas CodeIgniter) → Fase 1 (data model) → Fase 2 (scaffold + Docker + deploy real)
   → Fase 3 (leitura pública) → Fase 4 (auth) → Fase 5 (escrita/relacionamentos)
   → Fase 6 (ingestão em lote) → Fase 7 (observabilidade + cutover)
```

Cada seta só é cruzada com a spec da fase seguinte escrita e revisada, **e** o PR da fase
anterior mergeado — é o núcleo do spec-driven design com PR por fase aplicado aqui: a spec é o
artefato que se aprova, o PR é o artefato que se revisa, o código é a consequência de ambos.
