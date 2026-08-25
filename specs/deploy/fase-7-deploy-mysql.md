# Panorama — opções de MySQL/MariaDB sempre-gratuito para o item 7.5

> Caderno de decisão (não é spec de fase executada — mesmo papel de
> [`infra-pending.md`](./infra-pending.md) item 1). Este documento é um **índice/resumo** dos
> `fase-7-deploy-*.md` já escritos, filtrado especificamente pela pergunta "onde rodar
> MySQL/MariaDB de graça pro `php-codei`" — não introduz pesquisa nova, consolida o que já foi
> verificado nos documentos individuais linkados abaixo. Item 7.5 de [`../plan.md`](../plan.md)
> segue **sem provedor escolhido**.

## Por que essa pergunta é o filtro certo

O modelo de dados do `php-codei` (`specs/fase-1-data-model.md`) é relacional MySQL, com `CHECK`
constraints e `ON UPDATE CASCADE` já validados contra MySQL 8.4 real na Fase 2
(`fase-2-scaffold-infra.md`). Qualquer candidato de hospedagem que não ofereça MySQL/MariaDB
sempre-gratuito de alguma forma (gerenciado ou self-host) não resolve o item 7.5, mesmo que
resolva bem a parte de rodar PHP.

## Tabela consolidada

| Opção | Tipo | Status | Motivo | Detalhe |
|---|---|---|---|---|
| **Oracle Cloud Always Free** (VM Ampere A1) | Self-host (VM completa) | ✅ **Ainda viável** — decisão do usuário pendente | VM sempre-gratuita de verdade, roda `docker-compose.yml` com MySQL 8.4 real sem modificação. | `infra-pending.md`, item 1 |
| **GCP `e2-micro` Always Free** | Self-host (VM completa) | ✅ **Ainda viável** — decisão do usuário pendente | Mesma ideia; exige conta de billing anexada (risco de cobrança acidental), mais estável historicamente. | `infra-pending.md`, item 1 |
| **InfinityFree** | MySQL gerenciado (cPanel) | ⚠️ Reaberto, não confirmado | Cron/CLI têm workaround real (GitHub Actions `schedule:` batendo em `POST /video/refresh`, endpoint que já existe desde a Fase 6) — mas achado novo: chamadas de saída (`cURL`) do PHP pra APIs externas são relatadas como bloqueadas/instáveis no fórum oficial, o que quebraria GitHub OAuth (Fase 4) e a própria ingestão via JSONBin.io (Fase 6). Precisa de teste empírico direto antes de decidir. | `fase-7-deploy-infinityfree.md` (re-avaliação) |
| **000webhost** | MySQL gerenciado (cPanel) | ❌ Descartado | Serviço encerrado pela Hostinger em out/2024. | `fase-7-deploy-000webhost.md` |
| **Render** | — (não oferece MySQL) | ❌ Não se aplica | Só Postgres grátis, que expira em 30 dias. | `fase-7-deploy-render.md` |
| **Railway** | MySQL gerenciado | ❌ Descartado | MySQL nativo, mas "grátis" é trial US$5/30 dias + US$1/mês sem acumular — não sempre-gratuito. | `fase-7-deploy-railway.md` |
| **Fly.io** | — (sem MySQL gerenciado) | ❌ Descartado | Sem free tier permanente desde 2024 — só trial de $5/2h/7 dias. | `fase-7-deploy-flyio.md` |
| **db4free.net** | MySQL remoto grátis | ❌ Descartado | Domínio expirado (2026-06), hoje redireciona pra site não relacionado — serviço morto. | `fase-7-deploy-render.md` (re-avaliação) |
| **Clever Cloud** | MySQL gerenciado (DEV) | ❌ Descartado | Plano DEV existe, mas sem free tier permanente desde ago/2023 — crédito de trial. | `fase-7-deploy-render.md` (re-avaliação) |
| **FreeSQLDatabase.com** | MySQL remoto grátis | ⚠️ Não verificado | Citado em `zdeploy__.md`, só 5MB — provavelmente pequeno demais pro seed real (500 vídeos/186 canais/40 devs). Não pesquisado a fundo. | `fase-7-deploy-render.md` |
| **TiDB Cloud Starter** (ex-Serverless) | MySQL-compatível gerenciado (serverless) | ✅ **Testado e ativo como `tidbcloud` env staging** (2026-08-25) | Sempre-gratuito de verdade (sem cartão, sem prazo): 5GiB storage + 50M Request Units/mês, até 5 instâncias grátis/organização. Conexão TLS real confirmada, `php spark migrate` rodou as 7 migrations sem erro. Limitação real confirmada: `CHECK` constraint da tabela `dev_reactions` não é imposto (ver seção "TiDB Cloud" abaixo). | Ver seção "TiDB Cloud" abaixo (2026-08-25) |
| **Aiven for MySQL Free Tier** | MySQL gerenciado (VM dedicada) | ✅⚠️ **Candidato aberto, não confirmado** | Sempre-gratuito de verdade (sem cartão, sem prazo): 1GB storage/RAM + 1 CPU dedicado (não compartilhado), backups automáticos inclusos. MySQL real (não wire-compatible), mas serviço pode hibernar em inatividade prolongada — impacto em cron/ingestão não verificado. Sem tier grátis de MariaDB. | Ver seção "TiDB Cloud Starter e Aiven" abaixo (2026-08-25) |

## TiDB Cloud Starter e Aiven — candidatos gerenciados tipo "Neon para MySQL" (2026-08-25)

> Origem: pergunta do usuário ("não tem algo como Neon pra usar MySQL/MariaDB?") — Neon é o
> modelo de referência (Postgres serverless, free tier generoso, sem cartão), mas não existe
> equivalente para MySQL da mesma empresa. Pesquisa via WebSearch (não memória, mesmo padrão
> desta pasta) encontrou dois candidatos reais que a varredura de 2026-08-24 (tabela acima)
> não cobriu — nenhum dos dois tinha sido avaliado antes.

### TiDB Cloud Starter (ex-"TiDB Cloud Serverless", renomeado ago/2025)

O mais parecido com "Neon pra MySQL" que existe hoje: modelo serverless/autoscaling,
MySQL-compatível, **5GiB de row storage + 5GiB columnar + 50 milhões de Request Units/mês**,
até 5 instâncias grátis por organização, **sem cartão de crédito** para começar. Cobra por
consumo (RU + GiB-mês) só depois de estourar a cota grátis, não corta o serviço.

**Ressalva real, não cosmética**: TiDB é um banco **distribuído**, compatível com MySQL por
protocolo de conexão (wire-compatible), não é MySQL/MariaDB de fato por baixo. Isso importa
especificamente para este projeto porque `specs/fase-1-data-model.md` depende de dois recursos
já validados contra MySQL 8.4 real (`fase-2-scaffold-infra.md`):

| Recurso usado pelo schema | Status no TiDB (verificado 2026-08-25) |
|---|---|
| `CHECK` constraints | Suportado, mas **desabilitado por padrão** — exige `SET GLOBAL tidb_enable_check_constraint = ON` explicitamente. Não confirmado se o tier Starter (gerenciado) permite setar essa variável de sessão/global — pode ser bloqueada em ambiente serverless multi-tenant. **Não verificado.** |
| `FOREIGN KEY ... ON UPDATE CASCADE` | Suportado desde TiDB v6.6, GA (produção-pronto) desde v8.5.0. Positivo, mas documentação oficial adverte sobre possível degradação de performance com FKs habilitadas — "recomenda-se testar antes de usar em cenário sensível a performance". Versão do engine por trás do tier Starter não confirmada como ≥8.5. |

Nenhum dos dois é um "não funciona" confirmado — são **lacunas de verificação empírica**, mesmo
padrão de incerteza já registrado para o InfinityFree acima (não tratar como descartado nem
como aprovado sem teste direto).

### Aiven for MySQL Free Tier

MySQL **real** (não wire-compatible, é o motor de verdade), sempre-gratuito sem cartão e sem
prazo: 1GB storage/RAM + 1 CPU **dedicado** (VM própria, não recurso compartilhado), com
backups automáticos incluídos — perfil mais parecido com hospedagem gerenciada tradicional do
que com "serverless". Como é MySQL real, `CHECK` e `ON UPDATE CASCADE` não têm a ressalva de
compatibilidade do TiDB.

**Ressalva real**: a documentação da Aiven menciona que serviços do tier grátis **podem
hibernar após período longo de inatividade** ("just one click away from waking up"). Não
verificado o impacto disso em dois pontos que este projeto precisa de disponibilidade
constante: (1) `php spark video:refresh` rodando via cron a cada 6h (item 7.5,
`fase-7-deploy-mysql-gcp.md`, passo 12) — se o banco hibernar entre execuções, a próxima
chamada pode falhar ou precisar de retry/wake-up; (2) requisições HTTP reais de portfólio
chegando com o banco hibernado, gerando latência alta na primeira request. Também: **sem tier
grátis de MariaDB** especificamente, só MySQL — não é um problema para este projeto (que já usa
MySQL 8.4, não MariaDB), mas descarta a Aiven caso a pergunta fosse por MariaDB de verdade.

### Por que isso muda a moldura da decisão (não decide nada ainda)

Os dois adicionam uma **terceira categoria** à tabela consolidada acima, além de "self-host em
VM" (Oracle/GCP) e "gerenciado descartado" (Railway, PlanetScale etc.): **gerenciado
sempre-gratuito real, ainda não testado**. Isso é estritamente melhor do que o InfinityFree
como opção de exploração — nenhum dos dois bloqueio estrutural do InfinityFree (sem SSH, sem
cron, chamadas de saída potencialmente bloqueadas) se aplica aqui, já que tanto TiDB quanto
Aiven são bancos gerenciados acessíveis remotamente por qualquer app PHP rodando em qualquer
lugar (inclusive a própria VM self-host da GCP, se o objetivo for separar app e banco). O que
falta, antes de tratar qualquer um como viável, é o mesmo tipo de teste empírico direto já
pedido para o InfinityFree: rodar as migrations reais de `specs/fase-1-data-model.md` contra
uma instância de teste de cada um e confirmar que `CHECK`/`CASCADE` (TiDB) e comportamento de
hibernação (Aiven) não quebram o schema/fluxo de ingestão.

## TiDB Cloud

> Implementação real + teste empírico direto (2026-08-25), não mais só avaliação de
> viabilidade — decorre da seção "TiDB Cloud Starter e Aiven" acima, mas documenta o que foi
> de fato implementado em código e o resultado de rodar as migrations reais contra uma
> instância TiDB Cloud Starter de verdade (usuário já tinha provisionado uma e forneceu a
> connection string).

### Mecanismo de troca de ambiente (`app/Config/Database.php`)

Em vez de um único grupo `default` fixo, o projeto agora tem três grupos de conexão nomeados,
com um único ponto de troca:

```php
public string $defaultGroup = 'default'; // fallback — o que o CI usa, não altera nada lá

public array $default    = [ /* docker env local — MySQL 8.4 do docker-compose.yml */ ];
public array $tidbcloud  = [ /* TiDB Cloud Starter — TLS fixado em código (CA da Let's Encrypt) */ ];
public array $dockerprod = [ /* self-host GCP/Oracle — placeholder vazio, ver fase-7-deploy-mysql-gcp.md */ ];
```

A troca de ambiente ativo é feita **só em `.env`** (nunca versionado), sem tocar em
`app/Config/Database.php` de novo:

```
database.defaultGroup = tidbcloud   # ou: default | dockerprod
```

`database.tidbcloud.hostname/database/username/password` (específicos da instância, segredo)
ficam em `.env`; `encrypt.ssl_ca` (caminho pro certificado, não é segredo) fica fixado no
código, apontando pra `docker/tidbcloud/ca.pem` — CA raiz da Let's Encrypt (**ISRG Root X1**,
mesmo emissor usado pelo gateway TLS do TiDB Cloud), baixada de `letsencrypt.org/certs/` e
versionada no repo (certificado público, não é segredo). A suíte de testes (grupo `tests`,
Fase 7) continua sempre contra o docker local — `ENVIRONMENT === 'testing'` força
`defaultGroup = 'tests'` no `__construct()`, independente do que `.env` disser.

`.env.example` (versionado) documenta a mesma estrutura de três blocos com valores vazios —
sem nenhuma credencial real, só o padrão pra copiar.

### Teste real executado (2026-08-25)

Contra a instância TiDB Cloud Starter fornecida pelo usuário
(`gateway01.us-east-1.prod.aws.tidbcloud.com:4000`), via `docker compose run --no-deps app`
(container real do projeto, mesma imagem PHP 8.3 + mysqli do `docker-compose.yml`):

| Passo | Resultado |
|---|---|
| Conexão TLS (mysqli + CA da Let's Encrypt) | ✅ Conectou. `Ssl_cipher = TLS_AES_128_GCM_SHA256`. Versão do servidor: **TiDB v8.5.3-serverless**. |
| `CREATE DATABASE devfinder` | ✅ Schema criado (a connection string original apontava pra `sys`, schema de sistema — não é onde as tabelas do app devem morar). |
| `php spark migrate` (via `database.defaultGroup = tidbcloud`) | ✅ As 7 migrations de `specs/fase-1-data-model.md` rodaram sem erro, através do mecanismo de troca real (não teste isolado de conexão — o caminho de código completo do CI4). |
| `FOREIGN KEY ... ON UPDATE CASCADE` (`channel_reactions`) | ✅ Confirmado presente via `SHOW CREATE TABLE` — GA desde TiDB v8.5.0 (ver pesquisa de 2026-08-25 acima), e esta instância já roda v8.5.3. Resolve a dúvida que estava em aberto. |

### Achado real — `CHECK` constraint de `dev_reactions` não é imposto (limitação confirmada, não hipótese)

A migration `2026-08-24-120006_CreateDevReactionsTable.php` cria a constraint
`chk_dev_reactions_not_self CHECK (dev_id <> target_dev_id)` via `ALTER TABLE` bruto após
`createTable()`. Testado o comportamento real, não só a variável `tidb_enable_check_constraint`
isolada:

1. **Estado padrão do tier Starter** (`tidb_enable_check_constraint = OFF`, confirmado via
   `SHOW VARIABLES`): a migration roda **sem erro** — mas a constraint não é persistida em
   nenhum lugar. `information_schema.CHECK_CONSTRAINTS` não lista nada para o schema
   `devfinder`, e um `INSERT` real com `dev_id = target_dev_id` (um dev "curtindo/descurtindo a
   si mesmo") **é aceito silenciosamente**, sem erro nenhum. TiDB parseia a sintaxe e não
   reclama, mas não guarda a regra em lugar algum.
2. **Ligando a flag** (`SET GLOBAL tidb_enable_check_constraint = ON` — permitido de fato
   setar no tier gerenciado, o que não era óbvio antes de testar) e tentando recriar a mesma
   constraint: **falha** —
   `Column 'dev_id' cannot be used in a check constraint 'chk_dev_reactions_not_self': needed
   in a foreign key constraint referential action`. É o mesmo texto de erro que o comentário da
   própria migration já documentava ter reproduzido contra MySQL real (por isso a FK usa
   `ON UPDATE RESTRICT`, não `CASCADE`, nas duas colunas) — **mas no TiDB o conflito dispara
   mesmo só com `ON DELETE CASCADE`** (a FK atual já é `ON UPDATE RESTRICT`, só o `ON DELETE
   CASCADE` continua ativo). Ou seja: a regra de incompatibilidade `CHECK` + FK do TiDB é **mais
   ampla** que a do MySQL real — o raciocínio do comentário original (que assumia bastar tirar
   o `CASCADE` do `UPDATE`) não é suficiente para o TiDB.

**Implicação prática, se TiDB seguir como staging real**: a regra "um dev não pode
curtir/descurtir a si mesmo" **não tem garantia de banco** no TiDB nem com a flag ligada nem
desligada (ligada, sequer aplica; desligada, é a única forma de a migration passar, mas sem
imposição) — precisaria virar validação na camada de aplicação (controller/model) como controle
compensatório, ou aceitar essa lacuna de integridade como trade-off conhecido do ambiente de
staging (diferente de produção real em MySQL 8.4, onde o `CHECK` é sempre imposto por padrão).
Estado da instância de teste foi limpo depois (linhas de teste removidas, flag global
devolvida a `OFF`, que é o padrão do tier).

## Conclusão

**Atualizado em 2026-08-25** — a frase original desta seção ("nenhum provedor gerenciado e
sempre-gratuito foi confirmado como viável") ficou desatualizada duas vezes na mesma data:
primeiro com o achado do TiDB Cloud Starter e da Aiven (candidatos não cobertos pela varredura
de 2026-08-24), depois com a implementação e teste real do TiDB (seção "TiDB Cloud" acima) —
**TiDB Cloud Starter agora está implementado em código (`app/Config/Database.php`, grupo
`tidbcloud`) e ativo como ambiente de staging** (`database.defaultGroup = tidbcloud` no `.env`
local), com as migrations reais do projeto rodando contra ele com sucesso.

Comparativo atualizado dos três candidatos gerenciados:

| Candidato | Bloqueio estrutural confirmado? | Status |
|---|---|---|
| **TiDB Cloud Starter** | Não é bloqueio, mas **limitação real confirmada**: `CHECK` constraint de `dev_reactions` não é imposto pelo banco em nenhum dos dois estados da flag `tidb_enable_check_constraint` (ver seção "TiDB Cloud" acima) — precisa de validação compensatória na aplicação se for pra produção real. FK `CASCADE` funciona normalmente (GA confirmado, v8.5.3). | ✅ **Implementado e testado** — ativo como staging |
| **Aiven for MySQL** | Não verificado ainda | ⚠️ Candidato em aberto, não testado |
| **InfinityFree** | Historicamente sim (sem SSH/cron), com workaround viável (GitHub Actions `schedule:`) | ⚠️ Candidato em aberto, não testado — risco mais grave que os outros dois, por afetar funcionalidade já implementada (OAuth, ingestão), não só deploy |

Path sem obstáculo **já confirmado** (independente do TiDB seguir ou não como staging)
continua sendo **self-host**: MySQL como container seu (não serviço gerenciado de terceiro)
dentro de uma VM sempre-gratuita — Oracle Cloud Always Free ou GCP `e2-micro` Always Free,
ambas já candidatas documentadas em `infra-pending.md` desde a Fase 2, rodando o
`docker-compose.yml` real sem modificação; runbook de execução para a opção GCP já em
[`fase-7-deploy-mysql-gcp.md`](./fase-7-deploy-mysql-gcp.md) — grupo `dockerprod` já reservado
em `app/Config/Database.php` para quando essa decisão for tomada, sem precisar editar código
de novo, só preencher `.env`. Produção real (item 7.5 de `plan.md`) segue **sem provedor
escolhido** — o que existe agora é staging real em TiDB, com a lacuna de `CHECK` documentada
como trade-off consciente, não decisão final de onde a Fase 7 vai rodar em produção.
