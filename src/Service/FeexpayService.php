<?php

namespace App\Service;

use Feexpay\FeexpayPhp\FeexpayClass;

class FeexpayService
{
    private $feexpay;

    public function __construct(string $shopId, string $token, string $callbackUrl, string $mode, string $errorCallbackUrl)
    {
        $this->feexpay = new FeexpayClass($shopId, $token, $callbackUrl, $mode, $errorCallbackUrl);
    }

    public function paiementLocal(
        float $amount,
        string $phoneNumber,
        string $operatorName,
        string $fullname,
        string $email,
        string $callback_info,
        string $custom_id,
        string $otp = ""
    )
    {
        return $this->feexpay->paiementLocal($amount, $phoneNumber, $operatorName, $fullname, $email, $callback_info, $custom_id, $otp);
    }

    public function getPaiementStatus($response)
    {
        return $this->feexpay->getPaiementStatus($response);
    }
}
