<?php

namespace App\Mail;

use App\Models\LaundryTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public LaundryTransaction $transaction) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Receipt #'.$this->transaction->id.' from '.config('shop.name', 'SSK Laba Dami'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $transaction = $this->transaction;
        $items = [];
        $addOnItems = [];
        $addOnTotal = 0.0;

        foreach ($transaction->inventoryItems as $item) {
            $lineTotal = (float) ($item->pivot->line_total ?? 0);
            $addOnTotal += $lineTotal;
            $addOnItems[] = [
                'name' => $item->name,
                'quantity' => (int) $item->pivot->quantity,
                'amount' => $lineTotal,
            ];
        }

        if ($transaction->detergent && (int) $transaction->detergent_quantity > 0) {
            $lineTotal = (float) $transaction->detergent->unit_price * (int) $transaction->detergent_quantity;
            $addOnTotal += $lineTotal;
            $addOnItems[] = [
                'name' => $transaction->detergent->name,
                'quantity' => (int) $transaction->detergent_quantity,
                'amount' => $lineTotal,
            ];
        }

        $hasService = $transaction->service || $transaction->transaction_type === 'self_service';
        if ($hasService) {
            $items[] = [
                'name' => $transaction->transaction_type === 'self_service'
                    ? 'Self Service'
                    : 'Drop Off - '.($transaction->service?->name ?? 'Laundry service'),
                'quantity' => null,
                'amount' => max(0, (float) $transaction->total_amount - $addOnTotal),
            ];
        }
        array_push($items, ...$addOnItems);

        return new Content(
            view: 'mail.transaction-receipt',
            with: [
                'shopName' => config('shop.name', 'SSK Laba Dami'),
                'shopAddress' => config('shop.address', ''),
                'customerName' => $transaction->customer?->name ?? 'Customer',
                'cashierName' => $transaction->handledBy?->name ?? 'Staff',
                'items' => $items,
                'hasService' => $hasService,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
