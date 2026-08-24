# Specs — status

Esta pasta é o destino dos artefatos de especificação do plano em [`../plan.md`](../plan.md),
e também guarda o registro detalhado (plano/execução) de cada fase — mesmo padrão adotado em
`../../serverless/specs/`.

**Não fazem parte do plano de fases** (cadernos de decisão, não artefatos a serem seguidos por
uma sessão futura como se fossem spec de fase):
[`infra-pending.md`](./infra-pending.md) — hospedagem real sempre-gratuita (duas opções
levantadas na Fase 2, decisão de qual usar e provisionamento adiados por escolha do usuário),
cobertura do Context7/DeepWiki para a stack (e por que Context7 não serve para Docker), e a
skill de agente `yasserstudio/codeigniter-skills` instalada na Fase 0.

Existem também notas pessoais locais não versionadas (padrão `.gitignore`: `**/*__.*`) —
runbook humano de git/PR por fase e afins. Não citadas aqui por não fazerem parte do
repositório.

## Status das fases (visão geral)

| Fase | Nome | Status |
|---|---|---|
| 0 | Especificação (contrato + ferramentas de contexto) | ✅ encerrada — revisada 2026-08-24 |
| 1 | Especificação de dados (MySQL relacional) | ✅ encerrada — mergeada 2026-08-24 (PR #2) |
| 2 | Infraestrutura base (scaffold + Docker + deploy real) | ✅ mergeada (PR #3) — host real ainda adiado (decisão do usuário) |
| 3 | Migração dos endpoints públicos de leitura | ✅ mergeada (PR #4) |
| 4 | Autenticação (GitHub OAuth + JWT via Filters) | ✅ gerada 2026-08-24 — validada contra Docker Compose local, pendente PR |
| 5 | Endpoints autenticados de escrita e relacionamento | ✅ gerada 2026-08-24 — 25/25 requests validadas, pendente PR |
| 6 | Ingestão em lote (`spark` command + cron) | ✅ gerada 2026-08-24 — escopo vídeo apenas (canal descoped, decisão do usuário), testada contra o bin real de produção, pendente PR |
| 7 | Observabilidade, testes e corte (cutover) | ⬜ não iniciada |

Fase 0 (PR #1), Fase 1 (PR #2) e Fase 2 (PR #3) encerradas e mergeadas em 2026-08-24. Fase 3
(endpoints públicos de leitura — 9 rotas, ver `fase-3-endpoints-leitura.md`) executada no
mesmo dia: corrigidos os 9 casos de aceite herdados da Fase 0 (carregavam pressupostos do
design DynamoDB do projeto irmão), criada fixture sintética de seed
(`app/Database/Seeds/Acceptance*.php`), implementados Models/Controllers/rotas, e validados
manualmente todos os 9 endpoints contra o Docker Compose local. 5 achados reais registrados
(tipo string vs int em MySQLi, `(:any)` não cruzando `/`, `%2F` não sobrevivendo em path,
`Pager` fazendo clamp de página, Debug Toolbar poluindo resposta `text/html`) — mais 2 achados
de CI corrigidos depois do merge (`CI_ENVIRONMENT=testing` quebrando `spark migrate`, PCOV
ausente fazendo `phpunit` sair com exit 1 mesmo passando), ver `CLAUDE.md`.

Fase 4 (Autenticação — GitHub OAuth + JWT via CI4 Filters, ver
`fase-4-endpoints-auth.md`) gerada no mesmo dia: `RequiredAuthFilter`/`OptionalAuthFilter`,
`DevModel::findOrCreate`, personalização reativada em `GET /devs`/`GET /feed/trending`.
Validado com JWTs sintéticos e, depois, com login real: usuário cadastrou um GitHub OAuth App
de verdade, completou o fluxo no navegador, `GET /me` confirmado com dado real do GitHub
(nome, avatar) — ver `fase-4-endpoints-auth.md`, "Verificação humana".

Fase 5 (endpoints autenticados de escrita — `POST /devs`, `/channels`, `/video`, os 4 pares
like/dislike/follow/ignore, ver `fase-5-escrita-relacionamentos.md`) gerada no mesmo dia: 13
endpoints, 25/25 requests validadas de verdade (`execucao-fase-5.log`). 3 bugs reais
encontrados rodando (não só lendo código): `*/g` num comentário PHPDoc quebrando a sintaxe
PHP de verdade, `find(false)` do CI4 devolvendo a tabela inteira em vez de nada, e o
placeholder `{id}` de `is_unique` no `update()` só resolvendo a partir dos dados passados, não
do argumento `$id`. Host real provisionado continua adiado (decisão do usuário — opções em
`infra-pending.md`).

Fase 6 (ingestão em lote — só escopo de vídeo, canal descoped por decisão do usuário, mesma
decisão já tomada em `../serverless`, ver `fase-6-ingestao-lote.md`) gerada no mesmo dia:
`App\Libraries\VideoIngestor` compartilhado entre `php spark video:refresh` (cron) e
`POST /video/refresh` (gatilho manual) desde o início, sem duplicação. Testado contra o bin
real do JSONBin.io (mesmo usado em produção pelo `devfinder-api` original):
`candidatos=50 added=20 duplicated=30 errors=0` — números idênticos aos que `../serverless`
registrou rodando na AWS real, evidência de paridade comportamental entre as duas stacks. 1
achado real do framework (prefixo de `.env` do `Config\BaseConfig` é o nome da classe todo em
minúsculas, não camelCase — `videorefresh.*`, não `videoRefresh.*`) + reconfirmação do achado
de nome de campo (`channel_name` do bin vs. `channel` do contrato HTTP) já visto no projeto
irmão. Host real continua adiado (`infra-pending.md`); runbook de cron documentado pra quando
existir. Próximo passo: Fase 7 (observabilidade + cutover).

## Artefatos gerados

| Artefato | Status |
|---|---|
| `infra-pending.md` | ✅ escrito 2026-08-23, atualizado 2026-08-24 — hospedagem real ainda em aberto (decisão adiada pra Fase 2, referência anterior a um provedor específico removida por não se aplicar a este projeto), cobertura do Context7/DeepWiki por repositório da stack, e a skill `yasserstudio/codeigniter-skills`. |
| `fase-0-openapi.yaml` | ✅ gerado e revisado 2026-08-24 (Fase 0) — adaptado de `../../serverless/specs/fase-0-openapi.yaml`, 30 operações, cobertura completa. |
| `fase-0-especificacao.md` | ✅ registro de execução da Fase 0, **encerrada e revisada** 2026-08-24 — adaptações feitas, Context7 MCP instalado e aprovado (`.mcp.json`, `resolve-library-id` testado), skill `codeigniter` instalada, decisões em aberto documentadas. |
| `acceptance/*.http` | ✅ gerado 2026-08-24 (Fase 0), **9 arquivos corrigidos na Fase 3** — os herdados de `../../serverless/specs/acceptance/` carregavam pressupostos do design DynamoDB (slug como `_id`) que não se aplicam ao schema MySQL desta stack; corrigidos contra fixture sintética própria (`app/Database/Seeds/Acceptance*.php`), sem depender do dump real do projeto irmão. Casos que dependem de auth só executáveis a partir da Fase 4. |
| `fase-1-data-model.md` | ✅ gerado, revisado e **validado contra MySQL real na Fase 2** (2026-08-24) — 7 tabelas relacionais (`devs`, `channels`, `tags`, `channel_tag`, `videos`, `dev_reactions`, `channel_reactions`), evidência do dump real do projeto irmão (colisão de `channels.name`, campo vestigial `alternativeLink`, extração de `youtube_id`), migrations CI4 completas com soft delete + `UNIQUE` em `channels.name`/`link`, cobertura de todas as 30 operações do OpenAPI. |
| `fase-2-scaffold-infra.md` | ✅ gerado 2026-08-24 (Fase 2) — scaffold CI4 na raiz (não `api/`), Docker Compose (PHP-FPM 8.3 + Nginx + MySQL 8.4) validado de ponta a ponta, CI (GitHub Actions), achado real de MySQL/InnoDB (CHECK + ON UPDATE CASCADE incompatíveis), pesquisa de hospedagem sempre-gratuita (decisão adiada). |
| `fase-3-endpoints-leitura.md` | ✅ gerado 2026-08-24 (Fase 3) — 9 endpoints públicos de leitura implementados (Models/Controllers/rotas), fixture sintética de seed, 5 achados reais do CI4 (tipo string vs int no MySQLi, `(:any)` não cruzando `/`, `%2F` em path, clamp de página do `Pager`, Debug Toolbar em resposta `text/html`), validado manualmente contra Docker Compose local. |
| `fase-4-endpoints-auth.md` | ✅ gerado e **fluxo OAuth real confirmado** 2026-08-24 (Fase 4) — GitHub OAuth + JWT (`firebase/php-jwt`) via `RequiredAuthFilter`/`OptionalAuthFilter`, `DevModel::findOrCreate`, personalização reativada em `/devs`/`/feed/trending`. Decisões reaproveitadas do projeto irmão (payload `{username}`, `?user=` preservado) + 4 achados novos (prefixo `hex2bin:` não é genérico, Filters do CI4 sem o problema de `identitySource` do API Gateway, divergência 401 vs 400 em `/me`, scope do GitHub OAuth corrigido pra paridade). Validado com JWT sintético **e** login real no GitHub (usuário cadastrou OAuth App, `GET /me` retornou dado real). |
| `fase-5-escrita-relacionamentos.md` | ✅ gerado 2026-08-24 (Fase 5) — 13 endpoints de escrita (`POST /devs`, `/channels`, `/video`, 4 pares like/dislike/follow/ignore + 2 GET de listagem), 2 bugs reais corrigidos no design (Dev.create sem username no original, AddChannel incompleto no OpenAPI) + 3 bugs reais do framework encontrados rodando (`*/g` em PHPDoc, `find(false)` devolvendo tabela inteira, placeholder `{id}` de `is_unique`). 25/25 requests validadas via `httpyac` contra banco limpo. |
| `fase-6-ingestao-lote.md` | ✅ gerado 2026-08-24 (Fase 6) — escopo vídeo apenas (canal descoped, decisão do usuário), `App\Libraries\VideoIngestor` compartilhado entre `php spark video:refresh` (cron) e `POST /video/refresh` (HTTP), testado contra o bin real do JSONBin.io (`candidatos=50 added=20 duplicated=30 errors=0`, mesmos números da AWS real do projeto irmão), 1 achado real do framework (prefixo `.env` do `Config\BaseConfig` em minúsculas). |
