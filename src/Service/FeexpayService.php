<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


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
            'lastName' => '',
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

            // ✅ IMPORTANT : Toujours retourner un array
            $responseData = $response->toArray(false); // false = ne pas throw sur erreur HTTP

            // Ajouter le status code pour debug
            $responseData['http_status'] = $response->getStatusCode();

            return $responseData;
        } catch (TransportExceptionInterface $e) {
            // ✅ IMPORTANT : Retourner un array même en cas d'erreur
            return [
                'success' => false,
                'message' => 'Erreur réseau : ' . $e->getMessage(),
                'error' => true,
            ];
        } catch (\Exception $e) {
            // ✅ Gérer tous les autres types d'erreurs
            return [
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'error' => true,
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

            // ✅ IMPORTANT : Toujours retourner un array
            $responseData = $response->toArray(false);
            $responseData['http_status'] = $response->getStatusCode();

            return $responseData;
        } catch (TransportExceptionInterface $e) {
            // ✅ IMPORTANT : Retourner un array même en cas d'erreur
            return [
                'success' => false,
                'message' => 'Erreur statut : ' . $e->getMessage(),
                'error' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'error' => true,
            ];
        }
    }

    public function getMode(): string
    {
        return $this->mode;
    }
}
