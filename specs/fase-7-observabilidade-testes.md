# Fase 7 — Observabilidade e testes (itens 7.1, 7.2, 7.3)

> Fase 7 de [`../plan.md`](../plan.md), seção "8. Fase 7" — ver lá a "decisão de escopo"
> 2026-08-24 (cutover contra `devfinder-next` descartado, mesma decisão do projeto irmão
> `../serverless`). Este documento cobre os 3 itens que seguem valendo: **7.1** logs
> estruturados, **7.2** suíte CIUnit no CI, **7.3** testes de carga leves. O corte/promoção a
> host real fica fora deste documento (fora de escopo desta fase, ver `plan.md`).

## 7.1 — Logs estruturados (JSON)

`App\Libraries\Log\JsonFileHandler` (implementa `HandlerInterface` do CI4, mesmo padrão do
`FileHandler` padrão que substitui) grava 1 objeto JSON por linha
(`writable/logs/log-{Y-m-d}.json.log`) em vez do texto livre do handler padrão — parseável por
qualquer agregador de log (CloudWatch/Loki/ELK) sem regex. Registrado em `app/Config/Logger.php`
no lugar do `FileHandler`.

`$message` já chega interpolado (placeholders `{chave}` do PSR-3 substituídos por
`Logger::interpolate()` antes de qualquer handler rodar) — o campo "estruturado" é o envelope
(`timestamp`/`level`/`message`), não um contexto chave-valor à parte; suficiente pro objetivo
desta fase (log agregável), sem reescrever o sistema de log do framework.

**Verificado rodando de verdade**: uma exceção real disparada durante o desenvolvimento (erro
de unicidade do MySQL) foi capturada e gravada como uma única linha JSON válida
(`json_decode()` sem erro), com `timestamp`/`level`/`message` preenchidos corretamente.

## 7.2 — Suíte de testes CIUnit no CI

30 testes de feature (`tests/feature/*.php`), HTTP real via `FeatureTestTrait` contra o banco
real (`database.tests`, MySQL) via `DatabaseTestTrait`, cobrindo os casos de aceite críticos
já validados manualmente nas Fases 3/5/6 (`specs/acceptance/execucao-fase-{3,5,6}.log`) — a
mesma fixture sintética (`AcceptanceSeeder`) é reaproveitada, não duplicada:

| Arquivo | Cobertura |
|---|---|
| `DevsTest.php` | `GET /devs` (paginação), `GET /devs/{username}` (encontrado/null), `POST /devs` (401 sem token, idempotente) |
| `ChannelsTest.php` | `GET /channels` (com tags), `GET /channels/{searchQuery}` (por nome/não encontrado), `POST /channels` (401, criar, atualizar por dedup) |
| `VideosTest.php` | `GET /video/{id}`, `GET /feed/trending`/`feed/channel` (paginação), `POST /video` (401, 409 duplicado, 201 novo) |
| `ReactionsTest.php` | like/follow idempotentes + DELETE, auto-like vira no-op (`CHECK` da Fase 1), alvo inexistente → 400 |
| `VideoRefreshTest.php` | `POST /video/refresh` (401, record vazio, lote misto novo/duplicado/canal inexistente) |

`tests/_support/FeatureTestCase.php` é a base comum: `$namespace = null` (migra **todas** as
namespaces antes de cada teste — sem isso só o `example_migration` do scaffold rodaria contra
`database.tests`, não as 7 migrations reais de `App\Database\Migrations`), `$seed =
AcceptanceSeeder::class`.

**Caminhos com dependência de rede externa real (GitHub API, JSONBin.io) não entram no CI** —
mesmo critério já usado nos casos de aceite manuais: `POST /devs` com username novo (busca
perfil no GitHub), `POST /channels` com `userGithub` preenchido, e a busca real no JSONBin.io
do `video:refresh` já foram validados manualmente (`execucao-fase-5.log`,
`execucao-fase-6.log|.md`) e não são repetidos aqui — dependeriam de rede/credenciais reais
dentro do CI, frágil e fora do que "caso de aceite crítico" pede.

### Achado real — `AuthContext` vaza entre requests simuladas do `FeatureTestTrait`

Não previsível só lendo código — só apareceu rodando a suíte completa pela primeira vez:
`GET /devs` sem token devolvia `total=32` em vez de 35; `GET /feed/trending` devolvia
`total=20` em vez de 55.

Causa: `App\Libraries\AuthContext` é um serviço compartilhado (singleton via
`app/Config/Services.php`) cujo comentário original assumia "PHP-FPM não compartilha memória
entre requests, então não há risco de vazar entre usuários" — verdade em produção (cada
request PHP-FPM é um processo/worker novo), **falso** no harness de teste: `FeatureTestTrait::
call()` simula múltiplas requests dentro do **mesmo processo PHPUnit**, e reseta os serviços
`request`/`filters`/`validation` entre chamadas, mas não serviços customizados da aplicação.
Um teste que autentica como `dev01` deixava o `AuthContext` "logado" para o próximo teste,
mesmo sem token — disparando por acidente os filtros de personalização de
`DevController::index`/`VideoController::trending` (exclusão de devs já curtidos/canal
ignorado).

**Corrigido** em `FeatureTestCase::setUp()`: `Services::injectMock('authContext', new
AuthContext())` antes de cada teste — mesmo padrão que o próprio CI4 já aplica a
`filters`/`validation`. Não é uma mudança de comportamento em produção (`AuthContext` continua
correto lá, por request PHP-FPM real) — é puramente um artefato do harness de teste em
processo único, documentado aqui para não se repetir.

### CI (`.github/workflows/ci.yml`)

Já provisionava tudo que os testes de feature precisam desde a Fase 2/3 (antecipado antes da
Fase 7 existir de fato): `docker/mysql/init.sql` já cria `devfinder_test`, o workflow já
gerava `database.tests.*` no `.env` e rodava `vendor/bin/phpunit` a cada PR — só faltavam
testes de feature reais além do `HealthTest.php` do scaffold. Nenhuma mudança de infra de CI
foi necessária nesta fase, só os testes em si.

**Rodado localmente reproduzindo o CI** (Docker, `database.tests` apontando pro mesmo MySQL):
30/30 testes, 71 assertions, **exit code 0 conferido explicitamente** (não só "OK" visual —
lição já registrada em `../CLAUDE.md` desde o incidente do PCOV na Fase 3).

## 7.3 — Testes de carga leves

`npx autocannon -c 10 -d 15` contra Docker Compose local (banco seedado com o dump real —
`specs/seed/`, 500 vídeos/186 canais/40 devs), sem autenticação:

| Endpoint | p50 | p97.5 | p99 | Req/s (média) |
|---|---|---|---|---|
| `GET /devs` (30 devs/página) | 129 ms | 258 ms | 340 ms | 70.5 |
| `GET /feed/trending` (30 vídeos/página) | 28 ms | 46 ms | 54 ms | 338.7 |

### Achado real — N+1 em `GET /devs` (não corrigido nesta fase, documentado como candidato)

A diferença de quase 5x na latência entre os dois endpoints não é explicada só pelo volume de
dado (`feed/trending` devolve mais bytes por resposta). Contagem real de queries por request
(MySQL `general_log`, 1 request de cada):

- `GET /devs` (30 devs na página): **126 queries.**
- `GET /feed/trending` (30 vídeos na página): **6 queries.**

Causa: `App\Presenters\DevPresenter::present()` roda **4 queries por Dev**
(`DevReactionModel::targetIdsFor` × like/dislike, `ChannelReactionModel::targetIdsFor` ×
follow/ignore) — chamado via `array_map(DevPresenter::present(...), $rows)` sobre os 30 devs
da página, 30 × 4 = 120 queries, mais a query principal + a de contagem do `Pager`. Contraste
com `VideoController::present()` (usado por `feed/trending`): é só mapeamento de campos sobre
uma única query com `JOIN` já feita — nenhuma query por linha.

Isso é um N+1 clássico, real, medido — não uma suposição de leitura de código. **Não corrigido
nesta fase** (a Fase 7 pedia "documentar latência real", não otimizar) — candidato natural pra
uma iteração futura (`WHERE id IN (...)` agrupado por tipo de reação, resolvendo os 4 conjuntos
de uma vez por página em vez de por linha, mesmo princípio já aplicado em
`GET /feed/subscriptions` na Fase 1). Registrado aqui para não se perder.

### Material de comparação de portfólio

`GET /feed/trending` (338 req/s médio, p50 28ms) é o número mais representativo do "melhor
caso" do monolito PHP-FPM/MySQL local — sem N+1, JOIN único. Comparação direta contra a
versão `../serverless` fica fora de escopo aqui (arquiteturas e ambientes de execução
diferentes o bastante — Lambda cold/warm start vs. processo PHP-FPM sempre ativo — que uma
comparação de número bruto sem essa ressalva seria enganosa); os números acima servem como
registro isolado desta stack, não como *benchmark* comparativo direto.

## Critério de aceite (`plan.md`, Fase 7, itens 7.1–7.3)

- [x] 7.1: logs estruturados em JSON via handler custom do CI4, verificado com uma exceção
  real capturada e uma linha JSON válida gravada.
- [x] 7.2: suíte CIUnit (30 testes) cobrindo os casos de aceite críticos das Fases 3/5/6,
  rodando no CI a cada PR (infra já provisionada desde a Fase 2/3), 30/30 verde localmente
  reproduzindo o CI, exit code 0 conferido explicitamente. 1 achado real de framework
  (`AuthContext` vazando entre requests simuladas) corrigido.
- [x] 7.3: testes de carga leves (`autocannon`) contra 2 endpoints representativos, latência
  real documentada, 1 achado real (N+1 em `GET /devs`, 126 queries) registrado como candidato
  de otimização futura, não corrigido nesta fase.
