# Fase 0 — Especificação (Spec) — Registro de execução

> Referência: [`../plan.md`](../plan.md), seção "1. Fase 0 — Especificação (contrato +
> ferramentas de contexto)".
> Status: **encerrada — artefatos gerados e revisados pelo usuário.**
> Executado em: 2026-08-24. Revisão humana concluída em 2026-08-24 (mesmo dia).

## O que foi feito

1. **Adaptado o contrato** de `../../serverless/specs/fase-0-openapi.yaml` (não re-derivado do
   zero do Swagger de `devfinder-api`) para [`fase-0-openapi.yaml`](./fase-0-openapi.yaml) —
   mesmas 30 operações, mesmos `schemas` (o contrato público não muda entre arquiteturas de
   backend). Ajustado: `servers` (Docker local + placeholder do deploy real, Heroku mantido
   como referência de comportamento), comentário da convenção `x-auth` (agora referencia
   Filters do CodeIgniter 4 em vez de middlewares Express), descrição de `bearerAuth` (payload
   `{ username }`, já adotando a decisão que a versão serverless tomou na própria Fase 4 dela
   — não o `{ id }` original do `devfinder-api`), e as descrições de `/channels/refresh` e
   `/video/refresh` (referenciam `../plan.md` e cron/`spark` em vez de `../ssr.md` e
   EventBridge Scheduler).
2. **Validado sintaticamente** o YAML (`npx js-yaml specs/fase-0-openapi.yaml`) — passou.
3. **Adaptados os casos de aceite** de `../../serverless/specs/acceptance/*.http` (19
   arquivos) para [`acceptance/`](./acceptance/) — copiados como estão onde o conteúdo é
   puramente de domínio (14 arquivos, sem menção a AWS/Lambda/DynamoDB), e reescritos onde
   dependiam da stack serverless (5 arquivos: `auth-github.http`, `me.http`,
   `video-refresh.http`, `video-refresh-http.http`, `feed-trending.http` — trocado
   `serverless offline`/`api/.env` por `docker compose`/`.env` na raiz, geração de JWT via
   `node -e jsonwebtoken` por `php -r firebase/php-jwt`, Lambda agendada por Command `spark` +
   cron, corpo de erro do API Gateway por "definido na Fase 4", cursor DynamoDB por
   `LIMIT/OFFSET` MySQL). `http-client.env.json` recriado com ambientes `local`/`real` (em vez
   de `local`/`aws`); `http-client.private.env.json` **não copiado** (continha JWTs reais
   assinados com o `APP_SECRET` do outro projeto — sem validade aqui, e é gitignored por
   design). `README.md` de `acceptance/` reescrito para a stack Docker/MySQL/CodeIgniter.
4. **Context7 — cobertura de CodeIgniter 4 verificada, mas não via MCP nesta sessão**: o
   servidor MCP do Context7 não está configurado neste ambiente (`ToolSearch` não encontrou
   nenhuma ferramenta `resolve-library-id`/`context7`) — não foi possível chamar a ferramenta
   real. Como evidência substituta, consultado o endpoint público
   `https://context7.com/api/v1/search?query=codeigniter`: **confirma cobertura real** —
   3 entradas de CodeIgniter (UserGuide 3, CodeIgniter 4, "Version-4"), mais as extensões
   oficiais **Shield** (auth), **Tasks** (scheduler — relevante para o Command da Fase 6),
   **Settings** e **Queue**, várias libs da comunidade (Relations, HTMX, UUID, Signed URL), e
   um achado extra não previsto: uma entrada **"Docker Environment"** descrita como "Complete
   development setup with PHP 8.3, Nginx, MySQL, Redis" — candidata a consultar na Fase 2.
   Isso corrige o "não confirmada ainda" de `CLAUDE.md`/`infra-pending.md` (2026-08-23) para
   "confirmada por evidência de API pública, MCP ainda não instalado nesta sessão".
   **Atualização, mesmo dia**: MCP instalado — `claude mcp add --scope project context7 --
   npx -y @upstash/context7-mcp`, escrito em `.mcp.json` (raiz do projeto, scope `project` =
   versionado, disponível pra qualquer sessão futura aberta aqui). Rodado de dentro de uma
   sessão com `../serverless` como raiz (git deste projeto ainda não existe), por isso ficou
   **pendente de aprovação humana** (`⏸ Pending approval`) e não pôde ser exercitado
   (`resolve-library-id`) ainda nesta mesma execução — precisa de uma sessão `claude` aberta
   com `php-codei` como raiz pra aprovar.
   **Atualização, 2026-08-24**: aprovado numa sessão `claude` com `php-codei` como raiz;
   `resolve-library-id("CodeIgniter 4")` testado e retornando resultados (`codeigniter4/codeigniter4`,
   `codeigniter4/userguide`, `websites/codeigniter_user_guide`, `codeigniter4/shield`,
   `codeigniter4/tasks`). Pendência encerrada — ver `infra-pending.md`, item 2.
5. **Skill `yasserstudio/codeigniter-skills` instalada**: `npx skills add
   yasserstudio/codeigniter-skills`, rodado na raiz do projeto. Resultado: 1 skill
   ("codeigniter", cobrindo CI3 e CI4 — controllers, models, views, routing, query builder,
   migrations, libraries, helpers, segurança, upload, email, cache, testes, services, events,
   CLI, deploy) instalada em `.agents/skills/codeigniter/`, symlinked para
   `.claude/skills/codeigniter` (Claude Code) e mais 12 agentes. Avaliação de risco do
   instalador (Gen/Socket/Snyk): **Safe, 0 alerts, Low Risk**.

## Decisões em aberto deixadas para revisão humana

Herdadas de `../../serverless/specs/fase-0-especificacao.md` (mesmo domínio, mesma decisão
ainda não tomada lá nem aqui) + uma nova desta fase:

1. **`GET /description/category`** — mesmo comportamento acidental do Express
   (`res.send(array)` serializado como string) a decidir: preservar por paridade estrita ou
   normalizar para JSON de verdade. Ainda sem decisão em nenhum dos dois projetos.
2. **Busca textual (`GET /search`)** — decidir entre `FULLTEXT INDEX` do MySQL e `LIKE`
   simples na Fase 1 (ver nota na própria `fase-0-openapi.yaml`).
3. **URL do deploy real** — `servers` em `fase-0-openapi.yaml` tem placeholder
   (`https://TBD.exemplo.com/v1`) até a Fase 2 provisionar o host de verdade.
4. **`http-client.private.env.json`** só pode ser gerado de verdade a partir da Fase 4 (é
   quando `APP_SECRET`/JWT passam a existir) — os casos de aceite que dependem de auth não são
   executáveis ainda, só documentados.

## Critério de aceite da Fase 0 (de `plan.md`)

- [x] Toda rota de `devfinder-api/src/routes/index.ts` (+ `socialLoginGithub.ts`) tem entrada
  correspondente em `fase-0-openapi.yaml` — herdado 1:1 da versão serverless, 30/30.
- [x] Pelo menos um caso de aceite por endpoint — `acceptance/` cobre as mesmas rotas que a
  versão serverless já cobria (19 arquivos, múltiplos casos por arquivo). **Escopo explícito
  da Fase 0** (decisão do usuário, 2026-08-24): registrar os casos é suficiente nesta fase;
  não são reverificados rota a rota nem executados agora (não há API rodando ainda) — a
  verificação de verdade acontece quando os endpoints existirem de fato, principalmente na
  Fase 3 (leitura pública) e Fase 4+ (rotas autenticadas).
- [x] Context7/skill CodeIgniter avaliados e decisão registrada — feito (item 4 e 5 acima).
- [x] Specs revisadas e aprovadas (checklist manual) — **feito em 2026-08-24**. Revisão trouxe
  4 pontos, todos endereçados na própria conversa de execução: (1) referências ao Oracle Cloud
  removidas de todos os arquivos versionados — eram de um contexto que não se aplica a este
  projeto, host real fica como decisão em aberto pra Fase 2; (2) Context7 MCP instalado
  (`.mcp.json`, scope `project`), pendente só de aprovação humana na próxima sessão aberta em
  `php-codei`; (3) cobertura dos 30 endpoints em `acceptance/` — registrar é suficiente nesta
  fase, execução real fica pra quando os endpoints existirem (Fase 3+); (4) esta própria
  revisão. **Fase 0 formalmente encerrada.**

## Arquivos tocados nesta fase

- Criados: `fase-0-openapi.yaml`, `fase-0-especificacao.md` (este arquivo),
  `acceptance/*.http` (19 arquivos), `acceptance/README.md`, `acceptance/http-client.env.json`.
- Instalado: `.agents/skills/codeigniter/` + symlink `.claude/skills/codeigniter/` (via `npx
  skills add`); `.mcp.json` (raiz do projeto, via `claude mcp add --scope project context7`) —
  aprovado em 2026-08-24, ver item 4 acima.
- Atualizado: `.gitignore` (raiz do projeto — adicionadas entradas `.env` e
  `specs/acceptance/http-client.private.env.json`).
- Nenhum arquivo em `../../devfinder-api` ou `../../serverless` foi alterado (fase é somente
  leitura sobre os dois).
- **Não tocado**: git local (ainda não inicializado — branch/commit/push/PR desta fase ficam
  para você, ver seu runbook pessoal não versionado).
