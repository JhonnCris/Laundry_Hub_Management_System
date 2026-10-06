@php
    $isAdmin = auth()->user()?->role === 'admin';
    $guide = __($isAdmin ? 'faq.admin_guide' : 'faq.staff_guide');
    $faq = __($isAdmin ? 'faq.admin_faq' : 'faq.staff_faq');
    $system = __('faq.system');
@endphp
<button type="button" class="help-fab" data-open-help aria-label="{{ __('faq.button') }}" title="{{ __('faq.button') }}">?</button>

<div class="modal-backdrop" data-help-modal hidden>
    <section class="profile-modal help-modal" role="dialog" aria-modal="true" aria-label="{{ __('faq.title') }}">
        <button class="modal-close" data-close-help type="button" aria-label="{{ __('faq.close') }}">×</button>
        <h2>{{ __('faq.title') }}</h2>
        <p class="modal-hint">{{ __('faq.intro') }}</p>

        <div class="help-tabs" role="tablist">
            @foreach (__('faq.tabs') as $key => $label)
                <button type="button" role="tab" class="help-tab{{ $loop->first ? ' is-selected' : '' }}" data-help-tab="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="help-body" data-help-pane="guide">
            @foreach ($guide as $section)
                <details class="help-item" @if ($loop->first) open @endif>
                    <summary>{{ $section['title'] }}</summary>
                    <ol>
                        @foreach ($section['steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </details>
            @endforeach
        </div>

        <div class="help-body" data-help-pane="faq" hidden>
            @foreach ($faq as $entry)
                <details class="help-item">
                    <summary>{{ $entry['q'] }}</summary>
                    <p>{{ $entry['a'] }}</p>
                </details>
            @endforeach
        </div>

        <div class="help-body" data-help-pane="system" hidden>
            @foreach ($system as $section)
                <details class="help-item" @if ($loop->first) open @endif>
                    <summary>{{ $section['title'] }}</summary>
                    <ul>
                        @foreach ($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>

        <div class="modal-actions">
            <button type="button" class="save-button" data-close-help>{{ __('faq.close') }}</button>
        </div>
    </section>
</div>
