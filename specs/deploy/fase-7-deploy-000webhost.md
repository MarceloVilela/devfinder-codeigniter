# Avaliação — 000webhost (e AwardSpace) como candidato ao item 7.5 (deploy real)

> Caderno de decisão (não é spec de fase executada — mesmo papel de
> [`infra-pending.md`](./infra-pending.md) item 1 e de
> [`fase-7-deploy-render.md`](./fase-7-deploy-render.md), do qual este documento é irmão).
> Item 7.5 de [`../plan.md`](../plan.md) segue **sem provedor escolhido**; este documento
> registra a avaliação de mais um candidato (000webhost), não uma decisão.

## Origem

Opções 2 e 6 da nota local não versionada `specs/zdeploy__.md` (`**/*__.*`, gitignored —
pesquisa de terceiros trazida pelo usuário): "000webhost" e "000webhost / AwardSpace" (esta
última listada na nota original só como "alternativas similares ao InfinityFree", sem detalhe
próprio).

## Verificação (2026-08-24, via WebSearch — não memória)

**000webhost foi descontinuado pela Hostinger em outubro de 2024.** Não é mais uma opção —
qualquer artigo de 2025/2026 que ainda o recomenda está desatualizado (várias das fontes
buscadas confirmam o encerramento e apontam alternativas). Não há conta nova possível de
criar.

Para registro (como era, antes do encerramento): PHP + MySQL + cPanel, sem SSH, sem cron jobs
no plano grátis, 3GB de banda/mês — mesmas limitações estruturais do InfinityFree (ver
[`fase-7-deploy-infinityfree.md`](./fase-7-deploy-infinityfree.md)), então mesmo se estivesse
ativo o veredito técnico seria o mesmo: incompatível com os comandos Spark CLI (`migrate`,
`video:refresh`) que este projeto depende.

**AwardSpace**: não pesquisado em profundidade — a nota de origem só o cita de passagem como
"similar ao InfinityFree", sem detalhe específico verificado. Dado que a limitação decisiva do
InfinityFree (falta de SSH/CLI) é típica de hospedagem compartilhada cPanel em geral, é
improvável que um provedor "similar" escape dela — mas isso não foi confirmado com fonte
própria, e não vale a pena aprofundar dado que a família inteira desse padrão (cPanel
compartilhado sem SSH) já foi descartada pela mesma razão estrutural.

## Conclusão

**Descartado.** 000webhost não existe mais como opção (encerrado 2024). AwardSpace não foi
aprofundado por pertencer à mesma família de hospedagem compartilhada sem SSH/cron que já
reprovou no InfinityFree pelo mesmo motivo estrutural (sem CLI, sem como rodar `php spark
migrate`/`video:refresh`) — não há indício de que seria diferente, e pesquisar mais um provedor
idêntico em limitação não muda a conclusão.

Fontes: [Free Web Hosting 2026: Ranked By Real Limits – webhostmost](https://blog.webhostmost.com/best-free-web-hosting/),
[What Happened to 000WebHost? – androidexperto](https://androidexperto.com/what-happened-to-000webhost-find-out-the-best-alternative/),
[000webhost Free Hosting: Status, Risks, and Best Alternatives (2026) – middlehost](https://middlehost.com/blog/000webhost-free-hosting-alternative/).
