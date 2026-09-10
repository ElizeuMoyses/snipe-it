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

Vínculo de ativos: teste reproduziu associação de ativo de outra empresa por ID.
Controlador agora busca o ativo com o escopo de empresa e exige permissão de
visualização antes de associar. Seleção de ativos/scheduler em SQLite: **5 testes
/ 20 asserções passaram**, incluindo vínculo válido único, desvinculação e recusa
sem `assets.view`. Project atualizado: issues #1–#6 em andamento; nenhuma marcada
concluída enquanto a revisão final está pendente.

Revisão do scheduler encontrou disputa com pagamento: rotina de vencidos lia a
parcela pendente e podia sobrescrever um pagamento ocorrido depois da seleção.
Teste reproduziu a mudança incorreta; rotina agora bloqueia contrato/parcela e
revalida status e data na transação. SQLite: **2 testes / 9 asserções passaram**,
incluindo pagamento intercalado, preservação de vencimento hoje e repetição.
Esta alteração posterior não está na cópia imutável `a6691ab4f1`; foi coberta pelo delta posterior;
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

A suíte completa local de `a6691ab4f1` terminou na cópia imutável
`/tmp/candidate-a669`: **1532 testes / 5216 asserções, zero erros e falhas,
4 ignorados e 30 incompletos**. JUnit privado copiado para
`.local-validation/candidate-a669-results.xml`. Seleção MariaDB do delta `fa2c02cf5d` concluída: **16 testes / 63 asserções passaram**. JUnit privado `contracts-final-delta-fa2c.xml`. Inclui isolamento, ativos,
scheduler, criação e edição. O
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

- [x] Anexos, download/listagem, dashboard e assinatura/CSP desktop validados.
- [ ] Concluir auditoria da issue #3 confrontando os critérios e testes existentes;
      calendário, falhas parciais e disputa rescisão/pagamento possuem evidência acima.
- [x] Critérios da issue #4 e equivalência UI/API conferidos; geração opcional corrigida.
- [ ] Conferir CI final de efbcf9ab7d; revisão de integração registrada em READINESS-REVIEW.md.
- [x] Runbook e revisão NO-GO consolidados; acompanhamento atualizado sem fechar aceite pendente.
- [ ] Aceite funcional do responsável antes de liberar produção. CI verde não é
      aceite humano nem autorização de deploy.

## Como retomar

Leia este arquivo e AGENTS.md; verifique branch, HEAD, diff local, PR e issues.
Não recrie a réplica nem sobrescreva assets existentes. PHPUnit destrutivo somente
em banco descartável de testes, nunca na réplica, referência ou produção.
`scripts/qa/contracts-concurrency.php` exige banco sintético `snipeit_concurrency`
com migrations; modos: geração sem argumento, `payment`, `payment-ui`,
`payment-cancel`, `payment-termination`. Preserve fonte imutável durante suítes completas.

O contrato sintético local `QA-20260910-01` contém um pagamento e três aditivos (reajuste, renovação e rescisão); não repetir esses POSTs. A aba de QA atual é a 5 do navegador interno. O ambiente local não envia e-mails nem
executa integrações externas. Backups/credenciais e detalhes de infraestrutura não
podem ser publicados no repositório público. Issues podem ser encerradas somente
por evidências; milestone e PR permanecem abertos enquanto houver critérios pendentes.

## Verificação consolidada do candidato fa2c02cf5d

Todas as suítes habilitadas no CI passaram: PHP 8.2, 8.3 e 8.4,
SQLite e MariaDB 11.4 (contratos e suíte completa). Jobs Docker continuam
ignorados pela regra do fork; isso não comprova build de imagem de produção.
Execuções: 34498722376, 34498722139 e 34498722259.

No navegador local, a renovação criou três parcelas e preservou o pagamento.
A rescisão efetiva em 01/05/2026 mostrou prévia de uma parcela; após confirmar,
cancelou somente 01/06/2026. A parcela paga de 31/01 permaneceu em 33,33;
a parcela no próprio dia do corte e as anteriores foram preservadas.
Histórico exibe três aditivos e a listagem real do anexo sintético contém link
de download. Download autenticado já conferido por conteúdo e SHA-256.

**Decisão de negócio pendente:** sem dia fixo, a renovação hoje reinicia no dia
seguinte ao término. Exemplo observado: parcelas anteriores no dia 31;
renovação após 31/03 produziu 01/04, 01/05 e 01/06. Foi solicitada definição
entre preservar o dia original (com ajuste de fim de mês) e manter esse reinício.
Não alterar a regra nem tratar o calendário como aceito sem essa definição.

Assinatura/CSP desktop foi concluída na continuação abaixo. Permanecem a conferência final
UI/API e do diff da release, além do aceite funcional. Resultado atual: NO-GO.
## Assinatura e equivalência de criação (continuação)

Smoke EULA local com usuário/ativo exclusivamente sintéticos: validação de dados,
leitura do termo, captura no canvas, desfazer (desabilita finalizar), novo traço e
finalização passaram no navegador. Persistência confirmada: accepted_at preenchido,
declined_at vazio, PNG privado existente e tipo de dispositivo desktop.
Isso evidencia execução do JavaScript com middleware real nesse fluxo desktop;
não constitui validação em celular nem teste de entrega externa de e-mail.

A API ignorava auto_generate_installments=false e criava três parcelas mesmo
quando solicitada criação sem geração. Teste reproduziu a falha (3 versus 0).
Agora a API respeita o booleano, mantendo geração automática quando omitido.
MariaDB, criação UI/API: 10 testes / 26 asserções passaram; artefato privado
contracts-create-parity.xml. Os formatos de resposta seguem as convenções próprias
de cada interface (redirect UI e envelope JSON API).

Geração após cancelamento: teste reproduziu três parcelas novas ao usar uma
instância carregada antes do cancelamento persistido. O modelo agora verifica
status terminal após recarregar o contrato sob lock. MariaDB: 16 testes / 46
asserções passaram, incluindo geração e calendário; JUnit privado
contracts-terminal-generation.xml. Essa verificação fecha a janela entre a
checagem do controlador e a obtenção do bloqueio.


Validação final local do código efbcf9ab7d: **81 testes / 266 asserções**,
contratos e status em MariaDB; zero falhas. Artefato privado contracts-final-efbc.xml.
CI MariaDB contratos e suíte completa passaram no run 34501057231. PHP 8.2,
SQLite/PHP 8.3 e PHP 8.4 passaram; suíte herdada PHP 8.3 ainda executava no último
check (run 34501057316, job 102951650348). Não reiniciar por demora de observação.

Revisão: [READINESS-REVIEW.md](READINESS-REVIEW.md). Project conferido: #2, #4 e #5
concluídas/Done; #1, #3 e #6 permanecem abertas/In Progress. Milestone aberta,
PR draft. Próximo passo: resultado do job indicado, definição de calendário e
aceite humano. Smoke de assinatura já concluído; não recriar fixture nem repetir
seu POST. Dados e artefatos privados continuam ignorados pelo Git.


## Regra de renovação definida pelo responsável

O usuário escolheu preservar o dia original do vencimento, ajustando o fim de mês.
A renovação passa a usar start_date como âncora do ciclo e billing_day quando
configurado. O término anterior é somente limite inferior para novas parcelas.
Exemplo: 31/01 e término em 31/03 geram 30/04, 31/05 e 30/06.
Prévia e gravação usam o mesmo calendário; nenhuma parcela histórica é reescrita.
A escolha resolve a pendência de regra registrada anteriormente. Testes da alteração
em execução; aceite funcional da release permanece separado.

Validação da nova regra: seleção MariaDB de contratos/status passou com
87 testes / 309 asserções. Casos de calendário cobrem dia 30/31, fevereiro
bissexto, ajuste sem perda do dia original, ciclos trimestral/anual, dia fixo,
prévia versus parcelas persistidas, preservação do histórico e idempotência.

## Fornecedores integrados a contratos — issue #8

Cadastro completo e modal agora compartilham os mesmos campos, incluindo endereço,
dados fiscais, imagem e cor. O envio multipart fica restrito ao formulário do modal.
Erros CPF/CNPJ têm tradução e o CNPJ alfanumérico usa ASCII menos 48, conforme o
exemplo técnico da Receita Federal `12.ABC.345/01DE-35`; sequências numéricas
repetidas são rejeitadas.

Validação local: fornecedores e BrDocument passaram em MariaDB e SQLite,
23 testes / 94 asserções em cada banco. Comando: `php -d memory_limit=1G
vendor/bin/phpunit tests/Feature/Suppliers tests/Unit/Rules/BrDocumentTest.php`.
Usar exclusivamente `snipeit_test` ou `sqlite_testing`. Build `npm run production`
concluído. Browser: modal completo, CNPJ como PF rejeitado em português sem perda
de campos; corrigir para PJ salva e seleciona o fornecedor preservando o contrato.
Somente fixture sintética foi criada; nenhum contrato foi submetido nesse smoke.
Upload de arquivo não foi exercitado pelo navegador nesta etapa.

Issue #8 e PR #7 mantêm a rastreabilidade. Regressão de contratos e CI do novo
commit devem ser conferidos antes de concluir a issue. PR permanece draft;
aceite da release nas issues #1/#6 segue pendente, sem merge ou deploy.
Assets locais anteriores foram preservados; apenas bundle JS do modal e sua
entrada de versão no manifest fazem parte desta alteração.

## Correção visual do modal de fornecedores

Retorno do usuário mostrou que a validação anterior por árvore de acessibilidade
não detectou sobreposição visual. O formulário do modal não tinha `form-horizontal`,
presente no cadastro completo; sem o clearfix de `.form-group`, colunas flutuantes
empurravam os fieldsets fiscais para uma faixa lateral. Classe adicionada ao form.
Screenshot local após rolagem confirmou Dados Fiscais e cor em largura completa,
com rótulos e campos alinhados. Alteração somente de apresentação, sem build JS.

## Investigação de gestão e usabilidade — issues #10–#15

Base de código: 6fcfd8df05. Nenhuma correção funcional realizada nesta investigação.
Backlog detalhado na milestone Contratos v1 e Project, complementando #9:
#10 vínculo de ativos; #11 menu de status; #12 aditivos/prévia/retificação;
#13 histórico unificado; #14 exclusão rastreável; #15 lista/resumo/navegação.
Cada issue registra arquivos-alvo, fatos versus hipóteses, critérios e testes.

Validação: MariaDB snipeit_test, phpunit tests/Feature/Contracts: 79 testes/288
asserções aprovados; SQLite sqlite_testing, ContractAssetAccessTest,
ContractAmendmentEffectsTest e DeleteContractTest: 9 testes/32 asserções aprovados.
JUnit privado no container: /tmp/contracts-investigation.xml e
/tmp/contracts-investigation-sqlite.xml. Nenhum banco de trabalho foi recriado.

Visual: menu de status da última linha confirmado cortado; seletor de ativos abriu
com opções, mas POST de vínculo não foi exercitado na réplica de trabalho. Uma
navegação retornou 504 durante execução local; funcionou depois, sem causa firmada.
Não publicar screenshots ou detalhes dos registros reais inspecionados.
Histórico completo ausente confirmado por leitura de trait/observers/controladores;
prévia de aditivos calcula dados não exibidos no modal. Testes atuais não cobrem
essas lacunas. Dois revisores read-only verificaram auditoria e aditivos.

Coordenação sugerida: #13 define mecanismo de auditoria para #10/#11/#12/#14;
#9 define valores/calendário reutilizados por #12/#15. Evitar edições concorrentes
em view.blade.php e controladores; commits focados integrados no PR draft #7.
Decisões ainda explícitas nas issues: efeito de total manual, retificação de
aditivos aplicados, restauração e papéis. Aceite geral #1/#6 continua pendente.

## Issue #9 — implementação isolada

Execução de 2026-09-10 no worktree `D:\Projetos\Snipe-IT-Worktrees\issue-9`,
branch `codex/contracts-issue-9`, partindo de `089ba0494e35862bd31b2c12412e9e5d650d9ff0`.
O destino de integração solicitado é `codex/contracts-production-readiness`; não
houve merge, deploy, operação de VPS ou fechamento de issue.

Entrega publicada no commit `68dea2169106e90dfbed18a8883e28fd3d633f7b`, PR draft
[#16](https://github.com/ElizeuMoyses/snipe-it/pull/16), relacionado ao PR #7.

Implementados: campos obrigatórios de criação/edição com compatibilidade para
contratos legados; validação única UI/API; valores em BRL com centavos inteiros;
total automático/manual sem redistribuição implícita; calendário mensal,
trimestral, semestral, anual e único com dia 1–31 e ajuste ao fim do mês; prévia
servidor/cliente compartilhada com a geração; persistência idempotente sem
reescrever parcelas históricas; e CRUD de classificações configuráveis com
permissão, inativação e bloqueio de exclusão quando houver histórico.

Evidências reais no worktree: `96 testes / 363 asserções` passaram em
`tests/Feature/Contracts`, `tests/Feature/ContractTypes` e o teste unitário de
moeda, em SQLite descartável/em memória com Passport efêmero somente no processo;
54 arquivos PHP alterados passaram em lint; `artisan view:cache --no-ansi`
passou. O `route:list` standalone não foi certificado porque a inicialização sem
settings semeadas acessou `saml_enabled`; as rotas foram exercitadas pelos testes
HTTP. Não houve ainda CI nem validação visual em navegador/celular nesta issue.

Integração: #12 pode reutilizar `Contract::plannedInstallmentDates()`,
`installmentPreview()` e o contrato de valores em centavos; #13 permanece a
fonte do mecanismo de auditoria para #10/#11/#12/#14; não foram copiados commits
não revisados dessas issues nem alterados seus write sets.

Decisões de negócio ainda pendentes para aceite: quando o total negociado manual
diverge da soma das parcelas, este candidato registra a divergência visível e
mantém a parcela informada, sem redistribuir; a regra final de redistribuição ou
de lançamento separado deve ser confirmada pelo responsável. Também permanecem
pendentes a revisão humana da nomenclatura/classificações iniciais e a aprovação
da integração no PR #7. Nenhum dado privado, segredo ou arquivo `.env.testing`
faz parte do commit.
