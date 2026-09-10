# Contratos v1 — validação para produção

**Decisão atual: NO-GO.** Ainda há falhas de regressão e critérios pendentes.
Atualizado em 2026-09-10. Nenhum deploy realizado nesta etapa.

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

## Referências de código

- Base de produção observada: `5642b986afdcecdf6aedd34df092527d680987de`.
- Ponto inicial local: `e48adc30534f7da684fbbf1f81098bc8e8b6822b`.
- Branch de validação: `codex/contracts-production-readiness`.
- A diferença inclui contratos, fornecedores e alterações de aceite/EULA e exclusão
  em lote. O PR precisa revisar esse conjunto; o título da iniciativa não limita o diff.

## Evidências locais já obtidas

Baseline anterior ao primeiro commit desta branch, incluindo a reconciliação de
schema EULA agora versionada:

| Verificação | Resultado |
| --- | --- |
| Contratos, status e reconciliação de schema em MariaDB 11.4 / PHP 8.3 | 43 testes, 96 asserções, passaram |
| Suíte completa | 1501 testes, 5145 asserções, 1 erro, 23 falhas, 4 ignorados e 11 incompletos |
| Smoke autenticado local | 10 endpoints retornaram 200, PDF com cabeçalho válido |

Esses resultados não são uma execução de CI do futuro SHA do PR. O smoke não cobre
todos os fluxos de escrita ou permissões. A suíte desativa `SecurityHeaders` no
TestCase, portanto CSP e captura de assinatura exigem validação real no navegador.
Evidências detalhadas ficam em diretórios privados locais, excluídos do Git.

Reexecução local da seleção contratos/status/schema em 2026-09-10: 43 testes e
96 asserções passaram em MariaDB 11.4/PHP 8.3, com o código PHP do commit
`0988096dacc5ff3b01b7e8366ca2e97565653d5b`. A suíte completa não foi repetida
nesta etapa. O novo CI está em implantação e seu resultado deve ser consultado no PR.

O erro da suíte ocorre em `AccessoryAcceptanceTest` ao recusar um aceite. Há falhas
em notificações/aceite e filtros por contagens. Não foram classificadas como
preexistentes sem comparação equivalente da base.

## Próximos passos

Renovação pela API: **2 testes / 8 asserções** passaram, cobrindo sucesso e
rejeição de data anterior desatualizada sem alteração do contrato. Corrigida a
resposta que chamava `fullName()` inexistente. Criação de aditivos na API e UI
agora bloqueia o contrato antes de validar e gravar, na mesma transação.

Efeitos de aditivos: **2 testes / 11 asserções** passaram em SQLite em memória.
Reajuste preserva parcelas pagas e anteriores à vigência; encerramento preserva
pagas, vencidas e a parcela na data efetiva. Falta validar conflitos de aditivos
com pagamento/alteração de status e dados antigos enviados em renovações.

Concorrência real em MariaDB: `scripts/qa/contracts-concurrency.php` passou em
geração, pagamento API e pagamento UI, com dois processos independentes e uma
barreira de bloqueio. Pagamentos agora bloqueiam contrato/parcela na transação e
revalidam a situação antes de gravar. A segunda tentativa é rejeitada. Rodar o
script somente após migrations no banco sintético `snipeit_concurrency`; argumentos
`payment` e `payment-ui` selecionam os caminhos de pagamento; sem argumento testa
geração. Concorrência com aditivos/alteração de status ainda precisa de revisão.

Suíte completa local terminou: **1509 testes / 5112 asserções, zero erros/falhas,
4 ignorados e 30 incompletos**. A cópia começou em `3fa2f88891`, mas o controlador
API foi atualizado durante a execução para o ensaio concorrente; esse resultado é
diagnóstico e não certifica um SHA imutável. Repetir no candidato final sem editar
a cópia em execução. Casos incompletos/ignorados incluem testes legados de campos
customizados, limites, login e relatórios; não são cobertura de contratos concluída.

Anexos: **5 testes / 21 asserções** passaram em SQLite em memória, cobrindo
permissão de envio, persistência privada, download, exclusão, vínculo ao contrato,
rejeição de PHP, isolamento por empresa de anexos de parcelas e envio autorizado
em aditivos. Corrigido o registro ausente de política para `ContractAmendment`.

Contratos: testes de acesso a parcelas **3 / 12 asserções** aprovados em MariaDB;
pagamento negativo, parcela já paga e rollback da geração parcial, junto aos
limites financeiros/calendário: **8 testes / 17 asserções** aprovados em SQLite
em memória. Isso comprova rollback e validações sequenciais, não concorrência
entre processos. Suíte completa local do candidato `3fa2f88891` em execução
isolada no Docker, com banco `snipeit_test` (sem uso da réplica de trabalho).

Regressões de aceite: a referência a DomPDF ausente foi substituída pelo TCPDF já
instalado; teste verifica o cabeçalho do PDF persistido. Corrigidos quantidade do
aceite, remoção das unidades recusadas e notificação específica de itens.
Seleção local de aceite/recusa/resposta: **19 testes / 65 asserções passaram**.
Entrega em lote: **19 testes / 74 asserções passaram**, com e-mail consolidado e
preservação da criação de aceites/tokens. Lembretes: **9 testes passaram** após
seleção do destinatário do aceite, tipo de item e histórico correto da licença.
CC: verificado envio único com usuário e cópia administrativa; o teste de checkin
foi ajustado para exigir ambos no mesmo e-mail e impedir duplicação.
Ainda falta executar a suíte completa no novo candidato.

Progresso de implementação em 2026-09-10: testes reproduziram perda de centavo,
salto de fevereiro, deslocamento do calendário recorrente e duplicação na segunda
geração de contrato pontual. Corrigidos cálculo por centavos, calendário ancorado
com ajuste de fim de mês e geração transacional com bloqueio do contrato e guarda
de repetição. Seleção local de geração e limites: **12 testes / 26 asserções passaram**.
O bloqueio ainda requer teste com processos concorrentes; não considerar a issue #3
concluída. CI do candidato anterior: contratos passou e suíte completa falhou.

1. Estabelecer execução reproduzível no banco descartável e CI MariaDB 11.4/PHP 8.3.
2. Acrescentar casos de centavos, fim de mês e geração concorrente/idempotente.
   A leitura inicial indica risco em divisão arredondada e `addMonths`; confirmar
   por testes antes de corrigir ou declarar defeito comprovado.
3. Validar permissões e isolamento entre empresas, anexos, API e fluxos financeiros.
4. Corrigir e repetir as regressões da suíte completa, registrando os testes pelo SHA.
5. Ensaiar migração e restauração em ambiente isolado, revisar diff completo e obter
   aceite funcional. Emitir GO/NO-GO com evidências antes de propor deploy.

## Retomada em outra sessão

Verifique branch, HEAD, alterações locais, PR e issues antes de trabalhar. Use a
cópia em disco local, preserve os arquivos não commitados e não recrie a réplica
automaticamente. O ambiente de trabalho e o banco descartável têm finalidades
distintas. Dados reais permanecem somente no ambiente privado.

Ao terminar uma etapa, atualize este arquivo e a issue correspondente com resultado,
limitações e próximo passo. Use PR draft enquanto houver bloqueios; não use `Closes`
para encerrar critérios ainda não atendidos.
