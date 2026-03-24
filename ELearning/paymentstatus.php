<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

header("Pragma: no-cache");
header("Cache-Control: no-cache");
header("Expires: 0");

require_once __DIR__ . '/dbConnection.php';

// following files need to be included
require_once __DIR__ . "/PaytmKit/lib/config_paytm.php";
require_once __DIR__ . "/PaytmKit/lib/encdec_paytm.php";

$ORDER_ID = "";
$requestParamList = array();
$responseParamList = array();

if (isset($_POST["ORDER_ID"]) && $_POST["ORDER_ID"] !== "") {
  $ORDER_ID = trim((string)$_POST["ORDER_ID"]);

  $requestParamList = array(
    "MID" => PAYTM_MERCHANT_MID,
    "ORDERID" => $ORDER_ID
  );

  $StatusCheckSum = getChecksumFromArray($requestParamList, PAYTM_MERCHANT_KEY);
  $requestParamList['CHECKSUMHASH'] = $StatusCheckSum;

  $responseParamList = getTxnStatusNew($requestParamList);
}

// Header Include from mainInclude (prints HTML) — keep it AFTER all header() calls
require_once __DIR__ . '/mainInclude/header.php';
?>  
<div class="container-fluid bg-dark">
  <div class="row">
    </div> 
</div>

<div class="container">
  <h2 class="text-center my-4">Payment Status</h2>
  <form method="post" action="">
    <div class="form-group row">
      <label class="offset-sm-3 col-form-label">Order ID: </label>
      <div>
        <input
          class="form-control mx-3"
          id="ORDER_ID"
          tabindex="1"
          maxlength="20"
          size="20"
          name="ORDER_ID"
          autocomplete="off"
          value="<?php echo htmlspecialchars($ORDER_ID, ENT_QUOTES); ?>"
        >
      </div>
      <div>
        <input class="btn btn-primary mx-4" value="View" type="submit">
      </div>
    </div>
  </form>
</div>

<div class="container">
<?php
if (isset($responseParamList) && count($responseParamList) > 0) {
  $sql = "SELECT order_id FROM courseorder";
  $result = $conn->query($sql);

  if ($result) {
    while ($row = $result->fetch_assoc()) {
      if (
        isset($responseParamList["ORDERID"], $row["order_id"]) &&
        (string)$responseParamList["ORDERID"] === (string)$row["order_id"]
      ) {
?>
    <div class="row justify-content-center">
      <div class="col-auto">
        <h2 class="text-center">Payment Receipt</h2>
        <table class="table table-bordered">
          <tbody>
            <?php
            foreach ($responseParamList as $paramName => $paramValue) {
              if (
                $paramName === "TXNID" ||
                $paramName === "ORDERID" ||
                $paramName === "TXNAMOUNT" ||
                $paramName === "STATUS"
              ) {
            ?>
            <tr>
              <td><label><?php echo htmlspecialchars((string)$paramName, ENT_QUOTES); ?></label></td>
              <td><?php echo htmlspecialchars((string)$paramValue, ENT_QUOTES); ?></td>
            </tr>
            <?php
              }
            }
            ?>
            <tr>
              <td></td>
              <td><button class="btn btn-primary" onclick="javascript:window.print();">Print Receipt</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
<?php
      }
    }
  }
}
?>
</div>

<div class="mt-5">
<?php include('./contact.php'); ?> 
</div>

<?php include('./mainInclude/footer.php'); ?>
