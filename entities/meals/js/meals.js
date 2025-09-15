// Meals management JavaScript
window.MealUI = (function() {
    'use strict';
    
    function openAddModal() {
        loadModalForm('add');
    }
    
    function openEditModal(mealId) {
        loadModalForm('edit', mealId);
    }
    
    function openDetails(mealId) {
        $.ajax({
            url: '/api/meals.php',
            method: 'POST',
            data: {
                action: 'load_details',
                meal_id: mealId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#meal-details-modal .modal-content').html(response.html);
                    $('#meal-details-modal').modal('show');
                } else {
                    alert('Error loading meal details: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, err) {
                alert('AJAX Error: ' + err + "\n" + xhr.responseText);
            }
        });
    }
    
    function loadModalForm(type, mealId = null) {
        const data = {
            action: 'load_form',
            type: type
        };
        
        if (type === 'edit' && mealId) {
            data.meal_id = mealId;
        }
        
        $.ajax({
            url: '/api/meals.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#meal-modal .modal-content').html(response.html);
                    $('#meal-modal').modal('show');
                } else {
                    alert('Error loading form: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, err) {
                alert('AJAX Error: ' + err + "\n" + xhr.responseText);
            }
        });
    }
    
    // Delete functionality
    function initDeleteHandlers() {
        $(document).on('click', '.meal-delete-btn', function(e) {
            e.stopPropagation();
            const mealId = $(this).data('meal-id');
            const mealName = $(this).data('meal-name');
            
            $('#meal-delete-name').text(mealName);
            $('#meal-delete-confirm-modal').modal('show');
            
            $('#meal-delete-confirm-btn').off('click').on('click', function() {
                deleteMeal(mealId, mealName);
            });
        });
    }
    
    function deleteMeal(mealId, mealName) {
        $.ajax({
            url: '/api/meals.php?action=delete',
            method: 'POST',
            data: { id: mealId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#meal-delete-confirm-modal').modal('hide');
                    alert('Meal "' + mealName + '" deleted successfully!');
                    location.reload();
                } else {
                    $('#meal-delete-modal-error').removeClass('d-none').text(response.message || 'Unknown error');
                }
            },
            error: function(xhr, status, err) {
                $('#meal-delete-modal-error').removeClass('d-none').text('AJAX Error: ' + err);
            }
        });
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initDeleteHandlers();
        
        // Hide error on modal close
        $('#meal-delete-confirm-modal').on('hide.bs.modal', function() {
            $('#meal-delete-modal-error').addClass('d-none');
        });
        
        // Row click handler for details (if not clicking action buttons)
        $(document).on('click', '#meals-table tbody tr', function(e) {
            if (!$(e.target).closest('.action-icons').length) {
                const mealId = $(this).data('meal-id');
                if (mealId) {
                    openDetails(mealId);
                }
            }
        });
    });
    
    // Public API
    return {
        openAddModal: openAddModal,
        openEditModal: openEditModal,
        openDetails: openDetails
    };
})();