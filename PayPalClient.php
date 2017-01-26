<?php
/**
 * This function is used to perform PayPal money transactions.
 * Class PayPalIPN
 * @package App\Core\Payments
 */
namespace App\Lib\Web\Services;

class PayPalClient
{
    /**
     * @var string - PayPal URL
     */
    private $_url = 'https://www.paypal.com/cgi-bin/webscr';
    /**
     * PayPalIPN constructor.
     * @param string $mode - the mode in which to run
     */
    public function __construct($mode = "live")
    {
        if($mode != 'live') {
            $this->_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        }
    }

    public function execute()
    {
        $postFields = 'cmd=_notify-validate';

        foreach ($_POST as $key => $value) {
            $postFields .= "&$key=" . urlencode($value);
        }

        // Alternatively
        $rawPostData = file_get_contents('php://input');
        $postArray = explode('&', $rawPostData);
        $returnData = array();
        foreach ($postArray as $keyVal) {
            $keyVal = explode('=', $keyVal);
            if(count($keyVal) == 2) {
                $returnData[$keyVal[0]] = urlencode($keyVal[1]);
            }
        }

        $req = 'cmd=_notify-validate';
        if(function_exists('get_magic_quotes_gpc')) {
            $magicQuotesExist = true;
        }

        foreach ($returnData as $key => $value) {
            if($magicQuotesExist && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }

            $req .= "&$key=$value";
        } // End of alternative method

        $curlHandle = curl_init();

        curl_setopt_array($curlHandle, [
            CURLOPT_URL => $this->_url,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => $postFields
        ]);

        $response = curl_exec($curlHandle);
        curl_close($curlHandle);

        echo $response;
    }
}