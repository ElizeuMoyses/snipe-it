# Contratos v1 — validação para produção

**Decisão atual: NO-GO enquanto os critérios abaixo estiverem pendentes.**
Atualizado em 2026-09-10. Etapa autorizada: implementação e validação local/CI,
sem merge ou deploy. O goal permanece ativo.

## Acompanhamento

- [Project](https://github.com/users/ElizeuMoyses/projects/1)
- [Milestone](https://github.com/ElizeuMoyses/snipe-it/milestone/1)
- [Issue principal #1](https://github.com/ElizeuMoyses/snipe-it/issues/1)
- [PR draft #7](https://github.com/ElizeuMoyses/snipe-it/pull/7)
- [Baseline e CI #2](https://github.com/ElizeuMoyses/snipe-it/issues/2)
- [Valores, calendário e concorrência #3](https://github.com/ElizeuMoyses/snipe-it/issues/3)
- [Permissões, empresas, API e anexos #4](https://github.com/ElizeuMoyses/snipe-it/issues/4)
- [Regressões #5](https://github.com/ElizeuMoyses/snipe-it/issues/5)
- [Migração, recuperação e aceite #6](https://github.com/ElizeuMoyses/snipe-it/issues/6)

## Referências

- Branch: `codex/contracts-production-readiness`.
- Base de produção observada: `5642b986afdcecdf6aedd34df092527d680987de`.
- Ponto inicial local: `e48adc30534f7da684fbbf1f81098bc8e8b6822b`.
- O diff inclui contratos, fornecedores, aceite/EULA e exclusão em lote. A revisão
  da release deve considerar o conjunto, não apenas o título da iniciativa.
- Trabalhar em `D:\Projetos\Snipe-IT-Fork`. Preservar alterações locais de assets.
  Dados e evidências privadas ficam em `.local-production/` e `.local-validation/`.

## Evidências verificadas

| Verificação | Evidência e limite |
| --- | --- |
| Suíte completa local, fonte imutável `39229d1159` | 1522 testes, 5160 asserções, zero erros/falhas, 4 ignorados e 30 incompletos; JUnit privado `candidate-39229-results.xml` |
| CI `39680bf8ef` | Contratos e suíte completa MariaDB 11.4/PHP 8.3 passaram; suites herdadas PHP 8.2/8.3/8.4 e SQLite passaram; Docker ignorado pelo workflow do fork |
| Contratos após bloqueio de mutações `fd10eccc2e` | MariaDB e SQLite: 54 testes, 142 asserções passaram |
| Criação após ajustes de formulário `39680bf8ef` | SQLite: 6 testes, 16 asserções passaram |
| Concorrência real | Dois processos MariaDB passaram em geração, pagamento API, pagamento UI e disputa pagamento/cancelamento; segunda operação incompatível rejeitada |
| Recuperação do banco | Migrations sobre cópia isolada: 53 tabelas com campos originais preservados; restauração anterior em outro banco vazio: 54 tabelas, incluindo migrations, sem divergências de conteúdo |
| Recuperação de arquivos | 1182 arquivos extraídos em pasta privada nova, zero diferenças SHA-256; origem e uploads de trabalho preservados |
| Browser: criação e pagamento | Contrato sintético, 3 parcelas: 33,33 + 33,33 + 33,34; vencimentos 31/01, 28/02 e 31/03/2026; primeira parcela paga com valor 33,33 e ações de pagamento/edição removidas |
| Browser: reajuste | Prévia mostrou duas parcelas; gravação única conferida: primeira paga preservada, duas pendentes passaram para 40,00 |

As execuções acima têm escopos e SHAs específicos. Não certificam automaticamente
um commit posterior. Os testes ignorados/incompletos legados não representam
cobertura concluída. A suíte desativa SecurityHeaders; navegador real é necessário
para evidenciar CSP e assinatura. O resultado antigo 1509/5112 foi diagnóstico,
pois a cópia foi alterada durante a execução; não usar como certificação de SHA.

## Correções implementadas

- Centavos inteiros, saldo na última parcela, calendário ancorado com fim de mês,
  geração idempotente e transação com bloqueio do contrato.
- Pagamento, criação, edição, exclusão e status das parcelas usam bloqueio comum;
  geração parcial com falha tem teste de rollback.
- Criação de aditivos UI/API bloqueia contrato; renovação rejeita data anterior
  desatualizada. Reajuste/rescisão preservam parcelas pagas e limites de vigência.
- Política de anexos para aditivos; testes de permissão, vínculo ao pai, isolamento
  por empresa, upload/download/exclusão e rejeição de executável.
- Cache estático de status removido para não ignorar alterações e rollbacks.
- Aceite/PDF usa TCPDF existente; corrigidos quantidade, recusa, destinatários,
  consolidação em lote, CC e lembretes. Migration reconcilia schema EULA.
- Formulário preserva status e limita opções ao escopo contrato, sinaliza valor
  obrigatório; aba de parcelas ativa inicialmente e tradução de ações corrigida.

## Descobertas do smoke de aditivos nesta etapa

O POST gravou corretamente, mas o GET do contrato retornou 500: a tabela chamava
`Str` sem namespace e `fullName()` inexistente. Teste reproduziu 500 antes da
correção; depois, histórico abriu no navegador com autor, descrição e valores.
O botão de anexo apontava para ID ignorado pelo modal compartilhado. Agora o
modal respeita ID opcional e mantém o padrão dos demais módulos; input e mensagens
usam IDs correspondentes. A listagem passa o objeto do aditivo ao componente de
arquivos, com identificador distinto por tabela. O modal abriu no navegador.
Seleção de renderização com envio pela rota UI e testes de arquivos passou:
**6 testes / 28 asserções**, incluindo o ID distinto da tabela de anexos.

## Critérios ainda pendentes

Revisão do scheduler encontrou disputa com pagamento: rotina de vencidos lia a
parcela pendente e podia sobrescrever um pagamento ocorrido depois da seleção.
Teste reproduziu a mudança incorreta; rotina agora bloqueia contrato/parcela e
revalida status e data na transação. SQLite: **2 testes / 9 asserções passaram**,
incluindo pagamento intercalado, preservação de vencimento hoje e repetição.
Esta alteração posterior não está na cópia imutável `a6691ab4f1` em execução;
validar o delta e o CI antes de concluir a release.

Isolamento UI: **2 testes / 21 asserções passaram**, bloqueando leitura,
pagamento e aditivo entre empresas e parcela sob contrato incorreto, com dados
preservados. A exceção do filho inexistente tentava rota inexistente e retornava
500; o handler agora redireciona à lista de contratos com erro, seguindo o padrão
existente da aplicação. API mantém seu envelope de erro próprio.
CI de `271669824b` passou em todas as suítes habilitadas. PR foi reescrito para
refletir o conjunto final de mudanças e limites da validação.
Verificação HTTP autenticada de anexo sintético concluída (`47545`), script
privado `.local-validation/attachment-http.py`. O marcador
`.local-validation/attachment-http-result.json` impede repetir envio já efetuado;
resultado: download HTTP 200, `content_matches=true`, SHA-256 conferido e endpoint
da listagem renderizado. Dashboard abriu no navegador e exibiu 1 contrato ativo
e 33,33 pagos no mês, correspondendo ao registro sintético. Agendamentos locais
estão desativados; contadores de status vencido não comprovam execução do scheduler.

A suíte completa local de `a6691ab4f1` está em execução na cópia imutável
`/tmp/candidate-a669` do container local, banco descartável `snipeit_test`.
Handle de execução na tarefa: `82787`; não reiniciar enquanto estiver ativo.
JUnit ao concluir: `/tmp/candidate-a669-results.xml`. O
[runbook de liberação e recuperação](RELEASE-RUNBOOK.md) está preparado e exige
revalidação da implantação e backup atualizado numa futura janela autorizada.

Atualização financeira: casos mensal bissexto, trimestral, semestral e anual
passaram, incluindo retorno a 29/02 no próximo ano bissexto e repetição sem novas
parcelas. Teste de gravação recusada reproduziu reajuste parcial: `save()` falso
era ignorado. Os efeitos de aditivos agora lançam exceção nessas recusas para
abortar a transação do controlador. Rollback UI e API, junto aos limites de
calendário, passou em MariaDB: **9 testes / 34 asserções**. Seleção anterior em
SQLite: **11 testes / 41 asserções**. Concorrência real `payment-termination`
passou com dois processos, uma rescisão, nenhum pendente restante e coerência
entre status pago e valor pago.

Gravação recusada durante geração também foi reproduzida: `create()` retornava
modelo não persistido e a parcela era contada como criada. Geração agora exige
persistência e lança exceção para rollback. Reexecução MariaDB de geração,
calendário e rollback de reajuste, renovação e rescisão UI/API: **14 testes / 58
asserções passaram**. Os casos verificam que contrato, parcelas anteriores e
ausência de aditivo são preservados após falha parcial. Arquivo acima do limite:
**1 teste / 3 asserções passou**, sem arquivo nem registro persistido.

- [ ] Concluir validação de anexos no fluxo real e confirmar download/listagem;
      validar dashboard e fluxo de assinatura/CSP afetado pela release.
- [ ] Concluir auditoria da issue #3 confrontando os critérios e testes existentes;
      calendário, falhas parciais e disputa rescisão/pagamento possuem evidência acima.
- [ ] Auditar os critérios completos da issue #4, incluindo
      equivalência UI/API; testes existentes não dispensam essa conferência.
- [ ] Revisar o diff completo e obter resultado local/CI do candidato final estável.
- [ ] Consolidar runbook de liberação/recuperação e relatório GO/NO-GO; atualizar
      Project, issues e descrição do PR conforme evidência, sem fechar aceite pendente.
- [ ] Aceite funcional do responsável antes de liberar produção. CI verde não é
      aceite humano nem autorização de deploy.

## Como retomar

Leia este arquivo e AGENTS.md; verifique branch, HEAD, diff local, PR e issues.
Não recrie a réplica nem sobrescreva assets existentes. PHPUnit destrutivo somente
em banco descartável de testes, nunca na réplica, referência ou produção.
`scripts/qa/contracts-concurrency.php` exige banco sintético `snipeit_concurrency`
com migrations; modos: geração sem argumento, `payment`, `payment-ui`,
`payment-cancel`, `payment-termination`. Preserve fonte imutável durante suítes completas.

O contrato sintético local `QA-20260910-01` contém um pagamento e um reajuste já
persistidos; não repetir esses POSTs para resolver problema de leitura. A aba de
QA recuperada foi a 4 do navegador interno. O ambiente local não envia e-mails nem
executa integrações externas. Backups/credenciais e detalhes de infraestrutura não
podem ser publicados no repositório público. Issues podem ser encerradas somente
por evidências; milestone e PR permanecem abertos enquanto houver critérios pendentes.
