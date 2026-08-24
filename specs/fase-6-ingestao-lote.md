# Fase 6 — Ingestão em lote (vídeo)

> Fase 6 de [`../plan.md`](../plan.md), seção "7. Fase 6". Depende da Fase 5 (PR #6,
> mergeado) — reaproveita `RequiredAuthFilter`, `ChannelModel::findForVideoLink`,
> `VideoModel::{extractYoutubeId,stripTrackingParam}`, todos já existentes desde a Fase 5.

## Escopo — decisão do usuário, 2026-08-24

`plan.md` cita os dois `*RefreshController` do `devfinder-api` original (`Video` e
`Channel`). O `fase-0-openapi.yaml` já deixava `/channels/refresh` marcado como "ainda sem
plano de execução — decidir na Fase 6". Perguntado ao usuário no início desta execução:
**escopo fechado em vídeo apenas**, mesma decisão já tomada no projeto irmão `../serverless`
(`specs/fase-6-ingestao-lote.md` de lá) — `ChannelRefreshController` (que dependeria de
`APP_APIPLACEHOLDER_URL`, um serviço de scraping de terceiros ainda não validado como
operacional) fica descoped, não é pendência, é escopo fechado conscientemente.

## O que foi implementado

Dois entrypoints, um único serviço de ingestão compartilhado — decisão desde o início (não
uma refatoração posterior, diferente do que `../serverless` precisou fazer, ver
`fase-6-ingestao-lote.md` de lá, "Estrutura de código proposta"):

- **`App\Libraries\VideoIngestor::ingest(array $candidates)`** — porta de
  `VideoRefreshController.addVideo` (`../../devfinder-api`): resolve o canal por
  `name`/`link`/`alternative_link` (`ChannelModel::findForVideoLink`, já existente da Fase
  5), dedup por `url` exata (`VideoModel::findByExactUrl`), remove parâmetro de tracking
  (`&pp=`), corrige thumbnail `hq720_custom_N` quebrada (`VideoModel::resolveThumbnail`,
  nova), insere o vídeo. Devolve `{videosAdded, videosFounded, errors}` com as linhas
  completas (não só contadores) — cada chamador decide como serializar/logar.
- **`App\Commands\VideoRefresh`** (`php spark video:refresh`) — porta de
  `devfinder-api/src/task.ts`: busca candidatos num bin real do JSONBin.io
  (`videorefresh.jsonbinApiKey`/`jsonbinIdSubs`, `.env`), mapeia `channel_name` → `channel`
  (ver achado abaixo), chama `VideoIngestor::ingest()`, loga o resumo
  (`Adicionados: N | Já existiam: N | Erros: N`). Sem credenciais configuradas, roda como
  no-op explícito (não erro). Agendado via **cron nativo da VM do deploy real** — substitui
  `video-refresh.yml` (GitHub Actions) do original, mesma decisão já registrada em
  `plan.md`, Fase 6 (sem serviço gerenciado externo a manter).
- **`POST /video/refresh`** (`VideoController::refresh()`, `RequiredAuthFilter`) — mesmo
  `VideoIngestor`, candidatos vêm de `{ record: AddVideo[] }` no body (contrato já existente
  em `fase-0-openapi.yaml`), resposta serializada com o mesmo `present()` já usado por
  `GET /video/{id}`/`POST /video`. Existe por paridade de contrato + gatilho manual opcional
  (reprocessar um lote ad-hoc sem esperar o cron) — o cron continua sendo o mecanismo real de
  automação, mesma priorização já adotada em `../serverless/specs/fase-6-ingestao-lote.md`,
  "Prioridade: o agendamento continua sendo o mecanismo de automação".
- **`Config\VideoRefresh`** — `jsonbinApiKey`/`jsonbinIdSubs`, populados via `.env`
  (`videorefresh.jsonbinApiKey`/`jsonbinIdSubs` — ver achado do prefixo abaixo).

## Achados reais (rodando contra o bin real, não só lendo código)

1. **Prefixo de `.env` do `Config\BaseConfig` é o nome da classe todo em minúsculas, não
   camelCase.** `Config\VideoRefresh` gera prefixo `videorefresh` (via
   `strtolower(substr(...))` em `BaseConfig::init()`), não `videoRefresh`. Escrevi a chave
   errada (`videoRefresh.jsonbinApiKey`) primeiro, o comando silenciosamente tratava como "não
   configurado" (comportamento correto para esse caso, mas mascarou o erro de digitação — só
   percebido rodando de verdade e vendo `Config\VideoRefresh::$jsonbinApiKey` continuar vazio
   apesar do `.env` preenchido). Corrigido para `videorefresh.*` nos dois `.env`/`.env.example`.
   Contraste com `auth.jwtSecret` (Fase 4): funciona por coincidência — `Auth` é uma palavra
   só, minúsculo não muda nada.
2. **Campo `channel_name` vs. `channel`** — mesmo achado já documentado em
   `../serverless/specs/fase-6-ingestao-lote.md` ("Descoberta que orienta o desenho"), se
   repete aqui: o bin real do JSONBin usa `channel_name`, o contrato HTTP (`AddVideo`,
   `fase-0-openapi.yaml`) usa `channel`. Resolvido do mesmo jeito: `VideoIngestor::ingest()`
   só entende o shape neutro (`channel`), cada entrypoint mapeia seu formato de entrada antes
   de chamar o serviço — `App\Commands\VideoRefresh` mapeia `channel_name` → `channel`,
   `VideoController::refresh()` já recebe `channel` direto do body.
3. **Números batem com produção real do projeto irmão**: primeira rodada contra o bin real
   (banco recém-seedado com os 500 vídeos do dump, `specs/seed/`) devolveu
   `candidatos=50 added=20 duplicated=30 errors=0` — os *mesmos* números que
   `../serverless/specs/fase-6-ingestao-lote.md` registrou rodando contra a AWS real (item 4:
   "candidatos=50 added=20 duplicated=30"). Não é coincidência de design, é o mesmo bin real
   e o mesmo dump de vídeos preexistentes nas duas stacks — evidência forte de paridade
   comportamental entre as duas implementações independentes do mesmo domínio.

## Runbook — cron na VM do deploy real

Decisão adiada pra quando o host real for provisionado (`specs/infra-pending.md`) — registrado
aqui o comando exato pra quando isso acontecer, mesmo padrão de runbook humano já usado no
projeto irmão:

```cron
# A cada 12h (paridade com o `cron: '0 */12 * * *'` do video-refresh.yml original e o
# `schedule: rate(12 hours)` do ../serverless) — ajustar caminho depois do deploy real.
0 */12 * * * cd /caminho/do/deploy && php spark video:refresh >> /var/log/devfinder-video-refresh.log 2>&1
```

## Critério de aceite (`plan.md`, Fase 6)

- [x] `videosAdded`/`videosFounded`/`errors` no mesmo shape do `VideoRefreshController`
  original, confirmado tanto via `php spark video:refresh` (log) quanto via
  `POST /video/refresh` (JSON) — ver
  [`acceptance/execucao-fase-6.log`](./acceptance/execucao-fase-6.log).
- [x] Testado local (Docker Compose) contra o bin real de produção — host real de deploy
  continua adiado (`specs/infra-pending.md`), runbook de cron documentado acima pra quando
  existir.
- [x] Nenhuma duplicação de lógica entre o Command e a rota HTTP — os dois só chamam
  `VideoIngestor::ingest()`.
