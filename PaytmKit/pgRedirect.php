<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config_paytm.php';
require_once __DIR__ . '/lib/encdec_paytm.php';

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    return $scheme . '://' . $host;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$posted = $_POST;
if (empty($posted['ORDER_ID'])) {
    http_response_code(400);
    echo 'Missing ORDER_ID';
    exit;
}

$paramList = $posted;

$paramList['STATUS'] = $paramList['STATUS'] ?? 'TXN_SUCCESS';
$paramList['RESPCODE'] = $paramList['RESPCODE'] ?? '01';
$paramList['RESPMSG'] = $paramList['RESPMSG'] ?? 'Mock payment success';
$paramList['TXNID'] = $paramList['TXNID'] ?? ('MOCKTXN' . time());
$paramList['BANKTXNID'] = $paramList['BANKTXNID'] ?? ('MOCKBANK' . time());
$paramList['TXNDATETIME'] = $paramList['TXNDATETIME'] ?? date('Y-m-d H:i:s');

unset($paramList['CHECKSUMHASH']);
$checkSum = getChecksumFromArray($paramList, PAYTM_MERCHANT_KEY);
$paramList['CHECKSUMHASH'] = $checkSum;

$action = base_url() . '/PaytmKit/pgResponse.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Mock Paytm Redirect</title>
</head>
<body>
  <form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES); ?>" name="paytm_mock">
    <?php foreach ($paramList as $k => $v): ?>
      <input type="hidden" name="<?php echo htmlspecialchars((string)$k, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)$v, ENT_QUOTES); ?>">
    <?php endforeach; ?>
  </form>
  <script>document.paytm_mock.submit();</script>
</body>
</html>
