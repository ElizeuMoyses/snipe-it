# Link do termo após a liberação do ativo

Pedido: tornar a entrega de ativos ao usuário mais rápida, exibindo imediatamente o link do termo que hoje exige abrir Relatórios > Unaccepted Assets.

## Comportamento

- Após liberação bem-sucedida a um usuário, abrir uma janela Bootstrap com usuário, etiqueta/nome do ativo, validade e o link do termo recém-gerado.
- Oferecer Copiar link, Abrir termo e Fechar. Se a cópia automática falhar, selecionar o endereço e explicar como copiá-lo manualmente.
- Preservar a opção de destino escolhida no formulário. A janela aparece uma vez no destino; atualizar a página não a reabre.
- No checkout em lote, reunir os links dos ativos dessa operação na mesma janela.
- Não exibir links para checkout inválido, sem permissão, para local/ativo ou sem exigência de aceite. Nunca recuperar termo antigo como se fosse novo.

## Abordagens

Uma janela após o redirecionamento atende diretamente ao pedido e reutiliza Bootstrap 3 e o fluxo atual. Uma faixa de confirmação seria menos intrusiva, mas menos evidente; redirecionar para o relatório mantém a navegação adicional que motivou o pedido. A implementação adota a janela solicitada, sem novos endpoints ou dependências.

## Implementação e limites

Capturar o maior ID de aceite antes do checkout e consultar somente os novos aceites pendentes dos ativos e destinatário da operação. Reutilizar os tokens criados pelo listener existente, sem regenerá-los. Guardar apenas os dados de apresentação em flash de sessão. Conteúdo escapado pelo Blade; URL pública igual à do relatório. Não mudar assinatura, permissões, e-mail, schema ou validade dos tokens.

Testes em banco descartável, separados da réplica operacional. Dados privados e evidências locais permanecem fora do Git. Limpeza restrita aos recursos Docker locais do Snipe-IT substituídos pelo ambiente mantido. A solicitação não inclui merge ou implantação em produção.
