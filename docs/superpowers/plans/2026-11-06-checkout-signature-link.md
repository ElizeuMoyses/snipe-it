# Checkout signature link implementation plan

> Execution: implement inline with superpowers:executing-plans and review the completed branch with a fresh reviewer.

**Goal:** apresentar os links dos termos imediatamente após a liberação individual ou em lote.

**Architecture:** serviço de apresentação consulta somente novos aceites da operação; controllers adicionam payload em flash; partial Blade e JavaScript abrem o modal no destino escolhido.

**Tech Stack:** Laravel 11, Blade, Bootstrap 3, PHPUnit 10; nenhuma dependência nova.

**Spec:** ../specs/2026-11-06-checkout-signature-link-design.md

## Global constraints

- Preservar opções de redirecionamento, tokens e permissões existentes.
- Consultar somente aceites novos, pendentes, válidos e pertencentes aos ativos/destinatário dessa operação.
- Testes exclusivamente em banco descartável; nenhum dado privado no Git.
- Sem alteração de schema, merge ou deploy remoto.

## Review focus

- Aceite antigo não pode substituir um novo que não foi criado.
- Liberação para local/ativo e ativos sem aceite não devem abrir janela vazia.
- Falha da área de transferência precisa permitir cópia manual, sem afirmar sucesso.
- Atualização da página não pode reapresentar a janela; destinos existentes devem continuar funcionando.
- Liberação em lote não pode misturar links de outros ativos ou usuários.

### Task 1: dados e apresentação

**Files:** criar `app/Services/CheckoutSignatureLinks.php`, `resources/views/partials/checkout-signature-links.blade.php`, `public/js/checkout-signature-links.js`, `resources/lang/{en-US,pt-BR}/checkout.php`, `tests/Feature/Checkouts/Ui/AssetCheckoutSignatureLinkTest.php`; alterar os controllers de checkout individual/em lote e o layout.

**Interface:** `CheckoutSignatureLinks::forAssets(array $assetIds, Model $target, int $afterId): array`, consumida por ambos os controllers; payload flash `checkout_signature_links` consumido pelo partial.

- [x] Rodar regressão inicial de checkout individual/em lote no ambiente isolado.
- [x] Criar testes comportamentais: token real, destino, exibição única, sem aceite, sem permissão, erro, destinatário não usuário, aceite antigo e lote.
- [x] Executar os testes e conferir falhas pela ausência do novo comportamento.
- [x] Implementar serviço, integração dos controllers, traduções e modal com cópia/abertura.
- [x] Conferir PHPUnit pertinente, sintaxe, acessibilidade, desktop/mobile, cópia com fallback e fluxo público de assinatura.

### Task 2: ambiente e entrega

**Files:** evidências e utilitários privados em diretório ignorado; registro sanitizado em `docs/contracts/STATUS.md`.

- [x] Conferir snapshot e backup local; remover somente recursos Docker antigos do Snipe-IT.
- [x] Garantir que o único ambiente operacional mantido contém os arquivos da branch e persiste após recriação do app.
- [x] Fazer revisão independente da branch, tratar achados relevantes e registrar resultados/limites.
- [x] Commit local com staging explícito e entregar URL, branch e relatório.
