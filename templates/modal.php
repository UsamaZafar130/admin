<?php
/**
 * Unified Bootstrap Modal Template for FrozoFun Admin
 * 
 * Features:
 * - Static backdrop (no closing by clicking outside)
 * - ESC key enabled for closing
 * - Consistent close button in header
 * - Optional cancel button in footer
 * - Responsive modal sizing
 * 
 * Usage: include and provide $modal_id, $modal_title, $modal_body (HTML), and optional parameters
 */
if (!isset($modal_id)) $modal_id = 'unified-modal';
if (!isset($modal_title)) $modal_title = '';
if (!isset($modal_body)) $modal_body = '';
if (!isset($modal_size)) $modal_size = ''; // '', 'modal-sm', 'modal-lg', 'modal-xl'
if (!isset($modal_show_cancel)) $modal_show_cancel = false; // Whether to show cancel button
if (!isset($modal_submit_text)) $modal_submit_text = 'Save'; // Text for submit button
if (!isset($modal_submit_class)) $modal_submit_class = 'btn-primary'; // CSS class for submit button
?>
<div class="modal fade" id="<?= h($modal_id) ?>" tabindex="-1" 
     data-bs-backdrop="static" 
     data-bs-keyboard="true" 
     aria-labelledby="<?= h($modal_id) ?>Label" aria-hidden="true">
  <div class="modal-dialog <?= h($modal_size) ?>">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="<?= h($modal_id) ?>Label"><?= h($modal_title) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= $modal_body ?>
      </div>
      <?php if (isset($modal_footer) || $modal_show_cancel): ?>
      <div class="modal-footer">
        <?php if (isset($modal_footer)): ?>
          <?= $modal_footer ?>
        <?php else: ?>
          <div class="d-flex justify-content-end gap-2">
            <?php if (isset($modal_submit_text) && $modal_submit_text): ?>
              <button type="submit" class="btn <?= h($modal_submit_class) ?>" form="<?= h($modal_id) ?>-form"><?= h($modal_submit_text) ?></button>
            <?php endif; ?>
            <?php if ($modal_show_cancel): ?>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>