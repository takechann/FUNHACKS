
<?php

/* HTML特殊文字をエスケープする関数 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// XHTMLとしてブラウザに認識させる
// (IE8以下はサポート対象外ｗ)
header('Content-Type: application/xhtml+xml; charset=utf-8');

try {

    // データベースに接続
    $pdo = new PDO(
        'mysql:host=localhost;dbname=test;charset=utf8',
        'root',
        'root',
        [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]   
        );

    /* アップロードがあったとき */
    if (isset($_FILES['upfile']['error']) && is_int($_FILES['upfile']['error'])) {

        // バッファリングを開始
        ob_start();

        try {

            // $_FILES['upfile']['error'] の値を確認
            switch ($_FILES['upfile']['error']) {
                case UPLOAD_ERR_OK: // OK
                break;
                case UPLOAD_ERR_NO_FILE:   // ファイル未選択
                throw new RuntimeException('ファイルが選択されていません', 400);
                case UPLOAD_ERR_INI_SIZE:  // php.ini定義の最大サイズ超過
                case UPLOAD_ERR_FORM_SIZE: // フォーム定義の最大サイズ超過
                throw new RuntimeException('ファイルサイズが大きすぎます', 400);
                default:
                throw new RuntimeException('その他のエラーが発生しました', 500);
            }

            // $_FILES['upfile']['mime']の値はブラウザ側で偽装可能なので
            // MIMEタイプを自前でチェックする
            if (!$info = @getimagesize($_FILES['upfile']['tmp_name'])) {
                throw new RuntimeException('有効な画像ファイルを指定してください', 400);
            }
            if (!in_array($info[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                throw new RuntimeException('未対応の画像形式です', 400);
            }

            // サムネイルをバッファに出力
            $create = str_replace('/', 'createfrom', $info['mime']);
            $output = str_replace('/', '', $info['mime']);
            if ($info[0] >= $info[1]) {
                $dst_w = 120;
                $dst_h = ceil(120 * $info[1] / max($info[0], 1));
            } else {
                $dst_w = ceil(120 * $info[0] / max($info[1], 1));
                $dst_h = 120;
            }
            if (!$src = @$create($_FILES['upfile']['tmp_name'])) {
                throw new RuntimeException('画像リソースの生成に失敗しました', 500);
            }
            $dst = imagecreatetruecolor($dst_w, $dst_h);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dst_w, $dst_h, $info[0], $info[1]);
            $output($dst);
            imagedestroy($src);
            imagedestroy($dst);

            // INSERT処理
            $stmt = $pdo->prepare('INSERT INTO test(name,type,raw_data,thumb_data,date) VALUES(?,?,?,?,?)');
            $stmt->execute([
                $_FILES['upfile']['name'],
                $info[2],
                file_get_contents($_FILES['upfile']['tmp_name']),
                ob_get_clean(), // バッファからデータを取得してクリア
                (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s'),
                ]);

            $msgs[] = ['green', 'ファイルは正常にアップロードされました'];

        } catch (RuntimeException $e) {

            while (ob_get_level()) {
                ob_end_clean(); // バッファをクリア
            }
            http_response_code($e instanceof PDOException ? 500 : $e->getCode());
            $msgs[] = ['red', $e->getMessage()];

        }

        /* ID指定があったとき */
    } elseif (isset($_GET['id'])) {

        try {

            $stmt = $pdo->prepare('SELECT type, raw_data FROM test WHERE id = ? LIMIT 1');
            $stmt->bindValue(1, $_GET['id'], PDO::PARAM_INT);
            $stmt->execute();
            if (!$row = $stmt->fetch()) {
                throw new RuntimeException('該当する画像は存在しません', 404);
            }
            header('X-Content-Type-Options: nosniff');
            header('Content-Type: ' . image_type_to_mime_type($row['type']));
            echo $row['raw_data'];
            exit;

        } catch (RuntimeException $e) {

            http_response_code($e instanceof PDOException ? 500 : $e->getCode());
            $msgs[] = ['red', $e->getMessage()];

        }

    }

    // サムネイル一覧取得
    $rows = $pdo->query('SELECT id,name,type,thumb_data,date FROM test ORDER BY date DESC')->fetchAll();

} catch (PDOException $e) {

    http_response_code(500);
    $msgs[] = ['red', $e->getMessage()];

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>codestudio main</title>

    <!-- Bootstrap Core CSS -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Theme CSS -->
    <link href="css/freelancer.min.css" rel="stylesheet">

    <!-- Custom Fonts -->
    <link href="vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Lato:400,700,400italic,700italic" rel="stylesheet" type="text/css">

    

</head>

<body id="page-top" class="index">

    <!-- Navigation -->
    <nav id="mainNav" class="navbar navbar-default navbar-fixed-top navbar-custom">
        <div class="container">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header page-scroll">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                    <span class="sr-only">Toggle navigation</span> Menu <i class="fa fa-bars"></i>
                </button>
                <a class="navbar-brand" href="#page-top"　div align="center"></a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav navbar-right">
                    <li class="hidden">
                        <a href="#page-top"></a>
                    </li>
                    <li class="page-scroll">
                        <a href="index.php">今日の服装</a>
                    </li>
                    <li class="page-scroll">
                        <a href="kuroz2.php">クローゼット</a>
                    </li>
                    <li class="page-scroll">
                        <a href="contact.php">問い合わせ</a>
                    </li>
                </ul>
            </div>
            <!-- /.navbar-collapse -->
        </div>
        <!-- /.container-fluid -->
    </nav>

    <!-- Header -->
    <header>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">

                    <div class="intro-text">
                        <span class="name">CODE STUDIO</span>
                    </div>
                </div>
            </div>
        </div>
        
    </header>

    <!-- Portfolio Grid Section -->
    <section id="portfolio">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center">
                    <h2>クローゼット</h2>
                    <hr class="star-primary">
                </div>
            </div>
            <div class="row" >
<!--
                <div class="col-sm-4 portfolio-item">
                    <a href="#portfolioModal1" class="portfolio-link" data-toggle="modal">
                        <div class="caption">
                            <div class="caption-content">
                                <i class="fa fa-search-plus fa-3x"></i>
                            </div>
                        </div>
                        <img src="img/portfolio/sukato.jpg" class="img-responsive" alt="">
                    </a>
                </div>
                
                <div class="col-sm-4 portfolio-item">
                    <a href="#portfolioModal2" class="portfolio-link" data-toggle="modal">
                        <div class="caption">
                            <div class="caption-content">
                                <i class="fa fa-search-plus fa-3x"></i>
                            </div>
                        </div>
                        <img src="img/portfolio/sukato.jpg" class="img-responsive" alt="">
                    </a>
                </div>
                
                <div class="col-sm-4 portfolio-item">
                    <a href="#portfolioModal3" class="portfolio-link" data-toggle="modal">
                        <div class="caption">
                            <div class="caption-content">
                                <i class="fa fa-search-plus fa-3x"></i>
                            </div>
                        </div>
                        <img src="img/portfolio/sukato.jpg" class="img-responsive" alt="">
                    </a>
                </div>
                 
                 <div class="col-sm-4 portfolio-item">
                    <a href="#portfolioModal4" class="portfolio-link" data-toggle="modal">
                        <div class="caption">
                            <div class="caption-content">
                                <i class="fa fa-search-plus fa-3x"></i>
                            </div>
                        </div>
                        <img src="img/portfolio/sukato.jpg" class="img-responsive" alt="">
                    </a>

                </div>
            -->
            <?php if (!empty($rows)): ?>
             <fieldset>
                <?php foreach ($rows as $i => $row): ?>
                    <?php if ($i): ?>
                       <hr />
                   <?php endif; ?>
                   <p>
                     <?=sprintf(
                         '<a href="?id=%d"><img src="data:%s;base64,%s" alt="%s" /></a>',
                         $row['id'],
                         image_type_to_mime_type($row['type']),
                         base64_encode($row['thumb_data']),
                         h($row['name'])
                         )?><br />
                     </p>
                 <?php endforeach; ?>
             </fieldset>
         <?php endif; ?>

     </div>
 </div>
</section>


<!-- Footer -->
<div align="center">   
    <input type="file" name="upfile" id="upfile" accept="image/*" capture="camera" style="display:none" />
    <div>
        <img id="thumbnail" src="img/portfolio/kame.png" onClick="$('#upfile').click()" style="position:relative; top:40px;" width="75" height="75">
    </div>

    <div align="right">    
       <input type="submit" value= "送信" id="thumbnail" src="img/portfolio/send1.png" style="position:relative; top:60px;" width="60" height="60" style="display:none" />
    </div>

</div>



<footer class="text-center">

    <div class="footer-above">
        <div class="container">
            <div align="left">
                <a href="kuroz2.php">
                    <img src="img/portfolio/kuro.png" class="img-responsive" alt="" width="75" height="75">
                </a>
            </div>
        </div>
    </div>
        <!--
        <div class="footer-below">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        Copyright &copy; Your Website 2016
                    </div>
                </div>
            </div>
        </div>
    -->
</footer>
<!-- Scroll to Top Button (Only visible on small and extra-small screen sizes) -->
<div class="scroll-top page-scroll hidden-sm hidden-xs hidden-lg hidden-md">
    <a class="btn btn-primary" href="#page-top">
        <i class="fa fa-chevron-up"></i>
    </a>
</div>

<!-- Portfolio Modals -->
<div class="portfolio-modal modal fade" id="portfolioModal1" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-content">
        <div class="close-modal" data-dismiss="modal">
            <div class="lr">
                <div class="rl">
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-lg-offset-2">
                    <div class="modal-body">
                        <h2>スカート（仮）</h2>
                        <hr class="star-primary">
                        <img src="img/portfolio/sukato.jpg" class="img-responsive img-centered" alt="">
                        <p>★★★</p>
                        <ul class="list-inline item-details">
                            <li>Client:
                                <strong><a href="http://startbootstrap.com">Start Bootstrap</a>
                                </strong>
                            </li>
                            <li>Date:
                                <strong><a href="http://startbootstrap.com">April 2014</a>
                                </strong>
                            </li>
                            <li>Service:
                                <strong><a href="http://startbootstrap.com">Web Development</a>
                                </strong>
                            </li>
                        </ul>
                        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="portfolio-modal modal fade" id="portfolioModal2" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-content">
        <div class="close-modal" data-dismiss="modal">
            <div class="lr">
                <div class="rl">
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-lg-offset-2">
                    <div class="modal-body">
                        <h2>スカート（仮２）</h2>
                        <hr class="star-primary">
                        <img src="img/portfolio/cake.png" class="img-responsive img-centered" alt="">
                        <p>★★★★</p>
                        <ul class="list-inline item-details">
                            <li>Client:
                                <strong><a href="http://startbootstrap.com">Start Bootstrap</a>
                                </strong>
                            </li>
                            <li>Date:
                                <strong><a href="http://startbootstrap.com">April 2014</a>
                                </strong>
                            </li>
                            <li>Service:
                                <strong><a href="http://startbootstrap.com">Web Development</a>
                                </strong>
                            </li>
                        </ul>
                        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="portfolio-modal modal fade" id="portfolioModal3" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-content">
        <div class="close-modal" data-dismiss="modal">
            <div class="lr">
                <div class="rl">
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-lg-offset-2">
                    <div class="modal-body">
                        <h2>スカート（仮３）</h2>
                        <hr class="star-primary">
                        <img src="img/portfolio/circus.png" class="img-responsive img-centered" alt="">
                        <p>★★</p>
                        <ul class="list-inline item-details">
                            <li>Client:
                                <strong><a href="http://startbootstrap.com">Start Bootstrap</a>
                                </strong>
                            </li>
                            <li>Date:
                                <strong><a href="http://startbootstrap.com">April 2014</a>
                                </strong>
                            </li>
                            <li>Service:
                                <strong><a href="http://startbootstrap.com">Web Development</a>
                                </strong>
                            </li>
                        </ul>
                        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="portfolio-modal modal fade" id="portfolioModal4" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-content">
        <div class="close-modal" data-dismiss="modal">
            <div class="lr">
                <div class="rl">
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-lg-offset-2">
                    <div class="modal-body">
                        <h2>スカート（仮４）</h2>
                        <hr class="star-primary">
                        <img src="img/portfolio/game.png" class="img-responsive img-centered" alt="">
                        <p>★★★</p>
                        <ul class="list-inline item-details">
                            <li>Client:
                                <strong><a href="http://startbootstrap.com">Start Bootstrap</a>
                                </strong>
                            </li>
                            <li>Date:
                                <strong><a href="http://startbootstrap.com">April 2014</a>
                                </strong>
                            </li>
                            <li>Service:
                                <strong><a href="http://startbootstrap.com">Web Development</a>
                                </strong>
                            </li>
                        </ul>
                        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- jQuery -->
<script src="vendor/jquery/jquery.min.js"></script>

<!-- Bootstrap Core JavaScript -->
<script src="vendor/bootstrap/js/bootstrap.min.js"></script>

<!-- Plugin JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js"></script>

<!-- Contact Form JavaScript -->
<script src="js/jqBootstrapValidation.js"></script>
<script src="js/contact_me.js"></script>

<!-- Theme JavaScript -->
<script src="js/freelancer.min.js"></script>

</body>

</html>
