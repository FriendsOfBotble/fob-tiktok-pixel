<div class="mt-3">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">{{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.test_connection') }}</h4>
        </div>
        <div class="card-body">
            <button type="button" class="btn btn-primary" id="tiktok-pixel-test-connection">
                {{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.test_connection') }}
            </button>
            <div id="tiktok-pixel-test-result" class="mt-2" style="display: none;"></div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h4 class="card-title">{{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.setup_title') }}</h4>
        </div>
        <div class="card-body">
            <ol>
                <li>{{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.setup_step_1') }}</li>
                <li>{{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.setup_step_2') }}</li>
                <li>{{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.setup_step_3') }}</li>
                <li>{{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.setup_step_4') }}</li>
                <li>{{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.setup_step_5') }}</li>
            </ol>
            <a href="https://ads.tiktok.com/i18n/events_manager" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary mt-2">
                {{ trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.open_ads_manager') }}
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-external-link" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                    <path d="M11 13l9 -9" />
                    <path d="M15 4h5v5" />
                </svg>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('tiktok-pixel-test-connection');
    var result = document.getElementById('tiktok-pixel-test-result');

    if (btn) {
        btn.addEventListener('click', function() {
            btn.disabled = true;
            btn.textContent = @json(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.testing'));
            result.style.display = 'none';

            fetch(@json(route('fob-tiktok-pixel.settings.test-connection')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                result.style.display = 'block';
                result.className = 'mt-2 alert alert-' + (data.success ? 'success' : 'danger');
                result.textContent = data.message;
            })
            .catch(function(error) {
                result.style.display = 'block';
                result.className = 'mt-2 alert alert-danger';
                result.textContent = error.message || 'An error occurred';
            })
            .finally(function() {
                btn.disabled = false;
                btn.textContent = @json(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.test_connection'));
            });
        });
    }
});
</script>
