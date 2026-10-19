<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayService
{
    /**
     * @param  array<string, string>  $notes
     * @return array<string, mixed>
     */
    public function createOrder(int $amountPaise, string $receipt, array $notes): array
    {
        $response = Http::withBasicAuth($this->key(), $this->secret())
            ->acceptJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => $notes,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.description') ?: 'Razorpay could not create the order.');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPayment(string $paymentId): array
    {
        $response = Http::withBasicAuth($this->key(), $this->secret())
            ->acceptJson()
            ->get('https://api.razorpay.com/v1/payments/'.$paymentId);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.description') ?: 'Razorpay could not confirm the payment.');
        }

        return $response->json();
    }

    public function signatureIsValid(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->secret());

        return hash_equals($expected, $signature);
    }

    public function key(): string
    {
        return (string) config('services.razorpay.key');
    }

    public function secret(): string
    {
        $secret = (string) config('services.razorpay.secret');

        if ($this->key() === '' || $secret === '') {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return $secret;
    }
}
