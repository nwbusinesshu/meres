@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('admin/competencies.competencies') }}</h1>

{{-- Language Context Information --}}
<div class="current-language-info">
  <i class="fas fa-info-circle"></i>
  <strong>{{ __('translations.current-language') }}:</strong> 
  {{ $languageNames[$currentLocale] ?? strtoupper($currentLocale) }}
  <small class="text-muted ml-2">
    {{ __('translations.viewing-in-language', ['language' => $languageNames[$currentLocale] ?? strtoupper($currentLocale)]) }}
  </small>
</div>

<div class="fixed-row">
  <div class="tile tile-info competency-search">
    <span>{{ __('admin/competencies.search') }}</span>
    <div>
      <input type="text" class="form-control competency-search-input" placeholder="{{ __('admin/competencies.search') }}...">
      <i class="fa fa-ban competency-clear-search" data-tippy-content="{{ __('admin/competencies.search-clear') }}"></i>
    </div>
  </div>
  <div class="tile tile-button create-competency">
    <span><i class="fa fa-circle-plus"></i>{{ __('admin/competencies.create-competency') }}</span>
  </div>
</div>

{{-- ============================ --}}
{{-- Organization-specific Competencies --}}
{{-- ============================ --}}
<div class="competency-list competency-list--org-crud">
  @forelse ($competencies as $comp)
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->getTranslatedName() }}">
      <div class="bar">
        <span><i class="fa fa-caret-down"></i>{{ $comp->getTranslatedName() }}</span>
        
        {{-- Translation Status Indicators --}}
        <div class="competency-translation-status">
          @foreach($availableLanguages as $lang)
            @php
              $hasTranslation = $comp->hasTranslation($lang);
              $isOriginal = $lang === $comp->original_language;
              $statusClass = $isOriginal ? 'original' : ($hasTranslation ? 'available' : 'missing');
              $tooltip = $isOriginal ? 
                __('translations.original-language') . ': ' . ($languageNames[$lang] ?? strtoupper($lang)) :
                ($hasTranslation ? 
                  __('translations.translated-to') . ': ' . ($languageNames[$lang] ?? strtoupper($lang)) :
                  __('translations.missing-translation') . ': ' . ($languageNames[$lang] ?? strtoupper($lang))
                );
            @endphp
            <span class="language-indicator {{ $statusClass }}" 
                  data-tippy-content="{{ $tooltip }}">
              {{ strtoupper($lang) }}
            </span>
          @endforeach
        </div>
        
        <div>
          <button class="btn btn-outline-primary btn-sm manage-translations" 
                  data-competency-id="{{ $comp->id }}"
                  data-tippy-content="{{ __('translations.manage-translations') }}">
            <i class="fa fa-language"></i>
          </button>
          <button class="btn btn-outline-danger remove-competency" data-tippy-content="{{ __('admin/competencies.remove-competency') }}"><i class="fa fa-trash-alt"></i></button>
          <button class="btn btn-outline-warning modify-competency" data-tippy-content="{{ __('admin/competencies.modify-competency') }}"><i class="fa fa-file-pen"></i></button>
        </div>
      </div>

      <div class="questions hidden">
        <div class="create-question tile tile-button">
          <span><i class="fa fa-circle-plus"></i>{{ __('admin/competencies.create-question') }}</span>
        </div>

        @foreach ($comp->questions as $q)
          <div class="question-item tile" data-id="{{ $q->id }}">
            <div class="bar">
              <span>{{ $q->getTranslatedQuestion() }}</span>
              
              {{-- Question Translation Status --}}
              <div class="question-translation-status">
                @foreach($availableLanguages as $lang)
                  @php
                    $hasTranslation = $q->hasTranslation($lang);
                    $isOriginal = $lang === $q->original_language;
                    $isPartial = $q->hasPartialTranslation($lang);
                    $statusClass = $isOriginal ? 'original' : 
                                  ($hasTranslation ? 'available' : 
                                   ($isPartial ? 'partial' : 'missing'));
                    $tooltip = $isOriginal ? 
                      __('translations.original-language') . ': ' . ($languageNames[$lang] ?? strtoupper($lang)) :
                      ($hasTranslation ? 
                        __('translations.complete-translation') . ': ' . ($languageNames[$lang] ?? strtoupper($lang)) :
                        ($isPartial ?
                          __('translations.partial-translation') . ': ' . ($languageNames[$lang] ?? strtoupper($lang)) :
                          __('translations.missing-translation') . ': ' . ($languageNames[$lang] ?? strtoupper($lang))
                        )
                      );
                  @endphp
                  <span class="language-indicator {{ $statusClass }}" 
                        data-tippy-content="{{ $tooltip }}">
                    {{ strtoupper($lang) }}
                  </span>
                @endforeach
              </div>
              
              <div>
                <button class="btn btn-outline-primary btn-sm manage-question-translations" 
                        data-question-id="{{ $q->id }}"
                        data-tippy-content="{{ __('translations.manage-question-translations') }}">
                  <i class="fa fa-language"></i>
                </button>
                <button class="btn btn-outline-danger remove-question" data-tippy-content="{{ __('admin/competencies.remove-question') }}"><i class="fa fa-trash-alt"></i></button>
                <button class="btn btn-outline-warning modify-question" data-tippy-content="{{ __('admin/competencies.modify-question') }}"><i class="fa fa-file-pen"></i></button>
              </div>
            </div>

            <div class="question-details">
              <p><strong>{{ __('admin/competencies.question') }}:</strong> {{ $q->getTranslatedQuestion() }}</p>
              <p><strong>{{ __('admin/competencies.question-self') }}:</strong> {{ $q->getTranslatedQuestionSelf() }}</p>
              <div class="scale-info">
                <span class="badge badge-info">{{ $q->getTranslatedMinLabel() }}</span>
                <span class="mx-2">1 - {{ $q->max_value }}</span>
                <span class="badge badge-success">{{ $q->getTranslatedMaxLabel() }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @empty
    <div class="tile no-competency">
      <span>{{ __('admin/competencies.no-competencies') }}</span>
    </div>
  @endforelse
</div>

{{-- ============================ --}}
{{-- Global Competencies (Read-only) --}}
{{-- ============================ --}}
<div class="competency-list competency-list--global">
  <div class="tile tile-info" style="margin-top: 40px;">
    <span><strong>{{ __('admin/competencies.global-competencies') }}</strong></span>
    <small class="text-muted ml-2">{{ __('admin/competencies.global-competencies-help') }}</small>
  </div>

  @forelse ($globals as $comp)
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->getTranslatedName() }}">
      <div class="bar">
        <span><i class="fa fa-caret-down"></i>{{ $comp->getTranslatedName() }}</span>
        
        {{-- Translation Status for Global Competencies --}}
        <div class="competency-translation-status">
          @foreach($availableLanguages as $lang)
            @php
              $hasTranslation = $comp->hasTranslation($lang);
              $isOriginal = $lang === $comp->original_language;
              $statusClass = $isOriginal ? 'original' : ($hasTranslation ? 'available' : 'missing');
            @endphp
            <span class="language-indicator {{ $statusClass }}" 
                  data-tippy-content="{{ $languageNames[$lang] ?? strtoupper($lang) }} ({{ $isOriginal ? __('translations.original') : ($hasTranslation ? __('translations.available') : __('translations.missing')) }})">
              {{ strtoupper($lang) }}
            </span>
          @endforeach
        </div>
      </div>

      <div class="questions hidden">
        @foreach ($comp->questions as $q)
          <div class="question-item tile" data-id="{{ $q->id }}">
            <div class="bar">
              <span>{{ $q->getTranslatedQuestion() }}</span>
              
              {{-- Question Translation Status --}}
              <div class="question-translation-status">
                @foreach($availableLanguages as $lang)
                  @php
                    $hasTranslation = $q->hasTranslation($lang);
                    $isOriginal = $lang === $q->original_language;
                    $isPartial = $q->hasPartialTranslation($lang);
                    $statusClass = $isOriginal ? 'original' : 
                                  ($hasTranslation ? 'available' : 
                                   ($isPartial ? 'partial' : 'missing'));
                    $tooltip = $languageNames[$lang] ?? strtoupper($lang);
                    if ($isOriginal) $tooltip .= ' (' . __('translations.original') . ')';
                    elseif ($hasTranslation) $tooltip .= ' (' . __('translations.complete') . ')';
                    elseif ($isPartial) $tooltip .= ' (' . __('translations.partial') . ')';
                    else $tooltip .= ' (' . __('translations.missing') . ')';
                  @endphp
                  <span class="language-indicator {{ $statusClass }}" 
                        data-tippy-content="{{ $tooltip }}">
                    {{ strtoupper($lang) }}
                  </span>
                @endforeach
              </div>
            </div>

            <div class="question-details">
              <p><strong>{{ __('admin/competencies.question') }}:</strong> {{ $q->getTranslatedQuestion() }}</p>
              <p><strong>{{ __('admin/competencies.question-self') }}:</strong> {{ $q->getTranslatedQuestionSelf() }}</p>
              <div class="scale-info">
                <span class="badge badge-info">{{ $q->getTranslatedMinLabel() }}</span>
                <span class="mx-2">1 - {{ $q->max_value }}</span>
                <span class="badge badge-success">{{ $q->getTranslatedMaxLabel() }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @empty
    <div class="tile no-competency">
      <span>{{ __('admin/competencies.no-competencies') }}</span>
    </div>
  @endforelse
</div>

{{-- Translation Management Modal --}}
<div class="modal fade" id="translation-management-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('translations.manage-translations') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="translation-content">
          <!-- Content will be loaded dynamically -->
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Question Translation Management Modal --}}
<div class="modal fade" id="question-translation-modal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('translations.manage-question-translations') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="question-translation-content">
          <!-- Content will be loaded dynamically -->
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('modals')
  @include('admin.modals.competency')
  @include('admin.modals.competencyq')
@endsection

@section('scripts')
<script>
// Pass data to JavaScript
window.availableLanguages = @json($availableLanguages);
window.languageNames = @json($languageNames);
window.currentLocale = @json($currentLocale);

// Define routes for JavaScript
window.routes = {
  admin_competency_remove: "{{ route('admin.competency.remove') }}",
  admin_competency_question_remove: "{{ route('admin.competency.question.remove') }}",
  admin_competency_translations_get: "{{ route('admin.competency.translations.get') }}",
  admin_competency_translations_save: "{{ route('admin.competency.translations.save') }}",
  admin_competency_translations_ai: "{{ route('admin.competency.translations.ai') }}",
  admin_competency_question_translations_get: "{{ route('admin.competency.question.translations.get') }}",
  admin_competency_question_translations_save: "{{ route('admin.competency.question.translations.save') }}",
  admin_competency_question_translations_ai: "{{ route('admin.competency.question.translations.ai') }}"
};
</script>
@endsection