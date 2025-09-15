<div class="modal fade" id="add-item-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="addItemModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="add-item-form" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="add-item-msg" class="mb-2 text-danger"></div>
        <div class="mb-3">
          <label class="form-label">Name *</label>
          <input type="text" class="form-control" name="name" required maxlength="128">
        </div>
        <div class="mb-3">
          <label class="form-label">Code *</label>
          <input type="text" class="form-control" name="code" required maxlength="64">
        </div>
        <div class="mb-3">
          <label class="form-label">Default Pack Size *</label>
          <input type="number" class="form-control" name="default_pack_size" min="1" value="1" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Price Per Unit *</label>
          <input type="number" class="form-control" name="price_per_unit" min="0" step="0.01" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Category</label>
          <input type="text" class="form-control" name="category_id">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Add Item</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
$(function(){
  $('#add-item-form').on('submit', function(e){
    e.preventDefault();
    $('#add-item-msg').text('');
    $.post('api/items.php?action=add', $(this).serialize(), function(resp){
      if(resp.status=='success'){
        $('#add-item-modal').modal('hide');
        // Refresh all .item-select select2
        $('.item-select').each(function(){
          let $sel = $(this);
          $.get('api/items.php?action=search&term=' + encodeURIComponent($('input[name="name"]').val()), function(data){
            if(data && data.results && data.results.length){
              let opt = new Option(data.results[0].text, data.results[0].id, true, true);
              $sel.append(opt).trigger('change');
            }
          });
        });
        $('#add-item-form')[0].reset();
      } else {
        $('#add-item-msg').text(resp.message);
      }
    },'json');
  });
  $('#add-item-modal').on('hidden.bs.modal', function(){
    $('#add-item-form')[0].reset();
    $('#add-item-msg').text('');
  });
});
</script>