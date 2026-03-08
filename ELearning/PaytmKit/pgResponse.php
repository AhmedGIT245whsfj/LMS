<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php', true, 302);
    exit;
}

header('Pragma: no-cache');
header('Cache-Control: no-cache');
header('Expires: 0');

include('../dbConnection.php');
session_start();

require_once('./lib/config_paytm.php');
require_once('./lib/encdec_paytm.php');

$paytmChecksum = '';
$paramList = array();
$isValidChecksum = 'FALSE';

$paramList = $_POST;
$paytmChecksum = isset($_POST['CHECKSUMHASH']) ? $_POST['CHECKSUMHASH'] : '';

$isValidChecksum = verifychecksum_e($paramList, PAYTM_MERCHANT_KEY, $paytmChecksum);

if ($isValidChecksum == 'TRUE') {
    if (isset($_POST['STATUS']) && $_POST['STATUS'] == 'TXN_SUCCESS') {
        echo '<b>Transaction status is success</b><br/>';

        if (isset($_POST['ORDERID'], $_POST['TXNAMOUNT'], $_SESSION['stuLogEmail'], $_SESSION['course_id'])) {
            $order_id = $_POST['ORDERID'];
            $stu_email = $_SESSION['stuLogEmail'];
            $course_id = $_SESSION['course_id'];
            $status = $_POST['STATUS'];
            $respmsg = isset($_POST['RESPMSG']) ? $_POST['RESPMSG'] : '';
            $amount = $_POST['TXNAMOUNT'];
            $date = isset($_POST['TXNDATE']) ? $_POST['TXNDATE'] : date('Y-m-d H:i:s');

            $sql = "INSERT INTO courseorder(order_id, stu_email, course_id, status, respmsg, amount, order_date)
                    VALUES ('$order_id', '$stu_email', '$course_id', '$status', '$respmsg', '$amount', '$date')";

            if ($conn->query($sql) === TRUE) {
                echo 'Redirecting to My Profile....';
                echo "<script>
                    setTimeout(() => {
                        window.location.href = '../Student/myCourse.php';
                    }, 1500);
                </script>";
            }
        }
    } else {
        echo '<b>Transaction status is failure</b><br/>';
    }
} else {
    echo '<b>Checksum mismatched.</b>';
}
?>
