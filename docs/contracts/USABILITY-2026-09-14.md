# Contratos — correções de usabilidade

Base: `d481e8a280` (`production`). Issues: #22, #23, #24, #25 e #26.
Escopo: correção, réplica local e validação; sem merge ou deploy.

## Comportamento

- Pagamento pt-BR usa `dd/mm/aaaa`, com conversão estrita para ISO na persistência.
  Datas impossíveis são rejeitadas. Clientes existentes com ISO continuam aceitos.
- Parcelas permitem seleção individual e de todas as elegíveis. A seleção é
  mantida quando a tabela recria suas linhas; o formulário envia IDs únicos.
  Exclusão em lote valida todos os filhos antes de alterar qualquer registro e
  registra um evento de auditoria por parcela. Pagas/canceladas são bloqueadas.
- `Corrigir pagamento` abre uma página com motivo obrigatório. A confirmação
  preserva os dados anteriores na auditoria e reabre a parcela como pendente,
  permitindo editar, cancelar, excluir ou registrar o pagamento correto.
  Não há estorno bancário. Contratos encerrados e parcelas não pagas são recusados.
- Aditivos aplicados têm atalho `Retificar` na lista e na edição. A referência ao
  original é validada no mesmo contrato e pré-selecionada. Edição documental
  preserva datas/efeitos; correção financeira segue a prévia do novo aditivo.
- Ativos permitem seleção múltipla, contador, confirmação e recuperação da seleção
  após erro. Lotes de até 500 IDs distintos usam transação, mesma empresa,
  autorização individual e auditoria. Vínculos existentes são recusados sem
  alterações parciais. A entrada escalar anterior continua compatível.

## Evidência e limites

Foram reproduzidos testes vermelhos para data, reabertura e exclusão em lote.
A seleção em massa também falhou no navegador antes da correção: a tabela
substituía os checkboxes, deixando a seleção nos elementos removidos do DOM.
Após o ajuste, quantidade visível e quantidade enviada coincidiram.

A suíte de contratos passou em SQLite e MariaDB: **159 testes / 785 asserções**
antes dos ajustes finais de apresentação e da página dedicada de correção.
O delta final e a regressão geral devem ser registrados no PR antes do aceite.
Os testes usam banco descartável, nunca a réplica de trabalho.

A réplica foi restaurada de uma captura nova, com verificação de integridade,
em banco independente. E-mails/integracões foram desativados e a aplicação usa
rede interna. Somente o gateway local é publicado. Dados, credenciais e evidências
da réplica não fazem parte do repositório público. Os anexos não foram recapturados
para esta validação de contratos.

O checkout anterior e ambientes de upgrade foram preservados. Testes interrompidos
por I/O do volume Windows não contam como evidência; a suíte válida usa cópia Linux.
Chaves OAuth descartáveis foram geradas para os testes de API.

## Roteiro de aceite local

1. Em contrato sintético, registrar pagamento com `05/09/2026` e verificar 5 de setembro.
2. Abrir `Corrigir pagamento`, informar motivo e confirmar. Conferir estado pendente,
   resumo e histórico com valor/data anteriores. Registrar o pagamento correto.
3. Selecionar parcelas, filtrar/paginar a tabela, conferir contador, cancelar a
   confirmação e depois excluir somente parcelas sintéticas selecionadas.
4. Abrir `Retificar`, conferir original pré-selecionado, prévia e histórico.
5. Selecionar dois ativos sintéticos da mesma empresa e confirmar um único lote.
6. Revisar isolamento/permissões, testes automatizados e CI do SHA final.

O aceite funcional permanece separado de aprovação técnica e autorização de deploy.
