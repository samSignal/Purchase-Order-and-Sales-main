<?php 
// Get current user information from session
$current_user_id = $_SESSION['userdata']['id'] ?? 0;
$user_type = $_SESSION['userdata']['type'] ?? 0;
// Type 2 = sales rep
if(isset($_GET['id'])){
    $qry = $conn->query("SELECT * FROM sales_list where id = '{$_GET['id']}'");
    if($qry->num_rows >0){
        foreach($qry->fetch_array() as $k => $v){
            $$k = $v;
        }
    }
}
?>
<style>
    select[readonly].select2-hidden-accessible + .select2-container {
        pointer-events: none;
        touch-action: none;
        background: #eee;
        box-shadow: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection {
        background: #eee;
        box-shadow: none;
    }
</style>
<div class="card card-outline card-primary">
    <div class="card-header">
        <h4 class="card-title"><?php echo isset($id) ? "Sale Details - ".$sales_code : 'Create New Sale Record' ?></h4>
    </div>
    <div class="card-body">
        <form action="" id="sale-form">
            <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
            <input type="hidden" name="stock_ids" value="<?php echo isset($stock_ids) ? $stock_ids : '' ?>">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <label class="control-label text-info">Sale Code</label>
                        <input type="text" class="form-control form-control-sm rounded-0" value="<?php echo isset($sales_code) ? $sales_code : '' ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="client" class="control-label text-info">Client Name</label>
                            <input type="text" name="client" class="form-control form-control-sm rounded-0" value="<?php echo isset($client) ? $client : 'Guest' ?>" >
                        </div>
                    </div>
                </div>
                <hr>
                <fieldset>
                    <legend class="text-info">Item Form</legend>
                    <div class="row justify-content-center align-items-end">
                        <?php 
                            $item_arr = array();
                            $cost_arr = array();
                            $stock_arr = array(); // Added to track stock IDs
                            if ($user_type == 2) { 
                                $item = $conn->query("SELECT i.*, s.id as stock_id, pa.quantity_remaining as remaining 
                                FROM item_list i 
                                INNER JOIN product_assignments pa ON i.id = pa.product_id 
                                LEFT JOIN stock_list s ON i.id = s.item_id
                                WHERE i.status = 1 
                                AND pa.sales_rep_id = '$current_user_id' 
                                AND pa.quantity_remaining > 0 
                                ORDER BY i.name ASC");
                            } else {
                                $item = $conn->query("SELECT i.*, s.id as stock_id, 
                                COALESCE((SELECT SUM(quantity) FROM stock_list WHERE item_id = i.id AND type = 1), 0) -
                                COALESCE((SELECT SUM(quantity) FROM stock_list WHERE item_id = i.id AND type = 2), 0) 
                                as remaining 
                                FROM item_list i
                                LEFT JOIN stock_list s ON i.id = s.item_id
                                WHERE i.status = 1 ".
                                ($user_type == 2 ? "AND pa.sales_rep_id = '$current_user_id'" : "")." 
                                ORDER BY i.name ASC");
                            }
                            while($row = $item->fetch_assoc()):
                                $item_arr[$row['id']] = $row;
                                $cost_arr[$row['id']] = $row['sell_price'];
                                $stock_arr[$row['id']] = $row['stock_id']; // Store stock ID
                            endwhile;
                        ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="item_id" class="control-label">Item</label>
                                <select id="item_id" class="custom-select select2">
                                    <option disabled selected></option>
                                    // In the item option display
                                    <?php foreach($item_arr as $k => $v): ?>
                                        <option 
                                            value="<?php echo $k ?>"
                                            data-available="<?php echo $v['remaining'] ?>"
                                            data-stock-id="<?php echo $stock_arr[$k] ?>"
                                            class="<?php echo $v['remaining'] <= 0 ? 'text-danger' : '' ?>"
                                            <?php echo $v['remaining'] <= 0 ? 'disabled' : '' ?>
                                        >
                                            <?php echo $v['name'] ?>
                                            <span class="text-muted">(Available: <?php echo $v['remaining'] ?>)</span>
                                        </option>
                                    <?php endforeach; ?>
                                    
                                    // In the JavaScript section
                                    $(function(){
                                        $('#item_id').on('change', function() {
                                            var available = $(this).find('option:selected').data('available') || 0;
                                            $('#qty').attr({
                                                'max': available,
                                                'title': 'Maximum quantity: ' + available
                                            });
                                        });
                                    
                                        $('#add_to_list').click(function(){
                                            var available = $('#item_id option:selected').data('available');
                                            if(<?php echo $user_type ?> == 2 && $('#qty').val() > available) {
                                                alert_toast('Quantity exceeds available stock of ' + available, 'error');
                                                return false;
                                            }
                                            // ... existing add to list code ...
                                        });
                                    
                                        // Add input validation for quantity field
                                        $('#qty').on('input', function() {
                                            var max = parseFloat($(this).attr('max')) || Infinity;
                                            if(parseFloat($(this).val()) > max) {
                                                $(this).val(max);
                                                alert_toast('Adjusted quantity to maximum available stock', 'warning');
                                            }
                                        });
                                    });
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="unit" class="control-label">Unit</label>
                                <input type="text" class="form-control rounded-0" id="unit">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="qty" class="control-label">Qty</label>
                                <input type="number" step="any" class="form-control rounded-0" id="qty">
                            </div>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="form-group">
                                <button type="button" class="btn btn-flat btn-sm btn-primary" id="add_to_list">Add to List</button>
                            </div>
                        </div>
                </fieldset>
                <hr>
                <table class="table table-striped table-bordered" id="list">
                    <colgroup>
                        <col width="5%">
                        <col width="10%">
                        <col width="10%">
                        <col width="25%">
                        <col width="25%">
                        <col width="25%">
                    </colgroup>
                    <thead>
                        <tr class="text-light bg-navy">
                            <th class="text-center py-1 px-2"></th>
                            <th class="text-center py-1 px-2">Qty</th>
                            <th class="text-center py-1 px-2">Unit</th>
                            <th class="text-center py-1 px-2">Item</th>
                            <th class="text-center py-1 px-2">Unit Price</th>
                            <th class="text-center py-1 px-2">Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total = 0;
                        if(isset($id)):
                        $qry = $conn->query("SELECT s.*,i.name,i.description FROM `stock_list` s inner join item_list i on s.item_id = i.id where s.id in ({$stock_ids})");
                        while($row = $qry->fetch_assoc()):
                            $total += $row['total']
                        ?>
                        <tr>
                            <td class="py-1 px-2 text-center">
                                <button class="btn btn-outline-danger btn-sm rem_row" type="button"><i class="fa fa-times"></i></button>
                            </td>
                            <td class="py-1 px-2 text-center qty">
                                <span class="visible"><?php echo number_format($row['quantity']); ?></span>
                                <input type="hidden" name="item_id[]" value="<?php echo $row['item_id']; ?>">
                                <input type="hidden" name="unit[]" value="<?php echo $row['unit']; ?>">
                                <input type="hidden" name="qty[]" value="<?php echo $row['quantity']; ?>">
                                <input type="hidden" name="price[]" value="<?php echo $row['price']; ?>">
                                <input type="hidden" name="total[]" value="<?php echo $row['total']; ?>">
                                <input type="hidden" name="stock_id[]" value="<?php echo $row['id']; ?>">
                            </td>
                            <td class="py-1 px-2 text-center unit">
                            <?php echo $row['unit']; ?>
                            </td>
                            <td class="py-1 px-2 item">
                            <?php echo $row['name']; ?> <br>
                            <?php echo $row['description']; ?>
                            </td>
                            <td class="py-1 px-2 text-right cost">
                            <?php echo number_format($row['price']); ?>
                            </td>
                            <td class="py-1 px-2 text-right total">
                            <?php echo number_format($row['total']); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="text-right py-1 px-2" colspan="5">Total
                                <input type="hidden" name="amount" value="<?php echo isset($discount) ? $discount : 0 ?>">
                            </th>
                            <th class="text-right py-1 px-2 grand-total">0</th>
                        </tr>
                    </tfoot>
                </table>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="remarks" class="text-info control-label">Remarks</label>
                            <textarea name="remarks" id="remarks" rows="3" class="form-control rounded-0"><?php echo isset($remarks) ? $remarks : '' ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer py-1 text-center">
        <button class="btn btn-flat btn-primary" type="submit" form="sale-form">Save</button>
        <a class="btn btn-flat btn-dark" href="<?php echo base_url.'/admin?page=sale' ?>">Cancel</a>
    </div>
</div>
<table id="clone_list" class="d-none">
    <tr>
        <td class="py-1 px-2 text-center">
            <button class="btn btn-outline-danger btn-sm rem_row" type="button"><i class="fa fa-times"></i></button>
        </td>
        <td class="py-1 px-2 text-center qty">
            <span class="visible"></span>
            <input type="hidden" name="item_id[]">
            <input type="hidden" name="unit[]">
            <input type="hidden" name="qty[]">
            <input type="hidden" name="price[]">
            <input type="hidden" name="total[]">
            <input type="hidden" name="stock_id[]">
        </td>
        <td class="py-1 px-2 text-center unit">
        </td>
        <td class="py-1 px-2 item">
        </td>
        <td class="py-1 px-2 text-right cost">
        </td>
        <td class="py-1 px-2 text-right total">
        </td>
    </tr>
</table>
<script>
    var items = $.parseJSON('<?php echo json_encode($item_arr) ?>')
    var costs = $.parseJSON('<?php echo json_encode($cost_arr) ?>')
    var stocks = $.parseJSON('<?php echo json_encode($stock_arr) ?>')
    
    $(function(){
        $('.select2').select2({
            placeholder:"Please select here",
            width:'resolve',
        })
        $('#add_to_list').click(function(){
            var item = $('#item_id').val()
            var qty = $('#qty').val() > 0 ? $('#qty').val() : 0;
            var unit = $('#unit').val()
            var price = costs[item] || 0
            var stock_id = $('#item_id option:selected').data('stock-id')
            var total = parseFloat(qty) *parseFloat(price)
            var item_name = items[item].name || 'N/A';
            var item_description = items[item].description || 'N/A';
            var tr = $('#clone_list tr').clone()
            if(item == '' || qty == '' || unit == '' ){
                alert_toast('Form Item textfields are required.','warning');
                return false;
            }
            if($('table#list tbody').find('tr[data-id="'+item+'"]').length > 0){
                alert_toast('Item is already exists on the list.','error');
                return false;
            }
            tr.find('[name="item_id[]"]').val(item)
            tr.find('[name="unit[]"]').val(unit)
            tr.find('[name="qty[]"]').val(qty)
            tr.find('[name="price[]"]').val(price)
            tr.find('[name="total[]"]').val(total)
            tr.find('[name="stock_id[]"]').val(stock_id)
            tr.attr('data-id',item)
            tr.find('.qty .visible').text(qty)
            tr.find('.unit').text(unit)
            tr.find('.item').html(item_name+'<br/>'+item_description)
            tr.find('.cost').text(parseFloat(price).toLocaleString('en-US'))
            tr.find('.total').text(parseFloat(total).toLocaleString('en-US'))
            $('table#list tbody').append(tr)
            calc()
            $('#item_id').val('').trigger('change')
            $('#qty').val('')
            $('#unit').val('')
            tr.find('.rem_row').click(function(){
                rem($(this))
            })
            
            $('[name="discount_perc"],[name="tax_perc"]').on('input',function(){
                calc()
            })
            $('#supplier_id').attr('readonly','readonly')
        })
        $('#sale-form').submit(function(e){
            e.preventDefault();
            
            // Add validation check here
            if($('table#list tbody tr').length === 0) {
                alert_toast('Please add at least one item before submitting', 'error');
                return false;
            }
            
            var _this = $(this);
            $('.err-msg').remove();
            start_loader();
            
            // Collect all stock IDs
            var stockIds = [];
            $('input[name="stock_id[]"]').each(function() {
                if($(this).val()) stockIds.push($(this).val());
            });
            $('[name="stock_ids"]').val(stockIds.join(','));
            
            $.ajax({
                url:_base_url_+"classes/Master.php?f=save_sale",
                data: new FormData($(this)[0]),
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                type: 'POST',
                dataType: 'json',
                error:err=>{
                    console.log(err)
                    alert_toast("An error occured",'error');
                    end_loader();
                },
                success:function(resp){
                    if(resp.status == 'success'){
                        location.replace(_base_url_+"admin/?page=sales/view_sale&id="+resp.id);
                    }else if(resp.status == 'failed' && !!resp.msg){
                        var el = $('<div>')
                            el.addClass("alert alert-danger err-msg").text(resp.msg)
                            _this.prepend(el)
                            el.show('slow')
                            end_loader()
                    }else{
                        alert_toast("An error occured",'error');
                        end_loader();
                        console.log(resp)
                    }
                    $('html,body').animate({scrollTop:0},'fast')
                }
            })
        })

        if('<?php echo isset($id) && $id > 0 ?>' == 1){
            calc()
            $('#supplier_id').trigger('change')
            $('#supplier_id').attr('readonly','readonly')
            $('table#list tbody tr .rem_row').click(function(){
                rem($(this))
            })
        }
    })
    function rem(_this){
        _this.closest('tr').remove()
        calc()
        if($('table#list tbody tr').length <= 0)
            $('#supplier_id').removeAttr('readonly')
    }
    function calc(){
        var grand_total = 0;
        $('table#list tbody input[name="total[]"]').each(function(){
            grand_total += parseFloat($(this).val())
        })
        $('table#list tfoot .grand-total').text(parseFloat(grand_total).toLocaleString('en-US',{style:'decimal',maximumFractionDigit:2}))
        $('[name="amount"]').val(parseFloat(grand_total))
    }
</script>
