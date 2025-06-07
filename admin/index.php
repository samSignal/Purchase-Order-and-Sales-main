<?php require_once('../config.php'); ?>
<!DOCTYPE html>
<html lang="en" class="" style="height: auto;">
<?php require_once('inc/header.php') ?>
<body class="sidebar-mini layout-fixed control-sidebar-slide-open layout-navbar-fixed">
    <div class="wrapper">
        <?php require_once('inc/topBarNav.php') ?>
        <?php require_once('inc/navigation.php') ?>
        
        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0"><?= !empty($page_title) ? $page_title : '' ?></h1>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <?php 
                        $page = isset($_GET['page']) ? $_GET['page'] : 'home';
                        if(!file_exists($page.".php") && !is_dir($page)){
                            include '404.html';
                        }else{
                            if(is_dir($page))
                                include $page.'/index.php';
                            else
                                include $page.'.php';
                        }
                    ?>
                </div>
            </section>
        </div>
        
        <?php require_once('inc/footer.php') ?>
    </div>
</body>
</html>
