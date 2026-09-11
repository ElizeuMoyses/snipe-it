# Revisão de liberação — Contratos v1

Data: 2026-09-10. Candidato de código: `efbcf9ab7dfbcff8515fdf104d0481cc68746821`.
Base observada: `5642b986afdcecdf6aedd34df092527d680987de`.

**Decisão: NO-GO.** O responsável definiu preservar o dia original, ajustando o fim de mês. A alteração está em validação; o aceite funcional da release não foi registrado. Não fazer merge
ou implantação a partir deste documento. O CI deste candidato deve ser conferido
no PR; resultados de SHAs anteriores têm o escopo indicado abaixo.

## Matriz de evidências

| Requisito | Evidência | Limite ou pendência |
| --- | --- | --- |
| Ambiente local e dados preservados | Réplica isolada, sem saídas de integração; origem preservada; dados privados ignorados pelo Git | Não comprova entrega de e-mail ou operação de scheduler em produção |
| Contratos/status no candidato final | efbcf9ab7d local MariaDB: 81 testes, 266 asserções, todos aprovados | Complementa baseline e deltas abaixo |
| Baseline e regressões | Fonte imutável a6691ab4f1: 1532 testes, 5216 asserções, zero erros/falhas | 4 ignorados e 30 incompletos legados |
| Delta de isolamento, ativos e scheduler | MariaDB fa2c02cf5d: 16 testes, 63 asserções | Complementa a suíte completa anterior |
| Criação por UI/API | MariaDB 670b7e4982: 10 testes, 26 asserções; API permite adiar geração explicitamente | API mantém geração por padrão; UI usa checkbox |
| Geração, centavos e calendário | MariaDB efbcf9ab7d: 16 testes, 46 asserções; terminal conferido sob lock | Dia de vencimento da renovação ainda sem aceite |
| Concorrência | Dois processos MariaDB: geração, pagamento API/UI, pagamento/cancelamento e pagamento/rescisão | Cenários executados, não prova para qualquer interleaving possível |
| Aditivos e rollback | MariaDB: 14 testes, 58 asserções de geração, calendário e falhas parciais UI/API | Sem mudança silenciosa da regra de renovação |
| Permissões e anexos | Casos automatizados de empresa, pai/filho, permissão, tipo, tamanho, pagamento e ativos; listagem e download sintético no browser/HTTP | Papéis reais requerem aceite do responsável |
| Fluxo de contratos | Criar, gerar, pagar, reajustar, renovar, rescindir, anexar e consultar histórico executados com fixture local | Dados sintéticos; não é aceite do usuário |
| Assinatura desktop | Canvas capturou, desfez, recapturou e finalizou; accepted_at e PNG privado conferidos | Não testado em dispositivo móvel; não houve envio externo |
| Migração e recuperação | 53 tabelas com campos originais preservados; restauração de 54 tabelas e 1182 arquivos com SHA-256 sem divergência | Revalidar backup e implantação numa janela futura |
| Continuidade | Branch, PR draft #7, Project, milestone, issues, STATUS e runbook | Milestone e aceite permanecem abertos |

## Reconciliação das falhas anteriores

Os 30 casos com falha/erro do reteste local anterior foram reconciliados por nome
de classe e método contra o JUnit imutável a6691ab4f1: todos encontrados, nenhum
falhando e nenhum ignorado. A comparação privada está em
`.local-validation/regression-reconciliation.json`; nenhum conteúdo real integra
esse relatório público.

- Schema de aceite: colunas usadas pelo fork ausentes nas migrations versionadas;
  reconciliação aditiva e teste de preservação incorporados.
- Aceite/recusa: PDF passou a usar TCPDF já instalado; quantidade, tipos de item e
  remoção de vínculos na recusa foram corrigidos.
- Notificações: destinatários, CC, lote consolidado e lembretes por item/histórico
  foram corrigidos. Testes continuam ativos; não foram desativados para obter verde.
- Filtros por contagem: casos anteriores passaram na fonte e ambiente atuais.
  Não foi demonstrada isoladamente a causa histórica desses dois casos; não se
  atribui essa diferença a uma correção específica.

Não há exceção autorizada que transforme uma falha conhecida em aceite. Os casos
legados ignorados/incompletos são lacunas explícitas, não testes aprovados.

## Revisão de integração

O diff contempla contratos, fornecedores e aceite/EULA. Foram conferidos modelos,
políticas, controladores UI/API, geração e pagamento, efeitos de aditivos,
transformadores/listagens, vínculo de ativos, anexos compartilhados e migrations.
As rotas de filhos consultam a relação do contrato; pagamento usa campos próprios;
operações financeiras disputadas usam lock do contrato. A API usa envelope JSON e
a UI redirecionamento; equivalência significa efeitos e autorizações, não formato
HTTP idêntico. A diferença observada de geração opcional foi corrigida.

Exclusão de aditivo é lógica e não desfaz efeitos financeiros; alteração de campos
descritivos também não reaplica efeitos. Mudanças financeiras devem seguir os
fluxos previstos. O aceite deve incluir essa semântica de histórico.

Para continuidade e comandos, consultar [STATUS](STATUS.md) e
[procedimento de liberação/recuperação](RELEASE-RUNBOOK.md). Nunca restaurar a réplica
ou dados de produção para executar PHPUnit.


O smoke usa o ambiente local com assets previamente regenerados e preservados fora
do commit. A imagem de produção deverá ser construída de fonte rastreada em
ambiente limpo e validada na futura etapa de implantação; os jobs Docker do fork
foram ignorados e não comprovam essa imagem. Nenhum asset local preexistente foi
sobrescrito ou incluído silenciosamente nesta release.
