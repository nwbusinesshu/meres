<script>
function isEveryoneRanked(){
  if($('.employee-list .employee').length == 0){
    $('.save-ranks').removeClass('hidden');
  }else{
    $('.save-ranks').addClass('hidden');
  }
}
$(document).ready(function(){
  $('.employee').on('dragstart', function(ev){
    $(this).css('opacity', '0.4');
    ev.originalEvent.dataTransfer.setData('text', $(this).attr('data-id'));
  });

  $('.employee').on('dragend', function(ev){
    $(this).css('opacity', '1');
  });

  $('.employees, .employee-list').on('drop', function(ev){
    ev.preventDefault();

    var max = $(this).attr('data-max');
    var count = $(this).find('.employee').length;
    if(max != 'none' && max < count+1 ){
      swal_warning.fire({ title: '{{ __('ceorank.max-warning') }}'});
      return false;
    }

    var id = ev.originalEvent.dataTransfer.getData("text");
    $(this).append($('.employee[data-id="'+id+'"]'));

    isEveryoneRanked();
  });

  $('.employees, .employee-list').on('dragover', function(ev){
    ev.preventDefault();
  });

  $('.save-ranks').click(function(){
    swal_confirm.fire({
      title: '{{ __('ceorank.save-ranks-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();

        var ranks = [];
        var flag = false;
        $('.employees').each(function(){
          var obj = { 
            rankId: $(this).attr('data-id'),
            employees: [],
          };
          
          var min = $(this).attr('data-min');
          var count = $(this).find('.employee').length;
          if(min != 'none' && min*1 > count ){
            swal_warning.fire({ title: '{{ __('ceorank.min-warning') }}'});
            flag = true;
            return;
          }

          $(this).find('.employee').each(function(){
            obj.employees.push($(this).attr('data-id'));
          });

          ranks.push(obj);
        });
        if(flag){
          return;
        }
        $.ajax({
          url: "{{ route('ceorank.submit') }}",
          data: {
            ranks: ranks
          },
          successMessage: "{{ __('ceorank.save-ranks-success') }}",
          successUrl: "{{ route('home') }}"
        });
      }
    });
  });
});
</script>