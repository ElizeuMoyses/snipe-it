@extends('layouts/edit-form', [
    'createText' => trans('admin/contract_status_labels/general.create'),
    'updateText' => trans('admin/contract_status_labels/general.update'),
    'helpTitle' => trans('admin/contract_status_labels/general.about_title'),
    'helpText' => trans('admin/contract_status_labels/general.about_text'),
    'formAction' => (isset($item->id)) ? route('contract-status-labels.update', ['contract_status_label' => $item->id]) : route('contract-status-labels.store'),
])

{{-- Page content --}}
@section('inputFields')

@include ('partials.forms.edit.name', ['translated_name' => trans('general.name')])

<!-- Scope -->
<div class="form-group {{ $errors->has('scope') ? ' has-error' : '' }}">
    <label for="scope" class="col-md-3 control-label">{{ trans('admin/contract_status_labels/general.scope') }}</label>
    <div class="col-md-7 required">
        <select class="form-control" name="scope" id="scope">
            <option value="">{{ trans('general.select') }}</option>
            @foreach($scopes as $key => $label)
                <option value="{{ $key }}" {{ old('scope', $item->scope) == $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        {!! $errors->first('scope', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Meta Type -->
<div class="form-group {{ $errors->has('meta_type') ? ' has-error' : '' }}">
    <label for="meta_type" class="col-md-3 control-label">{{ trans('admin/contract_status_labels/general.meta_type') }}</label>
    <div class="col-md-7 required">
        <select class="form-control" name="meta_type" id="meta_type">
            <option value="">{{ trans('general.select') }}</option>
        </select>
        {!! $errors->first('meta_type', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Color -->
<div class="form-group {{ $errors->has('color') ? ' has-error' : '' }}">
    <label for="color" class="col-md-3 control-label">{{ trans('admin/contract_status_labels/general.color') }}</label>
    <div class="col-md-9">
        <x-input.colorpicker :item="$item" id="color" :value="old('color', ($item->color ?? '#f4f4f4'))" name="color" id="color" />
        {!! $errors->first('color', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
    </div>
</div>

<!-- Icon -->
<div class="form-group {{ $errors->has('icon') ? ' has-error' : '' }}">
    <label for="icon" class="col-md-3 control-label">{{ trans('admin/contract_status_labels/general.icon') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="icon" type="text" id="icon" value="{{ old('icon', $item->icon) }}" placeholder="fas fa-circle">
        <p class="help-block">{{ trans('admin/contract_status_labels/general.icon_help') }}</p>
        {!! $errors->first('icon', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Sort Order -->
<div class="form-group {{ $errors->has('sort_order') ? ' has-error' : '' }}">
    <label for="sort_order" class="col-md-3 control-label">{{ trans('admin/contract_status_labels/general.sort_order') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="sort_order" type="number" id="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
        {!! $errors->first('sort_order', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.notes')

<!-- Is Default -->
<div class="form-group {{ $errors->has('is_default') ? ' has-error' : '' }}">
    <div class="col-md-9 col-md-offset-3">
        <label class="form-control">
            <input type="checkbox" value="1" name="is_default" id="is_default" {{ old('is_default', $item->is_default) == '1' ? ' checked="checked"' : '' }}>
            {{ trans('admin/contract_status_labels/general.is_default') }}
        </label>
        <p class="help-block">{{ trans('admin/contract_status_labels/general.is_default_help') }}</p>
    </div>
</div>

@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    var metaTypes = @json($meta_types);

    function updateMetaTypes() {
        var scope = document.getElementById('scope').value;
        var metaTypeSelect = document.getElementById('meta_type');
        var currentValue = '{{ old('meta_type', $item->meta_type) }}';

        // Clear existing options
        metaTypeSelect.innerHTML = '<option value="">{{ trans('general.select') }}</option>';

        if (scope && metaTypes[scope]) {
            metaTypes[scope].forEach(function(type) {
                var option = document.createElement('option');
                option.value = type;
                option.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                if (type === currentValue) {
                    option.selected = true;
                }
                metaTypeSelect.appendChild(option);
            });
        }
    }

    document.getElementById('scope').addEventListener('change', updateMetaTypes);

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', updateMetaTypes);
</script>
@stop
