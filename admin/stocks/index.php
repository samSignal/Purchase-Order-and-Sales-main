<div class="card card-outline card-primary">
	<div class="card-header">
		<h3 class="card-title">List of Stocks</h3>
        <?php if($_settings->userdata('type') == 1): ?>
        <div class="card-tools">
			<button class="btn btn-flat btn-primary me-2" onclick="openAssignModal()"><span class="fas fa-user-plus"></span> Assign to Sales Rep</button>
            <button class="btn btn-flat btn-info" onclick="viewAssignments()"><span class="fas fa-list"></span> View Assignments</button>
		</div>
        <?php endif; ?>
	</div>
	<div class="card-body">
		<div class="container-fluid">
        <div class="container-fluid">
			<table class="table table-bordered table-stripped">
                    <colgroup>
                        <col width="5%">
                        <col width="20%">
                        <col width="20%">
                        <col width="40%">
                        <col width="15%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Supplier</th>
                            <th>Description</th>
                            <th>Available Stocks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        $qry = $conn->query("SELECT i.*,s.name as supplier FROM `item_list` i inner join supplier_list s on i.supplier_id = s.id order by `name` desc");
                        while($row = $qry->fetch_assoc()):
                            $in = $conn->query("SELECT SUM(quantity) as total FROM stock_list where item_id = '{$row['id']}' and type = 1")->fetch_array()['total'];
                            $out = $conn->query("SELECT SUM(quantity) as total FROM stock_list where item_id = '{$row['id']}' and type = 2")->fetch_array()['total'];
                            $row['available'] = $in - $out;
                        ?>
                            <tr>
                                <td class="text-center"><?php echo $i++; ?></td>
                                <td><?php echo $row['name'] ?></td>
                                <td><?php echo $row['supplier'] ?></td>
                                <td><?php echo $row['description'] ?></td>
                                <td class="text-right"><?php echo number_format($row['available']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
		</div>
		</div>
	</div>
</div>

<!-- Container for the assign modal -->
<div id="assign_modal_container"></div>

<script>
	$(document).ready(function(){
		$('.delete_data').click(function(){
			_conf("Are you sure to delete this Received Orders permanently?","delete_receiving",[$(this).attr('data-id')])
		})
		$('.view_details').click(function(){
			uni_modal("Receiving Details","receiving/view_receiving.php?id="+$(this).attr('data-id'),'mid-large')
		})
		$('.table td,.table th').addClass('py-1 px-2 align-middle')
		$('.table').dataTable();
	})

    function viewAssignments() {
        window.location.href = 'stocks/assigned_list.php';
    }

	function openAssignModal() {
		$.ajax({
			url: 'stocks/assign.php',
			data: {show_modal: true},
			success: function(html) {
				$("#assign_modal_container").html(html);
			},
			error: function() {
				alert_toast("Failed to load assign modal", 'error');
			}
		});
	}

	function delete_receiving($id){
		start_loader();
		$.ajax({
			url:_base_url_+"classes/Master.php?f=delete_receiving",
			method:"POST",
			data:{id: $id},
			dataType:"json",
			error:err=>{
				console.log(err)
				alert_toast("An error occured.",'error');
				end_loader();
			},
			success:function(resp){
				if(typeof resp== 'object' && resp.status == 'success'){
					location.reload();
				}else{
					alert_toast("An error occured.",'error');
					end_loader();
				}
			}
		})
	}
</script>