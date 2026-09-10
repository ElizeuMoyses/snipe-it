# Contratos v1 — validação para produção

**Decisão atual: NO-GO.** Ainda há falhas de regressão e critérios pendentes.
Atualizado em 2026-09-10. Nenhum deploy realizado nesta etapa.

## Acompanhamento

- [Project](https://github.com/users/ElizeuMoyses/projects/1)
- [Milestone](https://github.com/ElizeuMoyses/snipe-it/milestone/1)
- [Issue principal #1](https://github.com/ElizeuMoyses/snipe-it/issues/1)
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

O erro da suíte ocorre em `AccessoryAcceptanceTest` ao recusar um aceite. Há falhas
em notificações/aceite e filtros por contagens. Não foram classificadas como
preexistentes sem comparação equivalente da base.

## Próximos passos

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
