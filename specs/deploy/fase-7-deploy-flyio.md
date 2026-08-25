# Avaliação — Fly.io como candidato ao item 7.5 (deploy real)

> Caderno de decisão (não é spec de fase executada — mesmo papel de
> [`infra-pending.md`](./infra-pending.md) item 1 e de
> [`fase-7-deploy-render.md`](./fase-7-deploy-render.md), do qual este documento é irmão).
> Item 7.5 de [`../plan.md`](../plan.md) segue **sem provedor escolhido**; este documento
> registra a avaliação de mais um candidato (Fly.io), não uma decisão.

## Origem

Opção 5 da nota local não versionada `specs/zdeploy__.md` (`**/*__.*`, gitignored — pesquisa
de terceiros trazida pelo usuário, que já descrevia Fly.io como "free tier generoso" —
desatualizado, ver abaixo).

## Verificação (2026-08-24, via WebSearch — não memória)

| Aspecto | Achado |
|---|---|
| **Free tier atual** | **Não existe mais para contas novas.** Removido em 2024. |
| **O que resta pra conta nova** | Trial de **2 horas de VM ou 7 dias**, o que vier primeiro — depois, tudo é cobrado. |
| **Cota grátis antiga** | 3 VMs sempre-ativas (256MB RAM cada) + 3GB de armazenamento — existiu, mas só é honrada em **contas legadas** anteriores à mudança de plano. Contas novas não têm acesso. |
| **Postgres/MySQL grátis** | Nenhum banco gerenciado grátis permanente em 2026. |

O repositório remoto deste projeto foi criado em 2026-08-24 (`../CLAUDE.md`) — qualquer conta
Fly.io usada para este deploy seria necessariamente nova, sem direito à cota legada.

## Por que importa para este projeto especificamente

Nem chega a ser uma questão de arquitetura (Docker, MySQL, CLI) como nos outros candidatos —
o motivo de descarte é anterior a qualquer avaliação técnica: não há camada gratuita permanente
pra testar. A nota de origem (`zdeploy__.md`) descreve Fly.io como "free tier generoso", o que
era verdade até 2024 — informação desatualizada, confirma por que este projeto tem a disciplina
de reverificar cada opção via busca datada em vez de confiar em pesquisa de terceiros ou
memória do modelo (mesmo padrão já registrado em `infra-pending.md`, itens 1 e 2, sobre o corte
sem aviso da Oracle Cloud e do Context7).

## Conclusão

**Descartado.** Sem camada sempre-gratuita para contas novas — só trial de $5/2h/7 dias. Não
atende a Regra de custo do `../CLAUDE.md` de forma alguma, nem entra no mérito técnico
(Docker/MySQL/CLI, que aliás seriam adequados — Fly.io suportaria o `docker-compose.yml` bem
se tivesse camada grátis real).

Fontes: [Fly.io Pricing 2026: Free Tier, Postgres & Alternatives – srvrlss.io](https://www.srvrlss.io/provider/fly/),
[7 Fly.io Alternatives in 2026: Real Pricing After the Free Tier Died – ExpressTech](https://expresstech.io/7-fly-io-alternatives-in-2026-real-pricing-after-the-free-tier-died/),
[Fly.io Free Tier 2026: What's Left After the Cuts? – saaspricepulse](https://www.saaspricepulse.com/tools/flyio).
