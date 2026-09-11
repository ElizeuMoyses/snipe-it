# Validação central das issues de contratos — 10/09/2026

## Escopo

Integração das issues #9–#15 no PR #7, branch `codex/contracts-production-readiness`.
Sem merge ou deploy. Testes destrutivos somente em bancos descartáveis; navegação
com usuário, empresa, fornecedor, ativo e contratos sintéticos na réplica local.
As regras aprovadas são aplicação imediata de aditivos, edição posterior documental,
retificação por novo aditivo e total negociado separado, sem redistribuir parcelas.

## Evidências

| Área | Resultado |
| --- | --- |
| Cadastro #9 | Obrigatórios presentes; criação pelo navegador com BRL, calendário 31/01/2028 → 29/02/2028 → 31/03/2028; 3 × 1.234,56 = 3.703,68; total manual persistido como 3.500,00 |
| Ativos #10 | Busca, seleção, vínculo e desvínculo pela interface; histórico do contrato e do ativo na mesma transação, sem mudar checkout; idempotência e isolamento testados |
| Status #11 | Modal fora da tabela; visível em 390 px, foco e Escape; cancelamento persistido e auditado; pagamento não oferecido como mudança arbitrária de status |
| Aditivos #12 | Prévia mostrou reajuste 100,05 → 110,00, uma parcela afetada e duas preservadas; confirmação auditada; retificação limitada ao mesmo contrato, referência protegida pelo token e original preservado |
| Histórico #13 | Eventos de geração, vínculo/desvínculo, status, aditivo, arquivamento/restauração; paginação de 200 linhas; tratamento de evento somente com metadados; horário UTC convertido para o fuso da aplicação; texto escapado na tabela |
| Arquivamento #14 | Motivo obrigatório; contrato consultável após arquivamento com três parcelas e aditivo; restauração pela tela; ambos os eventos no histórico |
| Gestão #15 | Resumo separa negociado, previsto, pago, aberto, vencido e cancelado; labels nas abas; navegação Home; largura 390 px com scrollWidth 390 e sem elementos ultrapassando a página |
| Migrações | Quatro migrations novas aplicadas, revertidas e reaplicadas em clone descartável; contrato legado e 123,45 preservados; classificação mapeada e modo manual mantido |
| Recuperação | Dump sintético validado por gzip/SHA-256 e restaurado em outro banco; registro legado, total, modo e classificação conferidos |

## Testes

- Integração MariaDB: 159 testes / 756 asserções passaram.
- Suíte completa local Linux/MariaDB em cópia isolada: 1.624 testes / 5.814
  asserções, zero falhas/erros, quatro ignorados e 30 incompletos. Nenhum dos 34
  casos ignorados/incompletos pertence a contratos. Essa execução antecede os
  últimos ajustes de apresentação e horário; não é apresentada como execução do
  SHA final. Os ajustes finais são cobertos pelos deltas abaixo e pelo CI final.
- Regressão final de contratos/tipos/status/moeda/resumo SQLite: 161 testes /
  763 asserções passaram; último delta de auditoria, fuso e texto: seis testes /
  22 asserções passaram.
- Build JavaScript de produção e lint PHP passaram. Assets alheios às mudanças
  foram preservados; o manifest versiona apenas o JavaScript atualizado.
- CI em `2bd9bb62be`: contratos e suíte completa MariaDB, SQLite e PHP
  8.2/8.3/8.4 passaram. Conferir o CI do último commit no PR antes do aceite.

Comandos das suítes (com ambiente de teste previamente isolado):

```sh
php -d memory_limit=1G vendor/bin/phpunit --log-junit results.xml
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Contracts tests/Feature/ContractTypes tests/Feature/ContractStatusLabels tests/Unit/Contracts tests/Unit/Services
php vendor/bin/phpunit --filter ContractAuditIntegrationTest
```

## Limites e continuidade

Última verificação de navegador: a prévia da edição agora remove o método PUT
herdado do formulário e usa a rota POST dedicada. Confirmados cálculo inicial,
limpeza da prévia ao remover o término e preservação do total manual de R$ 299,00.
Também confirmado cancelamento da prévia de aditivo em tela de 390 px sem envio.

- Evidências detalhadas, screenshots sintéticas, JUnit e backups ficam privados em
  `.local-validation/`. Não publicar réplica, credenciais ou conteúdo de clientes.
- Histórico não inventa operações anteriores à implantação. Exclusão física de
  arquivo não é revertida pela restauração do contrato.
- Rescisão não calcula multa/estorno/proporcionalidade. Não há agendamento de
  aditivo nem notificações automáticas adicionadas nesta revisão.
- Os 34 testes legados ignorados/incompletos não representam cobertura concluída.
- Aceite do responsável e decisão de publicação do SHA exato permanecem em #1/#6.
  A milestone e o PR continuam abertos; não confundir fechamento técnico com
  autorização de produção.
