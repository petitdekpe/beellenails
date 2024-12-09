<?php

namespace App\Service;


use Feexpay\FeexpayPhp\FeexpayClass;

class FeexPayService
{
    private $feexpay;

    public function __construct($shopId, $token, $callbackUrl, $mode, $errorCallbackUrl)
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
    ) {
        return $this->feexpay->paiementLocal($amount, $phoneNumber, $operatorName, $fullname, $email, $callback_info, $custom_id, $otp);
    }

    public function getPaiementStatus($reference)
    {
        return $this->feexpay->getPaiementStatus($reference);
    }
}
