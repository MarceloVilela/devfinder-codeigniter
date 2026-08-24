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
| 2 | Infraestrutura base (scaffold + Docker + deploy real) | ✅ gerada 2026-08-24 — host real adiado (decisão do usuário), pendente PR |
| 3 | Migração dos endpoints públicos de leitura | ⬜ não iniciada |
| 4 | Autenticação (GitHub OAuth + JWT via Filters) | ⬜ não iniciada |
| 5 | Endpoints autenticados de escrita e relacionamento | ⬜ não iniciada |
| 6 | Ingestão em lote (`spark` command + cron) | ⬜ não iniciada |
| 7 | Observabilidade, testes e corte (cutover) | ⬜ não iniciada |

Fase 0 (PR #1) e Fase 1 (PR #2) encerradas e mergeadas em 2026-08-24. Fase 2 (scaffold CI4 +
Docker + MySQL 8.4 + CI, na raiz do projeto — não em `api/`, ver `fase-2-scaffold-infra.md`)
executada e validada de ponta a ponta no mesmo dia: `docker compose up` sobe os 3 serviços,
as 7 migrations da Fase 1 rodam contra MySQL real, health-check responde. Host real
provisionado fica pra depois (decisão explícita do usuário — duas opções sempre-gratuitas
levantadas em `infra-pending.md`). Próximo passo: Fase 3 (endpoints públicos de leitura).

## Artefatos gerados

| Artefato | Status |
|---|---|
| `infra-pending.md` | ✅ escrito 2026-08-23, atualizado 2026-08-24 — hospedagem real ainda em aberto (decisão adiada pra Fase 2, referência anterior a um provedor específico removida por não se aplicar a este projeto), cobertura do Context7/DeepWiki por repositório da stack, e a skill `yasserstudio/codeigniter-skills`. |
| `fase-0-openapi.yaml` | ✅ gerado e revisado 2026-08-24 (Fase 0) — adaptado de `../../serverless/specs/fase-0-openapi.yaml`, 30 operações, cobertura completa. |
| `fase-0-especificacao.md` | ✅ registro de execução da Fase 0, **encerrada e revisada** 2026-08-24 — adaptações feitas, Context7 MCP instalado e aprovado (`.mcp.json`, `resolve-library-id` testado), skill `codeigniter` instalada, decisões em aberto documentadas. |
| `acceptance/*.http` | ✅ gerado 2026-08-24 (Fase 0) — 19 casos de aceite adaptados de `../../serverless/specs/acceptance/`, mesma cobertura de endpoints. Casos que dependem de auth só executáveis a partir da Fase 4. |
| `fase-1-data-model.md` | ✅ gerado, revisado e **validado contra MySQL real na Fase 2** (2026-08-24) — 7 tabelas relacionais (`devs`, `channels`, `tags`, `channel_tag`, `videos`, `dev_reactions`, `channel_reactions`), evidência do dump real do projeto irmão (colisão de `channels.name`, campo vestigial `alternativeLink`, extração de `youtube_id`), migrations CI4 completas com soft delete + `UNIQUE` em `channels.name`/`link`, cobertura de todas as 30 operações do OpenAPI. |
| `fase-2-scaffold-infra.md` | ✅ gerado 2026-08-24 (Fase 2) — scaffold CI4 na raiz (não `api/`), Docker Compose (PHP-FPM 8.3 + Nginx + MySQL 8.4) validado de ponta a ponta, CI (GitHub Actions), achado real de MySQL/InnoDB (CHECK + ON UPDATE CASCADE incompatíveis), pesquisa de hospedagem sempre-gratuita (decisão adiada). |
