# EULA validation implementation plan

**Goal:** bloquear salto da validação de CPF/nome e do consentimento no servidor.
**Spec:** ../specs/2026-11-06-eula-validation-design.md
**Execution:** inline; TDD e revisão independente ao fim.

## Restrições

- Mesma branch `codex/checkout-link-termo`; produção fora do escopo.
- Banco descartável para PHPUnit; dados e utilitários privados ignorados pelo Git.
- Validação por termo/sessão com prazo padrão de 30 minutos, sem schema novo.

## Etapas

- [x] Criar ambiente descartável e testes de acesso direto, POST direto, CPF/nome
  incorretos, consentimento ausente, isolamento de token/sessão, expiração, troca de
  identidade/destinatário, rotação, bloqueio, conclusão, recusa e percurso correto.
- [x] Executar RED; implementar `PublicEulaProgress`, guardas do controller,
  configuração e retomada da validação expirada na interface de assinatura.
- [x] Executar testes e regressões de checkout/assinatura; testar no navegador
  liberação, pop-up, etapas, ataques diretos, assinatura, relatório e PDF.
- [ ] Revisão independente; atualizar status e commit local; imagem persistente,
  prova após recriação e retirada dos recursos temporários de testes.
