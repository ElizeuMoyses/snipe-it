@if (session('checkout_signature_links'))
    <div class="modal fade" id="checkout-signature-modal" tabindex="-1" role="dialog"
         aria-labelledby="checkout-signature-title" aria-describedby="checkout-signature-description"
         data-copied="{{ trans('checkout-signature.copied') }}"
         data-copy-error="{{ trans('checkout-signature.copy_error') }}">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('general.close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="checkout-signature-title">{{ trans('checkout-signature.title') }}</h4>
                </div>
                <div class="modal-body">
                    <p id="checkout-signature-description">{{ trans('checkout-signature.description') }}</p>
                    @foreach (session('checkout_signature_links') as $link)
                        <div class="panel panel-default checkout-signature-item">
                            <div class="panel-body">
                                <p><strong>{{ trans('general.user') }}:</strong> {{ $link['assignee'] }}</p>
                                <p><strong>{{ trans('general.asset') }}:</strong> {{ $link['asset_tag'] }}
                                    @if ($link['asset_name']) &mdash; {{ $link['asset_name'] }} @endif
                                </p>
                                <div class="form-group">
                                    <label for="checkout-signature-link-{{ $link['acceptance_id'] }}">{{ trans('checkout-signature.link') }}</label>
                                    <input type="text" class="form-control checkout-signature-link" readonly
                                           id="checkout-signature-link-{{ $link['acceptance_id'] }}" value="{{ $link['url'] }}">
                                </div>
                                <p class="text-muted">{{ trans('checkout-signature.expires', ['date' => $link['expires_at']]) }}</p>
                                <button type="button" class="btn btn-primary checkout-copy-link"
                                        data-clipboard-target="#checkout-signature-link-{{ $link['acceptance_id'] }}">
                                    <i class="fa fa-copy" aria-hidden="true"></i> {{ trans('checkout-signature.copy') }}
                                </button>
                                <a href="{{ $link['url'] }}" class="btn btn-default" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-external-link" aria-hidden="true"></i> {{ trans('checkout-signature.open') }}
                                </a>
                                <p class="checkout-link-status help-block" role="status" aria-live="polite"></p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('general.close') }}</button>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ url('js/checkout-signature-links.js') }}" nonce="{{ csrf_token() }}"></script>
@endif
