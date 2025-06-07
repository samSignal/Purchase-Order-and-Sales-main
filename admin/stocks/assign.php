<?php
require_once('../../config.php');

// Check if modal should be shown
if(isset($_GET['show_modal'])): 
?>
<div class="modal fade" id="assign_modal" tabindex="-1" role="dialog" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignModalLabel">Assign Stock to Sales Representative</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="assign-form">
                <div class="modal-body">
                    <div class="container-fluid">
                        <div class="form-group">
                            <label for="item_id">Select Item</label>
                            <select class="form-control" id="item_id" name="item_id" required>
                                <option value="" disabled selected>Select Item</option>
                                <?php
                                $items = $conn->query("SELECT i.*, s.name as supplier FROM `item_list` i 
                                                      INNER JOIN supplier_list s ON i.supplier_id = s.id 
                                                      ORDER BY i.name ASC");
                                while($row = $items->fetch_assoc()):
                                    $in = $conn->query("SELECT SUM(quantity) as total FROM stock_list WHERE item_id = '{$row['id']}' AND type = 1")->fetch_array()['total'];
                                    $out = $conn->query("SELECT SUM(quantity) as total FROM stock_list WHERE item_id = '{$row['id']}' AND type = 2")->fetch_array()['total'];
                                    $available = $in - $out;
                                    if($available > 0):
                                ?>
                                    <option value="<?php echo $row['id'] ?>" data-available="<?php echo $available ?>">
                                        <?php echo $row['name'] ?> (Available: <?php echo $available ?>)
                                    </option>
                                <?php endif; endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sales_rep_id">Sales Representative</label>
                            <select class="form-control" id="sales_rep_id" name="sales_rep_id" required>
                                <option value="" disabled selected>Select Sales Rep</option>
                                <?php
                                $reps = $conn->query("SELECT * FROM users WHERE type = 2 ORDER BY firstname ASC");
                                while($row = $reps->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $row['id'] ?>">
                                        <?php echo $row['firstname'] . ' ' . $row['lastname'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity to Assign</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                            <small class="text-muted" id="available-text">Available: 0</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
$(document).ready(function(){
    // Show modal
    $('#assign_modal').modal('show');
    
    // Update available quantity when item changes
    $('#item_id').change(function(){
        var available = $(this).find(':selected').data('available');
        $('#available-text').text('Available: ' + available);
        $('#quantity').attr('max', available);
    });

    // Validate form before submission
    $('#assign-form').submit(function(e){
        e.preventDefault();
        var quantity = parseInt($('#quantity').val());
        var available = parseInt($('#item_id').find(':selected').data('available'));
        
        if(quantity > available) {
            alert_toast('Cannot assign more than available stock!', 'error');
            return false;
        }
        
        if(quantity <= 0) {
            alert_toast('Please enter a valid quantity!', 'error');
            return false;
        }
        
        // Proceed with submission
        $.ajax({
            url: _base_url_+'classes/Master.php?f=assign_stock',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            error: function(xhr, status, error) {
                console.log(xhr.responseText);
                alert_toast('An error occurred while assigning stock: ' + error, 'error');
            },
            success: function(resp) {
                if(typeof resp === 'string') {
                    try {
                        resp = JSON.parse(resp);
                    } catch(e) {
                        console.error("Failed to parse response:", resp);
                        alert_toast('Invalid server response', 'error');
                        return;
                    }
                }
                if(resp.status == 'success') {
                    alert_toast(resp.msg || 'Stock assigned successfully!', 'success');
                    setTimeout(function(){
                        location.reload();
                    }, 2000);
                } else {
                    alert_toast(resp.msg || 'An error occurred.', 'error');
                }
            }
        });
    });

    // Close modal handler
    $('#assign_modal').on('hidden.bs.modal', function () {
        $('#assign_modal_container').html('');
    });
});
</script>
<?php endif; ?>

