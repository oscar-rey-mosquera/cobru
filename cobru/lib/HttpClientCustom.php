<?php

require __DIR__. '/../vendor/autoload.php';

use GuzzleHttp\Client as Http;

class HttpClientCustom {

    private $client;

    public function __construct($baseUrl)
    {

       $this->client = new Http([
            // Base URI is used with relative requests
            'base_uri' => $baseUrl
        ]);

    }


    public function post($basePath, $data = [], $headers = []) {
       return $this->core($basePath, 'POST', $data, $headers);
    }


    public function get($basePath, $data = [], $headers = []) {
        return $this->core($basePath, 'GET', $data, $headers);
    }

    public function getJson($basePath, $data = [], $headers = []) {
        return $this->get($basePath, json_encode($data), array_merge(["Content-Type" =>  "application/json", "Accept" => "application/json"], $headers));
    }

    public function postJson($basePath, $data = [], $headers = []) {
        return $this->post($basePath, json_encode($data), array_merge(["Content-Type" =>  "application/json", "Accept" => "application/json"], $headers));
    }


    private function core($basePath, $httpMethod, $data, $headers) {
        try{
            $response = $this->client->request($httpMethod, $basePath, [
                'body' => $data,
                'headers' => $headers
            ]);

            return  ['success' => true, 'result' => json_decode($response->getBody()->getContents(), true)];
        }catch (Exception $e) {
            return ['success' => false, 'result' => null];
        }

    }
}