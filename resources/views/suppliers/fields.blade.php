@include ('partials.forms.edit.name', ['translated_name' => trans('admin/suppliers/table.name')])
@include ('partials.forms.edit.address')

<div class="form-group {{ $errors->has('contact') ? ' has-error' : '' }}">
    <label for="contact" class="col-md-3 control-label">{{ trans('admin/suppliers/table.contact') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="contact" type="text" id="contact" value="{{ old('contact', $item->contact) }}">
        {!! $errors->first('contact', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.phone')
@include ('partials.forms.edit.fax')
@include ('partials.forms.edit.email')

<div class="form-group {{ $errors->has('url') ? ' has-error' : '' }}">
    <label for="url" class="col-md-3 control-label">{{ trans('general.url') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="url" type="url" id="url" value="{{ old('url', $item->url) }}">
        {!! $errors->first('url', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

{{-- Dados Fiscais BR --}}
<fieldset name="br-fiscal-data">
    <x-form.legend>{{ trans('admin/suppliers/table.br_data') }}</x-form.legend>

    {{-- Tipo de Pessoa --}}
    <div class="form-group {{ $errors->has('supplier_type') ? ' has-error' : '' }}">
        <label for="supplier_type" class="col-md-3 control-label">{{ trans('admin/suppliers/table.supplier_type') }}</label>
        <div class="col-md-7">
            <select class="form-control select2" name="supplier_type" id="supplier_type">
                <option value="">-- {{ trans('admin/suppliers/table.supplier_type') }} --</option>
                <option value="pj" {{ old('supplier_type', $item->supplier_type) === 'pj' ? 'selected' : '' }}>{{ trans('admin/suppliers/table.supplier_type_pj') }}</option>
                <option value="pf" {{ old('supplier_type', $item->supplier_type) === 'pf' ? 'selected' : '' }}>{{ trans('admin/suppliers/table.supplier_type_pf') }}</option>
                <option value="international" {{ old('supplier_type', $item->supplier_type) === 'international' ? 'selected' : '' }}>{{ trans('admin/suppliers/table.supplier_type_international') }}</option>
            </select>
            {!! $errors->first('supplier_type', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>

    {{-- Documento (CNPJ/CPF) --}}
    <div class="form-group {{ $errors->has('document') ? ' has-error' : '' }}">
        <label for="document" class="col-md-3 control-label" id="document_label">{{ trans('admin/suppliers/table.document') }}</label>
        <div class="col-md-7">
            <input class="form-control" name="document" type="text" id="document" maxlength="20" value="{{ old('document', $item->document) }}">
            {!! $errors->first('document', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>

    {{-- Razão Social --}}
    <div class="form-group {{ $errors->has('corporate_name') ? ' has-error' : '' }}">
        <label for="corporate_name" class="col-md-3 control-label">{{ trans('admin/suppliers/table.corporate_name') }}</label>
        <div class="col-md-7">
            <input class="form-control" name="corporate_name" type="text" id="corporate_name" maxlength="255" value="{{ old('corporate_name', $item->corporate_name) }}">
            {!! $errors->first('corporate_name', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>

    {{-- Código Interno --}}
    <div class="form-group {{ $errors->has('internal_code') ? ' has-error' : '' }}">
        <label for="internal_code" class="col-md-3 control-label">{{ trans('admin/suppliers/table.internal_code') }}</label>
        <div class="col-md-7">
            <input class="form-control" name="internal_code" type="text" id="internal_code" maxlength="50" value="{{ old('internal_code', $item->internal_code) }}">
            {!! $errors->first('internal_code', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>
</fieldset>

@include ('partials.forms.edit.notes')
@include ('partials.forms.edit.image-upload', ['image_path' => app('suppliers_upload_path')])

<fieldset name="color-preferences">
    <x-form.legend help_text="{{ trans('general.tag_color_help') }}">
        {{ trans('general.tag_color') }}
    </x-form.legend>
    <!--  color -->
    <div class="form-group {{ $errors->has('tag_color') ? 'error' : '' }}">
        <label for="tag_color" class="col-md-3 control-label">
            {{ trans('general.tag_color') }}
        </label>
        <div class="col-md-9">
            <x-input.colorpicker :item="$item" id="color" :value="old('color', ($item->color ?? '#f4f4f4'))" name="tag_color" id="tag_color" />
            {!! $errors->first('tag_color', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
        </div>
    </div>
</fieldset>
