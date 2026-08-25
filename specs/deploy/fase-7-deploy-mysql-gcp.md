# Runbook — deploy real na GCP `e2-micro` Always Free (self-host, item 7.5)

> Plano de execução (diferente dos demais `fase-7-deploy-*.md`, que são cadernos de
> avaliação/descarte de candidatos — ver [`fase-7-deploy-mysql.md`](./fase-7-deploy-mysql.md)
> para o panorama comparativo). Este documento assume que a decisão já caiu para
> **self-host na GCP** (a outra opção viável, Oracle Cloud Always Free, segue documentada em
> [`../infra-pending.md`](../infra-pending.md) item 1, não descartada — só não é o alvo deste
> runbook). MySQL roda como **container** dentro da VM, não Cloud SQL gerenciado — Cloud SQL
> não tem tier sempre-gratuito hoje, o que violaria a "Regra de custo" do `CLAUDE.md`.
>
> Escopo: subir o `docker-compose.yml` real (PHP-FPM + Nginx + MySQL 8.4) numa VM `e2-micro`
> Always Free, sem apontar `devfinder-next` para ela (decisão de escopo já fechada, ver
> `../../plan.md`, Fase 7). Execução manual (runbook humano), mesmo padrão já usado no projeto
> irmão `../serverless` para passos que não fazem sentido automatizar num agente.
>
> **Ponto crítico do plano inteiro: o limite de saída de rede (egress) é de só 1GB/mês** (fora
> da América do Norte) — o único item da cota Always Free que uso real de portfólio pode
> plausivelmente estourar (VM, disco e IP estático têm folga confortável para este projeto, ver
> passo 14). Diferente da Oracle Cloud (sem cartão anexado), a conta GCP exige billing
> (cartão) mesmo para uso 100% dentro da cota — estourar o egress é a única forma realista de
> gerar cobrança acidental neste plano. Configurar orçamento + alerta de billing **antes** de
> criar qualquer recurso (passo 0) existe especificamente por causa deste risco.

## Pré-requisitos

- Conta Google Cloud com billing (cartão) anexado — **obrigatório mesmo para uso 100% dentro
  da cota Always Free** (ver `../infra-pending.md`, item 1, risco documentado). Configurar
  **orçamento + alerta de billing** (Billing → Budgets & alerts) como primeiro passo, antes de
  criar qualquer recurso, para ter aviso se algo sair da cota por engano.
- `gcloud` CLI instalado localmente (`gcloud version`) e autenticado (`gcloud auth login`).
- Uma chave SSH própria (`~/.ssh/id_ed25519` ou similar) — a GCP injeta a chave pública na VM
  via metadata, não precisa gerar uma nova.
- Domínio ou uso direto por IP: este runbook usa o IP externo estático da VM diretamente
  (`app.baseURL` aponta pro IP) — não cobre configuração de domínio/DNS, fora de escopo.

## 1. Criar o projeto GCP (se ainda não existir um dedicado)

```bash
gcloud projects create devfinder-codeigniter --name="devfinder-codeigniter"
gcloud config set project devfinder-codeigniter
gcloud billing projects link devfinder-codeigniter --billing-account=<BILLING_ACCOUNT_ID>
```

Projeto dedicado (não reaproveitar um projeto GCP de outro propósito) — mantém a cota Always
Free e o orçamento isolados e fáceis de auditar depois.

## 2. Habilitar as APIs necessárias

```bash
gcloud services enable compute.googleapis.com
```

## 3. Provisionar a VM `e2-micro` Always Free

Regra da cota Always Free: só nas regiões `us-west1`, `us-east1` ou `us-central1` (ver
`../infra-pending.md`, item 1). Escolher uma delas — `us-central1` abaixo como exemplo.

```bash
gcloud compute instances create devfinder-vm \
  --zone=us-central1-a \
  --machine-type=e2-micro \
  --image-family=debian-12 \
  --image-project=debian-cloud \
  --boot-disk-size=30GB \
  --boot-disk-type=pd-standard \
  --tags=http-server,https-server
```

`pd-standard` (não SSD) e `30GB` são os limites da cota sempre-gratuita de disco — exceder
qualquer um dos dois tira a VM da faixa grátis.

## 4. Abrir firewall para HTTP (porta 8081, a mesma exposta pelo `nginx` do compose)

```bash
gcloud compute firewall-rules create allow-devfinder-http \
  --allow=tcp:8081 \
  --target-tags=http-server \
  --source-ranges=0.0.0.0/0
```

Não abrir a porta 3306 (MySQL) para `0.0.0.0/0` — o banco só precisa ser acessível de dentro
da rede Docker da própria VM (`app` → `mysql` via nome de serviço), nunca da internet.

## 5. Reservar IP externo estático

Um IP efêmero muda a cada restart da VM, quebrando qualquer configuração/cache de DNS feita
manualmente. Reservar como estático evita isso — e IPs estáticos **anexados a uma VM em
execução** também entram na cota sempre-gratuita (só cobram se ficarem reservados sem uso).

```bash
gcloud compute addresses create devfinder-ip \
  --region=us-central1

gcloud compute instances add-access-config devfinder-vm \
  --zone=us-central1-a \
  --address=$(gcloud compute addresses describe devfinder-ip --region=us-central1 --format='get(address)')
```

## 6. Instalar Docker + Docker Compose na VM

```bash
gcloud compute ssh devfinder-vm --zone=us-central1-a
```

Dentro da VM:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker

# Docker Compose plugin (docker compose, não docker-compose standalone)
sudo apt-get update && sudo apt-get install -y docker-compose-plugin
```

## 7. Clonar o repositório e configurar `.env` de produção

```bash
git clone git@github.com:MarceloVilela/devfinder-codeigniter.git
cd devfinder-codeigniter
cp .env.example .env
```

Editar `.env` (valores que **precisam** mudar em relação ao `.env.example` local — ver
`.env.example` para a lista completa de chaves):

| Chave | Valor local (dev) | Valor de produção |
|---|---|---|
| `CI_ENVIRONMENT` | `development` | `production` |
| `app.baseURL` | `http://localhost:8081/` | `http://<IP_ESTATICO>:8081/` |
| `database.default.password` / `MYSQL_PASSWORD` | `devfinder` | senha forte gerada (`openssl rand -hex 24`) — mudar em `docker-compose.yml` e `.env` juntos |
| `database.default.hostname` etc. | — | sem mudança — continua `mysql` (nome do serviço Docker) |
| `encryption.key` | `hex2bin:000...0` | gerar de verdade: `php spark key:generate` (ou `openssl rand -hex 32` prefixado com `hex2bin:`) |
| `auth.jwtSecret` | placeholder | `openssl rand -hex 32` |
| `auth.githubClientId` / `githubClientSecret` | vazio | criar um **GitHub OAuth App real** (github.com/settings/developers) com *Authorization callback URL* = `http://<IP_ESTATICO>:8081/v1/auth/github/callback` — não reaproveitar o app OAuth de dev local, o callback URL é diferente |
| `auth.webURL` | `http://localhost:3000` | manter como está OU remover se não houver frontend apontando pra cá — ver decisão de escopo (Fase 7: `devfinder-next` não é alterado) |
| `videorefresh.jsonbinApiKey` / `jsonbinIdSubs` | — | mesmas credenciais reais já usadas em dev (reaproveitar, mesmo bin JSONBin.io) |

**Nunca commitar o `.env` de produção** — ele já está fora do controle de versão (`.gitignore`
cobre `.env`, só `.env.example` é versionado).

Também editar `docker-compose.yml` na VM (não no repo local) para a senha real do MySQL, ou —
mais limpo — usar variáveis de ambiente do shell (`MYSQL_PASSWORD=$(openssl rand -hex 24)`)
antes do `docker compose up`, já que o compose já lê `${VAR:-default}` em alguns campos (ver
`UID`/`GID`); estender esse padrão pra senha do MySQL evita hardcode em texto plano no arquivo.

## 8. Subir a stack

```bash
docker compose up -d --build
docker compose ps   # confirmar os 3 serviços healthy/running
```

## 9. Rodar as migrations

```bash
docker compose exec app php spark migrate
```

Confirmar contra `specs/fase-1-data-model.md` — as 7 migrations relacionais devem aplicar sem
erro (mesmo comando já validado localmente nas Fases 1–2).

## 10. (Opcional) Popular dados de seed

Se o objetivo é ter o portfólio com dados reais visíveis (não banco vazio), rodar o comando de
seed/ingestão já existente:

```bash
docker compose exec app php spark video:refresh
```

Requer `videorefresh.jsonbinApiKey`/`jsonbinIdSubs` preenchidos no `.env` (passo 7).

## 11. Verificar os casos de aceite contra o deploy real

Reaproveitar os `.http` de `specs/acceptance/*.http` (mesmo padrão usado nas Fases 3/5/6, ver
`CLAUDE.md`), trocando o `baseURL` do ambiente `local` para `http://<IP_ESTATICO>:8081/`:

```bash
npx httpyac send specs/acceptance/*.http --env producao-gcp --all
```

Isso fecha a parte do critério de aceite revisado da Fase 7 (`../../plan.md`, item 7.5) que
depende do deploy real existir — "todos os casos de aceite verdes contra o deploy real, quando
o host real existir".

## 12. Ingestão recorrente (cron) — substituto do agendador local

Sem orquestrador externo, o cron do próprio host cobre o mesmo papel do `php spark
video:refresh` agendado (mesma função do `task.ts` do `../serverless`, ver `plan.md`):

```bash
# dentro da VM, crontab -e
0 */6 * * * cd ~/devfinder-codeigniter && docker compose exec -T app php spark video:refresh >> /var/log/devfinder-refresh.log 2>&1
```

## 13. Logs estruturados (item 7.1, já implementado) em produção

`app/Config/Logger.php` já grava JSON estruturado (ver `../fase-7-observabilidade-testes.md`).
Em produção, os logs ficam dentro do container `app` (`writable/logs/`, montado via bind mount
do próprio repo) — acessíveis via:

```bash
docker compose exec app tail -f writable/logs/log-$(date +%Y-%m-%d).log
```

Fora de escopo deste runbook: exportar para um agregador externo (Cloud Logging, etc.) — não é
necessário para o critério de aceite da Fase 7, que pede logs estruturados existirem, não
centralização.

## 14. Custo — o que monitorar continuamente

Nenhum passo acima deveria gerar cobrança se a cota Always Free for respeitada, mas a VM some
da cota se qualquer um destes limites for excedido — revisar o Billing → Budgets & alerts
(passo 0) periodicamente, não só na configuração inicial:

| Recurso | Limite sempre-gratuito | Risco se exceder |
|---|---|---|
| VM `e2-micro` | 1 instância, só nas 3 regiões dos EUA listadas | Trocar de região ou tipo de máquina passa a cobrar |
| Disco `pd-standard` | 30GB total | Redimensionar acima disso cobra pelo excedente |
| **Saída de rede (egress)** | **⚠️ 1GB/mês** (fora da América do Norte); tráfego dentro da América do Norte não conta | **Ponto crítico do plano** — o único limite que uso real de portfólio pode plausivelmente estourar (os outros três têm folga confortável). Primeiro a estourar se houver teste de carga (item 7.3, `autocannon`) repetido a partir de fora da América do Norte, ou tráfego de demo/portfólio vindo de visitantes fora da região |
| IP estático | Grátis **enquanto anexado a uma VM em execução** | Reservar um IP e não usá-lo (VM parada) cobra |

**Sobre o item marcado ⚠️**: é o ponto crítico de todo este runbook — os outros três recursos
(VM, disco, IP estático) não têm cenário plausível de estouro para este projeto; o egress sim.
Mitigação prática: evitar rodar `autocannon` (item 7.3) repetidamente contra o IP de produção a
partir de uma máquina fora da América do Norte — preferir gerar essa evidência de latência
contra o Docker Compose local (já é o padrão dos demais casos de aceite, ver `CLAUDE.md`) e
reservar o teste de carga contra o deploy real para poucas execuções pontuais.

## Rollback / desprovisionamento

Se a decisão mudar (ex.: trocar para Oracle Cloud, ou descartar deploy real) — desfazer na
ordem inversa evita deixar recurso órfão cobrando:

```bash
gcloud compute instances delete devfinder-vm --zone=us-central1-a
gcloud compute addresses delete devfinder-ip --region=us-central1
gcloud compute firewall-rules delete allow-devfinder-http
```

## Status

Runbook não executado ainda — registrado para quando o usuário decidir provisionar de fato
(mesma pendência de `../plan.md`, item 7.5: "Escolha do provedor... adiada pelo usuário").
Quando executado, atualizar este documento com o IP real, timestamps e qualquer desvio
encontrado durante a execução real (mesmo padrão de `../fase-7-observabilidade-testes.md` —
achados reais documentados, não só o plano ideal).
