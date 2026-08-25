# Avaliação — Render.com como candidato ao item 7.5 (deploy real)

> Caderno de decisão (não é spec de fase executada — mesmo papel de
> [`infra-pending.md`](./infra-pending.md) item 1, do qual este documento é um apêndice
> específico). Item 7.5 de [`../plan.md`](../plan.md) segue **sem provedor escolhido**; este
> documento registra a avaliação de mais um candidato (Render), não uma decisão.

## Origem

Usuário pesquisou manualmente e trouxe uma nota local não versionada
(`specs/zdeploy__.md` — cai no padrão `**/*__.*` do `.gitignore`, mesmo tratamento de "notas
pessoais locais" já descrito em `specs/README.md`) com sugestões de terceiros (Render,
Railway, Fly.io, InfinityFree, 000webhost) e uma menção ao repositório `maruf009sultan/PHDeploy`
via DeepWiki como exemplo de estrutura Dockerfile para PHP em Render. Conteúdo de origem
externa/não verificado — tratado aqui como ponto de partida, não como fonte confirmada.

## Verificação (2026-08-24, via WebSearch — não memória)

Mesma disciplina já aplicada em `infra-pending.md` (itens 1 e 2): free tier muda com
frequência, então a resposta não pôde vir de conhecimento pré-treinado. Consultado
`render.com/docs/free` e coberturas de terceiros (Kuberns, srvrlss.io) na data acima.

| Aspecto | Achado |
|---|---|
| **MySQL grátis** | **Não existe.** Render só oferece banco relacional grátis em Postgres. |
| **Postgres grátis** | Existe, mas **expira 30 dias após a criação** (14 dias de grace period pra fazer upgrade antes de apagar os dados) — é exatamente o padrão "free tier que expira" que a Regra de custo do `../CLAUDE.md` ("hospedagem sempre-gratuita") explicitamente exclui. |
| **Disco persistente** | Web service do plano free **não tem** disco persistente (recurso só nos planos pagos). |
| **Spin-down** | Web service free dorme após 15 min de inatividade, ~1 min pra acordar no próximo request, 750h grátis/mês. Isso *é* compatível com a Regra de custo (não expira, só dorme) — é só um quirk de UX pro portfólio, não um bloqueio. |
| **`docker-compose.yml`** | Render não roda docker-compose nativamente — cada serviço (web service, worker, cron) é 1 Dockerfile próprio. O compose atual do projeto (PHP-FPM + Nginx + MySQL, 3 containers) não sobe como está. |

## Por que isso importa para este projeto especificamente

O modelo de dados (`specs/fase-1-data-model.md`) é relacional MySQL, com `CHECK` constraints e
`ON UPDATE CASCADE` já validados contra MySQL/MariaDB real na Fase 2 (achado registrado em
`fase-2-scaffold-infra.md`). Trocar de banco para caber no free tier do Render (ex.: migrar
pra Postgres) não é um ajuste trivial de config — reabriria decisões já fechadas nas Fases 1–2,
fora do escopo de "só escolher onde hospedar".

## Re-avaliação — combo Render + db4free.net (2026-08-24)

`zdeploy__.md` (atualizado pelo usuário com mais uma rodada de pesquisa) propõe resolver o
obstáculo nº 1 acima combinando Render (app) com `db4free.net` como MySQL externo — a
recomendação explícita da nota é "App (Render/Railway) + Banco (db4free.net ou
FreeSQLDatabase)".

**Verificado agora, não só a reputação histórica do serviço**: tentei acessar
`https://www.db4free.net/` diretamente — a URL **redireciona (301) para um domínio sem
nenhuma relação** (`taxcreditsforworkersandfamilies.org`), sinal clássico de domínio expirado e
reaproveitado por terceiros (domain parking/squatting). Confirmado via busca: o domínio
`db4free.net` **expirou em 2026-06-01** e estava em "redemption period" em 2026-03-16 (registro
WHOIS). **O serviço não existe mais** — não é mais candidato, ponto final, independente de
qualquer avaliação de limites/confiabilidade que fizesse sentido quando ele ainda estava no ar
(200MB de banco, sem SSL nativo, renovação manual a cada 90 dias por e-mail, já descrito por
terceiros como "só para teste, não para produção").

Isso fecha a combinação Render+db4free especificamente. A mesma tabela de `zdeploy__.md` cita
outras duas opções de MySQL externo que **não foram verificadas nesta rodada** (fora do escopo
pedido, "Render + db4free"): `FreeSQLDatabase.com` (5MB — provavelmente pequeno demais mesmo
pra portfólio, já que o dump real de seed tem 500 vídeos/186 canais/40 devs, ver
`fase-7-observabilidade-testes.md`) e `Clever Cloud` (256MB). Ambas ficariam pendentes de
pesquisa própria se o usuário quiser insistir no padrão "Render + MySQL externo" — não
descartadas aqui, só não confirmadas.

## Re-avaliação — combo Render + Clever Cloud (2026-08-24)

`zdeploy__.md` lista Clever Cloud como "Free tier com banco pequeno (256MB), bom para provas de
conceito". Verificado agora (WebSearch + fetch direto de `clever.cloud/pricing/` e da doc do
addon MySQL, não a reputação antiga citada na nota):

- **Sem free tier permanente desde agosto de 2023** — múltiplas fontes independentes
  (pricingnow.com, europeanstack.com) confirmam que o que existe hoje é **crédito de trial pra
  quem se cadastra** ("no payment card required" pra começar, mas o uso — incluindo addons de
  banco — consome esse crédito), não uma camada sempre-gratuita. Mesmo padrão de "trial
  disfarçado de grátis" já descartado em `fase-7-deploy-railway.md` (Railway: US$5/30 dias, US$1/mês
  sem acumular) — a claim original de `zdeploy__.md` ("free tier") está desatualizada, mesmo
  caso da claim desatualizada sobre Fly.io já registrada em `fase-7-deploy-flyio.md`.
- A doc oficial confirma que existe um plano **MySQL "DEV"** (compartilhado entre várias
  aplicações, sem `CREATE TRIGGER`/`FUNCTION`, "delays em alta demanda") — mas nenhuma fonte
  confirmou que esse plano roda **fora** do consumo de crédito/cobrança por segundo que rege o
  resto da plataforma. Sem essa confirmação, não há como tratar como sempre-gratuito.

Mesmo problema estrutural do Railway: tecnicamente resolveria o MySQL (banco gerenciado de
verdade, sem precisar de host externo separado), mas fere a Regra de custo do `../CLAUDE.md`
("não crédito promocional") — um app sempre ativo (o compose gerado tem 3 serviços rodando
continuamente) consumiria o crédito de trial rapidamente e passaria a cobrar.

## Obstáculo nº1 resolvido — Render + TiDB Cloud (2026-08-25)

> Decorre de `fase-7-deploy-mysql.md`, seção "TiDB Cloud": TiDB Cloud Starter foi implementado
> em código (`app/Config/Database.php`, grupo `tidbcloud`), testado com conexão TLS real,
> migrations reais e o seed completo de produção (40 devs/186 canais/500 vídeos) — não é mais
> um candidato teórico.

O obstáculo nº1 da conclusão original ("sem MySQL sempre-gratuito nativo", que motivou a busca
por `db4free.net`/Clever Cloud/`FreeSQLDatabase.com`, todos descartados acima) **deixa de
existir**: TiDB Cloud Starter é um MySQL-compatível gerenciado, sempre-gratuito, acessível
remotamente de qualquer host — inclusive um web service do Render. Não é preciso nenhuma
combinação "Render + banco externo" adicional; o banco externo já existe e já está testado.

Sobra só o **obstáculo nº2** (`docker-compose.yml` não sobe como está no Render — 1
processo/container por serviço, não compose). Resolvido implementando o Dockerfile único
citado como padrão na pesquisa original do usuário (repo `PHDeploy`, PHP-FPM + Nginx fundidos):

| Arquivo novo | Papel |
|---|---|
| `docker/render/Dockerfile` | Imagem única — mesmas extensões PHP do `docker/php/Dockerfile` local + `nginx` + `supervisor` + `gettext-base` (`envsubst`). **Não substitui nem altera** `docker/php/Dockerfile`/`docker-compose.yml` — é um alvo de build separado, só usado pelo Render. |
| `docker/render/nginx.conf.template` | Bloco `server` do nginx com `listen ${PORT};` — porta não é fixa no Render, é injetada em runtime. `fastcgi_pass 127.0.0.1:9000` (mesmo container, não `app:9000` como no compose local). |
| `docker/render/supervisord.conf` | Roda `php-fpm` e `nginx -g "daemon off;"` como dois processos do mesmo container (Render não aceita múltiplos containers via compose). |
| `docker/render/entrypoint.sh` | `envsubst '${PORT}'` (só essa variável — sem a lista explícita, substituiria também `$uri`/`$document_root` do próprio nginx) renderiza o template com o `$PORT` real, depois `exec supervisord`. |
| `render.yaml` (raiz do repo) | Blueprint do Render — declara `dockerfilePath`, `healthCheckPath: /`, `preDeployCommand: php spark migrate --no-interaction` (⚠️ **ignorado no plano free** — recurso pago, ver todolist abaixo) e a lista de env vars como código. Segredos ficam `sync: false` (preenchidos manualmente no dashboard, nunca versionados) — ver todolist abaixo. |

**Testado de verdade (2026-08-25), não só validado por leitura do Dockerfile**:

1. `docker build -f docker/render/Dockerfile .` — build limpo, sem erro (mesmas extensões PHP
   do `docker/php/Dockerfile` local, mais `nginx`/`supervisor`/`gettext-base`).
2. `docker run` simulando exatamente as condições do Render — `$PORT=10000` injetado em
   runtime (não fixo na imagem) e as env vars do grupo `tidbcloud` direto no processo (sem
   `.env`, mesmo mecanismo que o Render usa de verdade). `supervisord` subiu `nginx` e
   `php-fpm` como dois processos do mesmo container, os dois em `RUNNING state`.
3. `curl http://localhost:18080/` → `200 OK`, `{"appname":"DevFinder"}` — nginx repassando pro
   PHP-FPM certo, `$PORT` renderizado certo pelo `envsubst`.
4. `curl http://localhost:18080/v1/devs` → `200 OK` com os **40 devs reais do seed** (mesmo
   dado inserido no TiDB Cloud nos testes anteriores) — confirma a cadeia completa: nginx →
   PHP-FPM → CodeIgniter → grupo `tidbcloud` do `Config\Database` → TLS → TiDB Cloud → resposta
   JSON de volta pro cliente, tudo dentro de um único container no formato que o Render exige.

Container e imagem de teste removidos depois (`docker rm -f` / `docker rmi`) — não ficou
nenhum artefato de teste no ambiente, só os arquivos de código versionados.

## O que falta — todolist manual (ações que só o usuário pode fazer)

Tudo que é código/config já está pronto (arquivos acima + `render.yaml`). O que resta é
inerentemente manual — conta de terceiro, segredos, cliques em dashboard — nenhum agente
consegue fazer isso pelo usuário:

- [x] **Criar conta no Render** (render.com) e conectar o repositório GitHub
      (`devfinder-codeigniter`) — autoriza o Render a ler o repo pra build.
- [x] **Criar o Web Service** a partir do `render.yaml` (Render detecta o Blueprint
      automaticamente ao conectar o repo, ou New → Blueprint no dashboard).
- [x] **Preencher os env vars marcados `sync: false`** no dashboard do Render (nunca vão pro
      `render.yaml`/git). ⚠️ **Chaves com underscore, não ponto** (`database_tidbcloud_hostname`,
      não `database.tidbcloud.hostname`) — achado real, ver seção "Causa raiz real" abaixo: o
      Render descarta silenciosamente qualquer env var com ponto no nome (a UI deixa salvar,
      sem erro, mas o valor nunca chega no container). O `.env` local continua com ponto, sem
      mudança — é só a convenção pro dashboard do Render:
  - `database_tidbcloud_hostname`, `database_tidbcloud_username`, `database_tidbcloud_password`
    — as credenciais reais do TiDB Cloud já testadas (mesmas do `.env` local, grupo
    `tidbcloud`)
  - `encryption_key` — gerar um valor novo e próprio pra produção (`openssl rand -hex 32`
    prefixado com `hex2bin:`), **não reaproveitar** o valor de dev local
  - `auth_jwtSecret` — idem, gerar novo (`openssl rand -hex 32`)
  - `videorefresh_jsonbinApiKey` / `videorefresh_jsonbinIdSubs` — reaproveitar os mesmos do
    `.env` local (mesmo bin JSONBin.io)
  - `auth_githubClientId` / `auth_githubClientSecret` — ver próximo item antes de preencher
  - `app_baseURL` / `auth_webURL` — só dá pra saber depois do 1º deploy, quando o Render atribui
    o subdomínio `https://<nome-do-serviço>.onrender.com/`
  - `database_defaultGroup` (valor `tidbcloud`) já vem fixo no `render.yaml` (não é
    `sync: false`), mas ainda assim vale conferir na aba Environment que existe com esse nome
    novo — variáveis antigas com ponto não são renomeadas sozinhas quando o `render.yaml` muda.
- [ ] **Criar (ou editar) o GitHub OAuth App** em github.com/settings/developers com
      *Authorization callback URL* = `https://<seu-app>.onrender.com/v1/auth/github/callback`
      — domínio diferente do de dev local (`localhost:8081`), precisa de callback próprio. Só
      depois desse passo dá pra preencher `auth.githubClientId`/`auth.githubClientSecret` no
      Render.
- [x] **`preDeployCommand` — confirmado que NÃO roda no plano free** (2026-08-25). A doc
      oficial do Render (`render.com/docs/deploys`) é explícita: *"The pre-deploy command is
      available for paid web services, private services, and background workers"* — recurso
      pago, não existe no free tier, independente do que `render.yaml` declara (o campo é
      aceito no Blueprint mas ignorado silenciosamente no plano free, não dá erro nem aviso).
      **Não bloqueia esse deploy específico**: as migrations já tinham rodado manualmente,
      local, direto contra o TiDB Cloud, antes do deploy (ver "Obstáculo nº1 resolvido" acima
      — `docker run` de teste + `curl` retornando os 40 devs do seed real), e confirmado de
      novo agora via `mysql` client (ver seção de Troubleshooting abaixo: 9 tabelas, 500
      vídeos). **Bloqueia migrations futuras**: qualquer alteração de schema depois de hoje
      precisa ser aplicada manualmente (`php spark migrate --no-interaction` local, apontando
      pro grupo `tidbcloud`) antes de cada deploy que dependa dela — o `preDeployCommand` no
      `render.yaml` fica como documentação de intenção, não como automação real, enquanto o
      serviço estiver no free tier.
- [ ] **(Opcional) Cron da ingestão** — Render free não tem cron nativo confiável; configurar
      um workflow do GitHub Actions com `schedule:` batendo em
      `POST https://<seu-app>.onrender.com/v1/video/refresh` (mesmo padrão já avaliado pro
      InfinityFree em `fase-7-deploy-infinityfree.md`), se quiser ingestão recorrente sem
      depender de execução manual.
- [ ] **Verificar os casos de aceite reais** contra a URL do Render depois do deploy —
      `npx httpyac send specs/acceptance/*.http --env <novo-ambiente> --all`, mesmo padrão já
      usado nas Fases 3/5/6 (`CLAUDE.md`).

## Troubleshooting — 500 em `/v1/feed/trending` (2026-08-25, em andamento)

Após o 1º deploy: `GET /v1` responde `200` (`{"appname":"DevFinder"}`, sem tocar banco), mas
`GET /v1/feed/trending` responde `500` com a página genérica "Whoops! We seem to have hit a
snag" — página de erro de produção do CI4 (`CI_ENVIRONMENT=production` no `render.yaml` esconde
o stack trace real do usuário final, por design).

**Hipótese descartada**: migration não aplicada. (Motivo real, descoberto depois: o
`preDeployCommand` do `render.yaml` nem roda no plano free do Render — ver todolist acima; as
tabelas existem porque as migrations foram aplicadas manualmente antes do deploy, não por causa
desse campo.) Verificado direto no TiDB Cloud, de fora do Render — `mysql` client
via container Docker local (`mysql:8.4`), TLS com o mesmo `docker/tidbcloud/ca.pem` do código,
credenciais do grupo `tidbcloud` do `.env`:

```bash
docker run --rm \
  -v "$(pwd)/docker/tidbcloud/ca.pem:/ca.pem:ro" \
  -e MYSQL_PWD='<senha do .env>' \
  mysql:8.4 \
  mysql -h gateway01.us-east-1.prod.aws.tidbcloud.com -P 4000 \
    -u '<username do .env>' \
    --ssl-ca=/ca.pem \
    -D devfinder \
    -e "SHOW TABLES; SELECT COUNT(*) AS total_videos FROM videos;"
```

Resultado: todas as 9 tabelas existem (`channel_reactions`, `channel_tag`, `channels`,
`dev_reactions`, `devs`, `migrations`, `tags`, `videos`) e `videos` tem **500 linhas** — os
dados de seed estão lá. Migration rodou, dado existe, conexão externa ao TiDB Cloud funciona.
Logo o erro não é "banco vazio" nem "credencial errada" — é algo específico do runtime da
aplicação dentro do container do Render (SQL que quebra em produção mas não nesse teste manual,
erro de config, ou algo na cadeia nginx→php-fpm→CI4 só nesse ambiente).

> Nota de segurança: o comando acima expõe a senha do banco em texto puro no shell local
> (`MYSQL_PWD`) — aceitável pra diagnóstico pontual numa máquina de desenvolvimento confiável,
> mas não deixar esse comando com a senha real em histórico de shell compartilhado/CI.

**Tentativa 1 — Shell do Render, descartada**: a ideia original era ler o arquivo de log que o
CI4 grava mesmo em produção (`writable/logs/log-<data>.log`, que não aparece no stream de logs
do Render — esse só mostra stdout/stderr dos processos supervisionados, não arquivos dentro do
container) via `tail` numa sessão de Shell do dashboard. **Verificado na prática**: Shell **não
está disponível no plano free** do Render — a UI mostra "Upgrade your instance" (só nos planos
pagos, Starter em diante). Sem acesso a shell nem a disco persistente pra baixar o arquivo,
essa rota fica fechada enquanto o serviço estiver no free tier.

**Tentativa 2 — `ErrorlogHandler` do CI4 (implementada em 2026-08-25)**: em vez de ler o
arquivo de dentro do container, mandar o log de erro pro mesmo canal que o Render já expõe sem
Shell — o stream de stdout/stderr. `app/Config/Logger.php` já vinha com um bloco
`CodeIgniter\Log\Handlers\ErrorlogHandler` comentado no scaffold padrão do framework; habilitado
agora com `messageType => ErrorlogHandler::TYPE_SAPI`, que usa `error_log()` do PHP endereçado
pro log do próprio SAPI — no php-fpm isso cai no error log do FPM, e `docker/render/
supervisord.conf` já redireciona o stderr do processo `php-fpm` pro stderr do container
(`stderr_logfile=/dev/stderr`), que é exatamente o que aparece no stream de logs do Render.
Handlers ativos em paralelo agora: `JsonFileHandler` (arquivo, inútil em produção sem Shell,
mas mantido — útil no `docker-compose.yml` local, onde dá pra montar volume) +
`ErrorlogHandler` (stderr, visível no Render free tier). `handles` limitado a
`['critical', 'alert', 'emergency', 'error']` — mesmo nível que já passa pelo threshold de
produção (`Config\Logger::$threshold = 4` quando `ENVIRONMENT === 'production'`), não muda o
volume de log, só adiciona um destino a mais pro que já seria logado.

**Ainda pendente**: commit + push desse ajuste, redeploy no Render (automático via push, dado
o Blueprint), reproduzir o 500 batendo em `/v1/feed/trending` de novo, e ler o stack trace real
na aba *Logs* do dashboard (não precisa mais de Shell). Resultado ainda não coletado nesta
rodada.

## Causa raiz confirmada — `database.defaultGroup` nunca chegou no Render (2026-08-25)

Commit do `ErrorlogHandler` acima (PR #9) mergeado, Render redeployou (`197739c`, live
09:47). Batendo em `/v1/feed/trending` de novo, a aba *Logs* mostrou a exceção real pela
primeira vez:

```
mysqli_sql_exception: No such file or directory
  ...->real_connect('localhost', '', ***, '', 3306, '', 0)
```

`hostname=localhost`, `port=3306`, usuário/senha vazios — exatamente os valores **hardcoded do
grupo `default`** em `app/Config/Database.php` (o grupo do `docker-compose.yml` local), não do
grupo `tidbcloud`. Ou seja: `database.defaultGroup` nunca virou `tidbcloud` em produção — a
aplicação sempre esteve tentando conectar no MySQL local inexistente dentro do container do
Render.

**Descartado código/imagem como causa**: reproduzi local com a mesma imagem
(`docker build -f docker/render/Dockerfile .`) e as mesmas env vars injetadas via
`--env-file` (sem `.env`, mesmo mecanismo do Render — nenhuma var lida de arquivo dentro do
container), incluindo `database.defaultGroup=tidbcloud`. **Funcionou**: `GET /v1/feed/trending`
→ `200`, `total: 500`, dados reais do TiDB Cloud. O Dockerfile/entrypoint/supervisord estão
corretos — o problema nunca foi o container, foi a variável faltando no ambiente real.

**Causa confirmada pelo usuário**: `database.defaultGroup` **não tem `sync: false`** no
`render.yaml` — o valor `tidbcloud` já vem embutido no Blueprint como código, então na teoria
não precisaria de preenchimento manual (diferente das credenciais/segredos, que são
`sync: false` por design e *precisam* ser preenchidas no dashboard). Na prática, essa variável
nunca foi criada no serviço — o Render não aplicou esse campo do Blueprint automaticamente.
**Mesma categoria de falha já flagada no item do `preDeployCommand`** acima (campos do
Blueprint que "nem sempre replicam 100% automaticamente"), agora confirmada também pra uma env
var comum, não só pra `preDeployCommand`. **Lição pro checklist**: depois de criar o Web
Service a partir de um Blueprint, não basta preencher só as chaves `sync: false` — é preciso
conferir a lista *completa* de env vars na aba Environment contra o `render.yaml` inteiro,
mesmo as que já têm valor fixo no código.

Corrigido manualmente pelo usuário (adicionou `database.defaultGroup=tidbcloud` direto na aba
Environment do dashboard) — Render redeployou de novo. **Resultado: o 500 continuou,
stack trace idêntico** (`localhost:3306`, mesmo depois de conferir por screenshot que a chave
estava lá, escrita certa, sem typo). O parágrafo acima (`preDeployCommand`/campo do Blueprint
"não replicado") estava **incompleto** — não era caso a caso, a causa é estrutural, ver abaixo.

## Causa raiz real — Render descarta env vars com ponto no nome (2026-08-25)

Com a chave certa confirmada na UI e o erro persistindo idêntico, a hipótese "Blueprint não
aplicou esse campo" não explicava mais nada — precisava de prova, não suposição. Criado um
endpoint de diagnóstico temporário, `GET /v1/_debug/env`
(`App\Controllers\DebugEnvController`, PRs #10 e #11, removido depois de usar) — não expõe
nenhum valor/segredo, só lista *nomes* de chave visíveis via `getenv()`/`$_ENV`/`$_SERVER` em
produção.

**1ª rodada** (só filtrando por `database`): zero chaves em qualquer uma das três vias.

**2ª rodada** (lista completa, sem filtro): `CI_ENVIRONMENT` e `PORT` (nomes sem ponto)
aparecem normalmente. Nenhuma das ~15 variáveis com ponto no nome configuradas no dashboard
(`database.*`, `auth.*`, `app.*`, `encryption.*`, `videorefresh.*`) aparece **em lugar
nenhum** — nem `getenv()`, nem `$_ENV`, nem `$_SERVER`. Em compensação, variáveis do próprio
Render com underscore (`RENDER_SERVICE_NAME`, `RENDER_GIT_COMMIT` etc.) e do `supervisord`
(`SUPERVISOR_ENABLED`, `SUPERVISOR_PROCESS_NAME`) chegam normalmente até o worker do php-fpm —
descarta qualquer teoria de `clear_env`/isolamento de processo do FPM. **Causa isolada: é
especificamente o ponto (`.`) no nome da env var que o Render descarta silenciosamente** — a UI
deixa digitar e salvar (sem erro, sem aviso), mas o valor nunca chega no container em runtime.
Comportamento documentado como comum em plataformas construídas sobre Kubernetes (nomes de env
var normalmente restritos a `[A-Za-z_][A-Za-z0-9_]*`) — `KUBERNETES_SERVICE_HOST` e
`KUBERNETES_PORT_*` aparecem na lista de env vars do container, confirmando que a infra do
Render roda sobre K8s por baixo.

**Corrigido sem tocar em `Config\Database`/`Config\Auth`/etc.**: o próprio CodeIgniter 4 já
previa esse tipo de restrição de plataforma — `BaseConfig::getEnvValue()`
(`vendor/codeigniter4/framework/system/Config/BaseConfig.php`) tenta a forma com ponto (usada
pelo `.env` local, continua igual) **e**, como fallback, a forma com underscore no lugar de
cada ponto (`database_tidbcloud_hostname` em vez de `database.tidbcloud.hostname`). Bastou
reescrever as chaves do `render.yaml` pra underscore — nenhuma mudança de código de app. O
usuário precisa recriar as variáveis `sync: false` no dashboard com o nome novo (o Render não
renomeia entradas existentes sozinho quando o `render.yaml` muda a chave) — as antigas com
ponto podem ser apagadas depois de confirmado que o novo deploy funciona.

## Conclusão

**Atualizado em 2026-08-25.** Render deixou de ter dois obstáculos e passou a ter um só:

1. ~~Sem MySQL sempre-gratuito nativo~~ — **resolvido**, TiDB Cloud Starter testado e ativo
   (ver seção acima).
2. `docker-compose.yml` não sobe como está — **resolvido em código**: `docker/render/`
   (Dockerfile único PHP-FPM+Nginx via supervisord) + `render.yaml` (Blueprint). O
   `docker-compose.yml`/`docker/php/`/`docker/nginx/` locais **não foram alterados** — o alvo
   Render é um Dockerfile adicional, não uma substituição.

O que resta não é mais avaliação de viabilidade — é execução manual (conta, segredos, cliques
de dashboard), listada no todolist acima. Path alternativo sem obstáculo nenhum (self-host
numa VM Always Free, rodando o `docker-compose.yml` real sem modificação) continua documentado
em [`fase-7-deploy-mysql-gcp.md`](./fase-7-deploy-mysql-gcp.md) — Render passou de "descartado"
pra "candidato real, pendente só de passos manuais do usuário", não é a decisão final do item
7.5 (`plan.md`), que segue com o usuário.

Fontes consultadas: [Deploy for Free – Render Docs](https://render.com/docs/free),
[Render Postgres 2026: Pricing, Limits & Alternatives — Kuberns](https://kuberns.com/blogs/render-postgres-pricing-setup-limits/),
[Render Pricing 2026: Free Tier, RAM Limits & Alternatives — srvrlss.io](https://www.srvrlss.io/provider/render/),
[Whois db4free.net](https://www.whois.com/whois/db4free.net), acesso direto a
`https://www.db4free.net/` (redirect 301 confirmado em 2026-08-24),
[Pricing – Clever Cloud](https://www.clever.cloud/pricing/),
[MySQL – Clever Cloud Documentation](https://developers.clever-cloud.com/doc/addons/mysql/),
[Clever Cloud Pricing 2026 — pricingnow.com](https://pricingnow.com/question/clever-cloud-pricing/),
[Clever Cloud Review 2026 — europeanstack.com](https://europeanstack.com/software/clever-cloud).
