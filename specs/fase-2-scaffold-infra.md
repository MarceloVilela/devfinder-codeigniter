# Fase 2 — Infraestrutura base (scaffold + Docker + deploy real)

> Fase 2 de [`../plan.md`](../plan.md), seção "3. Fase 2". Depende da Fase 1 (PR #2, mergeado)
> — as 7 migrations de [`fase-1-data-model.md`](./fase-1-data-model.md) viraram arquivos reais
> nesta fase.

## Scaffold: raiz, não `api/`

Decisão revista durante esta execução (ver discussão registrada em `../plan.md`, Fase 2): o
CI4 foi instalado **na raiz de `php-codei`**, não numa subpasta `api/` como o rascunho
original do plano sugeria (herdado sem verificação da estrutura do `../serverless`). Evidência
usada: a estrutura real do próprio `codeigniter4/appstarter` (`gh api
repos/codeigniter4/appstarter/contents/`) já vem com `app/`, `public/`, `tests/`,
`writable/`, `composer.json` soltos na raiz do template — ele é desenhado pra *virar* a raiz
do projeto onde é instalado.

**Como foi feito** (sem PHP/Composer instalados no host): `composer create-project
codeigniter4/appstarter` rodado dentro de um container `composer:2` descartável, contra um
diretório vazio temporário (o comando recusa rodar direto na raiz do repo por ela já ter
`specs/`, `CLAUDE.md`, `.git/` etc.), depois os arquivos gerados movidos pra raiz real,
mesclando o `.gitignore` do CI4 com as regras já existentes do projeto (`**/*__.*`, `.env`).

## Docker Compose — mesmo conjunto de containers do deploy real

3 serviços em [`docker-compose.yml`](../docker-compose.yml):

| Serviço | Imagem | Papel |
|---|---|---|
| `app` | build local (`docker/php/Dockerfile`, `php:8.3-fpm` + `pdo_mysql`/`mysqli`/`intl`/`mbstring`/`zip`/`gd`) | PHP-FPM, roda o código CI4 |
| `nginx` | `nginx:1.27-alpine` | serve `public/`, proxy FastCGI pro `app` |
| `mysql` | `mysql:8.4` | banco — versão LTS, mesma decisão já registrada em `infra-pending.md` (achado do DeepWiki na Fase 0) |

`docker/mysql/init.sql` cria também um banco `devfinder_test` (usado pelo grupo `tests` do CI4
— `DatabaseTestTrait`/PHPUnit não devem tocar o banco `devfinder` de desenvolvimento).

**`utf8mb4_0900_ai_ci`** (não `utf8mb4_unicode_ci`, citado como placeholder em
`fase-1-data-model.md`): decisão fechada agora que a versão do MySQL (8.4) está definida — é a
collation nativa recomendada do MySQL 8.0+, acento/case-insensitive sem custo de `LOWER()` em
lookups por `name`/`link`.

### Achado só visível rodando de verdade

As 7 migrations da Fase 1 foram copiadas para `app/Database/Migrations/` e rodadas contra
MySQL 8.4 real (`docker compose exec app php spark migrate --all`). Uma delas falhou na
primeira tentativa: MySQL/InnoDB **proíbe `CHECK` constraint numa coluna que também tenha `ON
UPDATE CASCADE`** — erro real reproduzido: `Column 'dev_id' cannot be used in a check
constraint 'chk_dev_reactions_not_self': needed in a foreign key constraint... referential
action`. Isolado com um teste mínimo direto no MySQL do container (`ON UPDATE RESTRICT` +
`ON DELETE CASCADE` juntos não têm esse problema). Corrigido nas duas FKs de
`dev_reactions` (`dev_id`, `target_dev_id`): `on_update` virou `RESTRICT` — sem perda prática,
já que a PK `devs.id` é `auto_increment` e nunca é atualizada. `ON DELETE CASCADE` continua
como estava (é o comportamento que importa pra essa tabela). Ambos os documentos
(`fase-1-data-model.md` e as migrations reais em `app/Database/Migrations/`) foram
atualizados para refletir isso.

**Validação manual de todas as constraints**, direto no MySQL do container:
- `CHECK (dev_id <> target_dev_id)` bloqueia auto-like (`ERROR 3819`).
- `UNIQUE` em `channels.name` bloqueia duplicata (`ERROR 1062`).
- `INSERT`/`SELECT` normais em `dev_reactions` funcionam.

## Health-check

`GET /` (já documentado em `fase-0-openapi.yaml` como o endpoint `AppInfo`) responde
`{"appname": "DevFinder"}` — serve tanto de contrato público quanto de health-check pro
critério de aceite desta fase. `app/Controllers/Home.php` ajustado pra retornar JSON em vez da
view padrão do template.

## CI (GitHub Actions)

[`​.github/workflows/ci.yml`](../.github/workflows/ci.yml): a cada PR/push em `main`, sobe um
serviço MySQL 8.4, instala dependências via Composer, roda `php spark migrate --all` contra o
banco `default` e `vendor/bin/phpunit` (que usa o grupo `tests`, MySQL real — não SQLite,
decisão deliberada: `CHECK`/`ENUM` são específicos de MySQL, não fariam sentido testados contra
SQLite). Hostname do MySQL sobrescrito via `.env` pra `127.0.0.1` (no Actions, serviço exposto
direto no runner — diferente do `mysql` do Docker Compose local, que resolve pela rede
interna do Compose).

## Hospedagem real — pesquisa feita, decisão adiada

Duas opções sempre-gratuitas genuínas levantadas (WebSearch, 2026-08-24 — free tier muda com
frequência, não confiar em memória): **Oracle Cloud Always Free** (Ampere A1, 2 OCPU/12GB desde
o corte de 2026-06, risco de disponibilidade por reclaim de capacidade) e **Google Cloud
e2-micro Always Free** (mais escasso, exige conta de billing anexada, historicamente mais
estável). Registradas com o comparativo completo em
[`infra-pending.md`](./infra-pending.md), item 1. **Decisão explícita do usuário**: não
escolher agora — o `docker-compose.yml` já reproduz localmente o mesmo ambiente que rodaria em
qualquer uma das duas, então a Fase 2 não fica bloqueada por isso. Provisionamento real e o
runbook específico ficam para quando a conta estiver pronta.

## Critério de aceite da Fase 2 (de `../plan.md`)

- [x] `docker compose up` sobe API + MySQL localmente — validado (`docker compose up -d
  --build`, todos os 3 serviços saudáveis).
- [x] Migrations aplicadas — `php spark migrate --all`, 7/7 com sucesso.
- [x] Endpoint de health-check responde — `GET /` → `{"appname":"DevFinder"}`, HTTP 200.
- [x] CI configurado (roda a cada PR: MySQL de serviço + migrations + testes) — validação real
  do workflow acontece quando este PR for aberto contra `main`.
- [ ] Host real provisionado — **adiado deliberadamente** (decisão do usuário, ver seção
  acima); runbook de provisionamento específico fica para quando a conta estiver pronta,
  conforme a própria ressalva do critério de aceite em `plan.md` ("ou runbook pronto, se a
  execução ficar para quando o usuário tiver a conta pronta").

## Próximo passo

Fase 3 (endpoints públicos de leitura) — implementar os Controllers reais
(`GET /devs`, `GET /channels`, `GET /feed/trending`, etc.) contra o schema desta fase, seguindo
os casos de aceite em `specs/acceptance/`.
