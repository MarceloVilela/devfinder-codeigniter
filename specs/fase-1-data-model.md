# Fase 1 — Especificação de dados (MySQL relacional)

> Fase 1 de [`../plan.md`](../plan.md), seção "2. Fase 1 — Especificação de dados (MySQL
> relacional)". Depende de [`fase-0-openapi.yaml`](./fase-0-openapi.yaml) (Fase 0, PR #1
> mergeado em 2026-08-24). Nenhum código de scaffold (Fase 2) deve começar antes deste
> documento estar escrito e revisado — ver `../CLAUDE.md`, "Regra do projeto".
>
> Foco explícito desta execução (pedido do usuário): **consistência de banco, normalização,
> convenções MySQL/PSR, e prioridade máxima para os padrões idiomáticos do CodeIgniter 4** —
> não uma tradução literal 1:1 do schema Mongoose, e não uma cópia do design DynamoDB
> single-table de `../../serverless/specs/fase-1-data-model.md` (esse é otimizado para chave
> particionada; aqui o motor é relacional, o design correto é outro).

## Entidades-fonte confirmadas

Lidas campo a campo em `../../devfinder-api/src/models/{Dev,Channel,Video}.ts` e cruzadas
contra os controllers reais (`src/controllers/**`) e um dump real de produção (ver seção
seguinte) — não assumidas a partir do design já feito para DynamoDB.

| Model Mongoose | Campos (schema) | Obrigatório no schema? |
|---|---|---|
| `Dev` | `name`, `user`, `bio`, `avatar`, `likes[]`, `deslikes[]`, `follow[]`, `ignore[]` | `name`, `user`, `avatar` sim; `bio` não |
| `Channel` | `name`, `link`, `avatar`, `userGithub`, `description`, `category`, `tags[]`, `likes[]`, `deslikes[]` | `name`, `link`, `category` sim; resto não |
| `Video` | `title`, `url`, `channel_id`, `channel`, `channel_url`, `channel_icon`, `thumbnail`, `viewnum`, `date` | `title`, `url`, `channel`, `channel_url`, `thumbnail` sim |

## Evidência do dump real (agregada — sem dados pessoais)

`../../serverless/specs/seed/*.json` é um dump real de produção (Mongo Extended JSON),
**gitignored no projeto irmão por risco de marca/LGPD** (ver
`../../serverless/specs/seed/README.md`) — usado aqui só para inspeção local, nenhum dado
identificável (nome de canal, bio, avatar) é citado abaixo, só estatísticas agregadas. Isso
substitui suposição por evidência para as decisões de constraint (unique, nullable) a seguir.

| Coleção | Registros | Achado |
|---|---|---|
| `devs` | 40 | `user` sem nenhuma duplicata (0/40) — `findOrCreateDev` já deduplica por `user.toLowerCase()` antes de criar. `bio` ausente em 8/40 (20%). |
| `channels` | 189 | **`name` colide**: um nome aparece em 4 documentos diferentes (~2%) — confirma o mesmo achado já registrado em `../../serverless/specs/fase-1-data-model.md`. `link` também colide: 3 pares de duplicata. Isso é dado sujo herdado do Mongo (sem constraint lá para impedir), não uma característica do domínio — decisão desta fase (ver tabela `channels` abaixo) é **corrigir isso com `UNIQUE` real e uma limpeza de import**, não perpetuar a ausência de constraint. `tags` ausente em 3/189. `userGithub` presente em só 34/189 (18%) — claramente opcional na prática, não só no schema. **`alternativeLink` existe em 160/189 (85%) apesar de não estar declarado em `Channel.ts`** — campo vestigial de uma versão anterior do schema, mas com dado real por trás e **usado ativamente em 3 pontos de código** (`ChannelController.store`, `VideoController.store`, `VideoRefreshController.addVideo` — todos fazem `$or: [name, link, alternativeLink]` na deduplicação). Precisa entrar no modelo relacional; ignorá-lo quebraria a paridade de dedup. |
| `videos` | 500 (amostra) | `url` sem nenhuma duplicata. Extração de `youtube_id` via `v=` (mesma regra do `videoId` documentado no design DynamoDB irmão) funciona em **100% dos 500 registros**, sem colisão. `viewnum`, `date`, `channel_icon` **ausentes em 500/500** — campos declarados no schema mas nunca preenchidos na prática. |

## Modelo relacional proposto

7 tabelas, InnoDB (obrigatório para `FOREIGN KEY`), `utf8mb4`/`utf8mb4_0900_ai_ci` (definido
uma vez em `app/Config/Database.php` na Fase 2, não por tabela — decisão fechada na execução
da Fase 2: `utf8mb4_0900_ai_ci` é a collation nativa recomendada do MySQL 8.0+, acento/case
insensitive, sem custo de `LOWER()` em lookups por `name`/`link`). Normalização: arrays
embutidos do Mongoose (`tags[]`, `likes[]`, `deslikes[]`, `follow[]`, `ignore[]`) viram
tabelas próprias — nenhuma coluna JSON/CSV como substituto de relação, para manter integridade
referencial de verdade (FK, não uma convenção de aplicação).

### `devs`

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | PK, auto_increment |
| `username` | `VARCHAR(190)` | NOT NULL, **UNIQUE** — evidência: 0 colisões em produção, e `findOrCreateDev` já assume unicidade antes de criar. Mapeia para `Dev.user` no contrato público (não renomear a coluna — só o serializer da Fase 3+ expõe como `user`). |
| `name` | `VARCHAR(255)` | NOT NULL |
| `bio` | `TEXT` | NULL |
| `avatar` | `VARCHAR(500)` | NOT NULL |
| `created_at` / `updated_at` | `DATETIME` | NULL (padrão `$useTimestamps` do CI4) |
| `deleted_at` | `DATETIME` | NULL — soft delete (ver "Consistência de banco") |

### `channels`

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | PK, auto_increment |
| `name` | `VARCHAR(255)` | NOT NULL, **UNIQUE** — decisão revista após revisão humana: apesar da colisão real (4x um mesmo nome no dump), a prioridade desta fase é consistência de banco, não perpetuar a ausência de constraint do Mongo. Import do dado existente precisa de limpeza prévia (ver nota de dedup abaixo); dedup de escrita nova (`POST /channels`) passa a poder confiar na constraint em vez de só na checagem de aplicação. |
| `link` | `VARCHAR(500)` | NOT NULL, **UNIQUE** (mesma decisão — 3 duplicatas reais no dump, mesma limpeza de import) |
| `alternative_link` | `VARCHAR(500)` | NULL — achado do dump real (85% preenchido), usado em dedup por 3 controllers na origem. Sem este campo a paridade de `POST /video`/`POST /video/refresh` quebra. **Não é `UNIQUE`**: é um campo de apoio a dedup por múltiplas origens possíveis do mesmo canal, não um identificador primário — nada garante que dois canais legítimos não apontem pra URLs alternativas coincidentes. |
| `user_github` | `VARCHAR(190)` | NULL (18% de preenchimento real) |
| `description` | `TEXT` | NULL |
| `category` | `VARCHAR(190)` | NOT NULL |
| `avatar` | `VARCHAR(500)` | NULL |
| `created_at` / `updated_at` | `DATETIME` | NULL |
| `deleted_at` | `DATETIME` | NULL — soft delete |

**Limpeza de import exigida pela `UNIQUE`**: a Fase 2 (scaffold/seed) precisa rodar uma
deduplicação **antes** do `INSERT` inicial — mesmo critério já usado pelo projeto irmão em
`../../serverless/api/scripts/seed-local.ts` para o mesmo problema: manter 1 linha por
`name`/`link` colidente (critério: `updatedAt` mais recente), descartar as demais com log
explícito (não descarte silencioso). Isso é responsabilidade do script de seed da Fase 2, não
desta spec — registrado aqui para não se perder.

**Trade-off aceito, documentado**: `UNIQUE` + soft delete (`deleted_at`, ver abaixo) juntos
significam que um `name`/`link` de um canal soft-deletado continua ocupando a constraint —
recriar um canal com o mesmo nome exige primeiro restaurar (ou hard-delete) o registro antigo,
já que MySQL não tem índice único parcial/condicional (diferente de Postgres). Aceito porque
recategorizar/recriar um canal com nome idêntico ao de um removido não é um fluxo hoje
existente no frontend — revisitar só se isso virar um requisito real.

### `tags` + `channel_tag` (substitui `Channel.tags[]`)

Normalização 3NF em vez de coluna JSON/CSV — permite `WHERE tag = ?` indexado e evita
duplicar o texto da tag em cada canal.

| Tabela | Colunas | Constraint |
|---|---|---|
| `tags` | `id` PK, `name VARCHAR(100)`, `deleted_at` | `name` **UNIQUE** (tag é um vocabulário controlado por texto — sem evidência de colisão de *caso* real a tratar aqui, diferente de `channels.name`); `deleted_at` NULL — soft delete |
| `channel_tag` | `channel_id`, `tag_id` | `PRIMARY KEY (channel_id, tag_id)`, ambos FK `ON DELETE CASCADE` |

### `videos`

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | PK, auto_increment |
| `youtube_id` | `VARCHAR(20)` | NOT NULL, **UNIQUE** — extraído de `url` (`v=` param), 0 colisões e 0 falhas de extração em 500 registros reais. Substitui o `GET /video/{idYoutubeWatch}` por regex do Mongo por um `WHERE youtube_id = ?` — lookup exato, sem scan. |
| `title` | `VARCHAR(500)` | NOT NULL |
| `url` | `VARCHAR(500)` | NOT NULL, UNIQUE (defesa em profundidade — mesma dedup exata que `Video.findOne({url})` já faz hoje) |
| `channel_id` | `BIGINT UNSIGNED` | NOT NULL, FK → `channels.id`, `ON DELETE RESTRICT` (ver nota sobre soft delete abaixo) |
| `thumbnail` | `VARCHAR(500)` | NOT NULL |
| `viewnum` | `INT UNSIGNED` | NULL — mantido só por paridade de contrato (`Video.viewnum` em `fase-0-openapi.yaml`); **nunca preenchido em 500/500 registros reais**, nenhum controller escreve nele hoje. |
| `published_at` | `DATETIME` | NULL — substitui o `date` do Mongoose. Renomeado porque `DATE` é palavra reservada do MySQL (evitar como nome de coluna é convenção comum PSR/CodeIgniter, mesmo sendo tecnicamente utilizável entre crases); o serializer da Fase 3 devolve como `date` no JSON público, sem mudar o contrato. |
| `created_at` / `updated_at` | `DATETIME` | NULL |
| `deleted_at` | `DATETIME` | NULL — soft delete |

**Removido em relação ao Mongoose**: `channel` (nome), `channel_url`, `channel_icon`. Os dois
primeiros eram desnormalização do Mongoose (nome/link do canal duplicados em cada vídeo) —
viram `JOIN channels` no momento da leitura, decisão que **já estava registrada em
`../plan.md`** antes desta execução ("campos desnormalizados... viram JOIN, não coluna
duplicada"), confirmada aqui com evidência. `channel_icon` é removido de vez: 0/500 registros
reais o têm, e nenhum controller (`VideoController`, `VideoRefreshController`) escreve nele —
campo morto, não vale nem a pena reconstruir via JOIN.

### `dev_reactions` (substitui `Dev.likes[]`/`Dev.deslikes[]`)

| Coluna | Tipo | Constraint |
|---|---|---|
| `dev_id` | `BIGINT UNSIGNED` | FK → `devs.id`, `ON DELETE CASCADE` |
| `target_dev_id` | `BIGINT UNSIGNED` | FK → `devs.id`, `ON DELETE CASCADE` |
| `type` | `ENUM('like','dislike')` | NOT NULL |
| `created_at` | `DATETIME` | NULL |

`PRIMARY KEY (dev_id, target_dev_id, type)` — **não** `(dev_id, target_dev_id)` sem `type`:
lendo `LikeController`/`DislikeController` (`../../devfinder-api/src/controllers/Dev/`), like
e dislike são arrays **independentes**, nada no código impede um Dev estar em `likes` E
`deslikes` do mesmo alvo ao mesmo tempo. Uma chave única sem `type` proibiria isso e quebraria
paridade comportamental.

**Nova invariante, não existia no Mongoose** (decisão explícita, não é cópia 1:1):
`CHECK (dev_id <> target_dev_id)` (MySQL 8.0.16+) — impede um Dev curtir/descurtir a si
mesmo. O schema Mongo não tinha nenhuma validação equivalente; adicionada aqui porque é uma
invariante de banco genuína e de custo zero, não uma mudança de comportamento visível pela API
(nenhum fluxo legítimo do frontend gera esse caso).

### `channel_reactions` (substitui `Dev.follow[]`/`Dev.ignore[]`)

| Coluna | Tipo | Constraint |
|---|---|---|
| `dev_id` | `BIGINT UNSIGNED` | FK → `devs.id`, `ON DELETE CASCADE` |
| `channel_id` | `BIGINT UNSIGNED` | FK → `channels.id`, `ON DELETE CASCADE` |
| `type` | `ENUM('follow','ignore')` | NOT NULL |
| `created_at` | `DATETIME` | NULL |

`PRIMARY KEY (dev_id, channel_id, type)` — mesmo raciocínio: `FollowController`/
`IgnoreController` mutam arrays independentes, um Dev pode teoricamente seguir e ignorar o
mesmo canal simultaneamente hoje (não checado no código-fonte).

## Consistência de banco (foco explícito desta execução)

- **Engine**: `InnoDB` em todas as tabelas — é o único engine MySQL com suporte real a
  `FOREIGN KEY` e transações; sem ele, nada abaixo funciona.
- **Toggle atômico de reação** (like/dislike/follow/ignore): `INSERT`/`DELETE` por chave
  primária composta é atômico por linha no InnoDB. Isso **corrige uma race condition
  documentada no `CLAUDE.md` de `devfinder-api`**: hoje a mutação é `.push()`/`.splice()` +
  `.save()` no array Mongoose, não atômica — dois likes concorrentes podem se perder (read
  stale → push → save sobrescreve). A chave composta única no MySQL torna essa classe de bug
  estruturalmente impossível, sem esforço extra de aplicação (mesmo ganho que
  `../../serverless/specs/fase-1-data-model.md` registrou para o `ADD`/`DELETE` atômico do
  DynamoDB — aqui obtido de graça pela constraint relacional).
- **Soft delete nas 4 tabelas de entidade** (`devs`, `channels`, `videos`, `tags`) —
  `deleted_at DATETIME NULL`, `$useSoftDeletes = true` no Model CI4: `delete()` vira
  `UPDATE ... SET deleted_at = NOW()`, e toda leitura via Model já filtra
  `WHERE deleted_at IS NULL` automaticamente (padrão do framework, não precisa reimplementar o
  filtro em cada query). **Não se aplica** às 3 tabelas de relação pura (`channel_tag`,
  `dev_reactions`, `channel_reactions`): remover um like/follow/tag é uma ação real de
  "desfazer", não um estado a preservar — hard `DELETE` continua correto ali, é o próprio
  significado do toggle (ver critério de PK composta acima).
- **`ON DELETE` por tabela, decisão explícita por relação** (não um padrão único aplicado sem
  pensar) — e agora lido junto com o soft delete acima, já que os dois interagem:
  - `videos.channel_id` → `RESTRICT`: com soft delete, apagar um canal pelo fluxo normal da
    aplicação (`ChannelModel::delete()`) **nunca** dispara essa constraint — a linha do canal
    continua fisicamente existindo (só `deleted_at` muda), então o `FOREIGN KEY` nem é
    avaliado. O `RESTRICT` fica como rede de segurança para o caso residual de um hard delete
    manual/administrativo (`forceDelete()` do CI4, ou um `DELETE` direto) — impede apagar de
    vez um canal que ainda tem vídeos apontando pra ele, cenário que soft delete sozinho não
    cobre porque `forceDelete()` existe exatamente pra pular o soft delete.
  - `dev_reactions.*`, `channel_reactions.*`, `channel_tag.*` → `CASCADE`: mesmo raciocínio
    de antes, mas vale notar que como essas 3 tabelas não têm soft delete, o `CASCADE` só
    dispara em hard delete do Dev/Channel/Tag pai — e um Dev/Channel soft-deletado continua
    com suas reações/tags intactas até (se algum dia) um `forceDelete()` real acontecer.
- **Transação explícita**: só `POST /channels` (Fase 5) precisa de `$db->transStart()` /
  `transComplete()` — é a única escrita que toca duas tabelas relacionadas de uma vez
  (`channels` + N linhas em `channel_tag`) onde uma falha parcial deixaria o canal sem tags
  ou com tags de uma tentativa anterior. `POST /video/refresh` **não** leva uma transação
  única envolvendo o lote inteiro — o comportamento atual já é "por item, com erros
  coletados individualmente" (`VideoRefreshController.addVideo`, chamado item a item dentro
  de um `for`, cada erro empurrado para um array `errors` sem interromper os demais); uma
  transação única no lote mudaria esse comportamento para tudo-ou-nada, o que não é paridade.
- **Charset/collation**: `utf8mb4` (suporta emoji — `ChannelController.store` já remove
  emoji de `category`, evidência de que o dado de origem contém) / `utf8mb4_0900_ai_ci`,
  configurado uma vez em `app/Config/Database.php` (Fase 2), não repetido em cada migration.

## Padrões CodeIgniter 4 (prioridade explícita desta execução)

Migrations usando `$this->forge` (API confirmada via Context7 MCP,
`codeigniter4/codeigniter4`, não assumida de memória — `addForeignKey($local, $refTable,
$refColumn, $onUpdate, $onDelete, $name)`, nessa ordem: **update antes de delete**, erro
comum de trocar a ordem). Nomenclatura: tabela plural snake_case, `App\Database\Migrations`,
uma classe por tabela, timestamp no nome do arquivo gerado por `php spark make:migration`.

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 190],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'bio'        => ['type' => 'TEXT', 'null' => true],
            'avatar'     => ['type' => 'VARCHAR', 'constraint' => 500],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('devs');
    }

    public function down()
    {
        $this->forge->dropTable('devs');
    }
}
```

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChannelsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'link'             => ['type' => 'VARCHAR', 'constraint' => 500],
            'alternative_link' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'user_github'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'category'         => ['type' => 'VARCHAR', 'constraint' => 190],
            'avatar'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');   // exige dedup do dado existente no import — ver nota acima
        $this->forge->addUniqueKey('link');   // idem
        $this->forge->addKey('alternative_link');
        $this->forge->createTable('channels');
    }

    public function down()
    {
        $this->forge->dropTable('channels');
    }
}
```

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTagsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('tags');
    }

    public function down()
    {
        $this->forge->dropTable('tags');
    }
}
```

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChannelTagTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'channel_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'tag_id'     => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['channel_id', 'tag_id'], true);
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('channel_tag');
    }

    public function down()
    {
        $this->forge->dropTable('channel_tag');
    }
}
```

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVideosTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'youtube_id'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'url'          => ['type' => 'VARCHAR', 'constraint' => 500],
            'channel_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'thumbnail'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'viewnum'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('youtube_id');
        $this->forge->addUniqueKey('url');
        $this->forge->addKey('channel_id');
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('videos');
    }

    public function down()
    {
        $this->forge->dropTable('videos');
    }
}
```

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevReactionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'dev_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'target_dev_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'type'          => ['type' => 'ENUM', 'constraint' => ['like', 'dislike']],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['dev_id', 'target_dev_id', 'type'], true);
        // on_update RESTRICT (não CASCADE): confirmado rodando a migration de verdade contra
        // MySQL 8.4 na Fase 2 — InnoDB proíbe CHECK constraint numa coluna que também tenha
        // ON UPDATE CASCADE ("Column 'dev_id' cannot be used in a check constraint... needed
        // in a foreign key constraint... referential action"). ON DELETE CASCADE sozinho
        // funciona normalmente; RESTRICT no update não perde nada de prático (PK
        // auto_increment nunca é atualizada).
        $this->forge->addForeignKey('dev_id', 'devs', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('target_dev_id', 'devs', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('dev_reactions');

        // CHECK constraint — nova invariante, ver "Consistência de banco". Forge não tem
        // helper dedicado para CHECK; SQL bruto via $this->db->query(), mesmo padrão
        // documentado no guia oficial de migrations do CI4 para o que o Forge não cobre.
        $this->db->query(
            'ALTER TABLE dev_reactions ADD CONSTRAINT chk_dev_reactions_not_self ' .
            'CHECK (dev_id <> target_dev_id)'
        );
    }

    public function down()
    {
        $this->forge->dropTable('dev_reactions');
    }
}
```

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChannelReactionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'dev_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'channel_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['follow', 'ignore']],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['dev_id', 'channel_id', 'type'], true);
        $this->forge->addForeignKey('dev_id', 'devs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('channel_reactions');
    }

    public function down()
    {
        $this->forge->dropTable('channel_reactions');
    }
}
```

**Ordem de execução** (FKs exigem que a tabela referenciada já exista): `devs`, `channels`,
`tags` (podem ser paralelas) → `channel_tag`, `videos` → `dev_reactions`,
`channel_reactions`. `php spark migrate` roda pela ordem do timestamp no nome do arquivo —
nomear os arquivos gerados respeitando essa ordem (ou usar `--group`/dependência implícita de
timestamp crescente na hora de rodar `make:migration` para cada um, em sequência).

Estas migrations ficam **descritas aqui** (não como arquivos soltos em `app/Database/` ainda)
porque não existe projeto CI4 real neste repositório até a Fase 2 (scaffold) — criar
`app/Database/Migrations/*.php` sem o resto do esqueleto (`composer.json`, `public/index.php`,
`app/Config/`) seria código de scaffold prematuro, que `../CLAUDE.md` explicitamente proíbe
antes desta spec estar aprovada. A Fase 2 copia o conteúdo acima para arquivos reais gerados
por `php spark make:migration`.

### Models (Fase 3+, referência de convenção já fixada aqui)

Um `Model` por tabela principal, `$allowedFields` explícito (armadilha #1 do CI4 documentada
na skill instalada — campo fora da lista é descartado silenciosamente no insert/update),
timestamps automáticos:

```php
<?php

namespace App\Models;

use CodeIgniter\Model;

class DevModel extends Model
{
    protected $table         = 'devs';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['username', 'name', 'bio', 'avatar'];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $validationRules = [
        'username' => 'required|is_unique[devs.username,id,{id}]',
        'name'     => 'required',
        'avatar'   => 'required',
    ];
}
```

`$useSoftDeletes = true` é o mesmo em `ChannelModel`, `VideoModel`, `TagModel` — CI4 aplica o
filtro `WHERE deleted_at IS NULL` automaticamente em todo `find*()`/`where()` desses Models, e
`delete($id)` vira soft delete por padrão (`delete($id, true)` força hard delete, usado só em
purga administrativa, não em fluxo normal de aplicação). `is_unique[...,id,{id}]` (não só
`is_unique[devs.username]`) é necessário justamente por causa do soft delete: sem o `,id,{id}}`
a regra de validação também ignoraria `deleted_at`, dando falso positivo de duplicata contra a
própria linha em updates — armadilha comum de combinar `is_unique` com soft delete no CI4, não
documentada na skill instalada, vale registrar aqui.

`DevReactionModel`/`ChannelReactionModel` (tabelas de relação pura) **não** usam
`$useSoftDeletes` — `delete()` ali é hard delete de propósito (ver "Consistência de banco").
Detalhamento completo dos Models fica pra Fase 3/5 quando os controllers que os usam forem
escritos (não antecipar código de controller aqui, só o contrato de dados).

## Decisões que esta fase precisava tomar — resolvidas

### 1. Paginação

**Trivial em MySQL, diferente do DynamoDB do projeto irmão** — `LIMIT`/`OFFSET` nativo
resolve `page` diretamente, sem a mecânica de cursor escondido que
`../../serverless/specs/fase-1-data-model.md` precisou desenhar. Decisão: usar o `Pager` /
`paginate()` nativo do Model CI4 (`$model->paginate($perPage = 30)`), não reimplementar
`OFFSET` manualmente — é o padrão idiomático do framework para exatamente este caso
(prioridade explícita desta execução).

### 2. Busca textual (`GET /search`)

Mesma evidência já registrada em `../../serverless/specs/fase-1-data-model.md` (lendo
`devfinder-next/src/components/Header/index.tsx` e `pages/channel/[slug].tsx`): só
`GET /search` (caixa de busca livre, chamada a cada tecla) precisa de correspondência
"contém" de verdade; `GET /channels/{searchQuery}` e `GET /feed/channel?channel_name=` sempre
recebem o nome exato já conhecido pelo frontend.

**Decisão**: `GET /search` usa `LIKE '%termo%'` sobre `channels.name`, `channels.link` e
`videos.title` — não `FULLTEXT INDEX`. Motivo: `FULLTEXT` em modo natural não faz
correspondência de substring dentro de uma palavra (buscar "java" não bate com "javascript"
sem modo booleano com wildcard), e o modo booleano (`+termo*`) só cobre prefixo, não infixo —
o comportamento atual (regex sem âncora) é infixo de verdade. Na escala assumida (centenas de
channels, milhares de videos — mesma premissa do projeto irmão), um `LIKE` sem índice é
aceitável. Documentado como a exceção de performance desta fase (mesmo papel do `Scan`
documentado no design DynamoDB) — revisitar com `FULLTEXT`/motor de busca dedicado se o
catálogo crescer uma ordem de grandeza. `GET /channels/{searchQuery}` e
`GET /feed/channel?channel_name=` usam `WHERE name = ?` exato, indexado.

### 3. Contadores de likes/follows

Mesma conclusão do projeto irmão, confirmada lendo os mesmos controllers: `DevController.show`,
`LikeController.store/delete`, `FollowController.store/delete` sempre devolvem o array
completo (`res.json(loggedDev)`), nunca uma contagem isolada. **Decisão**: sem coluna de
contador derivado — `dev_reactions`/`channel_reactions` são a fonte de verdade, consultadas
direto (`COUNT(*)` só se algum endpoint futuro pedir, o que não é o caso hoje).

### 4. `GET /feed/subscriptions` — vantagem real do relacional sobre o single-table

O design DynamoDB do projeto irmão precisou de **N `Query`s em paralelo** (uma por canal
seguido) mais merge na aplicação, por limitação do modelo de acesso do DynamoDB. Em MySQL,
isso é **uma única query**:

```sql
SELECT videos.*
FROM videos
INNER JOIN channel_reactions
  ON channel_reactions.channel_id = videos.channel_id
WHERE channel_reactions.dev_id = ?
  AND channel_reactions.type = 'follow'
ORDER BY videos.created_at DESC
LIMIT ? OFFSET ?
```

Nenhum fan-out de N chamadas — vantagem estrutural de ter um `JOIN` de verdade em vez de
condensar tudo em uma tabela particionada por chave.

## Cobertura: padrão de acesso por operação

Todo endpoint de `fase-0-openapi.yaml` que toca dado, mapeado a uma query CI4 concreta — sem
N+1 não documentado (critério de aceite desta fase).

| Operação | Resolução (Query Builder / Model) |
|---|---|
| `GET /devs` (paginado) | `DevModel::orderBy('created_at', 'DESC')->paginate(30)`; se autenticado, filtro pós-query excluindo o próprio Dev + `target_dev_id`s já em `dev_reactions` (equivalente ao `$nin` do Mongo — um `WHERE id NOT IN (SELECT target_dev_id FROM dev_reactions WHERE dev_id = ?)`, subquery única, não N+1) |
| `POST /devs` | `DevModel` find-or-create por `username` (mesma semântica de `findOrCreateDev`) |
| `GET /devs/{username}` | `DevModel::where('username', $u)->first()` |
| `GET /channels` | `ChannelModel::orderBy('name')->findAll()` + `channel_tag JOIN tags` agrupado por canal (1 query com `GROUP_CONCAT` ou 1 query extra + agrupamento em PHP — não N+1 por canal) |
| `GET /channels/{searchQuery}` | `WHERE name = ? OR link = ? OR alternative_link = ?` (exato, ver decisão 2) + tags |
| `POST /channels` | Dedup por `name`/`link`/`alternative_link`: checagem de aplicação primeiro (resposta amigável, mesma UX de hoje), `UNIQUE` em `name`/`link` como rede de segurança de banco (captura corrida entre checagem e insert) + transação (`channels` insert/update + `channel_tag` batch) |
| `POST /channels/refresh` | Fetch externo + upsert em lote por `name`/`link` |
| `GET /description/feed`, `GET /description/category` | Sem acesso a dado novo — **chamar `ChannelModel`/`VideoModel` diretamente em PHP**, não HTTP self-call como o `axios.get(APP_API_URL + ...)` do Node original faz hoje (essa é uma correção de arquitetura, não de dado: um monolito CI4 não precisa de round-trip HTTP interno) |
| `GET /feed/trending` (paginado) | `VideoModel::orderBy('created_at', 'DESC')->paginate(30)` + `JOIN channels` para reconstruir `channel`/`channel_url` na resposta (Fase 3) |
| `GET /feed/channel` (paginado, `channel_name`) | `ChannelModel` lookup exato por nome → `VideoModel::where('channel_id', $id)->paginate(30)` |
| `GET /feed/subscriptions` (paginado, auth) | 1 `JOIN` (decisão 4 acima), sem fan-out |
| `GET /video/{idYoutubeWatch}` | `VideoModel::where('youtube_id', $id)->first()` — lookup exato, sem regex |
| `POST /video` | Dedup por `youtube_id` (extraído da URL) + `channel_id` resolvido por `name`/`link`/`alternative_link` + insert |
| `POST /video/refresh` | Mesma lógica, por item, sem transação de lote única (ver "Consistência de banco") |
| `GET /search` | `LIKE` sobre `channels.{name,link}` e `videos.title` (decisão 2, exceção documentada) |
| `GET /me` (auth) | `DevModel::find($idDoJWT)` |
| `POST/DELETE /likes/devs/{u}`, `/dislikes/devs/{u}` | `INSERT`/`DELETE` em `dev_reactions` por `(dev_id, target_dev_id, type)` — atômico (ver "Consistência de banco") |
| `POST/DELETE /likes/channels/{u}`, `/dislikes/channels/{u}` | Idem em `channel_reactions`, `type` = `follow`/`ignore` |
| `GET /likes/devs`, `GET /dislikes/devs` (auth) | `dev_reactions JOIN devs ON target_dev_id = devs.id WHERE dev_id = ? AND type = ?` — 1 query, sem N+1 |
| `/auth/github`, `/auth/github/callback` | Fase 4 — `callback` faz find-or-create em `devs` por `username` (GitHub), mesma semântica de `findOrCreateDev` |

## Mapeamento de nomes na resposta pública (nota para a Fase 3+)

Confirmado lendo `../../devfinder-next` diretamente (mesmo achado que
`../../serverless/specs/fase-1-data-model.md` já documentou para a versão DynamoDB — walkthrough
repetido aqui porque vale para qualquer backend, não é específico de arquitetura):
`devfinder-next/src/pages/user/[slug].tsx` e outros usam `user.user` (não `user.username`);
`devfinder-next/src/pages/channel/[slug].tsx` compara `user.follow.includes(channel._id)`.
**Não é uma correção ao schema acima** (as colunas continuam `devs.username`,
`channels.id` como definidas) — é uma amarração que o serializer de resposta (Fase 3 leitura,
Fase 5 escrita) precisa aplicar: expor `username` como `user` no JSON, e garantir que
`follow`/`ignore` no payload do Dev autenticado carreguem o mesmo tipo de valor que `_id` do
Channel na resposta (nesta stack, `channels.id` inteiro — mais simples que o `slug` string que
a versão DynamoDB precisou inventar, já que MySQL tem PK numérica nativa sem custo de lookup
extra). Registrado aqui para não se perder até a implementação real.

## Revisão humana — mudanças aplicadas (2026-08-24)

Feedback do usuário sobre a primeira versão deste documento, 3 pontos:

1. **`UNIQUE` em `channels.name`/`link`** — a versão original tinha decidido **contra**
   `UNIQUE` por causa da colisão real encontrada no dump (4x um mesmo nome). Revertido:
   prioridade de consistência de banco vence a paridade "como o Mongo está hoje" — dado sujo
   se limpa no import (nota de dedup adicionada), não se codifica como ausência de constraint
   permanente.
2. **Sem auto-like** — já estava coberto na primeira versão (`CHECK (dev_id <> target_dev_id)`
   em `dev_reactions`); confirmado, sem mudança necessária.
3. **Soft delete** — adicionado `deleted_at` nas 4 tabelas de entidade (`devs`, `channels`,
   `videos`, `tags`), `$useSoftDeletes = true` nos Models correspondentes. **Não** aplicado às
   3 tabelas de relação pura (`channel_tag`, `dev_reactions`, `channel_reactions`) — nelas
   `DELETE` continua real, é o próprio significado de desfazer um like/follow/tag.

## Critério de aceite da Fase 1 (de `../plan.md`)

- [x] `specs/fase-1-data-model.md` escrito — este documento.
- [x] Migrations CI4 escritas para cada uma das 7 tabelas (código acima) — arquivos reais
  ficam para a Fase 2 (scaffold), pelo motivo explicado na seção de migrations.
- [x] Toda operação de leitura de `fase-0-openapi.yaml` mapeada a uma query concreta, sem N+1
  não documentado — tabela "Cobertura" acima.
- [x] Revisão humana deste documento — feedback de 2026-08-24 incorporado (seção acima).

## Próximo passo

Fase 2 (scaffold CI4 + Docker + deploy real) consome este documento para gerar o projeto CI4
de verdade (`composer create-project codeigniter4/appstarter`), copiar as 7 migrations acima
para `app/Database/Migrations/`, e declarar `app/Config/Database.php` com `utf8mb4`/InnoDB.

## Validação real (Fase 2, 2026-08-24)

As 7 migrations rodaram de verdade contra MySQL 8.4 (`docker compose exec app php spark
migrate --all`) — todas as constraints testadas manualmente e confirmadas: `CHECK` bloqueia
auto-like, `UNIQUE` em `channels.name` bloqueia duplicata, toggle de reação funciona. Um
achado só visível rodando de verdade (não previsível por leitura de código): MySQL/InnoDB
proíbe `CHECK` numa coluna que tenha `ON UPDATE CASCADE` — corrigido pra `RESTRICT` no update
das FKs de `dev_reactions` (nota já incorporada na migration acima). `utf8mb4_unicode_ci`
citado antes virou `utf8mb4_0900_ai_ci` — decisão fechada agora que a versão do MySQL (8.4)
está definida.
