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
| `render.yaml` (raiz do repo) | Blueprint do Render — declara `dockerfilePath`, `healthCheckPath: /`, `preDeployCommand: php spark migrate --no-interaction` e a lista de env vars como código. Segredos ficam `sync: false` (preenchidos manualmente no dashboard, nunca versionados) — ver todolist abaixo. |

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

- [ ] **Criar conta no Render** (render.com) e conectar o repositório GitHub
      (`devfinder-codeigniter`) — autoriza o Render a ler o repo pra build.
- [ ] **Criar o Web Service** a partir do `render.yaml` (Render detecta o Blueprint
      automaticamente ao conectar o repo, ou New → Blueprint no dashboard).
- [ ] **Preencher os env vars marcados `sync: false`** no dashboard do Render (nunca vão pro
      `render.yaml`/git):
  - `database.tidbcloud.hostname`, `database.tidbcloud.username`, `database.tidbcloud.password`
    — as credenciais reais do TiDB Cloud já testadas (mesmas do `.env` local, grupo
    `tidbcloud`)
  - `encryption.key` — gerar um valor novo e próprio pra produção (`openssl rand -hex 32`
    prefixado com `hex2bin:`), **não reaproveitar** o valor de dev local
  - `auth.jwtSecret` — idem, gerar novo (`openssl rand -hex 32`)
  - `videorefresh.jsonbinApiKey` / `videorefresh.jsonbinIdSubs` — reaproveitar os mesmos do
    `.env` local (mesmo bin JSONBin.io)
  - `auth.githubClientId` / `auth.githubClientSecret` — ver próximo item antes de preencher
  - `app.baseURL` / `auth.webURL` — só dá pra saber depois do 1º deploy, quando o Render atribui
    o subdomínio `https://<nome-do-serviço>.onrender.com/`
- [ ] **Criar (ou editar) o GitHub OAuth App** em github.com/settings/developers com
      *Authorization callback URL* = `https://<seu-app>.onrender.com/v1/auth/github/callback`
      — domínio diferente do de dev local (`localhost:8081`), precisa de callback próprio. Só
      depois desse passo dá pra preencher `auth.githubClientId`/`auth.githubClientSecret` no
      Render.
- [ ] **Confirmar `preDeployCommand`** no dashboard do Render após o 1º deploy — o
      `render.yaml` já declara `php spark migrate --no-interaction`, mas confirmar que o campo
      foi aplicado (Blueprints nem sempre replicam 100% dos campos automaticamente,
      dependendo da versão da UI do Render).
- [ ] **(Opcional) Cron da ingestão** — Render free não tem cron nativo confiável; configurar
      um workflow do GitHub Actions com `schedule:` batendo em
      `POST https://<seu-app>.onrender.com/v1/video/refresh` (mesmo padrão já avaliado pro
      InfinityFree em `fase-7-deploy-infinityfree.md`), se quiser ingestão recorrente sem
      depender de execução manual.
- [ ] **Verificar os casos de aceite reais** contra a URL do Render depois do deploy —
      `npx httpyac send specs/acceptance/*.http --env <novo-ambiente> --all`, mesmo padrão já
      usado nas Fases 3/5/6 (`CLAUDE.md`).

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
