# Avaliação — InfinityFree como candidato ao item 7.5 (deploy real)

> Caderno de decisão (não é spec de fase executada — mesmo papel de
> [`infra-pending.md`](./infra-pending.md) item 1 e de
> [`fase-7-deploy-render.md`](./fase-7-deploy-render.md), do qual este documento é irmão).
> Item 7.5 de [`../plan.md`](../plan.md) segue **sem provedor escolhido**; este documento
> registra a avaliação de mais um candidato (InfinityFree), não uma decisão.

## Origem

Opção 1 da nota local não versionada `specs/zdeploy__.md` (`**/*__.*`, gitignored — pesquisa de
terceiros trazida pelo usuário, tratada como ponto de partida, não fonte confirmada).

## Verificação (2026-08-24, via WebSearch — não memória)

| Aspecto | Achado |
|---|---|
| **PHP/MySQL** | Suporta PHP 8.3 e MySQL nativamente, até 400 bancos MySQL por conta, painel estilo cPanel, sem cartão de crédito. |
| **Acesso SSH** | **Não existe no plano grátis.** Sem linha de comando no servidor — nada de Composer, Git deploy ou qualquer CLI direto no host. |
| **Cron jobs** | **Não oferecidos no plano grátis.** |
| **Docker** | Hospedagem compartilhada tradicional (estilo cPanel) — sem suporte a container. |
| **Upload** | Só FTP. |
| **Limites de tráfego** | Sem limite de banda rígido, mas **30.000 hits/dia** (hard cap) e 80.000 inodes por conta. |
| **Avaliação geral** | Fontes dão nota baixa (~1.8/5) para qualquer uso além de site estático/demo de baixo tráfego — não é qualificado como apto a produção real com cron/login/tráfego. |

## Por que importa para este projeto especificamente

Este projeto depende de CLI em dois pontos que não são opcionais:

1. **`php spark migrate`** — as 7 migrations relacionais (`specs/fase-1-data-model.md`) são
   aplicadas via Spark CLI, não SQL manual acoplado ao deploy.
2. **`php spark video:refresh`** — o comando de ingestão em lote da Fase 6
   (`fase-6-ingestao-lote.md`), pensado pra rodar via cron, é o mecanismo real de atualização
   de dados do domínio (mesmo papel do `task.ts` do `../serverless`).

Sem SSH, não há como rodar nenhum dos dois comandos Spark no host — só FTP de arquivos
estáticos. Sem cron, mesmo que o comando rodasse manualmente uma vez, não haveria como
reagendar a ingestão. E sem Docker, o `docker-compose.yml` (PHP-FPM + Nginx + MySQL) da Fase 2
não se aplica de forma alguma — o modelo de deploy do InfinityFree é hospedagem compartilhada
clássica, arquitetura incompatível com o que já foi construído, não uma questão de config.

## Re-avaliação — ingestão via GitHub Actions em vez de cron no host (2026-08-24)

Ideia do usuário: em vez de depender do cron do host (que o InfinityFree não oferece), usar um
workflow do GitHub Actions com `schedule:` batendo periodicamente no endpoint HTTP público
`POST /video/refresh`. Isso **de fato resolve o obstáculo do cron sem mudar código nenhum** —
`App\Libraries\VideoIngestor` já foi construído na Fase 6 pra ser disparado tanto por
`php spark video:refresh` (cron) quanto por `POST /video/refresh` (HTTP), exatamente pra este
tipo de cenário (`fase-6-ingestao-lote.md`). O obstáculo de migrations via SSH também tem
workaround (manual, uma vez: `migrate` local + export SQL + import via phpMyAdmin) — mais
fricção, mas não bloqueante pra um schema que muda pouco depois do setup inicial.

**Verificado agora, achado novo e mais grave**: o fórum oficial do InfinityFree tem múltiplos
relatos (inclusive de 2025) de **chamadas de saída (`cURL`) bloqueadas ou intermitentes**
("PHP scripts sendo bloqueados pelo sistema de segurança do servidor", 403 ao chamar APIs
externas) — descrito como medida de segurança da própria hospedagem contra abuso, não um bug
pontual. Isso não é sobre deploy/tooling, é sobre **funcionalidade já implementada do app**:

- **Fase 4 (GitHub OAuth)**: o fluxo de login faz uma chamada de saída do PHP pra
  `api.github.com` (troca de code por token + busca de perfil) a cada login real.
- **Fase 6 (ingestão)**: `VideoIngestor` faz chamada de saída pro JSONBin.io pra buscar
  candidatos de vídeo.

A ideia do GitHub Actions não resolve isso — a chamada de saída acontece dentro do PHP rodando
*no InfinityFree*, não no runner do Actions (que só bateria no endpoint HTTP do próprio app,
disparando a ingestão que por sua vez tentaria sair pro JSONBin.io a partir do InfinityFree).
Se essas chamadas forem bloqueadas ou instáveis, as duas features mais centrais do projeto
(autenticação real, ingestão real) ficam comprometidas — pior que perder conveniência de CI.

Confirmado também (fórum oficial): **sem acesso remoto ao MySQL** — só acessível por PHP script
rodando dentro da própria hospedagem ou via phpMyAdmin. Não quebra a ideia do GitHub Actions
(que bateria no HTTP do app, não no banco direto), mas reforça que qualquer automação de
migration via CLI remota continua impossível.

## Conclusão

**Ainda não recomendado, mas por um motivo diferente do original.** A combinação SSH
ausente + sem cron deixou de ser o bloqueio decisivo — a ideia de disparar
`POST /video/refresh` via GitHub Actions `schedule:` resolve o cron de verdade, e migrations
têm workaround manual. O bloqueio agora é a **confiabilidade de chamadas de saída do PHP**
(GitHub OAuth, JSONBin.io) — não confirmado como bloqueio permanente/universal (relatos de
fórum são inconsistentes, "às vezes funciona"), então **não é definitivo como os outros
descartes desta pesquisa** (000webhost morto, db4free morto): seria preciso um teste empírico
real (subir um script mínimo com `curl_exec()` pra `api.github.com` e pro JSONBin.io na conta
InfinityFree e observar o resultado) antes de comprometer o plano de deploy nisso. Sem esse
teste, o risco de duas features centrais do portfólio (login real, ingestão real) ficarem
quebradas ou instáveis em produção é alto demais pra recomendar sem verificação direta.

Fontes: [InfinityFree Review 2026 – webhostmost](https://blog.webhostmost.com/infinityfree-review-2026/),
[InfinityFree Hosting Review: Is It REALLY Free? [2026] – positioniseverything](https://www.positioniseverything.net/infinityfree-hosting-review-is-it-really-free-2026/),
[10 Best Free PHP Web Hosts for 2026 – websiteplanet](https://www.websiteplanet.com/blog/best-free-php-hosting/),
[Blocked Requests – InfinityFree Forum](https://forum.infinityfree.com/t/blocked-requests/104505),
[403 Forbidden Error on PHP cURL Script – InfinityFree Forum](https://forum.infinityfree.com/t/403-forbidden-error-on-php-curl-script/114145),
[Can I connect to Remote MySQL DB hosted on external hosting service? – InfinityFree Forum](https://forum.infinityfree.com/t/can-i-connect-to-remote-mysql-db-hosted-on-external-hosting-service/106014).
