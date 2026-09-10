{{-- Uses the same fields and validation as the full supplier form. --}}
<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('general.close') }}"><span aria-hidden="true">&times;</span></button>
            <h2 class="modal-title">{{ trans('admin/suppliers/table.create') }}</h2>
        </div>
        <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
            <form action="{{ route('api.suppliers.store') }}" enctype="multipart/form-data" onsubmit="return false">
                <div class="alert alert-danger" id="modal_error_msg" style="display:none"></div>
                @include('suppliers.fields', ['item' => new \App\Models\Supplier()])
            </form>
        </div>
        <div class="dynamic-form-row">
            @include('modals.partials.footer')
        </div>
    </div>
</div>