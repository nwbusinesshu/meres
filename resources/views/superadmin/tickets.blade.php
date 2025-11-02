@extends('layouts.master')

@section('head-extra')
<link rel="stylesheet" href="{{ asset('css/superadmin-tickets.css') }}">
@endsection

@section('content')
<h1>{{ __('support.all-tickets') }}</h1>

{{-- Filters Row --}}
<div class="fixed-row tickets-filters">
  <div class="tile tile-info filter-tile">
    <span>{{ __('support.filter-status') }}</span>
    <select class="form-control filter-status">
      <option value="all">{{ __('support.all') }}</option>
      <option value="open">{{ __('support.status-open') }}</option>
      <option value="in_progress">{{ __('support.status-in_progress') }}</option>
      <option value="closed">{{ __('support.status-closed') }}</option>
    </select>
  </div>

  <div class="tile tile-info filter-tile">
    <span>{{ __('support.filter-organization') }}</span>
    <select class="form-control filter-organization">
      <option value="all">{{ __('support.all') }}</option>
      {{-- Organizations will be loaded via AJAX --}}
    </select>
  </div>

  <div class="tile tile-info filter-tile">
    <span>{{ __('support.filter-priority') }}</span>
    <select class="form-control filter-priority">
      <option value="all">{{ __('support.all') }}</option>
      <option value="low">{{ __('support.priority-low') }}</option>
      <option value="medium">{{ __('support.priority-medium') }}</option>
      <option value="high">{{ __('support.priority-high') }}</option>
      <option value="urgent">{{ __('support.priority-urgent') }}</option>
    </select>
  </div>
</div>

{{-- Tickets Table --}}
<div class="tile tickets-table-container">
  <table class="table table-hover tickets-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>{{ __('support.title') }}</th>
        <th>{{ __('support.user') }}</th>
        <th>{{ __('support.organization') }}</th>
        <th>{{ __('support.priority') }}</th>
        <th>{{ __('support.status') }}</th>
        <th>{{ __('support.created-at') }}</th>
        <th>{{ __('support.last-message') }}</th>
        <th>{{ __('support.messages-count') }}</th>
        <th>{{ __('global.actions') }}</th>
      </tr>
    </thead>
    <tbody class="tickets-tbody">
      {{-- Tickets will be loaded via AJAX --}}
      <tr class="loading-row">
        <td colspan="10" class="text-center">
          <i class="fa fa-spinner fa-spin"></i> {{ __('support.loading-tickets') }}
        </td>
      </tr>
    </tbody>
  </table>

  <div class="no-tickets hidden">
    <i class="fa fa-inbox"></i>
    <p>{{ __('support.no-tickets') }}</p>
  </div>
</div>

@endsection

@section('modals')
{{-- Ticket Detail Modal --}}
<div class="modal fade modal-drawer" id="ticket-detail-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('support.ticket-details') }}</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {{-- Ticket Header Info --}}
        <div class="ticket-detail-header">
          <div class="ticket-info-row">
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.title') }}:</span>
              <span class="ticket-detail-title"></span>
            </div>
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.status') }}:</span>
              <span class="ticket-detail-status"></span>
            </div>
          </div>

          <div class="ticket-info-row">
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.user') }}:</span>
              <span class="ticket-detail-user"></span>
            </div>
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.priority') }}:</span>
              <span class="ticket-detail-priority"></span>
            </div>
          </div>

          <div class="ticket-info-row">
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.organization') }}:</span>
              <span class="ticket-detail-organization"></span>
            </div>
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.created-at') }}:</span>
              <span class="ticket-detail-created"></span>
            </div>
          </div>

          <div class="ticket-info-row ticket-closed-info hidden">
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.closed-at') }}:</span>
              <span class="ticket-detail-closed-at"></span>
            </div>
            <div class="ticket-info-item">
              <span class="ticket-info-label">{{ __('support.closed-by') }}:</span>
              <span class="ticket-detail-closed-by"></span>
            </div>
          </div>
        </div>

        {{-- Messages Container --}}
        <div class="ticket-messages-container">
          <h6>{{ __('support.messages') }}</h6>
          <div class="ticket-messages">
            {{-- Messages will be loaded here --}}
          </div>
        </div>

        {{-- Reply Form --}}
        <div class="ticket-reply-form">
          <textarea class="form-control reply-textarea" rows="3" placeholder="{{ __('support.reply-placeholder') }}"></textarea>
          <div class="reply-actions">
            <button class="btn btn-primary send-reply-btn">
              <i class="fa fa-paper-plane"></i> {{ __('support.send-reply') }}
            </button>
            <button class="btn btn-warning close-ticket-btn">
              <i class="fa fa-times-circle"></i> {{ __('support.close-ticket') }}
            </button>
            <button class="btn btn-success reopen-ticket-btn hidden">
              <i class="fa fa-check-circle"></i> {{ __('support.reopen-ticket') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
@endsection