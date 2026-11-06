# Progressão obrigatória da assinatura pública

Pedido autorizado: corrigir o salto de CPF/nome na mesma branch e testar o percurso
completo localmente, sem alteração de produção.

Cada termo exige CPF/nome corretos na sessão daquele visitante antes de visualizar
ou responder ao EULA. A assinatura exige também consentimento explícito. Os GETs
sem autorização retornam à etapa necessária; POST de assinatura responde 403 com
orientação e URL para retomar o percurso. Nenhum aceite, recusa ou arquivo é gravado
nessas tentativas.

A autorização fica na sessão, vinculada ao token, aceite, destinatário e uma
impressão HMAC dos campos de identidade, sem armazenar CPF/nome em texto nessa
estrutura. Expira 30 minutos após validar os dados, configurável por
`eula.validation_session_minutes`; não prolonga a validade do token. Novo token,
outra sessão, mudança de identidade/destinatário e tentativa de revalidação
invalidam o progresso anterior. Recusa ou conclusão limpam a autorização. Aceites
concluídos, tokens expirados e bloqueados nunca avançam.
Tokens equivalentes pela collation do banco usam a mesma chave canônica de
autorização/revogação. A conclusão de recusa deve mostrar recusa, sem afirmar que
o termo foi assinado.

Abordagem: serviço de progresso e guardas no controller existente, sem migrations,
novas dependências ou alteração de contratos de checkout. Um flag global de sessão
permitiria usar a validação de um termo em outro; somente proteger o GET deixaria
o POST direto vulnerável. O fluxo deve proteger os dois.

Testes em banco descartável separado. Navegador usa termos fictícios da réplica.
Ao finalizar, manter apenas app/banco/gateway locais e a mesma URL de revisão.
Sem merge, publicação ou deploy remoto.
