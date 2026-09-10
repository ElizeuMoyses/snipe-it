@extends('layouts/edit-form', [
    'createText' => trans('admin/contract_types/general.create'),
    'updateText' => trans('admin/contract_types/general.update'),
    'helpTitle' => trans('admin/contract_types/general.about_title'),
    'helpText' => trans('admin/contract_types/general.about_text'),
    'formAction' => isset($item->id)
        ? route('contract-types.update', ['contract_type' => $item->id])
        : route('contract-types.store'),
])

@section('inputFields')
@include ('partials.forms.edit.name', ['translated_name' => trans('general.name')])

<div class="form-group {{ $errors->has('code') ? 'has-error' : '' }}">
    <label for="code" class="col-md-3 control-label">{{ trans('admin/contract_types/general.code') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="code" type="text" id="code" value="{{ old('code', $item->code) }}" pattern="[a-z0-9]+([_-][a-z0-9]+)*" aria-describedby="code-help" />
        <p id="code-help" class="help-block">{{ trans('admin/contract_types/general.code_help') }}</p>
        {!! $errors->first('code', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<div class="form-group {{ $errors->has('is_active') ? 'has-error' : '' }}">
    <div class="col-md-9 col-md-offset-3">
        <label class="form-control">
            <input type="checkbox" value="1" name="is_active" id="is_active" {{ old('is_active', $item->is_active) ? 'checked="checked"' : '' }} />
            {{ trans('admin/contract_types/general.is_active') }}
        </label>
        {!! $errors->first('is_active', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.notes')
@stop
