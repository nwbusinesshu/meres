<script>
$(document).ready(function(){
  $('.person').click(function(){
    swal_loader.fire();
    window.location.href = '{{ route('assessment.index') }}/'+$(this).attr('data-id');
  });

  $('.rank-users').click(function(){
    swal_loader.fire();
    window.location.href = '{{ route('ceorank.index') }}';
  });
});
</script>