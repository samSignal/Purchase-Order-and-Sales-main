<?php
require_once('../../config.php');
include('../inc/header.php');


$qry = $conn->query("SELECT a.*, p.name as product, 
                            CONCAT(u.firstname, ' ', COALESCE(u.middlename, ''), ' ', u.lastname) as rep 
                     FROM product_assignments a 
                     INNER JOIN item_list p ON a.product_id = p.id 
                     INNER JOIN users u ON a.sales_rep_id = u.id 
                     ORDER BY a.assigned_at DESC");
?>

<style>
    .main-content {
        padding: 20px;
        background: #f8f9fa;
    }
    .page-header {
        background: #fff;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .card {
        border: none;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .card-header {
        background: #fff;
        border-bottom: 1px solid #eee;
        padding: 15px 20px;
    }
    .table-container {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
    }
    .table {
        margin-bottom: 0;
    }
    .table thead th {
        border-bottom: 2px solid #eee;
        background: #f8f9fa;
        color: #495057;
        font-weight: 600;
        padding: 12px;
    }
    .table td {
        padding: 12px;
        vertical-align: middle;
        color: #495057;
        border-color: #eee;
    }
    .btn-create {
        background: #007bff;
        color: #fff;
        border-radius: 6px;
        padding: 8px 16px;
        font-weight: 500;
    }
    .btn-create:hover {
        background: #0069d9;
        color: #fff;
    }
    .entries-search {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .entries-length {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .entries-length select {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 6px 10px;
    }
    .search-box {
        display: flex;
        align-items: center;
    }
    .search-box input {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 6px 12px;
        width: 200px;
    }
    .pagination {
        margin-top: 20px;
        justify-content: flex-end;
    }
    .pagination .page-link {
        border-color: #ddd;
        color: #495057;
    }
    .pagination .page-link:hover {
        background: #e9ecef;
    }
</style>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">List of Assigned Products</h4>
        <a href="stocks.index.php" class="btn btn-secondary">← Back to Stocks</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="entries-search">
                <div class="entries-length">
                    <label>Show</label>
                    <select class="form-select">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <label>entries</label>
                </div>
                <div class="search-box">
                    <label class="me-2">Search:</label>
                    <input type="search" class="form-control" placeholder="Type to search...">
                </div>
            </div>

            <div class="table-container">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sales Rep</th>
                            <th>Product</th>
                            <th>Assigned Qty</th>
                            <th>Remaining Qty</th>
                            <th>Assigned Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        while($row = $qry->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $row['rep'] ?></td>
                            <td><?= $row['product'] ?></td>
                            <td><?= $row['quantity_assigned'] ?></td>
                            <td><?= $row['quantity_remaining'] ?></td>
                            <td><?= date("Y-m-d H:i A", strtotime($row['assigned_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="showing-entries">
                    Showing 1 to 10 of 0 entries
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination mb-0">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">Previous</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item disabled">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<?php include('../inc/footer.php'); ?>
