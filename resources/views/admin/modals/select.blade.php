<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="select-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="search-row">
          <span>{{ __('select.search') }}</span>
          <div>
            <input type="text" class="form-control select-search-input">
            <i class="fa fa-ban select-clear-search" data-tippy-content="{{ __('select.clear-search') }}"></i>
          </div>
        </div>
        <div class="select-list">
            
        </div>
      </div>
    </div>
  </div>
</div>
<script>
$(document).ready(function(){
  $('.select-clear-search').click(function(){
    $('.select-search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });
});
function openSelectModal({
    title,ajaxRoute,
    parentSelector = null,
    itemData = function(item){ return {
      id: item.id,
      name: item.name,
      top: null,
      bottom: null,
    }; },
    selectFunction = function(){},
    exceptArray = [],
    exceptFunction = function(item){
      return !exceptArray.includes(item.id);
    },
    emptyMessage = ''
  }){
  $('#select-modal .modal-title').html(title);
  
  if(parentSelector !== null){
    $(parentSelector).modal('hide');
  }
  
  swal_loader.fire();

  $.ajax({
    url: ajaxRoute,
  })
  .done(function(response){
    $('.select-list').html('');
    $('.select-search-input').val('');
    $('.select-search-input').prop('readonly', false);

    response = response.filter(exceptFunction);

    if(response.length == 0){
      $(".select-list").append('<div class="select-modal-empty"><p>'+emptyMessage+'</p><button class="btn btn-outline-secondary" data-dismiss="modal">{{ __('global.back') }}</button></div>');
      $('.select-search-input').prop('readonly', true);
    }else{
      response.forEach((item) => {
        var data = itemData(item);
        var html = '<div class="select-modal-item" data-id="'+data.id+'" data-name="'+data.name+'">';
        if(typeof data.top != 'undefined' && data.top !== null){
          html+='<span>'+data.top+'</span>';
        }
        html+='<p>'+data.name+'</p>';
        if(typeof data.bottom != 'undefined' && data.bottom !== null){
          html+='<span>'+data.bottom+'</span>';
        }
        html+='</div>';
        $(".select-list").append(html);
      });
    }

    swal_loader.close();
    $("#select-modal").off('hidden.bs.modal');
    if(parentSelector !== null){
      $("#select-modal").on('hidden.bs.modal', function (e) {
        $(parentSelector).modal();
      });
    }

    $(document).undelegate('.select-modal-item','click');
    $(document).delegate('.select-modal-item','click',selectFunction);

    $(".select-search-input").off('keyup');
    $(".select-search-input").on('keyup', function(e){
      if(e.keyCode != 13){ return; }
      swal_loader.fire();
      search = $(this).val().toLowerCase();

      $('.select-modal-item').addClass('hidden');
      $('.select-modal-empty').remove();

      $('.select-modal-item').each(function(){
        if($(this).attr('data-name').toLowerCase().includes(search)){
          $(this).removeClass('hidden');
        }
      });

      if($('.select-modal-item:not(.hidden)').length == 0){
        $(".select-list").append('<div class="select-modal-empty"><p>'+emptyMessage+'</p><button class="btn btn-outline-secondary" data-dismiss="modal">{{ __('global.back') }}</button></div>');
      }
      swal_loader.close();
    });

    $("#select-modal").modal();
  });
}
</script>
