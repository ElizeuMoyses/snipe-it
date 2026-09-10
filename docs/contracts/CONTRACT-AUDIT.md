# Histórico unificado de contratos

Issue de origem: #13. O histórico de contratos usa `contract_audit_events` como
fonte nova e append-only. Eventos são escritos pelo `ContractAuditService` dentro
da mesma transação da mutação. Os controllers preservam a autorização de cada
fluxo; o serviço valida ação, vínculo do sujeito ao contrato e filtra
snapshots/metadados por allowlist.

## Contrato de integração

```php
record(
    Contract $contract,
    string $action,
    ?Model $subject = null,
    array $before = [],
    array $after = [],
    array $metadata = [],
    ?string $correlationId = null,
    ?string $idempotencyKey = null,
): ContractAuditEvent
```

O sujeito pode ser `Contract`, `ContractInstallment`, `ContractAmendment` ou
`Asset`. Parcelas/aditivos precisam apontar para o contrato recebido. O ator é
o usuário autenticado; chamadas sem usuário são `system`. A origem é derivada
de UI, API ou console. `occurred_at` é UTC.

Os consumidores devem chamar o serviço depois da mutação confirmada e antes do
commit. Uma falha ao criar o evento aborta a transação. `idempotencyKey` é único
no contrato e retorna o evento já existente em uma repetição. Eventos não são
editáveis nem removíveis pelo modelo.

Ações v1: `contract.created|updated|deleted|restored`,
`installment.created|updated|deleted|paid|status_changed`,
`installments.generated`, `amendment.created|updated|deleted|applied`,
`file.uploaded|deleted` e `asset.attached|detached`.

## Leitura e legado

`historyFor()` combina eventos novos com `action_logs` históricos associados ao
contrato ou aos filhos, incluindo filhos soft-deleted. A leitura não grava
linhas retroativas; Actionlog legado recebe `source=legacy` e uma marcação de
histórico anterior à implantação. Uploads novos mantêm o Actionlog necessário
para o download, mas o evento novo referencia seu ID para impedir duplicação na
aba. Eventos de arquivo não expõem conteúdo, caminho ou URL privada.

Filtros aceitos: `action`, `action_type`, `entity`, `created_by`, `source`,
`search`, `from` e `to`. A ordenação é `occurred_at DESC, id DESC`, com paginação
por `offset` e `limit` limitado a 100.

## Limites

Não há backfill: dados antigos sem Actionlog continuam sem evento. A exclusão
física de um arquivo não é recuperada pelo histórico. Restauração e retificação
serão integradas pelos fluxos #14 e #12 chamando o mesmo serviço.
