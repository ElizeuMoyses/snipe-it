<?php

return [

    'does_not_exist' => 'O contrato não existe.',
    'assoc_installments' => 'Este contrato possui parcelas pagas e não pode ser excluído.',
    'dashboard_info' => 'O painel de contratos estará disponível na Fase 4.',

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

];
