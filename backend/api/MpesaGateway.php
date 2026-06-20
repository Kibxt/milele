<?php
// MILELE - Safaricom Daraja API Gateway (Master Engine)

class MpesaGateway {
    // Your Secure Sandbox Keys
    private $consumer_key = 'LA33OtNfdXyPyrTozI5KGULDecju2sAyNYMGdp85mTuRX9UA';
    private $consumer_secret = 'MjthfBtuHJS2ezFAdMuGW87qaJd5MLn2fDRLiSnc2EVY4czOuJA1aZD3oyKmiGno';
    private $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    private $shortcode = '174379'; // Safaricom Standard Test Shortcode
    
    // Environment Toggle ('sandbox' for testing, 'live' for real money later)
    private $env = 'sandbox'; 
    
    // Your Live Cloud Server URL (Where Safaricom sends the receipts)
    private $callback_base_url = 'https://milele-campus-live-56fbf7c046b3.herokuapp.com';

    private function getBaseUrl() {
        return $this->env === 'sandbox' ? 'https://sandbox.safaricom.co.ke' : 'https://api.safaricom.co.ke';
    }

    // 1. Generate the Temporary Access Token
    public function getAccessToken() {
        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response);
        return $result->access_token ?? null;
    }

    // 2. STK Push (Money IN - Prompt the Buyer's Phone)
    public function stkPush($phone, $amount, $reference, $description) {
        $token = $this->getAccessToken();
        if (!$token) return ["error" => "Failed to get access token."];

        $url = $this->getBaseUrl() . '/mpesa/stkpush/v1/processrequest';
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        // Smart Phone Number Formatter: Changes 07XX or +254 to standard 254 format
        $phone = preg_replace('/^0/', '254', $phone);
        $phone = preg_replace('/^\+/', '', $phone);

        $body = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => ceil($amount), // M-Pesa rejects decimals, round up
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callback_base_url . '/backend/api/stk_callback.php',
            'AccountReference' => substr($reference, 0, 12), // Max 12 chars allowed
            'TransactionDesc' => substr($description, 0, 13) // Max 13 chars allowed
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
?>