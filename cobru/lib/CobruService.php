<?php

require 'HttpClientCustom.php';

class CobruService {

    private $httpClient;

    private $xApiKey;

    private $refreshToken;

    private $isProduction;

    public $cobru;

    private $auth;

    public const DEVELOPMENT_URL_CHECKOUT = 'https://dev.cobru.co';

    public const PRODUCTION_URL_CHECKOUT = 'https://cobru.me';

    public const PRODUCTION_IP = '35.183.151.137';

    public const DEVELOMENT_IP = '52.60.68.103';

    public $linkId;

    public function __construct($xApiKey, $refreshToken, $isProduction)
   {
     $this->httpClient = new HttpClientCustom($this->resolveUrlApi($isProduction));
     $this->xApiKey = $xApiKey;
     $this->refreshToken = $refreshToken;
     $this->isProduction = $isProduction;

   }

   private function resolveUrlApi($isProduction){
      return !$isProduction ? self::DEVELOPMENT_URL_CHECKOUT : 'https://prod.cobru.co';
   }

   public function auth(){

       $this->auth = $this->httpClient->postJson('/token/refresh/',['refresh' => $this->refreshToken] ,['x-api-key' => $this->xApiKey ]);

       if (!$this->auth['success']) {
          $this->exception();
       }

       return $this;

   }

   private function exception(){
       throw new Exception("Error al comunicarse con cobru");
   }

   public function createLinkPayment(
       $id,
       $amount,
       $images,
       $reference,
       $description,
       $experiationDays,
       $paymentMethodEnabled,
       $urlNotify,
       $urlPayerRedirect
   ) {

      $accessToken = $this->getAccesToken();

      $data = array_merge([
          'amount' => $amount,
          'description' => $description,
          'expiration_days' => $experiationDays,
          'payment_method_enabled' => json_encode($paymentMethodEnabled),
          'platform' => "API",
          'callback' => trim($urlNotify),
          'images' => $images,
          'payer_redirect_url' => trim($urlPayerRedirect)
      ], $this->execNotifyCallbackCobru());

       $this->cobru = $this->httpClient->postJson('/cobru/', $data , [
           'Authorization' => 'Bearer '.$accessToken,
           'x-api-key' => $this->xApiKey
       ]);

       if (!$this->cobru['success']) {
           $this->exception();
       }

      return $this;

   }

    public function findPaymentLink($url) {

        $accessToken = $this->getAccesToken();

        $this->cobru = $this->httpClient->getJson('/cobru_detail/'. $url , [], [
            'Authorization' => 'Bearer '.$accessToken,
            'x-api-key' => $this->xApiKey
        ]);

    }

    public function getAccesToken() {
        return $this->auth['result']['access'];
    }

   public function getLink() {
       $this->linkId = $this->cobru['result']['url'];

       if(!$this->linkId) {
           return $this->linkId;
       }

       return $this->resolveCheckoutUrl() . '/'. $this->linkId;
   }

    private function resolveCheckoutUrl(){
        return !$this->isProduction ? self::DEVELOPMENT_URL_CHECKOUT : self::PRODUCTION_URL_CHECKOUT;
    }

    private function execNotifyCallbackCobru() {
       return !$this->isProduction ? ['payer_id' => 7777777] : [];
    }
}