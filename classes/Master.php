<?php
require_once('../config.php');
Class Master extends DBConnection {
	private $settings;
	public function __construct(){
		global $_settings;
		$this->settings = $_settings;
		parent::__construct();
	}
	public function __destruct(){
		parent::__destruct();
	}
	function capture_err(){
		if(!$this->conn->error)
			return false;
		else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
			return json_encode($resp);
			exit;
		}
	}

	function assign_stock() {
		extract($_POST);

		// Validate inputs
		if(empty($item_id) || empty($sales_rep_id) || empty($quantity) || $quantity <= 0) {
			return json_encode(['status' => 'failed', 'msg' => 'Invalid input data']);
		}

		$assigned_by = $this->settings->userdata('id'); // Get current user ID

		// Check available stock
		$in = $this->conn->query("SELECT SUM(quantity) as total FROM stock_list WHERE item_id = '$item_id' AND type = 1")->fetch_array()['total'];
		$out = $this->conn->query("SELECT SUM(quantity) as total FROM stock_list WHERE item_id = '$item_id' AND type = 2")->fetch_array()['total'];
		$available = $in - $out;
		
		// Ensure the requested quantity does not exceed available stock
		if($quantity > $available) {
			return json_encode(['status' => 'failed', 'msg' => 'Cannot assign more than available stock']);
		}

		// Start database transaction
		$this->conn->begin_transaction();

		try {
			// Record stock movement (assignment)
			$this->conn->query("INSERT INTO stock_movements (product_id, from_user, to_user, quantity, movement_type)
								VALUES ('$item_id', '$assigned_by', '$sales_rep_id', '$quantity', 'assign')");

			// Check if an assignment already exists for the sales rep and product
			$assignment_check = $this->conn->query("SELECT * FROM product_assignments
													WHERE sales_rep_id = '$sales_rep_id' AND product_id = '$item_id'");
			
			// If an assignment exists, update it
			if($assignment_check->num_rows > 0) {
				$assignment = $assignment_check->fetch_assoc();
				$new_qty_assigned = $assignment['quantity_assigned'] + $quantity;
				$new_qty_remaining = $assignment['quantity_remaining'] + $quantity;
				
				$this->conn->query("UPDATE product_assignments 
									SET quantity_assigned = '$new_qty_assigned', 
										quantity_remaining = '$new_qty_remaining', 
										assigned_at = CURRENT_TIMESTAMP() 
									WHERE id = '{$assignment['id']}'");
			} else {
				// Create a new assignment if none exists
				$this->conn->query("INSERT INTO product_assignments (sales_rep_id, product_id, quantity_assigned, quantity_remaining, assigned_by)
									VALUES ('$sales_rep_id', '$item_id', '$quantity', '$quantity', '$assigned_by')");
			}

			// Deduct assigned quantity from the main inventory (create an OUT entry)
			$item = $this->conn->query("SELECT * FROM item_list WHERE id = '$item_id'")->fetch_assoc();
			$price = $item['price'] ?? 0;
			$total = $price * $quantity;
			
			$this->conn->query("INSERT INTO stock_list (item_id, quantity, unit, price, total, type)
								VALUES ('$item_id', '$quantity', '{$item['unit']}', '$price', '$total', 2)");

			// Commit transaction if all queries were successful
			$this->conn->commit();

			// Set a success message and return success response
			$this->settings->set_flashdata('success', "Stock assigned successfully");
			return json_encode(['status' => 'success', 'msg' => 'Stock assigned successfully']);
		} catch (Exception $e) {
			// Rollback transaction in case of error
			$this->conn->rollback();
			return json_encode(['status' => 'failed', 'msg' => 'Database error: ' . $e->getMessage()]);
		}
	}

	function get_assignments() {
	    $qry = $this->conn->query("SELECT pa.*,
	                                 i.name as item_name,
	                                 CONCAT(u.firstname, ' ', u.lastname) as sales_rep,
	                                 CONCAT(au.firstname, ' ', au.lastname) as assigned_by_name
	                                  FROM product_assignments pa
	                                  JOIN item_list i ON pa.product_id = i.id
	                                  JOIN users u ON pa.sales_rep_id = u.id
	                                  JOIN users au ON pa.assigned_by = au.id
	                                  ORDER BY pa.assigned_at DESC");

	    $data = array();
	    while($row = $qry->fetch_assoc()) {
	        $data[] = $row;
	    }

	    return json_encode(['status' => 'success', 'data' => $data]);
	}

	function save_supplier(){
		extract($_POST);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id'))){
				if(!empty($data)) $data .=",";
				$data .= " `{$k}`='{$v}' ";
			}
		}
		$check = $this->conn->query("SELECT * FROM `supplier_list` where `name` = '{$name}' ".(!empty($id) ? " and id != {$id} " : "")." ")->num_rows;
		if($this->capture_err())
			return $this->capture_err();
		if($check > 0){
			$resp['status'] = 'failed';
			$resp['msg'] = "supplier Name already exist.";
			return json_encode($resp);
			exit;
		}
		if(empty($id)){
			$sql = "INSERT INTO `supplier_list` set {$data} ";
			$save = $this->conn->query($sql);
		}else{
			$sql = "UPDATE `supplier_list` set {$data} where id = '{$id}' ";
			$save = $this->conn->query($sql);
		}
		if($save){
			$resp['status'] = 'success';
			if(empty($id)){
				$res['msg'] = "New Supplier successfully saved.";
				$id = $this->conn->insert_id;
			}else{
				$res['msg'] = "Supplier successfully updated.";
			}
		$this->settings->set_flashdata('success',$res['msg']);
		}else{
			$resp['status'] = 'failed';
			$resp['err'] = $this->conn->error."[{$sql}]";
		}
		return json_encode($resp);
	}
	function delete_supplier(){
		extract($_POST);
		$del = $this->conn->query("DELETE FROM `supplier_list` where id = '{$id}'");
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success',"Supplier successfully deleted.");
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
	function save_item(){
		extract($_POST);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id'))){
				$v = $this->conn->real_escape_string($v);
				if(!empty($data)) $data .=",";
				$data .= " `{$k}`='{$v}' ";
			}
		}
		$check = $this->conn->query("SELECT * FROM `item_list` where `name` = '{$name}' and `supplier_id` = '{$supplier_id}' ".(!empty($id) ? " and id != {$id} " : "")." ")->num_rows;
		if($this->capture_err())
			return $this->capture_err();
		if($check > 0){
			$resp['status'] = 'failed';
			$resp['msg'] = "Item already exists under selected supplier.";
			return json_encode($resp);
			exit;
		}
		if(empty($id)){
			$sql = "INSERT INTO `item_list` set {$data} ";
			$save = $this->conn->query($sql);
		}else{
			$sql = "UPDATE `item_list` set {$data} where id = '{$id}' ";
			$save = $this->conn->query($sql);
		}
		if($save){
			$resp['status'] = 'success';
			if(empty($id))
				$this->settings->set_flashdata('success',"New Item successfully saved.");
			else
				$this->settings->set_flashdata('success',"Item successfully updated.");
		}else{
			$resp['status'] = 'failed';
			$resp['err'] = $this->conn->error."[{$sql}]";
		}
		return json_encode($resp);
	}
	function delete_item(){
		extract($_POST);
		$del = $this->conn->query("DELETE FROM `item_list` where id = '{$id}'");
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success',"Item  successfully deleted.");
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
	function save_po(){
		if(empty($_POST['id'])){
			$prefix = "PO";
			$code = sprintf("%'.04d",1);
			while(true){
				$check_code = $this->conn->query("SELECT * FROM `purchase_order_list` where po_code ='".$prefix.'-'.$code."' ")->num_rows;
				if($check_code > 0){
					$code = sprintf("%'.04d",$code+1);
				}else{
					break;
				}
			}
			$_POST['po_code'] = $prefix."-".$code;
		}
		extract($_POST);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id')) && !is_array($_POST[$k])){
				if(!is_numeric($v))
				$v= $this->conn->real_escape_string($v);
				if(!empty($data)) $data .=", ";
				$data .=" `{$k}` = '{$v}' ";
			}
		}
		if(empty($id)){
			$sql = "INSERT INTO `purchase_order_list` set {$data}";
		}else{
			$sql = "UPDATE `purchase_order_list` set {$data} where id = '{$id}'";
		}
		$save = $this->conn->query($sql);
		if($save){
			$resp['status'] = 'success';
			if(empty($id))
			$po_id = $this->conn->insert_id;
			else
			$po_id = $id;
			$resp['id'] = $po_id;
			$data = "";
			foreach($item_id as $k =>$v){
				if(!empty($data)) $data .=", ";
				$data .= "('{$po_id}','{$v}','{$qty[$k]}','{$price[$k]}','{$unit[$k]}','{$total[$k]}')";
			}
			if(!empty($data)){
				$this->conn->query("DELETE FROM `po_items` where po_id = '{$po_id}'");
				$save = $this->conn->query("INSERT INTO `po_items` (`po_id`,`item_id`,`quantity`,`price`,`unit`,`total`) VALUES {$data}");
				if(!$save){
					$resp['status'] = 'failed';
					if(empty($id)){
						$this->conn->query("DELETE FROM `purchase_order_list` where id '{$po_id}'");
					}
					$resp['msg'] = 'PO has failed to save. Error: '.$this->conn->error;
					$resp['sql'] = "INSERT INTO `po_items` (`po_id`,`item_id`,`quantity`,`price`,`unit`,`total`) VALUES {$data}";
				}
			}
		}else{
			$resp['status'] = 'failed';
			$resp['msg'] = 'An error occured. Error: '.$this->conn->error;
		}
		if($resp['status'] == 'success'){
			if(empty($id)){
				$this->settings->set_flashdata('success'," New Purchase Order was Successfully created.");
			}else{
				$this->settings->set_flashdata('success'," Purchase Order's Details Successfully updated.");
			}
		}

		return json_encode($resp);
	}
	function delete_po(){
		extract($_POST);
		$bo = $this->conn->query("SELECT * FROM back_order_list where po_id = '{$id}'");
		$del = $this->conn->query("DELETE FROM `purchase_order_list` where id = '{$id}'");
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success',"po's Details Successfully deleted.");
			if($bo->num_rows > 0){
				$bo_res = $bo->fetch_all(MYSQLI_ASSOC);
				$r_ids = array_column($bo_res, 'receiving_id');
				$bo_ids = array_column($bo_res, 'id');
			}
			$qry = $this->conn->query("SELECT * FROM receiving_list where (form_id='{$id}' and from_order = '1') ".(isset($r_ids) && count($r_ids) > 0 ? "OR id in (".(implode(',',$r_ids)).") OR (form_id in (".(implode(',',$bo_ids)).") and from_order = '2') " : "" )." ");
			while($row = $qry->fetch_assoc()){
				$this->conn->query("DELETE FROM `stock_list` where id in ({$row['stock_ids']}) ");
				// echo "DELETE FROM `stock_list` where id in ({$row['stock_ids']}) </br>";
			}
			$this->conn->query("DELETE FROM receiving_list where (form_id='{$id}' and from_order = '1') ".(isset($r_ids) && count($r_ids) > 0 ? "OR id in (".(implode(',',$r_ids)).") OR (form_id in (".(implode(',',$bo_ids)).") and from_order = '2') " : "" )." ");
			// echo "DELETE FROM receiving_list where (form_id='{$id}' and from_order = '1') ".(isset($r_ids) && count($r_ids) > 0 ? "OR id in (".(implode(',',$r_ids)).") OR (form_id in (".(implode(',',$bo_ids)).") and from_order = '2') " : "" )."  </br>";
			// exit;
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
	function save_receiving(){
		if(empty($_POST['id'])){
			$prefix = "BO";
			$code = sprintf("%'.04d",1);
			while(true){
				$check_code = $this->conn->query("SELECT * FROM `back_order_list` where bo_code ='".$prefix.'-'.$code."' ")->num_rows;
				if($check_code > 0){
					$code = sprintf("%'.04d",$code+1);
				}else{
					break;
				}
			}
			$_POST['bo_code'] = $prefix."-".$code;
		}else{
			$get = $this->conn->query("SELECT * FROM back_order_list where receiving_id = '{$_POST['id']}' ");
			if($get->num_rows > 0){
				$res = $get->fetch_array();
				$bo_id = $res['id'];
				$_POST['bo_code'] = $res['bo_code'];	
			}else{

				$prefix = "BO";
				$code = sprintf("%'.04d",1);
				while(true){
					$check_code = $this->conn->query("SELECT * FROM `back_order_list` where bo_code ='".$prefix.'-'.$code."' ")->num_rows;
					if($check_code > 0){
						$code = sprintf("%'.04d",$code+1);
					}else{
						break;
					}
				}
				$_POST['bo_code'] = $prefix."-".$code;

			}
		}
		extract($_POST);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id','bo_code','supplier_id','po_id')) && !is_array($_POST[$k])){
				if(!is_numeric($v))
				$v= $this->conn->real_escape_string($v);
				if(!empty($data)) $data .=", ";
				$data .=" `{$k}` = '{$v}' ";
			}
		}
		if(empty($id)){
			$sql = "INSERT INTO `receiving_list` set {$data}";
		}else{
			$sql = "UPDATE `receiving_list` set {$data} where id = '{$id}'";
		}
		$save = $this->conn->query($sql);
		if($save){
			$resp['status'] = 'success';
			if(empty($id))
			$r_id = $this->conn->insert_id;
			else
			$r_id = $id;
			$resp['id'] = $r_id;
			if(!empty($id)){
				$stock_ids = $this->conn->query("SELECT stock_ids FROM `receiving_list` where id = '{$id}'")->fetch_array()['stock_ids'];
				$this->conn->query("DELETE FROM `stock_list` where id in ({$stock_ids})");
			}
			$stock_ids= array();
			foreach($item_id as $k =>$v){
				if(!empty($data)) $data .=", ";
				$sql = "INSERT INTO stock_list (`item_id`,`quantity`,`price`,`unit`,`total`,`type`) VALUES ('{$v}','{$qty[$k]}','{$price[$k]}','{$unit[$k]}','{$total[$k]}','1')";
				$this->conn->query($sql);
				$stock_ids[] = $this->conn->insert_id;
				if($qty[$k] < $oqty[$k]){
					$bo_ids[] = $k;
				}
			}
			if(count($stock_ids) > 0){
				$stock_ids = implode(',',$stock_ids);
				$this->conn->query("UPDATE `receiving_list` set stock_ids = '{$stock_ids}' where id = '{$r_id}'");
			}
			if(isset($bo_ids)){
				$this->conn->query("UPDATE `purchase_order_list` set status = 1 where id = '{$po_id}'");
				if($from_order == 2){
					$this->conn->query("UPDATE `back_order_list` set status = 1 where id = '{$form_id}'");
				}
				if(!isset($bo_id)){
					$sql = "INSERT INTO `back_order_list` set 
							bo_code = '{$bo_code}',	
							receiving_id = '{$r_id}',	
							po_id = '{$po_id}',	
							supplier_id = '{$supplier_id}',	
							discount_perc = '{$discount_perc}',	
							tax_perc = '{$tax_perc}'
						";
				}else{
					$sql = "UPDATE `back_order_list` set 
							receiving_id = '{$r_id}',	
							po_id = '{$form_id}',	
							supplier_id = '{$supplier_id}',	
							discount_perc = '{$discount_perc}',	
							tax_perc = '{$tax_perc}',
							where bo_id = '{$bo_id}'
						";
				}
				$bo_save = $this->conn->query($sql);
				if(!isset($bo_id))
				$bo_id = $this->conn->insert_id;
				$stotal =0; 
				$data = "";
				foreach($item_id as $k =>$v){
					if(!in_array($k,$bo_ids))
						continue;
					$total = ($oqty[$k] - $qty[$k]) * $price[$k];
					$stotal += $total;
					if(!empty($data)) $data.= ", ";
					$data .= " ('{$bo_id}','{$v}','".($oqty[$k] - $qty[$k])."','{$price[$k]}','{$unit[$k]}','{$total}') ";
				}
				$this->conn->query("DELETE FROM `bo_items` where bo_id='{$bo_id}'");
				$save_bo_items = $this->conn->query("INSERT INTO `bo_items` (`bo_id`,`item_id`,`quantity`,`price`,`unit`,`total`) VALUES {$data}");
				if($save_bo_items){
					$discount = $stotal * ($discount_perc /100);
					$stotal -= $discount;
					$tax = $stotal * ($tax_perc /100);
					$stotal += $tax;
					$amount = $stotal;
					$this->conn->query("UPDATE back_order_list set amount = '{$amount}', discount='{$discount}', tax = '{$tax}' where id = '{$bo_id}'");
				}

			}else{
				$this->conn->query("UPDATE `purchase_order_list` set status = 2 where id = '{$po_id}'");
				if($from_order == 2){
					$this->conn->query("UPDATE `back_order_list` set status = 2 where id = '{$form_id}'");
				}
			}
		}else{
			$resp['status'] = 'failed';
			$resp['msg'] = 'An error occured. Error: '.$this->conn->error;
		}
		if($resp['status'] == 'success'){
			if(empty($id)){
				$this->settings->set_flashdata('success'," New Stock was Successfully received.");
			}else{
				$this->settings->set_flashdata('success'," Received Stock's Details Successfully updated.");
			}
		}

		return json_encode($resp);
	}
	function delete_receiving(){
		extract($_POST);
		$qry = $this->conn->query("SELECT * from  receiving_list where id='{$id}' ");
		if($qry->num_rows > 0){
			$res = $qry->fetch_array();
			$ids = $res['stock_ids'];
		}
		if(isset($ids) && !empty($ids))
		$this->conn->query("DELETE FROM stock_list where id in ($ids) ");
		$del = $this->conn->query("DELETE FROM receiving_list where id='{$id}' ");
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success',"Received Order's Details Successfully deleted.");

			if(isset($res)){
				if($res['from_order'] == 1){
					$this->conn->query("UPDATE purchase_order_list set status = 0 where id = '{$res['form_id']}' ");
				}
			}
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
	function delete_bo(){
		extract($_POST);
		$bo =$this->conn->query("SELECT * FROM `back_order_list` where id = '{$id}'");
		if($bo->num_rows >0)
		$bo_res = $bo->fetch_array();
		$del = $this->conn->query("DELETE FROM `back_order_list` where id = '{$id}'");
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success',"po's Details Successfully deleted.");
			$qry = $this->conn->query("SELECT `stock_ids` from  receiving_list where form_id='{$id}' and from_order = '2' ");
			if($qry->num_rows > 0){
				$res = $qry->fetch_array();
				$ids = $res['stock_ids'];
				$this->conn->query("DELETE FROM stock_list where id in ($ids) ");

				$this->conn->query("DELETE FROM receiving_list where form_id='{$id}' and from_order = '2' ");
			}
			if(isset($bo_res)){
				$check = $this->conn->query("SELECT * FROM `receiving_list` where from_order = 1 and form_id = '{$bo_res['po_id']}' ");
				if($check->num_rows > 0){
					$this->conn->query("UPDATE `purchase_order_list` set status = 1 where id = '{$bo_res['po_id']}' ");
				}else{
					$this->conn->query("UPDATE `purchase_order_list` set status = 0 where id = '{$bo_res['po_id']}' ");
				}
			}
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);
	}
	function save_return(){
		if(empty($_POST['id'])){
			$prefix = "R";
			$code = sprintf("%'.04d",1);
			while(true){
				$check_code = $this->conn->query("SELECT * FROM `return_list` where return_code ='".$prefix.'-'.$code."' ")->num_rows;
				if($check_code > 0){
					$code = sprintf("%'.04d",$code+1);
				}else{
					break;
				}
			}
			$_POST['return_code'] = $prefix."-".$code;
		}
		extract($_POST);
		$data = "";
		foreach($_POST as $k =>$v){
			if(!in_array($k,array('id')) && !is_array($_POST[$k])){
				if(!is_numeric($v))
				$v= $this->conn->real_escape_string($v);
				if(!empty($data)) $data .=", ";
				$data .=" `{$k}` = '{$v}' ";
			}
		}
		if(empty($id)){
			$sql = "INSERT INTO `return_list` set {$data}";
		}else{
			$sql = "UPDATE `return_list` set {$data} where id = '{$id}'";
		}
		$save = $this->conn->query($sql);
		if($save){
			$resp['status'] = 'success';
			if(empty($id))
			$return_id = $this->conn->insert_id;
			else
			$return_id = $id;
			$resp['id'] = $return_id;
			$data = "";
			$sids = array();
			$get = $this->conn->query("SELECT * FROM `return_list` where id = '{$return_id}'");
			if($get->num_rows > 0){
				$res = $get->fetch_array();
				if(!empty($res['stock_ids'])){
					$this->conn->query("DELETE FROM `stock_list` where id in ({$res['stock_ids']}) ");
				}
			}
			foreach($item_id as $k =>$v){
				$sql = "INSERT INTO `stock_list` set item_id='{$v}', `quantity` = '{$qty[$k]}', `unit` = '{$unit[$k]}', `price` = '{$price[$k]}', `total` = '{$total[$k]}', `type` = 2 ";
				$save = $this->conn->query($sql);
				if($save){
					$sids[] = $this->conn->insert_id;
				}
			}
			$sids = implode(',',$sids);
			$this->conn->query("UPDATE `return_list` set stock_ids = '{$sids}' where id = '{$return_id}'");
		}else{
			$resp['status'] = 'failed';
			$resp['msg'] = 'An error occured. Error: '.$this->conn->error;
		}
		if($resp['status'] == 'success'){
			if(empty($id)){
				$this->settings->set_flashdata('success'," New Returned Item Record was Successfully created.");
			}else{
				$this->settings->set_flashdata('success'," Returned Item Record's Successfully updated.");
			}
		}

		return json_encode($resp);
	}
	function delete_return(){
		extract($_POST);
		$get = $this->conn->query("SELECT * FROM return_list where id = '{$id}'");
		if($get->num_rows > 0){
			$res = $get->fetch_array();
		}
		$del = $this->conn->query("DELETE FROM `return_list` where id = '{$id}'");
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success',"Returned Item Record's Successfully deleted.");
			if(isset($res)){
				$this->conn->query("DELETE FROM `stock_list` where id in ({$res['stock_ids']})");
			}
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
function save_sale(){
    $this->conn->begin_transaction();
    try {
        // 1. Authentication Check
        if (!isset($_SESSION['userdata']['user_id'])) {
            throw new Exception('User not logged in.');
        }
        $user_id = $_SESSION['userdata']['user_id'];
        $user_type = $_SESSION['userdata']['type'] ?? 1; // 1=admin, 2=sales rep

        // 2. Sales Code Generation (for new sales)
        if(empty($_POST['id'])) {
            $prefix = "SALE";
            $code = sprintf("%'.04d",1);
            while(true) {
                $check = $this->conn->prepare("SELECT id FROM `sales_list` WHERE sales_code = ?");
                $sales_code = $prefix.'-'.$code;
                $check->bind_param("s", $sales_code);
                $check->execute();
                $result = $check->get_result();
                if($result->num_rows > 0) {
                    $code = sprintf("%'.04d",$code+1);
                } else {
                    break;
                }
            }
            $_POST['sales_code'] = $prefix."-".$code;
        }

        // 3. Prepare Sale Data
        $data = [];
        foreach($_POST as $k => $v) {
            if(!in_array($k, ['id', 'item_id', 'qty', 'unit', 'price', 'total', 'stock_ids']) && !is_array($v)) {
                $data[$k] = is_numeric($v) ? $v : $this->conn->real_escape_string($v);
            }
        }
        $data['user_id'] = $user_id;

        // 4. Validate Sales Rep Stock (if user is sales rep)
        if ($user_type == 2) {
            foreach($_POST['item_id'] as $k => $item_id) {
                $qty = (float)$_POST['qty'][$k];
                $check = $this->conn->prepare("SELECT quantity_remaining FROM product_assignments WHERE sales_rep_id = ? AND product_id = ?");
                $check->bind_param("ii", $user_id, $item_id);
                $check->execute();
                $result = $check->get_result();
                if ($result->num_rows == 0) {
                    throw new Exception("You are not assigned to sell item ID: $item_id");
                }
                $assignment = $result->fetch_assoc();
                if ($qty > $assignment['quantity_remaining']) {
                    throw new Exception("Insufficient assigned stock for item ID: $item_id");
                }
            }
        } else {
            // For admin, check main stock
            foreach($_POST['item_id'] as $k => $item_id) {
                $qty = (float)$_POST['qty'][$k];
                $in = $this->conn->query("SELECT SUM(quantity) as total FROM stock_list WHERE item_id = '$item_id' AND type = 1")->fetch_array()['total'];
                $out = $this->conn->query("SELECT SUM(quantity) as total FROM stock_list WHERE item_id = '$item_id' AND type = 2")->fetch_array()['total'];
                $available = $in - $out;
                if ($qty > $available) {
                    throw new Exception("Insufficient main stock for item ID: $item_id");
                }
            }
        }

        // 5. Save Sale Record
        if(empty($_POST['id'])) {
            $fields = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
            $values = implode(', ', array_fill(0, count($data), '?'));
            $types = str_repeat('s', count($data));
            $stmt = $this->conn->prepare("INSERT INTO `sales_list` ($fields) VALUES ($values)");
            $stmt->bind_param($types, ...array_values($data));
            $stmt->execute();
            $sale_id = $this->conn->insert_id;
        } else {
            $sale_id = $_POST['id'];
            $set = [];
            foreach($data as $k => $v) {
                $set[] = "`$k`=?";
            }
            $types = str_repeat('s', count($data));
            $stmt = $this->conn->prepare("UPDATE `sales_list` SET ".implode(', ', $set)." WHERE id=?");
            $params = array_values($data);
            $params[] = $sale_id;
            $types .= 'i';
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            // Remove old stock_list if updating
            $get = $this->conn->query("SELECT stock_ids FROM `sales_list` WHERE id = '{$sale_id}'");
            if($get && $row = $get->fetch_assoc()) {
                if(!empty($row['stock_ids'])) {
                    $this->conn->query("DELETE FROM `stock_list` WHERE id IN ({$row['stock_ids']})");
                }
            }
        }

        // 6. Process Items and Stock
        $sids = [];
        foreach($_POST['item_id'] as $k => $item_id) {
            $qty = (float)$_POST['qty'][$k];
            $price = (float)$_POST['price'][$k];
            $total = (float)$_POST['total'][$k];
            $unit = $this->conn->real_escape_string($_POST['unit'][$k]);

            // Create stock record for both admin and sales rep
            $stock = $this->conn->prepare("INSERT INTO `stock_list` (item_id, quantity, unit, price, total, type) VALUES (?, ?, ?, ?, ?, 2)");
            $stock->bind_param("idssd", $item_id, $qty, $unit, $price, $total);
            $stock->execute();
            $sids[] = $this->conn->insert_id;

            if ($user_type == 2) {
                // Deduct from assigned stock for sales rep
                $update = $this->conn->prepare("UPDATE product_assignments SET quantity_remaining = quantity_remaining - ? WHERE sales_rep_id = ? AND product_id = ?");
                $update->bind_param("dii", $qty, $user_id, $item_id);
                $update->execute();
                if ($update->affected_rows == 0) {
                    throw new Exception("Failed to update assigned stock for item ID: $item_id");
                }
            }

            // Record stock movement
            $from_user = ($user_type == 2) ? $user_id : 1; // 1 for admin
            $movement = $this->conn->prepare("INSERT INTO stock_movements (product_id, from_user, to_user, quantity, movement_type, reference_id) VALUES (?, ?, 0, ?, 'sale', ?)");
            $movement->bind_param("iiii", $item_id, $from_user, $qty, $sale_id);
            $movement->execute();
        }

        // 7. Update sale with stock IDs for both admin and sales rep
        if (!empty($sids)) {
            $this->conn->query("UPDATE `sales_list` SET stock_ids = '".implode(',',$sids)."' WHERE id = '$sale_id'");
        }

        $this->conn->commit();
        $resp = [
            'status' => 'success',
            'id' => $sale_id,
            'msg' => empty($_POST['id']) ? 'New Sales Record was Successfully created.' : "Sales Record's Successfully updated."
        ];
        $this->settings->set_flashdata('success', $resp['msg']);
    } catch (Exception $e) {
        $this->conn->rollback();
        $resp = [
            'status' => 'failed',
            'msg' => 'Error: ' . $e->getMessage()
        ];
    }
    return json_encode($resp);
}
	
	
	function delete_sale(){
		extract($_POST);
		$get = $this->conn->query("SELECT * FROM sales_list where id = '{$id}'");
		if($get->num_rows > 0){
			$res = $get->fetch_array();
		}
		$del = $this->conn->query("DELETE FROM `sales_list` where id = '{$id}'");
		if($del){
			$resp['status'] = 'success';
			$this->settings->set_flashdata('success',"Sales Record's Successfully deleted.");
			if(isset($res)){
				$this->conn->query("DELETE FROM `stock_list` where id in ({$res['stock_ids']})");
			}
		}else{
			$resp['status'] = 'failed';
			$resp['error'] = $this->conn->error;
		}
		return json_encode($resp);

	}
}

$Master = new Master();
$action = !isset($_GET['f']) ? 'none' : strtolower($_GET['f']);
$sysset = new SystemSettings();
switch ($action) {
	case 'save_supplier':
		echo $Master->save_supplier();
	break;
	case 'delete_supplier':
		echo $Master->delete_supplier();
	break;
	case 'save_item':
		echo $Master->save_item();
	break;
	case 'delete_item':
		echo $Master->delete_item();
	break;
	
	case 'save_po':
		echo $Master->save_po();
	break;
	case 'delete_po':
		echo $Master->delete_po();
	break;
	case 'save_receiving':
		echo $Master->save_receiving();
	break;
	case 'delete_receiving':
		echo $Master->delete_receiving();
	break;
	case 'save_return':
		echo $Master->save_return();
	break;
	case 'delete_return':
		echo $Master->delete_return();
	break;
	case 'save_sale':
		echo $Master->save_sale();
	break;
	case 'delete_sale':
		echo $Master->delete_sale();
	break;
	case 'assign_stock':
		echo $Master->assign_stock();
	break;
	case 'get_assignments':
		echo $Master->get_assignments();
	break;
	default:
		// echo $sysset->index();
		break;
}

