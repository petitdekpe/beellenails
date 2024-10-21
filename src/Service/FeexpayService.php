<?php

namespace App\Service;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeexpayService
{
    private $client;
    private $apiKey;
    private $shopId;
    private $jsonDecoder;

    public function __construct(HttpClientInterface $client, string $apiKey, string $shopId)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->shopId = $shopId;
        $this->jsonDecoder = new JsonEncoder();
    }

    public function requestToPayMtn(string $phoneNumber, float $amount): array
    {
        $response = $this->client->request('POST', 'https://api.feexpay.me/api/transactions/public/requesttopay/mtn', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'shop' => $this->shopId,
                'amount' => $amount,
                'phoneNumber' => $phoneNumber,
            ],
        ]);

        $content = $response->getContent();
        return $this->jsonDecoder->decode($content, JsonEncoder::FORMAT);
    }

    public function requestToPayMoov(string $phoneNumber, float $amount): array
    {
        $response = $this->client->request('POST', 'https://api.feexpay.me/api/transactions/public/requesttopay/moov', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'shop' => $this->shopId,
                'amount' => $amount,
                'phoneNumber' => $phoneNumber,
            ],
        ]);

        $content = $response->getContent();
        return $this->jsonDecoder->decode($content, JsonEncoder::FORMAT);
    }

    public function getTransactionStatus(string $reference): array
    {
        $url = 'https://api.feexpay.me/api/transactions/public/single/status/' . $reference;
        $response = $this->client->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
        ]);

        $content = $response->getContent();
        $decodedResponse = $this->jsonDecoder->decode($content, JsonEncoder::FORMAT);

        // Map FeexPay statuses to your application statuses
        $statusMap = [
            'PENDING' => 'pending',
            'IN PENDING STATE' => 'pending',
            'SUCCESSFUL' => 'successful',
            'FAILED' => 'failed',
        ];

        $status = $decodedResponse['status'] ?? null;

        return [
            'status' => $statusMap[$status] ?? 'invalid',
            'reference' => $decodedResponse['reference'] ?? null,
        ];
    }
}
