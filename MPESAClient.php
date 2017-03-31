<?php
/**
 * Class MPESA - Carry out payment processes.
 * e.g. BUYING GOODS
 *--------------------------------------------
 * $mpesaClient = new MPESAClient;
 * $mpesaClient->processCheckoutRequest($data);
 * if($mpesaClient->_response) {
 *      $results = $mpesaClient->getResults();
 *      if($results['code'] === 00 && $results['status'] === 'success')
 *      {
            // Authentication succeeded. Display the $customerMessage on the page as the customer awaits an
 *          // USSD push validation by the system to their device.
 *      } else {
 *          // Notify user that there was a problem
 *      }
 * } else {
 *      // Notify customer that there was a connection error. Check network connection!
 *      // You could do something with the error curl_error($mpesaClient->_cURLHandle)
 * }
 * @package App\Lib\Web\Services
 */
class MPESAClient
{
    /**
     * @var - Safaricom MPESA online checkout endpoint URL.
     */
    private $_SAF_END_POINT_URL = 'https://safaricom.co.ke/mpesa_online/lnmo_checkout_server.php?wsdl';
    /**
     * Callback method options: HTTP POST, HTTP GET, XML Result Message
     * @var array - cURL options array.
     */
    private $_MERCHANT_ID = 'xxxxx';
    private $_MERCHANT_TRANSACTION_ID;
    private $_passkey = 'xxxxx';
    private $_REFERENCE_ID = 'xxxxx';
    private $_ENC_PARAMS;
    private $_TIMESTAMP;
    private $_PASSWORD;
    private $_XML;
    private $_AMOUNT;
    private $_MSISDN;
    private $_TRX_ID;
    private $_PaymentCallbackURL = 'http://smartshop.com/payments/mpesa/receive';
    private $_CallbackMethod = 'xml';
    private $_headers;
    private $_particulars;
    // ID 0 for systems
    private $_seller_id = 0;
    private $_buyer_id;
    /**
     * The current results of the operation.
     * @var array - RESULTS = ['code'=> xxx, 'status' => 'xxx']
     */
    private $_RESULTS = array();
    private $_response;
    private $_cURLHandle;

    function __construct()
    {
        $this->_TIMESTAMP = FormHelper::getTimestamp();
        $this->_PASSWORD = strtoupper(base64_encode(hash("sha256", $this->_MERCHANT_ID .
            $this->_passkey . $this->_TIMESTAMP)));
        $this->_MERCHANT_TRANSACTION_ID = PaymentsHelper::generateMerchantTXNID();

    }

    /**
     * This function processes payments; setting up necessary parameters.
     * @param $details - the details of the purchase.
     * $details = [ 'enc_param'    => 'xxxxxx',
     *              'buyer_id'     => 'xxxxxx',
     *              'msisdn'       => '2547xxxxxxxx'
     *              'seller_id'    =>  'xxxxxx',
     *              'gross'        =>  'xxxxxx',
     *              'particulars'  =>  'xxxxxx']
     */
    public function processCheckoutRequest($details)
    {
        $this->_MSISDN = $details['msisdn'];
        $this->_AMOUNT = $details['gross'];
        $this->_ENC_PARAMS = $details['enc_param'];
        $this->_buyer_id = $details['buyer_id'];

        if(!empty($details['seller_id'])) {
            $this->_seller_id = $details['seller_id'];
        }
        $this->_particulars = $details['particulars'];

        $this->_XML = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="tns:ns">
<soapenv:Header>
	<tns:CheckOutHeader>
		<MERCHANT_ID>$this->_MERCHANT_ID</MERCHANT_ID>
		<PASSWORD>$this->_PASSWORD</PASSWORD>
		<TIMESTAMP>$this->_TIMESTAMP</TIMESTAMP>
	</tns:CheckOutHeader>
</soapenv:Header>
<soapenv:Body>
	<tns:processCheckOutRequest>
		<MERCHANT_TRANSACTION_ID>$this->_MERCHANT_TRANSACTION_ID</MERCHANT_TRANSACTION_ID>
		<REFERENCE_ID>$this->_REFERENCE_ID</REFERENCE_ID>
		<AMOUNT>$this->_AMOUNT</AMOUNT>
		<MSISDN>$this->_MSISDN</MSISDN>
		<ENC_PARAMS>$this->_ENC_PARAMS</ENC_PARAMS>
		<CALL_BACK_URL>$this->_PaymentCallbackURL</CALL_BACK_URL>
		<CALL_BACK_METHOD>$this->_CallbackMethod</CALL_BACK_METHOD>
		<TIMESTAMP>$this->_TIMESTAMP</TIMESTAMP>
	</tns:processCheckOutRequest>
</soapenv:Body>
</soapenv:Envelope>
XML;
        self::execute();
        self::parseCheckoutResponse();
    }

    /**
     * Function sets the xml request for a transaction confirmation request. It may not be used unless there
     * is a delay in the processing of the transaction. A link could be availed on the customer check out page
     * so that the customer can prompt the system.
     */
    public function confirmTransactionRequest()
    {
        $this->_XML = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="tns:ns">
   <soapenv:Header>
      <tns:CheckOutHeader>
         <MERCHANT_ID>$this->_MERCHANT_ID</MERCHANT_ID>
	     <PASSWORD>$this->_PASSWORD</PASSWORD>
	     <TIMESTAMP>$this->_TIMESTAMP</TIMESTAMP>
      </tns:CheckOutHeader>
   </soapenv:Header>
   <soapenv:Body>
      <tns:transactionConfirmRequest>
         <TRX_ID>$this->_TRX_ID</TRX_ID>
         <MERCHANT_TRANSACTION_ID>$this->_MERCHANT_TRANSACTION_ID</MERCHANT_TRANSACTION_ID>
      </tns:transactionConfirmRequest>
   </soapenv:Body>
</soapenv:Envelope>
XML;
        self::execute();
        self::parseConfirmTransactionResponse();
    }

    /**
     * A helper function that can be used to check the status of an initialised/online MPESA transaction. Called
     * to ascertain the status of the transaction before the customer is issued with products.
     */
    public function checkStatus()
    {
        $this->_XML = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="tns:ns">
    <soapenv:Header>
        <tns:CheckOutHeader>
            <MERCHANT_ID>$this->_MERCHANT_ID</MERCHANT_ID>
            <PASSWORD>$this->_PASSWORD</PASSWORD>
            <TIMESTAMP>$this->_TIMESTAMP</TIMESTAMP>
        </tns:CheckOutHeader>
    </soapenv:Header>
    <soapenv:Body>
        <tns:transactionStatusRequest>
            <TRX_ID>$this->_TRX_ID</TRX_ID>
            <MERCHANT_TRANSACTION_ID>$this->_MERCHANT_TRANSACTION_ID</MERCHANT_TRANSACTION_ID>
        </tns:transactionStatusRequest>
    </soapenv:Body>
</soapenv:Envelope>
XML;
        self::execute();
        self::parseStatusResponse();
    }

    /**
     * Member's only function that sends xml requests and receives xml responses.
     */
    protected function execute()
    {
        $this->_cURLHandle = curl_init();
        curl_setopt($this->_cURLHandle, CURLOPT_URL,            $this->_SAF_END_POINT_URL);
        curl_setopt($this->_cURLHandle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($this->_cURLHandle, CURLOPT_TIMEOUT,        10);
        curl_setopt($this->_cURLHandle, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($this->_cURLHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->_cURLHandle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->_cURLHandle, CURLOPT_POST,           true );
        curl_setopt($this->_cURLHandle, CURLOPT_POSTFIELDS,     $this->_XML);
        curl_setopt($this->_cURLHandle, CURLOPT_HTTPHEADER,     $this->_headers);

        if(!curl_exec($this->_cURLHandle))
        {
            $this->_response = FALSE;
        } else {
            $this->_response = curl_exec($this->_cURLHandle);
        }

        // Release resource
        curl_close($this->_cURLHandle);
    }

    /**
     * Function processes the response from SAG (Merchant validation) and returns Custom message to be displayed to
     * the customer while awaiting payment processing. Only called after processCheckoutRequest.
     *
     * Sample response
     * ------------------------
     *<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="tns:ns">
     *  <SOAP-ENV:Body>
     *      <ns1:processCheckOutResponse>
     *          <RETURN_CODE>00</RETURN_CODE>
     *          <DESCRIPTION>Success</DESCRIPTION>
     *          <TRX_ID>cce3d32e0159c1e62a9ec45b67676200</TRX_ID>
     *          <ENC_PARAMS>e4t4ff4778hh4sa7u7j</ENC_PARAMS>
     *          <CUST_MSG>To complete this transaction, enter your Bonga PIN on your handset. if you don't have one dial *126*5# for instructions</CUST_MSG>
     *      </ns1:processCheckOutResponse>
     *  </SOAP-ENV:Body>
     *</SOAP-ENV:Envelope>
     *
     * NOTE:: We need to make a record of the payments with TRX_ID and ENC_PARAMS fields and other payments
     * details (See above) with STATUS = FALSE and stored in a SESSION or database awaiting confirmation.
     * A callback from MPESA would set the status and complete the transaction via the finalise() method.
     * @return string - message from SAG that will be displayed to the Customer online.
     */
    protected function parseCheckoutResponse()
    {
        // Parse the xml document
        preg_match("/<RETURN_CODE>(.*)<\/RETURN_CODE>/", $this->_response, $returnCode);
        preg_match("/<DESCRIPTION>(.*)<\/DESCRIPTION>/", $this->_response, $desc);
        preg_match("/<TRX_ID>(.*)<\/TRX_ID>/", $this->_response, $trxId);
        preg_match("/<ENC_PARAMS>(.*)<\/ENC_PARAMS>/", $this->_response, $encParam);
        preg_match("/<CUST_MSG>(.*)<\/CUST_MSG>/", $this->_response, $custMSG);

        $this->_TRX_ID = $trxId[0];
        $this->_RESULTS['status'] = $desc[0];
        $this->_RESULTS['code'] = $returnCode[0];
        // Set message in the results
        $this->_RESULTS['message'] = $custMSG[0];

        // Save the transaction in purchases records
        if($this->_RESULTS['status'] === 'Success') {
            $purchaseTxn = new Purchase();
            $purchaseTxn->buyer_id = $this->_buyer_id;
            $purchaseTxn->seller_id = $this->_seller_id;
            $purchaseTxn->msisdn = $this->_MSISDN;
            $purchaseTxn->enc_param = $this->_ENC_PARAMS;
            $purchaseTxn->mpesa_txn_id = $this->_TRX_ID;
            $purchaseTxn->particulars = $this->_particulars;
            $purchaseTxn->gross = $this->_AMOUNT;
            $purchaseTxn->status = FALSE;
            $purchaseTxn->save();
        }
    }

    /**
     *<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="tns:ns">
     *  <SOAP-ENV:Body>
     *      <ns1:transactionConfirmResponse>
     *          <RETURN_CODE>00</RETURN_CODE>
     *          <DESCRIPTION>Success</DESCRIPTION>
     *          <MERCHANT_TRANSACTION_ID/>
     *          <TRX_ID>5f6af12be0800c4ffabb4cf2608f0808</TRX_ID>
     *      </ns1:transactionConfirmResponse>
     *  </SOAP-ENV:Body>
     *</SOAP-ENV:Envelope>
     */
    protected function parseConfirmTransactionResponse()
    {
        preg_match("/<RETURN_CODE>(.*)<\/RETURN_CODE>/", $this->_response, $returnCode);
        preg_match("/<DESCRIPTION>(.*)<\/DESCRIPTION>/", $this->_response, $desc);
        preg_match("/<MERCHANT_TRANSACTION_ID>(.*)<\/MERCHANT_TRANSACTION_ID>/", $this->_response, $merchantId);
        preg_match("/<TRX_ID>(.*)<\/TRX_ID>/", $this->_response, $transactionID);

        // Set parameters
        $this->_RESULTS['code'] = $returnCode[0];
        $this->_RESULTS['status'] = $desc[0];
        $this->_MERCHANT_TRANSACTION_ID = $merchantId[0];
        $this->_TRX_ID = $transactionID[0];
    }

    /**
     * Function is called each time a request is made to set the $_response and $_RESULT fields of the
     * MPESA object/instance except after processCheckoutRequest.
     *------------------
     * Sample Response
     * -----------------
     * <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="tns:ns">
     *  <SOAP-ENV:Body>
     *      <ns1:transactionStatusResponse>
     *          <MSISDN>254720471865</MSISDN>
     *          <AMOUNT>54000</AMOUNT>
     *          <M-PESA_TRX_DATE>2014-12-01 16:59:07</M-PESA_TRX_DATE>
     *          <M-PESA_TRX_ID>N/A</M-PESA_TRX_ID>
     *          <TRX_STATUS>Failed</TRX_STATUS>
     *          <RETURN_CODE>01</RETURN_CODE>
     *          <DESCRIPTION>InsufficientFunds</DESCRIPTION>
     *          <MERCHANT_TRANSACTION_ID/>
     *          <ENC_PARAMS/>
     *          <TRX_ID>ddd396509b168297141a747cd2dc1748</TRX_ID>
     *      </ns1:transactionStatusResponse>
     *  </SOAP-ENV:Body>
     *</SOAP-ENV:Envelope>
     */
    protected function parseStatusResponse()
    {
        preg_match("/<RETURN_CODE>(.*)<\/RETURN_CODE>/", $this->_response, $msisdn);
        preg_match("/<DESCRIPTION>(.*)<\/DESCRIPTION>/", $this->_response, $amount);
        preg_match("/<TRX_ID>(.*)<\/TRX_ID>/", $this->_response, $MPESATransactionDate);
        preg_match("/<ENC_PARAMS>(.*)<\/ENC_PARAMS>/", $this->_response, $MPESATransactionID);
        preg_match("/<CUST_MSG>(.*)<\/CUST_MSG>/", $this->_response, $transactionStatus);
        preg_match("/<RETURN_CODE>(.*)<\/RETURN_CODE>/", $this->_response, $returnCode);
        preg_match("/<DESCRIPTION>(.*)<\/DESCRIPTION>/", $this->_response, $desc);
        preg_match("/<TRX_ID>(.*)<\/TRX_ID>/", $this->_response, $merchantTransactionID);
        preg_match("/<ENC_PARAMS>(.*)<\/ENC_PARAMS>/", $this->_response, $encParam);
        preg_match("/<CUST_MSG>(.*)<\/CUST_MSG>/", $this->_response, $transactionID);

        if($msisdn[0] = $this->_MSISDN && $amount[0] === $this->_AMOUNT && $encParam[0] = $this->_ENC_PARAMS) {
            $this->_RESULTS['status'] = $desc[0];
            $this->_RESULTS['code'] = $returnCode[0];
        }
    }

    /**
     * Internal method that creates the header options to passed to cURL handle.
     */
    protected function createHeader()
    {
        $this->_headers = [
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: \"run\"",
            "Content-length: ".strlen($this->_XML),
        ];
    }

    /**
     * This function is called after parseResponse() and parseCheckoutResponse() methods have been called
     *  in order to check the response.
     * @return array - the current $_RESULT.
     */
    public function getResults()
    {
        return $this->_RESULTS;
    }

    /**
     * Globally accessible function used to finalise the payment process once the transaction has been completed.
     * Here, the payment validation is performed and the records in the database set appropriately. Echos 'success'
     * to acknowledge completion of the whole process.
     * @param $finalResponse - the XML message received from MPESA send to the callback url set initially when the 
     * transaction was initiated.
     * -----------------------------------------
     * Sample response message from the system
     *-----------------------------------------
     *<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="tns:ns">
     *  <SOAP-ENV:Body>
     *      <ns1:ResultMsg>
     *          <MSISDN ns1:type="xsd:string">254720471865</MSISDN>
     *          <AMOUNT ns1:type="xsd:string">54.0</AMOUNT>
     *          <M-PESA_TRX_DATE ns1:type="xsd:string">2014-12-01 16:24:06</M-PESA_TRX_DATE>
     *          <M-PESA_TRX_ID ns1:type="xsd:string">null</M-PESA_TRX_ID>
     *          <TRX_STATUS ns1:type="xsd:string">Success</TRX_STATUS>
     *          <RETURN_CODE ns1:type="xsd:string">00</RETURN_CODE>
     *          <DESCRIPTION ns1:type="xsd:string">Success</DESCRIPTION>
     *          <MERCHANT_TRANSACTION_ID ns1:type="xsd:string">911-000</MERCHANT_TRANSACTION_ID>
     *          <ENC_PARAMS ns1:type="xsd:string"></ENC_PARAMS>
     *          <TRX_ID ns1:type="xsd:string">cce3d32e0159c1e62a9ec45b67676200</TRX_ID>
     *      </ns1:ResultMsg>
     *  </SOAP-ENV:Body>
     *</SOAP-ENV:Envelope>
     */
    public static function finalise($finalResponse)
    {
        preg_match("/<MSISDN>(.*)<\/MSISDN>/", $finalResponse, $msisdn);
        preg_match("/<AMOUNT>(.*)<\/AMOUNT>/", $finalResponse, $amount);
        preg_match("/<M-PESA_TRX_DATE>(.*)<\/M-PESA_TRX_DATE>/", $finalResponse, $MPESATransactionDate);
        preg_match("/<M-PESA_TRX_ID>(.*)<\/M-PESA_TRX_ID>/", $finalResponse, $MPESATransactionID);
        preg_match("/<TRX_STATUS>(.*)<\/TRX_STATUS>/", $finalResponse, $transactionStatus);
        preg_match("/<RETURN_CODE>(.*)<\/RETURN_CODE>/", $finalResponse, $returnCode);
        preg_match("/<DESCRIPTION>(.*)<\/DESCRIPTION>/", $finalResponse, $description);
        preg_match("/<MERCHANT_TRANSACTION_ID>(.*)<\/MERCHANT_TRANSACTION_ID>/", $finalResponse, $merchantTxnID);
        preg_match("/<ENC_PARAMS>(.*)<\/ENC_PARAMS>/", $finalResponse, $encParams);
        preg_match("/<TRX_ID>(.*)<\/TRX_ID>/", $finalResponse, $transactionID);

        // Check whether there is a transaction pending
        $purchaseTxn = Purchase::where('msisdn', $msisdn[0])
            ->where('gross', $amount[0])
            ->where('enc_params', $encParams[0])
            ->where('mpesa_txn_id', $MPESATransactionID[0])
            ->first();

        if(!empty($purchaseTxn) && $returnCode[0] === 00 && strtolower($transactionStatus[0]) === 'success') {
            $purchaseTxn->completed_on = $MPESATransactionDate[0];
            $purchaseTxn->status = TRUE;
            $purchaseTxn->save();
            // Acknowledge end of transaction
            echo 'OK';
        }

        if(!empty($purchaseTxn) && $returnCode[0] !== 00)
            $purchaseTxn->delete();
        // Acknowledge end of transaction
        echo 'OK';
    }
}
