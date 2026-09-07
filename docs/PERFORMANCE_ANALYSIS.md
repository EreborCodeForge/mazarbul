# Mazarbul — Análise de Performance e Limites da Plataforma

**Pacote:** `ereborcodeforge/mazarbul`  
**Runtime alvo:** PHP 8.5 + PDO  
**Premissa:** maximizar performance **sem quebrar** contratos públicos (`Contract\*`, API `Database` / `Pipeline` / `BulkWriter`).  
**Data da análise:** 2026-09-06

---

## 1. Veredito executivo

A Mazarbul **já está alinhada ao teto útil do PDO** nos caminhos que importam:

- streaming MySQL unbuffered + generators;
- bulk chunked com respeito a `maxBindParameters`;
- pipeline pull-based lazy.

O trabalho pesado (fetch, bind, execute, rede, parse no servidor) ocorre em **C dentro do PDO/mysqlnd/libpq**.  
Contratos/interfaces **não são o gargalo** frente a I/O.

**Não estamos no limite absoluto da linguagem em micro-otimizações PHP**, mas **estamos no limite racional do modelo PDO** para uma lib genérica.  
Os próximos ganhos reais vêm de:

1. cortar alocações no hot path (PHP puro, sem breaking);
2. implementar cursor server-side no PostgreSQL (já previsto pela API de driver);
3. reusar `prepare` / templates SQL no bulk.

**Extensão custom em Rust/Go/C** quase não ajuda enquanto o transporte for PDO. Só faria sentido em paths *fora* do PDO (COPY, LOAD DATA, protocolo binário dedicado).

---

## 2. Arquitetura e hot paths

```text
ConnectionManager
      ↓
ManagedConnection (lazy open → reuse → health/lifetime)
      ↓
Database
 ├─ execute / fetch* / transaction     → PdoQueryExecutor
 ├─ stream()                           → DatabaseResultStream → Pipeline → Stages → Sink
 └─ bulk()                             → BulkWriter → dialect strategies
```

### 2.1 Caminhos quentes

| Caminho | Arquivos-chave | Limitante principal |
|---------|----------------|---------------------|
| `stream()` MySQL | `Stream/DatabaseResultStream.php`, `Driver/MySql/MySqlStreamConfigurator.php` | Driver/rede (`fetch`) |
| `stream()` PostgreSQL | `PostgreSqlCursorReader.php` + `DatabaseResultStream` | Cursor server-side (`DECLARE`/`FETCH`); TX se a stream abrir |
| `bulk()->insert` | `Bulk/BulkWriter.php`, `Bulk/BulkInsert.php` | Misto: prepare/execute (PDO) + build SQL (PHP) |
| `bulk()->update` MySQL | `MySqlBulkUpdateStrategy.php` | SQL CASE/WHEN pesado |
| `bulk()->update` Pg | `PostgreSqlBulkUpdateStrategy.php` | Melhor shape (VALUES + casts) |
| Pipeline CPU-bound | `Pipeline/*`, `Stage/*` | PHP (generators + closures) |
| Alta frequência `execute`/`fetchOne` | `ManagedConnection`, `PdoQueryExecutor` | PDO + alocação de eventos |

---

## 3. O que a lib já faz bem (performance)

### 3.1 Streaming pull-based

`DatabaseResultStream` consome com `PDO::FETCH_ASSOC` + `yield`, fecha cursor em `finally` e restaura buffering. Não materializa o result set em PHP.

### 3.2 MySQL unbuffered de verdade

`MySqlStreamConfigurator` desliga `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY` quando o PDO é mysql. Isso é o requisito mínimo para “streaming” não ser marketing.

### 3.3 Pipeline lazy

Stages só encadeiam `iterable`; consumo só em sinks (`each`, `collect`, `toBatchInsert`, …). Backpressure síncrona natural.

### 3.4 Bulk consciente de limites

`effectiveChunkSize` limita por `maxBindParameters / paramsPerRow`. Entrada é `iterable` — nunca exige dataset inteiro em memória.

### 3.5 Contratos baratos no happy path

Interfaces finas (`PipelineStage`, `ResultStream`, `Dialect`, `Observer`). Dispatch de método é ruído vs round-trip de rede.

### 3.6 PHP 8.5 já aproveitado de forma útil

- `#[\NoDiscard]` em APIs cujo retorno importa;
- `clone($obj, [...])` em options/policies;
- `array_first` em `scalar()`;
- `readonly`, enums, named arguments, asymmetric visibility em testes.

`|>` e outras features “cosméticas” não movem o hot path interno.

---

## 4. Gargalos (com severidade)

### Alta

| Problema | Onde | Impacto |
|----------|------|---------|
| ~~`ConnectionReused` alocado em todo `pdo()`~~ (resolvido em 1.1.0) | `ManagedConnection::emit` + `Observer::isNoop()` | Hot paths pulam eventos com no-op |
| ~~PostgreSQL stream client-buffered~~ (resolvido em 1.1.0) | `PostgreSqlCursorReader` | Cursor server-side incremental |
| Rebuild de SQL por chunk (mitigado em 1.1.0) | `BulkInsert` + cache de `PDOStatement` | Template de row + reuse de prepare (limite 32) |
| Bulk UPDATE MySQL CASE/WHEN | `MySqlBulkUpdateStrategy` | Params ≈ `cols×rows×2 + rows`; SQL enorme |

### Média

| Problema | Onde | Impacto |
|----------|------|---------|
| Pipeline = N generators + N closures/item | `MapStage` / `FilterStage` / … | Mensurável em milhões de rows CPU-bound |
| Double-chunk (`pipeline()->chunk()->toBatchInsert`) | Pipeline + `BulkWriter` | Cópia extra de arrays |
| ~~Eventos sempre criados~~ (resolvido em 1.1.0) | `Observer::isNoop()` | Hot paths pulam alloc com no-op |
| `FETCH_ASSOC` por row | `DatabaseResultStream` | Hash com chaves string repetidas (PDO-limited; API pública é assoc; `FETCH_NUM` opt-in adiado) |
| ~~`scalar()` com `array_values`~~ (resolvido em 1.1.0) | `PdoQueryExecutor` | `array_first` direto no assoc |
| `quoteIdentifier` revalida com `preg_match` | `AbstractDialect` | Redundante após validação na borda do bulk |

### Baixa

- Cópia de `$stages` ao compor pipeline (só no *build*).
- `Stream\Chunk` pouco/não usado no hot path (stages yield `list`).
- Health check `SELECT 1` só no intervalo de lifecycle.
- Classificação ampla de `HY000` no retry (política, não CPU).

---

## 5. Estamos no limite do PHP?

### 5.1 Onde **sim** (teto PDO / runtime)

- `PDOStatement::fetch` / `execute` / bind — já são C.
- Round-trips de rede e `COMMIT` por chunk (`PER_CHUNK`).
- Parse/execução SQL no servidor.
- Com `ATTR_EMULATE_PREPARES => false`, prepare server-side é custo consciente e correto.

Nesses caminhos, micro-otimizar PHP além de um wrapper fino **não muda a ordem de grandeza**.

### 5.2 Onde **não** (ainda há margem em PHP puro)

Sem breaking de contratos:

1. **Fast-path de observability** — se observer for no-op, não alocar eventos (`ConnectionReused`, `QueryExecuted`, `ChunkProcessed`).
2. **Reuse de prepared statement** no bulk de chunk cheio (mesmo SQL).
3. **Template de placeholders** pré-computado (`(?,?,?),…`) após quote único das colunas.
4. **Cursor server-side PostgreSQL** atrás de `StreamConfigurator` (`DECLARE CURSOR` + `FETCH`).
5. **`scalar()`** sem `array_values`.
6. **Fusão interna opcional de stages** do pipeline (API pública igual).
7. **Identifiers trusted** após validação única na borda.

### 5.3 Contratos vs performance

Manter `Contract\*` **não impede** máxima performance útil:

- interfaces PHP têm custo desprezível vs I/O;
- strategies por driver (`BulkUpdateStrategy`, `StreamConfigurator`) são o mecanismo certo para otimizar *sem* `if ($driver === …)` espalhado;
- o que dói é **trabalho PHP evitável no hot path**, não a existência de interfaces.

---

## 6. Roadmap de otimização (PHP puro, API estável)

Ordem sugerida por ROI:

| Prioridade | Mudança | Breaking? | Ganho esperado |
|------------|---------|-----------|----------------|
| P0 | Skip de eventos com `NullObserver` / `Observer::isNoop()` | **Feito em 1.1.0** | Alto em QPS |
| P0 | Cursor PG real (`PostgreSqlCursorReader`) | **Feito em 1.1.0** | Alto em memória PG |
| P1 | Cache de `PDOStatement` por SQL | **Feito em 1.1.0** | Médio em bulk |
| P1 | Builder SQL com template de row | **Feito em 1.1.0** | Médio (menos alloc) |
| P2 | `scalar()` sem cópia | **Feito em 1.1.0** | Baixo |
| P2 | Evitar/documentar double-chunk | **Feito em 1.1.0** (docs) | Médio em pipelines ETL |
| P3 | Pipeline fused interno | **Feito em 1.1.0** | Médio só em CPU-bound |
| P3 | Opt-in `FETCH_NUM` / row hydrator | Follow-up (adiado) | Médio (trade-off DX) |

---

## 7. Extensões custom (Rust / Go / C / FFI)

### 7.1 Quando **não** vale a pena

| Área | Extensão ajuda? | Por quê |
|------|-----------------|---------|
| `PDO::fetch` / `execute` / bind | Quase não | Já é C; FFI adiciona marshaling |
| Pipeline map/filter | Pouco | Melhor fundir stages em PHP |
| Retry / backoff / Observer | Não | Lógica de política |
| Montagem de SQL multi-VALUES | Marginal | Só em chunks enormes CPU-local; servidor/rede dominam |

### 7.2 Quando **poderia** valer (produto separado ou path opt-in)

| Capacidade | Stack sugerido | Por quê |
|------------|----------------|---------|
| `COPY` / `LOAD DATA` / ingest binário | Rust (`tokio-postgres` / mysql crates) ou Go (`pgx`/libpq) exposto via **FFI** ou **processo sidecar** | Fora do PDO; throughput de carga massiva |
| Protocolo binário + batch row encode | Rust | Menos alloc e encode mais previsível |
| Compressão / framing de rows para ETL | Rust | CPU-bound real |
| Substituir PDO por libmysql/libpq direto | Extensão PHP em C/Rust (`ext-php-rs`) | Cursores, multi-result, APIs que PDO não expõe bem — **alto custo de manutenção** |

### 7.3 Modelos de integração possíveis

```text
A) PHP (Mazarbul API) ──PDO──► MySQL/Pg          ← modelo atual (recomendado)

B) PHP (mesmos contratos)
     └─ Driver "native" opcional
           └─ FFI → lib Rust/Go (COPY / binary)   ← path avançado, opt-in

C) Sidecar Go/Rust (gRPC/Unix socket)
     PHP só orquestra streams/pipelines          ← bom para workers isolados
```

**Recomendação:**  
- **Curto prazo:** só PHP (itens da §6).  
- **Médio prazo:** cursor PG + prepare reuse.  
- **Longo prazo / niche:** pacote opcional `ereborcodeforge/mazarbul-native` (FFI) só para ingest (`COPY`/`LOAD`), mantendo a API de alto nível (`BulkWriter` / sinks) como fachada — **sem forçar** todos os usuários a carregar extensão.

Go é excelente para sidecar/workers; Rust é melhor para FFI/extensão embutida (controle de memória e bind PHP via `ext-php-rs`). C “puro” só se precisar de extensão clássica `phpize` com suporte máximo a versões.

---

## 8. Classificação rápida: PDO-limited vs app-limited

| Operação | Limitante |
|----------|-----------|
| `stream()` MySQL unbuffered | **Driver/rede** |
| `stream()` PostgreSQL 1.1+ | **Cursor server-side** + rede |
| `bulk()->insert` grande | **Misto** |
| `bulk()->update` MySQL | **App + motor SQL** |
| Pipeline sobre generator in-memory | **PHP** |
| `fetchOne`/`execute` em loop apertado | **PDO** + eventos PHP |
| Retry com backoff | **Wall clock** |

---

## 9. Conclusão

1. **A Mazarbul não é “lenta por abstração”.** Os contratos são baratos e corretos.  
2. **No modelo PDO, já estamos próximos do teto útil** para streaming MySQL e bulk chunked.  
3. **Ainda há ganhos claros em PHP puro** sem perder interfaces — sobretudo observability no-op, cursor PG e reuse de prepare/SQL.  
4. **Rust/Go/C não são o próximo passo default**; só justificam paths de ingest/protocolo fora do PDO, preferencialmente como extensão/sidecar **opt-in**.  
5. **Prioridade correta:** honestidade de streaming por driver + menos alocação no hot path + melhores strategies SQL — não reescrever a lib em outra linguagem.

---

## 10. Referências internas

- `src/Stream/DatabaseResultStream.php`
- `src/Bulk/BulkWriter.php`
- `src/Connection/ManagedConnection.php`
- `src/Query/PdoQueryExecutor.php`
- `src/Pipeline/Pipeline.php`
- `src/Driver/PostgreSql/PostgreSqlCursorReader.php`
- `src/Driver/MySql/MySqlStreamConfigurator.php`
- `src/Driver/PostgreSql/PostgreSqlStreamConfigurator.php`
- `src/Driver/MySql/MySqlBulkUpdateStrategy.php`
- `src/Driver/PostgreSql/PostgreSqlBulkUpdateStrategy.php`
- `tests/Performance/StreamingMemoryTest.php`
- `docs/streaming.md`, `docs/bulk-operations.md`
