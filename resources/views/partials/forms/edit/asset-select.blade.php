@php
    $selectId = $select_id ?? 'assigned_asset_select';
    $isContractAssetSelector = !empty($contract_asset_selector);
    $selectorStatusId = $status_id ?? ($selectId.'-status');
@endphp

<!-- Asset -->
<div id="{{ $asset_selector_div_id ?? "assigned_asset" }}"
     class="form-group{{ $errors->has($fieldname) ? ' has-error' : '' }}"{!!  (isset($style)) ? ' style="'.e($style).'"' : ''  !!}>
    <label for="{{ $selectId }}" class="col-md-3 control-label">{{ $translated_name }}</label>
    <div class="col-md-7">
        <select class="js-data-ajax select2"
                data-endpoint="hardware"
                data-placeholder="{{ $placeholder ?? trans('general.select_asset') }}"
                aria-label="{{ $fieldname }}"
                name="{{ $fieldname }}"
                style="width: 100%"
                id="{{ $selectId }}"
                @if(!empty($ajax_url)) data-ajax-url="{{ $ajax_url }}" @endif
                @if($isContractAssetSelector)
                    data-contract-asset-selector="true"
                    data-status-target="{{ $selectorStatusId }}"
                    data-loading-message="{{ trans('admin/contracts/message.asset.selector.loading') }}"
                    data-no-results-message="{{ trans('admin/contracts/message.asset.selector.no_results') }}"
                    data-error-message="{{ trans('admin/contracts/message.asset.selector.error') }}"
                @endif
                @if(!empty($describedby)) aria-describedby="{{ $describedby }}" @endif
                {{ ((isset($multiple)) && ($multiple === true)) ? ' multiple' : '' }}
                {!! (!empty($asset_status_type)) ? ' data-asset-status-type="' . $asset_status_type . '"' : '' !!}
                {!! (!empty($company_id)) ? ' data-company-id="' .$company_id.'"'  : '' !!}
                {{  ((isset($required) && ($required =='true'))) ?  ' required' : '' }}
        >

            @if ((!isset($unselect)) && ($asset_id = old($fieldname, (isset($asset) ? $asset->id  : (isset($item) ? $item->{$fieldname} : '')))))
                <option value="{{ $asset_id }}" selected="selected" role="option" aria-selected="true"  role="option">
                    {{ (\App\Models\Asset::find($asset_id)) ? \App\Models\Asset::find($asset_id)->present()->fullName : '' }}
                </option>
            @else
                @if(!isset($multiple))
                    <option value=""  role="option">{{ trans('general.select_asset') }}</option>
                @else
                    @if(isset($asset_ids))
                        @foreach($asset_ids as $asset_id)
                            <option value="{{ $asset_id }}" selected="selected" role="option" aria-selected="true"
                                    role="option">
                                {{ (\App\Models\Asset::find($asset_id)) ? \App\Models\Asset::find($asset_id)->present()->fullName : '' }}
                            </option>
                        @endforeach
                    @endif
                @endif
            @endif
        </select>
    </div>
    @if($isContractAssetSelector)
        <div class="col-md-8 col-md-offset-3">
            <span id="{{ $selectorStatusId }}" class="help-block contract-asset-selector-status" role="status" aria-live="polite"></span>
        </div>
    @endif
    {!! $errors->first($fieldname, '<div class="col-md-8 col-md-offset-3"><span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}

</div>
