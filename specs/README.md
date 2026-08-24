# Specs — status

Esta pasta é o destino dos artefatos de especificação do plano em [`../plan.md`](../plan.md),
e também guarda o registro detalhado (plano/execução) de cada fase — mesmo padrão adotado em
`../../serverless/specs/`.

**Não fazem parte do plano de fases** (cadernos de decisão, não artefatos a serem seguidos por
uma sessão futura como se fossem spec de fase):
[`infra-pending.md`](./infra-pending.md) — hospedagem real sempre-gratuita (provedor ainda em
aberto, decisão adiada pra Fase 2), cobertura do Context7/DeepWiki para a stack (e por que
Context7 não serve para Docker), e a skill de agente `yasserstudio/codeigniter-skills`
instalada na Fase 0.

Existem também notas pessoais locais não versionadas (padrão `.gitignore`: `**/*__.*`) —
runbook humano de git/PR por fase e afins. Não citadas aqui por não fazerem parte do
repositório.

## Status das fases (visão geral)

| Fase | Nome | Status |
|---|---|---|
| 0 | Especificação (contrato + ferramentas de contexto) | ✅ encerrada — revisada 2026-08-24 |
| 1 | Especificação de dados (MySQL relacional) | ✅ gerada e revisada 2026-08-24 — pendente PR |
| 2 | Infraestrutura base (scaffold + Docker + deploy real) | ⬜ não iniciada |
| 3 | Migração dos endpoints públicos de leitura | ⬜ não iniciada |
| 4 | Autenticação (GitHub OAuth + JWT via Filters) | ⬜ não iniciada |
| 5 | Endpoints autenticados de escrita e relacionamento | ⬜ não iniciada |
| 6 | Ingestão em lote (`spark` command + cron) | ⬜ não iniciada |
| 7 | Observabilidade, testes e corte (cutover) | ⬜ não iniciada |

Fase 0 encerrada, revisada e mergeada (PR #1) em 2026-08-24. Fase 1 (modelo de dados MySQL)
gerada no mesmo dia, evidência-baseada (schema Mongoose + dump real de produção do projeto
irmão + código dos controllers) — falta só a revisão humana antes de abrir o PR
`fase-1-data-model → main`. Próximo passo depois disso: Fase 2 (scaffold), que não pode
começar antes desta spec estar aprovada (ver `../CLAUDE.md`, "Regra do projeto").

## Artefatos gerados

| Artefato | Status |
|---|---|
| `infra-pending.md` | ✅ escrito 2026-08-23, atualizado 2026-08-24 — hospedagem real ainda em aberto (decisão adiada pra Fase 2, referência anterior a um provedor específico removida por não se aplicar a este projeto), cobertura do Context7/DeepWiki por repositório da stack, e a skill `yasserstudio/codeigniter-skills`. |
| `fase-0-openapi.yaml` | ✅ gerado e revisado 2026-08-24 (Fase 0) — adaptado de `../../serverless/specs/fase-0-openapi.yaml`, 30 operações, cobertura completa. |
| `fase-0-especificacao.md` | ✅ registro de execução da Fase 0, **encerrada e revisada** 2026-08-24 — adaptações feitas, Context7 MCP instalado e aprovado (`.mcp.json`, `resolve-library-id` testado), skill `codeigniter` instalada, decisões em aberto documentadas. |
| `acceptance/*.http` | ✅ gerado 2026-08-24 (Fase 0) — 19 casos de aceite adaptados de `../../serverless/specs/acceptance/`, mesma cobertura de endpoints. Casos que dependem de auth só executáveis a partir da Fase 4. |
| `fase-1-data-model.md` | ✅ gerado e revisado 2026-08-24 (Fase 1) — 7 tabelas relacionais (`devs`, `channels`, `tags`, `channel_tag`, `videos`, `dev_reactions`, `channel_reactions`), evidência do dump real do projeto irmão (colisão de `channels.name`, campo vestigial `alternativeLink`, extração de `youtube_id`), migrations CI4 completas com soft delete + `UNIQUE` em `channels.name`/`link`, cobertura de todas as 30 operações do OpenAPI. |
