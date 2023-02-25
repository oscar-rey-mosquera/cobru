<?php

class PaymentMethod
{
   public const COBRU = 'cobru_phone';

   public const PSE = 'pse';
   public const  BANCOLOMBIA_TRANSFER = 'bancolombia_transfer';
   public  const  CREDIT_CARD = 'credit_card';
   public const NEQUI = 'NEQUI';
   public const EFECTY = 'efecty';
   public const CORRESPONSAL_BANCOLOMBIA = 'corresponsal_bancolombia';
   public const BTC = 'BTC';
   public const CUSD = 'CUSD';
   public const BALOTO = 'baloto';

    public const DALE = 'dale';

    public const BANCOLOMBIA_QR = 'bancolombia_qr';

   public static function toArray() {
        return [
            PaymentMethod::CUSD,
            PaymentMethod::BTC,
            PaymentMethod::PSE,
            PaymentMethod::NEQUI,
            PaymentMethod::CORRESPONSAL_BANCOLOMBIA,
            PaymentMethod::BANCOLOMBIA_TRANSFER,
            PaymentMethod::COBRU,
            PaymentMethod::EFECTY,
            PaymentMethod::CREDIT_CARD,
            PaymentMethod::BALOTO,
            PaymentMethod::DALE,
            PaymentMethod::BANCOLOMBIA_QR
        ];
     }

    public static function toString()
    {
        return implode(',', PaymentMethod::toArray());
    }


}