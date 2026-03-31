<div>
    <style>
        /* Open Maintenances Table Specific Styles - Otimizado */
        .open-maintenances-container {
            position: relative;
            display: flex;
            flex-direction: column;
            height: auto; /* Altura automática baseada no conteúdo */
            min-height: 0; /* Permite que o container encolha */
            margin: 0; /* Remove margens desnecessárias */
            padding: 0; /* Remove padding desnecessário */
        }
        
        /* Remove margens e espaçamentos desnecessários - otimizado */
        .open-maintenances-container > *:not(.table-responsive):not(.pagination-container) {
            margin: 0;
        }
        
        /* Garante que a tabela tenha altura fixa com scroll - otimizado */
        .open-maintenances-container .table-responsive {
            margin: 0; /* Remove todas as margens */
            padding: 0; /* Remove padding desnecessário */
            height: 400px; /* Altura fixa para a área da tabela */
            overflow-y: auto; /* Scroll vertical quando necessário */
            overflow-x: auto; /* Scroll horizontal para responsividade */
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        
        /* Corrige altura da paginação - otimizado */
        .open-maintenances-container .pagination-container {
            flex-shrink: 0;
            margin-top: 1rem; /* Margem aumentada */
            margin-bottom: 0; /* Remove margem inferior */
            height: auto; /* Altura automática */
            min-height: 60px; /* Altura mínima garantida */
            border-top: 1px solid #e9ecef;
            background: #fff;
        }
        
        .open-maintenances-container .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        
        .open-maintenances-container .modern-table tbody tr {
            transition: all 0.2s ease;
        }
        
        /* Cabeçalho fixo da tabela */
        .open-maintenances-container .modern-table thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .open-maintenances-container .modern-table tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .open-maintenances-container .btn-group .btn {
            transition: all 0.2s ease;
        }
        
        .open-maintenances-container .btn-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* Enhanced Mobile Responsiveness */
        @media (max-width: 768px) {
            .open-maintenances-container .table-responsive {
                height: 300px; /* Altura menor da tabela em mobile */
            }
            
            .open-maintenances-container .modern-table {
                font-size: 0.875rem;
                border-radius: 12px;
                overflow: hidden;
            }
            
            .open-maintenances-container .modern-table th,
            .open-maintenances-container .modern-table td {
                padding: 0.875rem 0.75rem;
                vertical-align: middle;
            }
            
            .open-maintenances-container .btn-group .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.8rem;
                border-radius: 6px;
                margin: 0 2px;
            }
            
            /* Enhanced responsive pagination layout */
            .open-maintenances-container .pagination-container {
                flex-direction: column;
                gap: 1.25rem;
                align-items: center;
                text-align: center;
                padding: 1.25rem 0;
                background: rgba(248, 249, 250, 0.5);
                border-radius: 12px;
                border: 1px solid #e9ecef;
                margin-top: 1.5rem;
            }
            
            .open-maintenances-container .pagination-info {
                order: 1;
                width: 100%;
            }
            
            .open-maintenances-container .pagination-info .results-info {
                font-size: 0.95rem;
                font-weight: 500;
                color: #495057;
                padding: 0.5rem 1rem;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                display: inline-block;
            }
            
            .open-maintenances-container .pagination-controls {
                order: 2;
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 1.5rem;
                align-items: center;
            }
            
            .open-maintenances-container .items-per-page {
                background: white;
                padding: 0.75rem 1rem;
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border: 1px solid #e9ecef;
            }
            
            .open-maintenances-container .pagination-nav {
                background: white;
                padding: 0.5rem;
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border: 1px solid #e9ecef;
            }
        }
        
        @media (max-width: 576px) {
            /* Optimized table for small screens */
            .open-maintenances-container .table-responsive {
                height: 250px; /* Altura ainda menor da tabela em telas pequenas */
            }
            
            .open-maintenances-container .modern-table {
                font-size: 0.8rem;
            }
            
            .open-maintenances-container .modern-table th,
            .open-maintenances-container .modern-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
                line-height: 1.4;
            }
            
            .open-maintenances-container .btn-group .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
                min-width: 32px;
                height: 32px;
            }
            
            /* Compact pagination for small screens */
            .open-maintenances-container .pagination-container {
                gap: 1rem;
                padding: 1rem;
                margin-top: 1rem;
            }
            
            .open-maintenances-container .pagination-info .results-info {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
                line-height: 1.3;
            }
            
            .open-maintenances-container .items-per-page {
                padding: 0.625rem 0.875rem;
                gap: 0.625rem;
                min-width: auto;
            }
            
            .open-maintenances-container .items-per-page label {
                font-size: 0.8rem;
                font-weight: 500;
                color: #495057;
                white-space: nowrap;
            }
            
            .open-maintenances-container .items-per-page select {
                min-width: 65px;
                max-width: 75px;
                font-size: 0.8rem;
                padding: 0.375rem 0.5rem;
                height: 32px;
                border-radius: 6px;
                border: 1px solid #ced4da;
                background-color: #fff;
                text-align: center;
                font-weight: 500;
            }
            
            .open-maintenances-container .pagination-controls {
                gap: 1.25rem;
                flex-direction: column;
                align-items: center;
            }
            
            .open-maintenances-container .pagination-nav {
                padding: 0.375rem;
            }
            
            /* Improve pagination links for touch */
            .open-maintenances-container .pagination-nav .pagination {
                margin: 0;
            }
            
            .open-maintenances-container .pagination-nav .page-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
                border-radius: 6px;
                margin: 0 2px;
                min-width: 36px;
                text-align: center;
                border: 1px solid #dee2e6;
                color: #495057;
                transition: all 0.2s ease;
            }
            
            .open-maintenances-container .pagination-nav .page-link:hover {
                background-color: #e9ecef;
                border-color: #adb5bd;
                transform: translateY(-1px);
            }
            
            .open-maintenances-container .pagination-nav .page-item.active .page-link {
                background-color: #3498db;
                border-color: #3498db;
                color: white;
                font-weight: 600;
            }
        }
        
        @media (max-width: 480px) {
            /* Ultra-compact layout for very small screens */
            .open-maintenances-container .pagination-container {
                padding: 0.875rem;
                gap: 0.875rem;
                border-radius: 8px;
            }
            
            .open-maintenances-container .pagination-info .results-info {
                font-size: 0.8rem;
                padding: 0.4rem 0.6rem;
                border-radius: 6px;
            }
            
            .open-maintenances-container .items-per-page {
                padding: 0.5rem 0.75rem;
                border-radius: 8px;
                gap: 0.5rem;
            }
            
            .open-maintenances-container .items-per-page label {
                font-size: 0.75rem;
            }
            
            .open-maintenances-container .items-per-page select {
                min-width: 60px;
                max-width: 70px;
                font-size: 0.75rem;
                padding: 0.25rem 0.375rem;
                height: 28px;
                border-radius: 4px;
            }
            
            .open-maintenances-container .pagination-nav {
                padding: 0.25rem;
                border-radius: 8px;
            }
            
            .open-maintenances-container .pagination-nav .page-link {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
                min-width: 32px;
                border-radius: 4px;
                margin: 0 1px;
            }
            
            /* Hide some pagination elements on very small screens */
            .open-maintenances-container .pagination-nav .page-item:not(.active):not(.disabled) {
                display: none;
            }
            
            .open-maintenances-container .pagination-nav .page-item.active,
            .open-maintenances-container .pagination-nav .page-item.disabled,
            .open-maintenances-container .pagination-nav .page-item:first-child,
            .open-maintenances-container .pagination-nav .page-item:last-child {
                display: inline-block;
            }
        }
        
        /* Loading Animation - Otimizado para não afetar layout */
        .open-maintenances-loading {
            background: rgba(255, 255, 255, 0.95);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            min-height: 200px; /* Altura mínima para evitar colapso do layout */
            pointer-events: none; /* Não interfere com interações */
        }
        
        .open-maintenances-loading i {
            color: #3498db;
            margin-right: 0.5rem;
            font-size: 1.2rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Elimina elementos órfãos e vazios que podem causar problemas de layout */
        .open-maintenances-container > div:empty,
        .open-maintenances-container > span:empty,
        .open-maintenances-container > p:empty {
            display: none !important;
        }
        
        /* Remove elementos invisíveis que ocupam espaço */
        .open-maintenances-container [style*="display: none"],
        .open-maintenances-container [style*="visibility: hidden"] {
            position: absolute !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Badge Improvements */
        .open-maintenances-container .badge {
            border-radius: 12px;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Link Improvements */
        .open-maintenances-container a:not(.btn) {
            color: #3498db;
            transition: color 0.2s ease;
        }
        
        .open-maintenances-container a:not(.btn):hover {
            color: #2980b9;
            text-decoration: none;
        }
        
        /* Scroll customizado para a tabela */
        .open-maintenances-container .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .open-maintenances-container .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .open-maintenances-container .table-responsive::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .open-maintenances-container .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Consolidated Pagination Styles - Otimizado */
        .open-maintenances-container .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0; /* Padding reduzido */
            border-top: 1px solid #ecf0f1;
            margin-top: 0.75rem; /* Margem reduzida */
            margin-bottom: 0; /* Remove margem inferior */
            flex-wrap: nowrap;
            gap: 1rem;
            min-height: 60px; /* Altura mínima consistente */
            height: 60px; /* Altura fixa */
            background: #fff; /* Fundo branco para destacar */
        }

        .open-maintenances-container .pagination-info {
            flex-shrink: 0;
        }

        .open-maintenances-container .pagination-info .results-info {
            color: #6c757d;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .open-maintenances-container .pagination-controls {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-shrink: 0;
            flex-wrap: nowrap;
        }

        .open-maintenances-container .items-per-page {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .open-maintenances-container .items-per-page label {
            margin: 0;
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: normal;
            line-height: 1;
        }

        .open-maintenances-container .items-per-page select {
            min-width: 80px;
            max-width: 100px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.2;
            height: 32px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1rem;
            padding-right: 2rem;
        }

        .open-maintenances-container .items-per-page select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            outline: none;
        }

        .open-maintenances-container .pagination-nav {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        /* Per Page Selector Improvements */
        .open-maintenances-container .form-control-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border: 1px solid #ced4da;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .open-maintenances-container .form-control-sm:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        /* Additional layout stability */
        .open-maintenances-container .pagination-container > * {
            flex-shrink: 0;
        }

        .open-maintenances-container .pagination-controls > * {
            flex-shrink: 0;
        }

        /* Ensure consistent alignment */
        .open-maintenances-container .items-per-page label,
        .open-maintenances-container .items-per-page select {
            vertical-align: middle;
        }

        /* Touch interface optimizations */
        @media (hover: none) and (pointer: coarse) {
            .open-maintenances-container .btn-group .btn {
                min-height: 44px;
                min-width: 44px;
                padding: 0.5rem;
                margin: 0 4px;
                border-radius: 8px;
                font-size: 0.875rem;
                touch-action: manipulation;
            }
            
            .open-maintenances-container .items-per-page select {
                min-height: 44px;
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
                touch-action: manipulation;
            }
            
            .open-maintenances-container .pagination-nav .page-link {
                min-height: 44px;
                min-width: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                touch-action: manipulation;
            }
            
            /* Enhanced touch feedback */
            .open-maintenances-container .btn:active {
                transform: scale(0.95);
                transition: transform 0.1s ease;
            }
            
            .open-maintenances-container .items-per-page select:active {
                transform: scale(0.98);
                transition: transform 0.1s ease;
            }
            
            .open-maintenances-container .pagination-nav .page-link:active {
                transform: scale(0.95);
                transition: transform 0.1s ease;
            }
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .open-maintenances-container .pagination-container {
                border: 2px solid #000;
                background: #fff;
            }
            
            .open-maintenances-container .items-per-page,
            .open-maintenances-container .pagination-nav {
                border: 2px solid #000;
                background: #fff;
            }
            
            .open-maintenances-container .pagination-nav .page-item.active .page-link {
                background-color: #000;
                border-color: #000;
                color: #fff;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .open-maintenances-container .modern-table tbody tr,
            .open-maintenances-container .btn-group .btn,
            .open-maintenances-container .pagination-nav .page-link,
            .open-maintenances-container .items-per-page select {
                transition: none;
                transform: none;
            }
            
            .open-maintenances-container .modern-table tbody tr:hover {
                transform: none;
            }
            
            .open-maintenances-container .btn-group .btn:hover {
                transform: none;
            }
        }
        
        /* Focus management for better keyboard navigation */
        .open-maintenances-container .items-per-page select:focus {
            outline: 3px solid #3498db;
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.25);
        }
        
        .open-maintenances-container .pagination-nav .page-link:focus {
            outline: 3px solid #3498db;
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.25);
            z-index: 2;
        }
        
        .open-maintenances-container .btn:focus {
            outline: 3px solid #3498db;
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.25);
        }
        
        /* Prevent layout breaks on very long content */
        @media (max-width: 480px) {
            .open-maintenances-container .pagination-container {
                min-height: auto;
                overflow-x: hidden;
            }
            
            .open-maintenances-container .pagination-info .results-info {
                font-size: 0.8rem;
                line-height: 1.3;
                word-wrap: break-word;
                max-width: 100%;
            }
            
            /* Ensure table doesn't overflow */
            .open-maintenances-container .table-responsive {
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border: 1px solid #e9ecef;
            }
            
            .open-maintenances-container .modern-table {
                margin-bottom: 0;
                border-radius: 0;
            }
        }
    </style>

    <!-- Loading Indicator Otimizado -->
    <div wire:loading class="open-maintenances-loading">
        <i class="fas fa-spinner fa-spin"></i>
        <span>{{ trans('general.loading') }}</span>
    </div>

    <!-- Container Principal Otimizado -->
    <div wire:loading.remove class="open-maintenances-container">
        @if(isset($error) && $error)
            <!-- Estado de Erro Otimizado -->
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <span>{{ $error }}</span>
            </div>
        @elseif(!isset($maintenances) || $maintenances->count() === 0)
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>{{ trans('general.no_results') }}</h3>
                <p>{{ trans('admin/maintenances/general.no_open_maintenances') }}</p>
                @can('create', \App\Models\Maintenance::class)
                    <a href="{{ route('maintenances.create') }}" class="action-btn" style="background: #3498db;">
                        <i class="fas fa-plus"></i>
                        {{ trans('general.create') }} {{ trans('admin/maintenances/general.maintenance') }}
                    </a>
                @endcan
            </div>
        @else
            <!-- Maintenances Table -->
            <div class="table-responsive" role="region" aria-label="{{ trans('admin/maintenances/general.open_maintenances') }}">
                <table class="table table-striped modern-table" role="table" aria-label="{{ trans('admin/maintenances/general.open_maintenances') }}">
                    <thead>
                        <tr role="row">
                            <th>
                                <a href="#" wire:click.prevent="sortBy('name')" class="text-decoration-none" style="color: #2c3e50; font-weight: 600;">
                                    {{ trans('general.name') }}
                                    @if($sortField === 'name')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}" style="color: #3498db;"></i>
                                    @else
                                        <i class="fas fa-sort text-muted"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="d-none d-md-table-cell">
                                <a href="#" wire:click.prevent="sortBy('asset_name')" class="text-decoration-none" style="color: #2c3e50; font-weight: 600;">
                                    {{ trans('admin/maintenances/table.asset_name') }}
                                    @if($sortField === 'asset_name')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}" style="color: #3498db;"></i>
                                    @else
                                        <i class="fas fa-sort text-muted"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="d-none d-lg-table-cell">
                                <a href="#" wire:click.prevent="sortBy('asset_maintenance_type')" class="text-decoration-none" style="color: #2c3e50; font-weight: 600;">
                                    {{ trans('admin/maintenances/form.asset_maintenance_type') }}
                                    @if($sortField === 'asset_maintenance_type')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}" style="color: #3498db;"></i>
                                    @else
                                        <i class="fas fa-sort text-muted"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="#" wire:click.prevent="sortBy('start_date')" class="text-decoration-none" style="color: #2c3e50; font-weight: 600;">
                                    {{ trans('admin/maintenances/form.start_date') }}
                                    @if($sortField === 'start_date')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}" style="color: #3498db;"></i>
                                    @else
                                        <i class="fas fa-sort text-muted"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="d-none d-lg-table-cell">
                                <a href="#" wire:click.prevent="sortBy('supplier_name')" class="text-decoration-none" style="color: #2c3e50; font-weight: 600;">
                                    {{ trans('general.supplier') }}
                                    @if($sortField === 'supplier_name')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}" style="color: #3498db;"></i>
                                    @else
                                        <i class="fas fa-sort text-muted"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="d-none d-md-table-cell text-right">
                                <a href="#" wire:click.prevent="sortBy('cost')" class="text-decoration-none" style="color: #2c3e50; font-weight: 600;">
                                    {{ trans('admin/maintenances/form.cost') }}
                                    @if($sortField === 'cost')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}" style="color: #3498db;"></i>
                                    @else
                                        <i class="fas fa-sort text-muted"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-right" style="color: #2c3e50; font-weight: 600;">{{ trans('table.actions') }}</th>
                        </tr>
                    </thead>
                        <tbody>
                            @foreach($maintenances as $maintenance)
                                <tr role="row">
                                    <td>
                                        <a href="{{ route('maintenances.show', $maintenance->id) }}" class="text-decoration-none">
                                            {{ $maintenance->name }}
                                        </a>
                                        @if($maintenance->is_warranty)
                                            <span class="badge badge-info">{{ trans('admin/maintenances/table.is_warranty') }}</span>
                                        @endif
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        @if($maintenance->asset)
                                            <a href="{{ route('hardware.show', $maintenance->asset->id) }}" class="text-decoration-none">
                                                {{ $maintenance->asset->name }}
                                                @if($maintenance->asset->asset_tag)
                                                    <small class="text-muted">({{ $maintenance->asset->asset_tag }})</small>
                                                @endif
                                            </a>
                                        @else
                                            <span class="text-muted">{{ trans('general.not_found') }}</span>
                                        @endif
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        @php
                                            $types = [
                                                'maintenance' => trans('admin/maintenances/general.maintenance'),
                                                'repair' => trans('admin/maintenances/general.repair'),
                                                'upgrade' => trans('admin/maintenances/general.upgrade'),
                                                'calibration' => trans('admin/maintenances/general.calibration'),
                                                'software_support' => trans('admin/maintenances/general.software_support'),
                                                'hardware_support' => trans('admin/maintenances/general.hardware_support'),
                                                'configuration_change' => trans('admin/maintenances/general.configuration_change'),
                                                'pat_test' => trans('admin/maintenances/general.pat_test'),
                                            ];
                                        @endphp
                                        {{ $types[$maintenance->asset_maintenance_type] ?? $maintenance->asset_maintenance_type }}
                                    </td>
                                    <td>
                                        @if($maintenance->start_date)
                                            {{ \Carbon\Carbon::parse($maintenance->start_date)->format(config('app.date_display_format', 'Y-m-d')) }}
                                        @else
                                            <span class="text-muted">{{ trans('general.not_set') }}</span>
                                        @endif
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        @if($maintenance->supplier)
                                            <a href="{{ route('suppliers.show', $maintenance->supplier->id) }}" class="text-decoration-none">
                                                {{ $maintenance->supplier->name }}
                                            </a>
                                        @else
                                            <span class="text-muted">{{ trans('general.not_set') }}</span>
                                        @endif
                                    </td>
                                    <td class="d-none d-md-table-cell text-right">
                                        @if($maintenance->cost > 0)
                                            {{ \App\Helpers\Helper::formatCurrencyOutput($maintenance->cost) }}
                                        @else
                                            <span class="text-muted">{{ trans('general.not_set') }}</span>
                                        @endif
                                    </td>
                                <td class="text-right">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('maintenances.show', $maintenance->id) }}" 
                                           class="btn btn-sm btn-primary" 
                                           data-tooltip="true" 
                                           title="{{ trans('general.view') }}"
                                           style="border-radius: 6px; margin-right: 4px;">
                                            <i class="fas fa-eye"></i>
                                            <span class="sr-only">{{ trans('general.view') }}</span>
                                        </a>
                                        @can('update', $maintenance)
                                            <a href="{{ route('maintenances.edit', $maintenance->id) }}" 
                                               class="btn btn-sm btn-warning" 
                                               data-tooltip="true" 
                                               title="{{ trans('general.edit') }}"
                                               style="border-radius: 6px;">
                                                <i class="fas fa-pencil-alt"></i>
                                                <span class="sr-only">{{ trans('general.edit') }}</span>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                                </tr>
                            @endforeach
                        </tbody>
                </table>
            </div>

            <!-- Paginação Consolidada e Otimizada -->
            @if($maintenances->count() > 0)
                <div class="pagination-container">
                    <div class="pagination-info">
                        <span class="results-info">
                            @if(isset($totalCount) && $totalCount > 0)
                                {{ trans('general.showing') }} 
                                <strong>{{ $maintenances->firstItem() ?? 1 }}</strong>-<strong>{{ $maintenances->lastItem() ?? $maintenances->count() }}</strong> 
                                {{ trans('general.of') }} 
                                <strong>{{ $totalCount }}</strong>
                            @else
                                {{ trans('general.showing') }} 
                                <strong>{{ $maintenances->count() }}</strong> 
                                {{ trans('general.items') }}
                            @endif
                        </span>
                    </div>
                    
                    <div class="pagination-controls">
                        <div class="items-per-page">
                            <label for="perPage-{{ $this->getId() }}">{{ trans('general.items_per_page') }}:</label>
                            <select wire:model.live="perPage" id="perPage-{{ $this->getId() }}" class="form-control form-control-sm">
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected($option == $perPage)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        @if($maintenances->hasPages())
                            <div class="pagination-nav">
                                {{ $maintenances->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

    <!-- JavaScript Otimizado para OpenMaintenancesTable -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Otimização: Gerenciamento de loading states
            const container = document.querySelector('.open-maintenances-container');
            if (container) {
                // Listener para eventos Livewire
                Livewire.on('perPageUpdated', (perPage) => {
                    console.log('Items per page updated to:', perPage);
                });
                
                Livewire.on('sortUpdated', (field, direction) => {
                    console.log('Sort updated:', field, direction);
                    
                    // Feedback visual para ordenação
                    const sortButtons = container.querySelectorAll('th a');
                    sortButtons.forEach(btn => {
                        const icon = btn.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-sort-up', 'fa-sort-down');
                            icon.classList.add('fa-sort');
                            icon.style.color = '#6c757d';
                        }
                    });
                    
                    // Destaca o campo ativo
                    const activeButton = container.querySelector(`th a[wire\\:click\\.prevent="sortBy('${field}')"]`);
                    if (activeButton) {
                        const icon = activeButton.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-sort');
                            icon.classList.add(direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
                            icon.style.color = '#3498db';
                        }
                    }
                });
                
                Livewire.on('dataRefreshed', () => {
                    console.log('Data refreshed');
                });
                
                // Otimização: Melhora acessibilidade dos botões de ordenação
                const sortLinks = container.querySelectorAll('th a[wire\\:click\\.prevent]');
                sortLinks.forEach(link => {
                    link.setAttribute('role', 'button');
                    link.setAttribute('tabindex', '0');
                    
                    // Suporte para navegação por teclado
                    link.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            this.click();
                        }
                    });
                });
                
                // Otimização: Melhora performance do select de items per page
                const perPageSelect = container.querySelector('select[wire\\:model\\.live="perPage"]');
                if (perPageSelect) {
                    let timeout;
                    perPageSelect.addEventListener('change', function() {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => {
                            console.log('Per page changed to:', this.value);
                        }, 100);
                    });
                }
            }
        });
    </script>
</div>