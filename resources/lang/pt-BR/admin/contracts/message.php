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
        ],
        'contract_terminal'  => 'Não é possível criar aditivos em um contrato encerrado (expirado ou cancelado).',
        'contract_not_active' => 'Reajustes só podem ser aplicados a contratos ativos.',
        'not_activatable'    => 'Renovações só podem ser aplicadas a contratos ativos.',
        'no_side_effects'    => 'Este tipo de aditivo não possui efeitos colaterais automáticos.',
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
