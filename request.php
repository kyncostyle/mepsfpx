<?php
require_once './MepsFpx.class.php';
$mepsFpx = new MepsFpx('SELLER_ID', '127.0.0.1', PORT_NUMBER, MepsFpx::PAYMENT_MODE_DEV);
$message = $mepsFpx->generateMessage(array(
    'orderNo' => 'ABC0000000001',
    'amount' => '1.00',
), true);
?>
<!doctype html>
<html>
<head>
    <title>MEPS FPX test - Request</title>
</head>
<body>
    <form action="<?php echo $mepsFpx->getPaymentUrl(); ?>" method="post">
        <input type="hidden" name="MsgToFpx" value="<?php echo $message; ?>" />
        <input type="hidden" name="ItemName" value="Some description (max 30 chars)" />
        <input type="submit" value="Pay with MEPS FPX" />
    </form>
</body>
</html>