<script>
$(document).ready(function() {
  let currentTicketId = null;
  let currentFilters = {
    status: 'all',
    organization_id: 'all',
    priority: 'all'
  };

  /* =======================================================================
     INITIALIZATION
     ======================================================================= */
  
  function init() {
    loadOrganizations();
    loadTickets();
    bindEvents();
  }

  /* =======================================================================
     EVENT BINDINGS
     ======================================================================= */
  
  function bindEvents() {
    // Filter changes
    $('.filter-status').on('change', function() {
      currentFilters.status = $(this).val();
      loadTickets();
    });

    $('.filter-organization').on('change', function() {
      currentFilters.organization_id = $(this).val();
      loadTickets();
    });

    $('.filter-priority').on('change', function() {
      currentFilters.priority = $(this).val();
      loadTickets();
    });

    // View ticket button
    $(document).on('click', '.view-ticket-btn', function() {
      const ticketId = $(this).data('ticket-id');
      openTicketDetail(ticketId);
    });

    // Send reply
    $('.send-reply-btn').on('click', function() {
      sendReply();
    });

    // Close ticket
    $('.close-ticket-btn').on('click', function() {
      closeTicket();
    });

    // Reopen ticket
    $('.reopen-ticket-btn').on('click', function() {
      reopenTicket();
    });

    // Enter key in reply textarea
    $('.reply-textarea').on('keydown', function(e) {
      if (e.ctrlKey && e.keyCode === 13) { // Ctrl+Enter
        sendReply();
      }
    });
  }

  /* =======================================================================
     LOAD ORGANIZATIONS
     ======================================================================= */
  
  function loadOrganizations() {
    $.ajax({
      url: '{{ route("superadmin.tickets.organizations") }}',
      method: 'GET',
      success: function(response) {
        if (response.success && response.organizations) {
          const $select = $('.filter-organization');
          response.organizations.forEach(function(org) {
            $select.append(`<option value="${org.id}">${org.name}</option>`);
          });
        }
      },
      error: function(xhr) {
        console.error('Failed to load organizations:', xhr);
      }
    });
  }

  /* =======================================================================
     LOAD TICKETS
     ======================================================================= */
  
  function loadTickets() {
    const $tbody = $('.tickets-tbody');
    const $noTickets = $('.no-tickets');
    
    $tbody.html(`
      <tr class="loading-row">
        <td colspan="10" class="text-center">
          <i class="fa fa-spinner fa-spin"></i> {{ __('support.loading-tickets') }}
        </td>
      </tr>
    `);
    $noTickets.addClass('hidden');

    $.ajax({
      url: '{{ route("superadmin.tickets.all") }}',
      method: 'GET',
      data: currentFilters,
      success: function(response) {
        if (response.success && response.tickets) {
          if (response.tickets.length === 0) {
            $tbody.empty();
            $noTickets.removeClass('hidden');
          } else {
            renderTicketsTable(response.tickets);
          }
        }
      },
      error: function(xhr) {
        $tbody.html(`
          <tr>
            <td colspan="10" class="text-center text-danger">
              <i class="fa fa-exclamation-triangle"></i> {{ __('support.error-loading-tickets') }}
            </td>
          </tr>
        `);
        console.error('Failed to load tickets:', xhr);
      }
    });
  }

  /* =======================================================================
     RENDER TICKETS TABLE
     ======================================================================= */
  
  function renderTicketsTable(tickets) {
    const $tbody = $('.tickets-tbody');
    $tbody.empty();

    tickets.forEach(function(ticket) {
      const statusBadge = getStatusBadge(ticket.status);
      const priorityBadge = getPriorityBadge(ticket.priority);
      const lastMessage = ticket.last_message ? truncate(ticket.last_message, 50) : '-';
      const createdDate = formatDate(ticket.created_at);
      const lastMessageDate = ticket.last_message_at ? formatDate(ticket.last_message_at) : '-';

      const $row = $(`
        <tr data-ticket-id="${ticket.id}">
          <td>${ticket.id}</td>
          <td><strong>${escapeHtml(ticket.title)}</strong></td>
          <td>
            <div>${escapeHtml(ticket.user_name)}</div>
            <small class="text-muted">${escapeHtml(ticket.user_email)}</small>
          </td>
          <td>${escapeHtml(ticket.organization_name)}</td>
          <td>${priorityBadge}</td>
          <td>${statusBadge}</td>
          <td>${createdDate}</td>
          <td><small>${lastMessage}</small></td>
          <td><span class="badge badge-secondary">${ticket.message_count}</span></td>
          <td>
            <button class="btn btn-sm btn-outline-primary view-ticket-btn" data-ticket-id="${ticket.id}">
              <i class="fa fa-eye"></i> {{ __('support.view-ticket') }}
            </button>
          </td>
        </tr>
      `);

      $tbody.append($row);
    });
  }

  /* =======================================================================
     OPEN TICKET DETAIL
     ======================================================================= */
  
  function openTicketDetail(ticketId) {
    currentTicketId = ticketId;

    // Show loading state
    $('#ticket-detail-modal').modal('show');
    $('.ticket-messages').html(`
      <div class="text-center">
        <i class="fa fa-spinner fa-spin"></i> {{ __('support.loading-tickets') }}
      </div>
    `);

    $.ajax({
      url: `{{ route("superadmin.tickets.details", ":id") }}`.replace(':id', ticketId),
      method: 'GET',
      success: function(response) {
        if (response.success && response.ticket) {
          renderTicketDetail(response.ticket);
        }
      },
      error: function(xhr) {
        Swal.fire({
          icon: 'error',
          title: '{{ __("support.error-load") }}',
          text: xhr.responseJSON?.error || '{{ __("support.error-load") }}'
        });
        $('#ticket-detail-modal').modal('hide');
        console.error('Failed to load ticket:', xhr);
      }
    });
  }

  /* =======================================================================
     RENDER TICKET DETAIL
     ======================================================================= */
  
  function renderTicketDetail(ticket) {
    // Update header info
    $('.ticket-detail-title').text(ticket.title);
    $('.ticket-detail-status').html(getStatusBadge(ticket.status));
    $('.ticket-detail-priority').html(getPriorityBadge(ticket.priority));
    $('.ticket-detail-user').html(`${escapeHtml(ticket.user_name)}<br><small class="text-muted">${escapeHtml(ticket.user_email)}</small>`);
    $('.ticket-detail-organization').text(ticket.organization_name);
    $('.ticket-detail-created').text(formatDate(ticket.created_at));

    // Handle closed ticket info
    if (ticket.status === 'closed') {
      $('.ticket-closed-info').removeClass('hidden');
      $('.ticket-detail-closed-at').text(formatDate(ticket.closed_at));
      $('.ticket-detail-closed-by').text(ticket.closed_by_name || '-');
      $('.reply-textarea').prop('disabled', true);
      $('.send-reply-btn').addClass('hidden');
      $('.close-ticket-btn').addClass('hidden');
      $('.reopen-ticket-btn').removeClass('hidden');
    } else {
      $('.ticket-closed-info').addClass('hidden');
      $('.reply-textarea').prop('disabled', false);
      $('.send-reply-btn').removeClass('hidden');
      $('.close-ticket-btn').removeClass('hidden');
      $('.reopen-ticket-btn').addClass('hidden');
    }

    // Render messages
    const $messagesContainer = $('.ticket-messages');
    $messagesContainer.empty();

    if (ticket.messages && ticket.messages.length > 0) {
      ticket.messages.forEach(function(msg) {
        const messageClass = msg.is_staff_reply ? 'staff-message' : 'user-message';
        const messageLabel = msg.is_staff_reply ? 
          `<span class="message-label staff-label">{{ __('support.staff-reply') }}</span>` : 
          `<span class="message-label user-label">${escapeHtml(msg.user_name)}</span>`;

        const $message = $(`
          <div class="ticket-message ${messageClass}">
            <div class="message-header">
              ${messageLabel}
              <span class="message-time">${formatDate(msg.created_at)}</span>
            </div>
            <div class="message-body">${escapeHtml(msg.message)}</div>
          </div>
        `);

        $messagesContainer.append($message);
      });
    } else {
      $messagesContainer.html('<p class="text-muted">{{ __("support.no-messages") }}</p>');
    }

    // Clear reply textarea
    $('.reply-textarea').val('');
  }

  /* =======================================================================
     SEND REPLY
     ======================================================================= */
  
  function sendReply() {
    const message = $('.reply-textarea').val().trim();

    if (!message) {
      Swal.fire({
        icon: 'warning',
        title: '{{ __("support.message") }}',
        text: '{{ __("support.message-placeholder") }}'
      });
      return;
    }

    const $btn = $('.send-reply-btn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("global.loading") }}...');

    $.ajax({
      url: `{{ route("superadmin.tickets.reply", ":id") }}`.replace(':id', currentTicketId),
      method: 'POST',
      data: {
        message: message,
        _token: '{{ csrf_token() }}'
      },
      success: function(response) {
        if (response.success) {
          Swal.fire({
            icon: 'success',
            title: '{{ __("support.reply-sent") }}',
            timer: 2000,
            showConfirmButton: false
          });

          // Append new message to the list
          if (response.message) {
            const $messagesContainer = $('.ticket-messages');
            const $message = $(`
              <div class="ticket-message staff-message">
                <div class="message-header">
                  <span class="message-label staff-label">{{ __('support.staff-reply') }}</span>
                  <span class="message-time">${formatDate(response.message.created_at)}</span>
                </div>
                <div class="message-body">${escapeHtml(response.message.message)}</div>
              </div>
            `);
            $messagesContainer.append($message);

            // Update status badge if changed to in_progress
            if (response.ticket && response.ticket.status === 'in_progress') {
              $('.ticket-detail-status').html(getStatusBadge('in_progress'));
            }
          }

          // Clear textarea
          $('.reply-textarea').val('');

          // Reload tickets list
          loadTickets();
        }
      },
      error: function(xhr) {
        Swal.fire({
          icon: 'error',
          title: '{{ __("support.error-reply") }}',
          text: xhr.responseJSON?.error || '{{ __("support.error-reply") }}'
        });
        console.error('Failed to send reply:', xhr);
      },
      complete: function() {
        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> {{ __("support.send-reply") }}');
      }
    });
  }

  /* =======================================================================
     CLOSE TICKET
     ======================================================================= */
  
  function closeTicket() {
    Swal.fire({
      title: '{{ __("support.close-ticket") }}',
      text: '{{ __("support.close-ticket") }}?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: '{{ __("global.yes") }}',
      cancelButtonText: '{{ __("global.cancel") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        const $btn = $('.close-ticket-btn');
        $btn.prop('disabled', true);

        $.ajax({
          url: `{{ route("superadmin.tickets.close", ":id") }}`.replace(':id', currentTicketId),
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.success) {
              Swal.fire({
                icon: 'success',
                title: '{{ __("support.ticket-closed") }}',
                timer: 2000,
                showConfirmButton: false
              });

              // Reload ticket detail
              openTicketDetail(currentTicketId);

              // Reload tickets list
              loadTickets();
            }
          },
          error: function(xhr) {
            Swal.fire({
              icon: 'error',
              title: '{{ __("support.error-close") }}',
              text: xhr.responseJSON?.error || '{{ __("support.error-close") }}'
            });
            console.error('Failed to close ticket:', xhr);
          },
          complete: function() {
            $btn.prop('disabled', false);
          }
        });
      }
    });
  }

  /* =======================================================================
     REOPEN TICKET
     ======================================================================= */
  
  function reopenTicket() {
    Swal.fire({
      title: '{{ __("support.reopen-ticket") }}',
      text: '{{ __("support.reopen-ticket") }}?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: '{{ __("global.yes") }}',
      cancelButtonText: '{{ __("global.cancel") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        const $btn = $('.reopen-ticket-btn');
        $btn.prop('disabled', true);

        $.ajax({
          url: `{{ route("superadmin.tickets.reopen", ":id") }}`.replace(':id', currentTicketId),
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.success) {
              Swal.fire({
                icon: 'success',
                title: '{{ __("support.ticket-reopened") }}',
                timer: 2000,
                showConfirmButton: false
              });

              // Reload ticket detail
              openTicketDetail(currentTicketId);

              // Reload tickets list
              loadTickets();
            }
          },
          error: function(xhr) {
            Swal.fire({
              icon: 'error',
              title: '{{ __("support.error-reply") }}',
              text: xhr.responseJSON?.error || '{{ __("support.error-reply") }}'
            });
            console.error('Failed to reopen ticket:', xhr);
          },
          complete: function() {
            $btn.prop('disabled', false);
          }
        });
      }
    });
  }

  /* =======================================================================
     HELPER FUNCTIONS
     ======================================================================= */
  
  function getStatusBadge(status) {
    const badges = {
      'open': `<span class="ticket-badge status-open">{{ __('support.status-open') }}</span>`,
      'in_progress': `<span class="ticket-badge status-in-progress">{{ __('support.status-in_progress') }}</span>`,
      'closed': `<span class="ticket-badge status-closed">{{ __('support.status-closed') }}</span>`
    };
    return badges[status] || status;
  }

  function getPriorityBadge(priority) {
    const badges = {
      'low': `<span class="ticket-badge priority-low">{{ __('support.priority-low') }}</span>`,
      'medium': `<span class="ticket-badge priority-medium">{{ __('support.priority-medium') }}</span>`,
      'high': `<span class="ticket-badge priority-high">{{ __('support.priority-high') }}</span>`,
      'urgent': `<span class="ticket-badge priority-urgent">{{ __('support.priority-urgent') }}</span>`
    };
    return badges[priority] || priority;
  }

  function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('hu-HU', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function escapeHtml(text) {
    if (!text) return '';
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
  }

  function truncate(str, length) {
    if (!str) return '';
    return str.length > length ? str.substring(0, length) + '...' : str;
  }

  /* =======================================================================
     START
     ======================================================================= */
  
  init();
});
</script>