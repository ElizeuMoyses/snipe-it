<?php

namespace App\Livewire;

use App\Models\Maintenance;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

class OpenMaintenancesTable extends Component
{
    use WithPagination;

    public $perPage = 5;
    public $perPageOptions = [5, 10, 25, 50];
    public $sortField = 'start_date';
    public $sortDirection = 'desc';
    
    // Otimização: Reduz parâmetros na query string para URLs mais limpas
    protected $queryString = [
        'sortField' => ['except' => 'start_date', 'as' => 'sort'],
        'sortDirection' => ['except' => 'desc', 'as' => 'dir'],
        'perPage' => ['except' => 5, 'as' => 'per'],
    ];

    // Cache key for total count
    private function getCacheKey(): string
    {
        return 'open_maintenances_count';
    }

    public function updatedPerPage()
    {
        $this->resetPage();
        // Clear cache when per page changes to ensure accurate count display
        Cache::forget($this->getCacheKey());
        
        // Otimização: Dispatch evento para atualizar layout se necessário
        $this->dispatch('perPageUpdated', $this->perPage);
    }

    public function sortBy($field)
    {
        // Otimização: Validação de campo para evitar ordenação inválida
        $allowedFields = ['name', 'asset_name', 'asset_tag', 'supplier_name', 'start_date', 'cost', 'asset_maintenance_type'];
        
        if (!in_array($field, $allowedFields)) {
            return;
        }
        
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
        $this->resetPage();
        
        // Otimização: Dispatch evento para feedback visual
        $this->dispatch('sortUpdated', $field, $this->sortDirection);
    }

    public function render(): View
    {
        try {
            $query = $this->getOptimizedQuery();

            // Otimização: Apply sorting com validação melhorada
            $this->applySorting($query);

            // Otimização: Use simple paginate para melhor performance
            $maintenances = $query->simplePaginate($this->perPage);
            
            // Otimização: Cache total count apenas quando necessário
            $totalCount = $this->shouldShowTotalCount() ? $this->getTotalCount() : null;

            return view('livewire.open-maintenances-table', [
                'maintenances' => $maintenances,
                'totalCount' => $totalCount,
                'perPageOptions' => $this->perPageOptions,
                'hasError' => false,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao carregar manutenções em aberto: ' . $e->getMessage(), [
                'sortField' => $this->sortField,
                'sortDirection' => $this->sortDirection,
                'perPage' => $this->perPage,
                'exception' => $e->getTraceAsString()
            ]);
            
            return view('livewire.open-maintenances-table', [
                'maintenances' => collect(),
                'error' => trans('admin/maintenances/general.error_loading_maintenances'),
                'totalCount' => 0,
                'perPageOptions' => $this->perPageOptions,
                'hasError' => true,
            ]);
        }
    }

    public function getMaintenanceTypeDisplayAttribute($type)
    {
        $types = Maintenance::getImprovementOptions();
        return $types[$type] ?? $type;
    }

    /**
     * Get optimized query for open maintenances
     */
    private function getOptimizedQuery()
    {
        return Maintenance::openWithRelations();
    }

    /**
     * Get total count with caching for performance
     */
    public function getTotalCount(): int
    {
        $cacheKey = $this->getCacheKey();
        
        return Cache::remember($cacheKey, 300, function () { // Cache for 5 minutes
            return $this->getOptimizedQuery()->count();
        });
    }

    /**
     * Otimização: Aplica ordenação de forma centralizada
     */
    private function applySorting($query)
    {
        switch ($this->sortField) {
            case 'asset_name':
                $query->orderByAssetName($this->sortDirection);
                break;
            case 'asset_tag':
                $query->orderByTag($this->sortDirection);
                break;
            case 'supplier_name':
                $query->orderBySupplier($this->sortDirection);
                break;
            default:
                // Validação adicional para campos da tabela maintenances
                $allowedMaintenanceFields = ['name', 'start_date', 'cost', 'asset_maintenance_type'];
                if (in_array($this->sortField, $allowedMaintenanceFields)) {
                    $query->orderBy('maintenances.' . $this->sortField, $this->sortDirection);
                } else {
                    // Fallback para ordenação padrão
                    $query->orderBy('maintenances.start_date', 'desc');
                }
                break;
        }
    }

    /**
     * Otimização: Determina se deve mostrar contagem total
     */
    private function shouldShowTotalCount(): bool
    {
        // Só calcula total count se realmente necessário (ex: primeira página)
        return $this->getPage() <= 2;
    }

    /**
     * Otimização: Limpa cache quando necessário
     */
    public function clearCache()
    {
        Cache::forget($this->getCacheKey());
    }

    /**
     * Otimização: Método para recarregar dados sem recriar componente
     */
    public function refresh()
    {
        $this->clearCache();
        $this->dispatch('dataRefreshed');
    }
}