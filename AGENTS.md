# Continuidade da validação de contratos

Leia `docs/contracts/STATUS.md` antes de alterar esta release. O Project, a milestone
e as issues vinculadas são o registro compartilhado; atualize o status com o commit,
comandos executados, resultados e próximo passo ao encerrar uma etapa.

- Preserve alterações locais e histórico. Use staging explícito e branches `codex/`.
- Este repositório é público. Não publique réplicas, dados de produção, arquivos de
  clientes, credenciais, `.env`, logs privados ou configurações específicas da máquina.
- Testes que recriam o banco devem usar exclusivamente banco descartável de testes.
  Nunca execute PHPUnit sobre a réplica de trabalho, referência ou produção.
- Aprovação técnica exige testes locais, regressões, ensaio de migração/recuperação
  e aceite funcional. Registre falhas e limitações sem transformar um teste parcial
  verde em aprovação da release inteira.
- A etapa atual não inclui merge nem deploy. Para um deploy autorizado, faça backup
  recuperável validado, preflight, rollout controlado e validação funcional.
- Issues podem ser concluídas por evidências; não feche a milestone antes do aceite
  da release. Evite cerimônia adicional para correções locais reversíveis.
