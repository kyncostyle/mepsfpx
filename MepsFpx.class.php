<?php

/**
 * Wrapper class for integrating MEPS FPX payment method to your application.
 *
 * @author Leow Kah Thong <http://kahthong.com>
 * @copyright Leow Kah Thong 2012
 * @version 1.0
 */
class MepsFpx {

    const PAYMENT_MODE_DEV  = 'dev';
    const PAYMENT_MODE_PROD = 'prod';

    private $mepsFpxPaymentUrlDev  = 'https://uat.mepsfpx.com.my/FPXMain/sellerB2CMesgRecv_v2.jsp';
    private $mepsFpxpaymentUrlProd = 'https://www.mepsfpx.com.my/FPXMain/sellerB2CMesgRecv_v2.jsp';
    private $paymentMode           = '';
    private $sellerId              = '';
    private $address               = '';
    private $port                  = '';

    /**
     * @access public
     * @param string $sellerId Seller ID.
     * @param string $address Address to the FPX service.
     * @param string $port Port of the FPX service.
     * @param string $epaymentMode (Optional )Payment mode to use. Defaults to production server.
     */
    public function __construct($sellerId, $address, $port, $epaymentMode = self::PAYMENT_MODE_PROD) {
        $this->setSellerId($sellerId);
        $this->setAddress($address);
        $this->setPort($port);
        $this->setPaymentMode($epaymentMode);
    }

    /**
     * Get seller ID.
     *
     * @access public
     * @return string Seller ID.
     */
    public function getSellerId() {
        return $this->sellerId;
    }

    /**
     * Get MEPS FPX service address.
     *
     * @access public
     * @return string MEPS FPX service address.
     */
    public function getAddress() {
        return $this->address;
    }

    /**
     * Get MEPS FPX service port
     *
     * @access public
     * @return string MEPS FPX service address.
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * Get payment mode.
     *
     * @access public
     * @return string Payment mode.
     */
    public function getPaymentMode() {
        return $this->paymentMode;
    }

    /**
     * Get payment URL.
     *
     * @access public
     * @return string Payment URL.
     */
    public function getPaymentUrl() {
        $paymentMode = $this->getPaymentMode();
        $url = '';

        if ($paymentMode == self::PAYMENT_MODE_DEV) {
            $url = $this->mepsFpxPaymentUrlDev;
        } else {
            $url = $this->mepsFpxpaymentUrlProd;
        }

        return $url;
    }

    /**
     * Set seller ID.
     *
     * @access public
     * @param string $sellerId Seller ID.
     */
    public function setSellerId($sellerId) {
        $this->sellerId = $sellerId;
    }

    /**
     * Set MEPS FPX service address.
     *
     * @access public
     * @param string $address MEPS FPX service address.
     */
    public function setAddress($address) {
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            $this->address = $address;
        } else {
            trigger_error('Service IP address is not valid.');
        }
    }

    /**
     * Set MEPS FPX service port.
     *
     * @access public
     * @param string $address MEPS FPX service port.
     */
    public function setPort($port) {
        $this->port = (int) $port;
    }

    /**
     * Set the payment mode to use.
     *
     * @access public
     * @param string $epaymentMode Use constant PAYMENT_MODE_DEV for development or PAYMENT_MODE_PROD for production use.
     */
    public function setPaymentMode($paymentMode) {
        if ($paymentMode == self::PAYMENT_MODE_DEV || $paymentMode == self::PAYMENT_MODE_PROD) {
            $this->paymentMode = $paymentMode;
        } else {
            trigger_error('Payment mode must be either PAYMENT_MODE_DEV or PAYMENT_MODE_PROD constant.');
        }
    }

    /**
     * Create and connect socket to use MEPS FPX service.
     *
     * @access private
     * @return resource Socket resource.
     */
    private function initializeSocket() {
        $address = $this->getAddress();
        $port = $this->getPort();

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket < 0) {
            trigger_error('socket_create() failed: ' . socket_strerror(socket_last_error()));
        }

        $result = socket_connect($socket, $address, $port);
        if (!$result) {
            trigger_error('socket_create() failed: ' . socket_strerror(socket_last_error()));
            return false;
        }

        return $socket;
    }

    /**
     * 'Message' generally refers to the digest message to be passed to MEPS FPX to be processed.
     * You want to make a payment by sending this message to their server.
     *
     * @access public
     * @param array $data The data to create the message. Accepted values are;
     *   - orderNo
     *   - time (Optional)
     *   - amount
     * @param boolean $htmlentities (Optional) Set to true to convert all applicable characters in message to HTML entities.
     * @return string Generated message by FPX service.
     */
    public function generateMessage($data = array(), $htmlentities = false) {
        if (!$data) {
            return false;
        }

        $msg = '';

        $socket = $this->initializeSocket();
        if ($socket) {
            // Generate string to send to plugin.
            $orderNo = $sellerOrderNo = $data['orderNo'];
            $time = (isset($data['time']) && $data['time']) ? $data['time'] : time();
            $time = date('YmdHis', $time);
            $amount = $data['amount'];
            $sellerId = $this->getSellerId();

            $in = "message:request|message.type:AR|message.token:01|message.orderno:$orderNo|message.ordercount:1|message.txntime:$time|message.serialno:1|message.currency:MYR|message.amount:$amount|charge.type:AA|seller.orderno:$sellerOrderNo|seller.id:$sellerId|seller.bank:01|\n";
            $out = '';

            socket_write($socket, $in);

            $fpxValue = '';
            while ($out = socket_read($socket, 6001)) {
                $fpxValue = $out;
            }

            $msg = str_replace("\n", '', $fpxValue);

            socket_close($socket);
        }

        if ($htmlentities) {
            $msg = htmlentities($msg);
        }

        return $msg;
    }

    /**
     * Get the response returned by MEPS FPX (from $_POST superglobal).
     *
     * @access public
     * @return array Response returned by MEPS FPX server.
     */
    public function getResponse() {
        if (!isset($_POST['mesgFromFpx']) && !$_POST['mesgFromFpx']) {
            return false;
        }

        $msg = array();
        $in = "message:response|response.string:" . $_POST['mesgFromFpx'];
        $out = '';
        $fpxValue = '';

        $socket = $this->initializeSocket();

        socket_write($socket, $in . "\n");

        while ($out = socket_read($socket, 200)) {
            $fpxValue .= $out;
        }

        socket_close($socket);

        foreach (explode('|', $fpxValue) as $val) {
            list($key, $val) = explode(':', $val);
            $msg[$key] = $val;
        }

        return $msg;
    }

}