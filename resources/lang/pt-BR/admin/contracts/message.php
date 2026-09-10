<?php

return [

    'does_not_exist' => 'O contrato não existe.',
    'assoc_installments' => 'Este contrato possui parcelas pagas e não pode ser excluído.',
    'dashboard_info' => 'Painel de Contratos',

    // Comando overdue
    'overdue' => [
        'check_complete' => 'Verificação de atrasos concluída.',
        'marked'         => ':count parcela(s) marcada(s) como em atraso.',
        'none_found'     => 'Nenhuma parcela pendente vencida encontrada.',
    ],

    'create' => [
        'error' => 'O contrato não foi criado, por favor tente novamente.',
        'success' => 'Contrato criado com sucesso.',
    ],

    'update' => [
        'error' => 'O contrato não foi atualizado, por favor tente novamente.',
        'success' => 'Contrato atualizado com sucesso.',
    ],

    'delete' => [
        'confirm' => 'Tem certeza de que deseja excluir este contrato?',
        'error' => 'Houve um problema ao excluir o contrato. Por favor, tente novamente.',
        'success' => 'Contrato excluído com sucesso.',
    ],

    'installment' => [
        'contract_terminal' => 'Não é possível criar parcelas em um contrato encerrado (expirado ou cancelado).',
        'create' => [
            'success' => 'Parcela criada com sucesso.',
            'error'   => 'Não foi possível criar a parcela. Por favor, tente novamente.',
            'missing_default_status' => 'Status padrão pendente não configurado. Por favor, crie um rótulo de status de parcela padrão com meta_type "pending".',
        ],
        'update' => [
            'success' => 'Parcela atualizada com sucesso.',
            'error'   => 'Não foi possível atualizar a parcela. Por favor, tente novamente.',
        ],
        'delete' => [
            'success' => 'Parcela excluída com sucesso.',
            'error'   => 'Não foi possível excluir a parcela. Por favor, tente novamente.',
        ],
        'payment' => [
            'success'                => 'Pagamento registrado com sucesso.',
            'error'                  => 'Não foi possível registrar o pagamento. Por favor, tente novamente.',
            'already_terminal'       => 'Esta parcela já está paga ou cancelada. Nenhuma alteração adicional é permitida.',
            'missing_default_status' => 'Status padrão de pagamento não configurado. Por favor, crie um rótulo de status de parcela padrão com meta_type "paid".',
        ],
        'status' => [
            'success'            => 'Status da parcela atualizado com sucesso.',
            'error'              => 'Não foi possível atualizar o status da parcela. Por favor, tente novamente.',
            'terminal_locked'    => 'Esta parcela tem um status terminal (pago/cancelado) e não pode ser modificada.',
            'transition_blocked' => 'Esta transição de status não é permitida. Verifique as transições permitidas.',
            'invalid_scope'      => 'O status selecionado não pertence ao escopo de parcelas.',
        ],
        'terminal_locked' => 'Esta parcela tem um status terminal e não pode ser modificada.',
        'generate' => [
            'success' => ':count parcela(s) gerada(s) com sucesso.',
            'error'   => 'Não foi possível gerar parcelas. Verifique as datas e o ciclo de faturamento do contrato.',
        ],
        'already_generated' => 'Já existem parcelas para este contrato.',
    ],

    'amendment' => [
        'no_amendments' => 'Nenhum aditivo registrado para este contrato.',
        'create' => [
            'success' => 'Aditivo criado com sucesso.',
            'error'   => 'Não foi possível criar o aditivo. Por favor, tente novamente.',
        ],
        'update' => [
            'success' => 'Aditivo atualizado com sucesso.',
            'error'   => 'Não foi possível atualizar o aditivo. Por favor, tente novamente.',
        ],
        'delete' => [
            'success' => 'Aditivo excluído com sucesso.',
            'error'   => 'Não foi possível excluir o aditivo. Por favor, tente novamente.',
            'confirm' => 'Tem certeza de que deseja excluir este aditivo? Os efeitos colaterais NÃO serão revertidos.',
            'applied' => 'Este aditivo já aplicou efeitos no contrato ou nas parcelas. A exclusão simples está bloqueada; use uma retificação rastreável.',
        ],
        'contract_terminal'  => 'Não é possível criar aditivos em um contrato encerrado (expirado ou cancelado).',
        'contract_not_active' => 'Reajustes só podem ser aplicados a contratos ativos.',
        'not_activatable'    => 'Renovações só podem ser aplicadas a contratos ativos.',
        'no_side_effects'    => 'Este tipo de aditivo não possui efeitos colaterais automáticos.',
        'validation' => [
            'type_required' => 'Selecione o tipo de aditivo.',
            'type_invalid' => 'Selecione um tipo de aditivo válido.',
            'description_required' => 'Informe a descrição/motivo do aditivo.',
            'date_required' => 'Informe a data de vigência.',
            'date_invalid' => 'Informe uma data válida no formato AAAA-MM-DD.',
            'value_invalid' => 'Informe um valor monetário válido com até duas casas decimais.',
            'new_value_required' => 'Informe o novo valor.',
            'new_value_invalid' => 'O novo valor deve ser maior que zero.',
            'old_end_required' => 'Informe a data final atual do contrato.',
            'new_end_required' => 'Informe a nova data final.',
            'new_end_after_old' => 'A nova data final deve ser posterior à data final anterior.',
            'old_value_mismatch' => 'O valor anterior deve corresponder ao valor atual do servidor. Gere uma nova prévia.',
            'old_end_mismatch' => 'A data final anterior não corresponde ao contrato atual. Gere uma nova prévia.',
            'ticket_too_long' => 'A referência documental não pode ter mais de 100 caracteres.',
            'preview_required' => 'Gere uma nova prévia antes de confirmar o aditivo.',
            'preview_invalid' => 'A prévia não pôde ser validada. Gere uma nova prévia.',
            'preview_stale' => 'A prévia está desatualizada ou os dados foram alterados. Revise e gere uma nova prévia.',
        ],
        'preview_details' => [
            'description' => 'Descrição registrada',
            'server_snapshot_help' => 'Valor atual da parcela. Para corrigi-lo, revise o contrato antes de criar o aditivo.',
            'current_value' => 'Valor atual da parcela',
            'new_value' => 'Novo valor',
            'difference_absolute' => 'Diferença absoluta',
            'difference_percent' => 'Variação percentual',
            'effective_date' => 'Data de efeito',
            'affected_installments' => 'Parcelas afetadas',
            'preserved_installments' => 'Parcelas preservadas',
            'current_end_date' => 'Término atual',
            'new_end_date' => 'Novo término',
            'period_added' => 'Período acrescentado',
            'generated_installments' => 'Novas parcelas',
            'result_status' => 'Status resultante',
            'cancelled_installments' => 'Parcelas canceladas',
            'financial_effect' => 'Efeito financeiro automático',
            'financial_policy' => 'Regra financeira',
            'documentary_only' => 'Nenhum: somente registro documental.',
            'termination_policy' => 'Multa, estorno e proporcionalidade não são calculados automaticamente sem regra aprovada.',
            'cancelled_status' => 'Cancelado',
            'installments_summary' => ':count parcela(s) — :value',
            'from_to' => '(:from → :to)',
            'days' => ':count dia(s)',
            'none' => 'Nenhuma',
            'not_calculated' => 'Não calculada',
        ],
        'readjustment' => [
            'auto_update' => ':count parcela(s) atualizada(s) de :old para :new.',
            'preview'     => 'Este reajuste atualizará :count parcela(s) pendente(s) de :old para :new.',
        ],
        'renewal' => [
            'generated' => ':count nova(s) parcela(s) gerada(s) até :date.',
            'overlap'   => 'A nova data final deve ser posterior à data final anterior.',
            'preview'   => 'Esta renovação estenderá o contrato até :date e gerará :count nova(s) parcela(s).',
        ],
        'termination' => [
            'cancelled' => ':count parcela(s) pendente(s) cancelada(s).',
            'preview'   => 'Esta rescisão cancelará :count parcela(s) pendente(s) após :date.',
        ],
    ],

    'asset' => [
        'no_assets' => 'Nenhum ativo vinculado a este contrato.',
        'attach' => [
            'success' => 'Ativo vinculado ao contrato com sucesso.',
            'error'   => 'Não foi possível vincular o ativo. Por favor, tente novamente.',
        ],
        'detach' => [
            'success' => 'Ativo desvinculado do contrato com sucesso.',
            'confirm' => 'Tem certeza de que deseja desvincular este ativo do contrato?',
        ],
        'already_linked' => 'Este ativo já está vinculado ao contrato.',
    ],

];
