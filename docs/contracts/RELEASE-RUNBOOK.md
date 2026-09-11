# Runbook de liberação e recuperação — Contratos v1

Este documento prepara uma futura implantação. Não constitui autorização para
merge ou deploy. O candidato e a decisão vigente estão em STATUS.md e no PR #7.

## Antes da janela

### Preparação de imagem do fork

O workflow `release-image.yml` valida a imagem Ubuntu sem login em registry,
push ou deploy. Executa build de `git archive HEAD`, testa exclusão de sentinelas
privadas e inicia containers com banco sintético em rede interna. Pode ser
reproduzido localmente com `bash scripts/qa/image-readiness.sh`; não usa o banco
da réplica nem produção. A imagem local fica identificada por SHA e label OCI.

O startup continua aplicando migrations automaticamente: não iniciar a imagem
contra a VPS fora da janela autorizada. `docker/initialize-app.sh` limpa cache
antigo antes de migrar e interrompe em qualquer falha de configuração/migration,
impedindo liberar o servidor web com schema parcialmente aplicado. Validar esse
comportamento não reverte DDL já executado; preservar o plano de recuperação.

O contexto Docker exclui `.env`, réplicas, dumps, uploads, chaves e caches locais.
Ainda assim, a origem da release deve ser o checkout limpo do SHA aprovado.
Não executar build da pasta com dados reais nem reutilizar a imagem local de
desenvolvimento. Fixar o ID/digest resultante para a implantação autorizada.

### Inspeção de produção pendente

O acesso SSH exige a rede da empresa. Antes da liberação, confirmar ao vivo
checkout versus conteúdo da imagem, digest, stack, mounts, permissões de escrita,
espaço, histórico de migrations, cobertura/restauração do backup e scheduler.
O candidato introduz `contracts:check-overdue` às 06:00 no fuso da aplicação;
não executar esse comando na auditoria de leitura. Confirmar também concessões
de permissões de contratos e integrações externas. A captura anterior não
substitui essa inspeção nem o backup da janela.

1. Fixar o SHA aprovado e construir a imagem a partir dele, com lockfiles e assets
   correspondentes. Não construir a imagem de produção a partir do workspace com
   alterações locais não revisadas. Registrar digest e versões PHP/MariaDB.
2. Confirmar CI, validação local, testes funcionais, revisão de permissões e aceite
   do responsável. Conferir o diff completo contra a branch production, incluindo
   fornecedores e aceite/EULA. Resolver todas as pendências bloqueantes do PR.
3. Inventariar a implantação real: imagem/digest, stack/compose, volumes, redes,
   rotas, variáveis, banco, filas e agendamentos. Não reutilizar nomes de outro host.
4. Definir janela, responsável, critérios de interrupção e duração aceitável.
   Verificar espaço livre para backup e restauração sem remover cópias existentes.

## Backup imediatamente anterior à implantação autorizada

- Interromper novas escritas de forma controlada; pausar filas/agendamentos que
  possam alterar dados. Confirmar que não há trabalhos em execução.
- Fazer backup consistente do banco e de todos os arquivos persistentes, incluindo
  uploads privados/públicos e configurações necessárias à recuperação. Preservar
  a APP_KEY original de forma privada para dados criptografados.
- Preservar imagem/digest anterior e configuração da implantação. Registrar SHA-256,
  horário, tamanho, permissões e localização dos backups em registro privado.
- Restaurar a cópia em banco e diretório separados, verificar integridade e leitura
  pela aplicação. A cópia de ensaio anterior desta iniciativa não substitui um
  backup atualizado da janela de produção.
- Só avançar quando a recuperação estiver comprovada. Não publicar dumps, chaves,
  variáveis, documentos de clientes ou logs com dados privados no GitHub.

## Implantação controlada

1. Usar a imagem imutável aprovada e a configuração previamente inventariada.
   Aplicar migrations no banco correto, registrando resultado e duração.
2. Se ocorrer falha, parar a implantação e aplicar o plano de recuperação; não
   insistir com comandos destrutivos ou alterar o schema manualmente sem análise.
3. Antes de reabrir escritas, validar login, permissões por empresa, contratos,
   parcelas, histórico, leitura de anexos e os fluxos existentes afetados pelo diff.
4. Reativar filas/agendamentos e validar seu comportamento, e-mail e integrações
   conforme a configuração real. O ambiente local bloqueia essas saídas e, portanto,
   não comprova entrega externa de produção.
5. Monitorar erros HTTP, logs, banco e filas durante a janela acordada. Registrar
   evidências sanitizadas e o SHA/digest efetivamente implantado.

## Recuperação

- Interromper escritas e trabalhos antes de restaurar. Preservar uma cópia do estado
  com falha para diagnóstico e eventual conciliação de dados criados na janela.
- Retomar imagem/digest e configuração anteriores. Se houve alteração de dados ou
  schema incompatível, restaurar banco e arquivos como conjunto consistente, usando
  os backups validados. Preservar a APP_KEY correspondente.
- Não usar `migrate:rollback` como mecanismo de recuperação desta release: a migration
  de reconciliação EULA tem down não destrutivo e não reconstitui o estado anterior.
- Não sobrescrever a única cópia disponível. Restaurar primeiro em destino separado,
  verificar e só então selecionar o conjunto recuperado na implantação autorizada.
- Revalidar integridade, autenticação, arquivos e fluxos essenciais antes de liberar
  escritas. Conciliar qualquer alteração legítima ocorrida após o backup.

## Evidências já disponíveis e limites

O ensaio local aplicou migrations sobre cópia do banco e verificou os campos
originais de 53 tabelas sem divergências. A restauração anterior em outro banco
conferiu 54 tabelas, incluindo migrations. Foram recuperados 1182 arquivos com
SHA-256 correspondente ao backup. Nenhum desses ensaios alterou produção.

A decisão final deve distinguir aprovação técnica, aceite funcional humano e
validação operacional na janela. Manter PR draft e milestone aberta enquanto
existirem critérios pendentes; encerrar issues somente com evidência do seu escopo.
