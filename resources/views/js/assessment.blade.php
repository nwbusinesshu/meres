<script>
$(document).ready(function(){
  $('.value').click(function(){
    $(this).parents('.values').find('.value').removeClass('selected');
    $(this).addClass('selected');
    var counts = [];
    $(this).parents('.competency').find('.value.selected').each(function(){
      counts[$(this).attr('data-value')*1] = (counts[$(this).attr('data-value')*1] || 0) + 1;
    });
    counts.some(function(count){
      if(count > 3){
        swal_info.fire({title: '{{ __('assessment.warning-3') }}'});
        return true;
      }
      return false;
    });
  });

  $('.send-in').click(function(){
    swal_loader.fire();

    // checking if all of the questions are answered
    if($('.question').length != $('.value.selected').length){
      swal_warning.fire({title: '{{ __('assessment.warning-2') }}'});
      return;
    }

    // checking selected values?

    swal_confirm.fire({
      title: '{{ __('assessment.send-in-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        @if (session('uid') == $target->id)

        var questionsCount = $('.value.selected').length;
        var counts = [];
        $('.value.selected').each(function(){
          counts[$(this).attr('data-value')*1] = (counts[$(this).attr('data-value')*1] || 0) + 1;
        });
        if((0 in counts && counts[0] == questionsCount) || (7 in counts && counts[7] == questionsCount)){
          swal_warning.fire({title: '{{ __('assessment.warning-4') }}', didClose: function(){  window.scrollTo(0, 0); }});
          $('.value').removeClass('selected');
          return;
        }
        @endif

        var answers = [];
        $('.value.selected').each(function(){
          answers.push({
            value: $(this).attr('data-value'),
            questionId: $(this).parents('.question').attr('data-id')
          });
        });

        $.ajax({
          url: "{{ route('assessment.submit') }}",
          data: {
            target: $('.values').attr('data-target-id'),
            answers: answers
          },
          successUrl: "{{ route('home') }}",
          successMessage: "{{ __('assessment.send-in-success') }}",
        });
      }
    });
  });
});
</script>