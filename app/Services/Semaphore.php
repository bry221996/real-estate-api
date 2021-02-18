<?php

namespace App\Services;

use GuzzleHttp\Client;

class Semaphore
{
    private $apiKey;
    private $apiUrl;
    private $from;
    private $client;

    /**
     * Construct class.
     *
     * @param string $apiKey
     * @param string|null $from
     */
    public function __construct($apiKey, $from = null)
    {
        $this->apiUrl = config('services.semaphore.domain');
        $this->apiKey = $apiKey;
        $this->from = $from ?? 'SEMAPHORE';

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout'  => 30,
            'headers' => [
                'Content-type' => 'application/json',
            ]
        ]);
    }

    /**
     * Get current account info.
     *
     * @return object
     */
    public function getAccountInfo()
    {
        return $this->client->request('GET', 'account', [
                'json' => [
                    'apikey' => $this->apiKey
                ]
            ])
            ->getBody();
    }

    /**
     * Send SMS.
     *
     * @param string $recipient
     * @param string $message
     * @return object
     */
    public function send($recipient, $message)
    {   
        $response = $this->client->request('POST', 'messages', [
            'json' => [
                'apikey'     => $this->apiKey,
                'number'     => $recipient,
                'message'    => $message,
                'sendername' => $this->from
            ], 
        ]);

        return $response->getBody();
    }
}
