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
Teste de renderização com envio pela rota UI passou: 1 teste / 7 asserções antes
do ajuste final de ID da tabela; seleção de arquivos está sendo repetida.

## Critérios ainda pendentes

- [ ] Concluir validação de anexos no fluxo real e confirmar download/listagem;
      validar dashboard e fluxo de assinatura/CSP afetado pela release.
- [ ] Auditar cobertura explícita da issue #3: trimestral/anual, bissexto, aditivos
      com rollback em falha e disputa entre aditivo e pagamento.
- [ ] Auditar os critérios completos da issue #4, incluindo tamanho de arquivo e
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
`payment-cancel`. Preserve fonte imutável durante suítes completas.

O contrato sintético local `QA-20260910-01` contém um pagamento e um reajuste já
persistidos; não repetir esses POSTs para resolver problema de leitura. A aba de
QA recuperada foi a 4 do navegador interno. O ambiente local não envia e-mails nem
executa integrações externas. Backups/credenciais e detalhes de infraestrutura não
podem ser publicados no repositório público. Issues podem ser encerradas somente
por evidências; milestone e PR permanecem abertos enquanto houver critérios pendentes.
