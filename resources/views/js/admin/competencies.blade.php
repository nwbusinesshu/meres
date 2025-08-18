<script>
$(document).ready(function(){
  const url = new URL(window.location.href);

  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  $('.modify-competency').click(function(){
    id = $(this).parents('.competency-item').attr('data-id')*1;
    name = $(this).parents('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  $('.competency-item .bar span').click(function(e){
    var show = $(this).parents('.competency-item').find('.questions').hasClass('hidden');
    $('.competency-item .questions').addClass('hidden');
    $('.competency-item .bar span i').removeClass('fa-caret-up');
    $('.competency-item .bar span i').addClass('fa-caret-down');

    if(show){
      $(this).parents('.competency-item').find('.questions').removeClass('hidden');
      $(this).parents('.bar').find('span i').addClass('fa-caret-up');
      url.searchParams.set('open', $(this).parents('.competency-item').attr('data-id'));
      window.history.replaceState(null, null, url);
    }
  });

  if(url.searchParams.has('open')){
    $('.competency-item[data-id="'+url.searchParams.get('open')+'"] .bar span').click();
  }

  $('.create-question').click(function(){
    openCompetencyQModal(null, $(this).parents('.competency-item').attr('data-id'));
  });

  $('.modify-question').click(function(){
    openCompetencyQModal($(this).parents('.question-item').attr('data-id'), $(this).parents('.competency-item').attr('data-id'));
  });
  
  $('.competency-search-input').keyup(function(e){
    if(e.keyCode != 13){ return; }
    swal_loader.fire();
    search = $(this).val().toLowerCase();

    $('.competency-item').addClass('hidden');
    $('.no-competency').addClass('hidden');

    $('.competency-item').each(function(){
      if($(this).attr('data-name').toLowerCase().includes(search)){
        $(this).removeClass('hidden');
      }
    });

    url.searchParams.delete('search');
    if(search.length != 0){
      url.searchParams.set('search', search);
    }
    window.history.replaceState(null, null, url);

    if($('.competency-item:not(.hidden)').length == 0){
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
    swal_confirm.fire({
      title: '{{ __('admin/competencies.question-remove-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.question.remove') }}",
          data: { id: $(this).parents('.question-item').attr('data-id') },
          successMessage: "{{ __('admin/competencies.question-remove-success') }}",
        });
      }
    });
  });

  $('.remove-competency').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-competency-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.remove') }}",
          data: { id: $(this).parents('.competency-item').attr('data-id') },
          successMessage: "{{ __('admin/competencies.remove-competency-success') }}",
        });
      }
    });
  });
});
</script>