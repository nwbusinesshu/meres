<script>
$(document).ready(function(){
  const url = new URL(window.location.href);

  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  $('.modify-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    const name = $(this).closest('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  $('.competency-item .bar span').click(function(e){
    const $item = $(this).closest('.competency-item');
    const show = $item.find('.questions').hasClass('hidden');

    $('.competency-item .questions').addClass('hidden');
    $('.competency-item .bar span i').removeClass('fa-caret-up').addClass('fa-caret-down');

    if (show) {
      $item.find('.questions').removeClass('hidden');
      $(this).find('i').removeClass('fa-caret-down').addClass('fa-caret-up');
      url.searchParams.set('open', $item.attr('data-id'));
      window.history.replaceState(null, null, url);
    }
  });

  if(url.searchParams.has('open')){
    $('.competency-item[data-id="'+url.searchParams.get('open')+'"] .bar span').click();
  }

  $('.create-question').click(function(){
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(null, compId);
  });

  $('.modify-question').click(function(){
    const id = $(this).closest('.question-item').attr('data-id');
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(id, compId);
  });

  $('.competency-search-input').keyup(function(e){
    if(e.keyCode != 13) return;

    swal_loader.fire();
    const search = $(this).val().toLowerCase();

    $('.competency-item').addClass('hidden');
    $('.no-competency').addClass('hidden');

    $('.competency-item').each(function(){
      const name = $(this).attr('data-name')?.toLowerCase();
      if(name.includes(search)){
        $(this).removeClass('hidden');
      }
    });

    url.searchParams.delete('search');
    if(search.length !== 0){
      url.searchParams.set('search', search);
    }
    window.history.replaceState(null, null, url);

    if($('.competency-item:not(.hidden)').length === 0){
      $('.no-competency').removeClass('hidden');
    }
    swal_loader.close();
  });

  if(url.searchParams.has('search')){
    $('.competency-search-input').val(url.searchParams.get('search'))
      .trigger(jQuery.Event('keyup', { keyCode: 13 }));
  }

  $('.competency-clear-search').click(function(){
    $('.competency-search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });

  $('.remove-question').click(function(){
    const id = $(this).closest('.question-item').attr('data-id');
    swal_confirm.fire({
      title: '{{ __("admin/competencies.question-remove-confirm") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('superadmin.competency.q.remove') }}",
          data: { id: id },
          successMessage: "{{ __('admin/competencies.question-remove-success') }}",
        });
      }
    });
  });

  $('.remove-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id');
    swal_confirm.fire({
      title: '{{ __("admin/competencies.remove-competency-confirm") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('superadmin.competency.remove') }}",
          data: { id: id },
          successMessage: "{{ __('admin/competencies.remove-competency-success') }}",
        });
      }
    });
  });
});
</script>
