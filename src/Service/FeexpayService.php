<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class FeexpayService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $shopId;
    private string $callbackUrl;
    private string $errorCallbackUrl;
    private string $mode;

    public function __construct(
        HttpClientInterface $client,
        string $token,
        string $shopId,
        string $callbackUrl,
        string $errorCallbackUrl,
        string $mode
    ) {
        $this->client = $client;
        $this->apiKey = $token;
        $this->shopId = $shopId;
        $this->callbackUrl = $callbackUrl;
        $this->errorCallbackUrl = $errorCallbackUrl;
        $this->mode = $mode;
    }

    public function paiementLocal(
        float $amount,
        string $phone,
        string $operator,
        string $fullname,
        string $email,
        string $customId
    ): array {
        $data = [
            'phoneNumber' => $phone,
            'amount' => $amount,
            'shop' => $this->shopId,
            'firstName' => $fullname,
            'lastName' => '',           // tu peux diviser fullname ou le laisser vide
            'description' => '',
        ];

        $url = sprintf(
            'https://%s/api/transactions/public/requesttopay/%s',
            $this->mode === 'sandbox' ? 'sandbox.feexpay.me' : 'api.feexpay.me',
            strtolower($operator)
        );

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'json' => $data,
            ]);

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            return [
                'success' => false,
                'message' => 'Erreur réseau : ' . $e->getMessage(),
            ];
        }
    }

    public function getPaiementStatus(string $reference): array
    {
        $url = sprintf(
            'https://%s/api/transactions/public/single/status/%s',
            $this->mode === 'sandbox' ? 'sandbox.feexpay.me' : 'api.feexpay.me',
            $reference
        );

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept'        => 'application/json',
                ],
            ]);

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            return [
                'success' => false,
                'message' => 'Erreur statut : ' . $e->getMessage(),
            ];
        }
    }
    public function getMode(): string
{
    return $this->mode;
}
}

