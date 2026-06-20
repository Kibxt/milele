<?php
// MILELE - Safaricom Daraja API Gateway (Master Engine V2 - B2C Enabled)

class MpesaGateway {
    private $consumer_key = 'LA33OtNfdXyPyrTozI5KGULDecju2sAyNYMGdp85mTuRX9UA';
    private $consumer_secret = 'MjthfBtuHJS2ezFAdMuGW87qaJd5MLn2fDRLiSnc2EVY4czOuJA1aZD3oyKmiGno';
    private $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    private $shortcode = '174379'; 
    
    // B2C Specific Credentials (Standard Safaricom Sandbox Initiator)
    private $initiator_name = 'testapi';
    private $security_credential = 'Safaricom_sandbox_encrypted_password_placeholder'; // In production, this requires an RSA encrypted cert. For sandbox, standard Daraja mocks it.
    
    private $env = 'sandbox'; 
    private $callback_base_url = 'https://milele-campus-live-56fbf7c046b3.herokuapp.com';

    private function getBaseUrl() {
        return $this->env === 'sandbox' ? 'https://sandbox.safaricom.co.ke' : 'https://api.safaricom.co.ke';
    }

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

    // Money IN (STK Push)
    public function stkPush($phone, $amount, $reference, $description) {
        $token = $this->getAccessToken();
        if (!$token) return ["error" => "Failed to get access token."];

        $url = $this->getBaseUrl() . '/mpesa/stkpush/v1/processrequest';
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $phone = preg_replace('/^0/', '254', $phone);
        $phone = preg_replace('/^\+/', '', $phone);

        $body = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => ceil($amount),
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callback_base_url . '/backend/api/stk_callback.php',
            'AccountReference' => substr($reference, 0, 12),
            'TransactionDesc' => substr($description, 0, 13)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    // NEW: Money OUT (B2C Payout to Seller)
    public function b2cPayment($seller_phone, $amount, $transaction_id) {
        $token = $this->getAccessToken();
        if (!$token) return ["error" => "Failed to get access token."];

        $url = $this->getBaseUrl() . '/mpesa/b2c/v1/paymentrequest';
        
        $seller_phone = preg_replace('/^0/', '254', $seller_phone);
        $seller_phone = preg_replace('/^\+/', '', $seller_phone);

        $body = [
            'InitiatorName' => $this->initiator_name,
            'SecurityCredential' => $this->security_credential,
            'CommandID' => 'BusinessPayment',
            'Amount' => ceil($amount),
            'PartyA' => $this->shortcode, // The Vault sending the money
            'PartyB' => $seller_phone,    // The Seller receiving the money
            'Remarks' => 'MILELE Escrow Payout',
            'QueueTimeOutURL' => $this->callback_base_url . '/backend/api/b2c_callback.php',
            'ResultURL' => $this->callback_base_url . '/backend/api/b2c_callback.php',
            'Occasion' => 'Payout TX: ' . $transaction_id
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
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