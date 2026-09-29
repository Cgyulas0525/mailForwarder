<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Services\Forward\ForwardMessageFactory;
use App\Services\Forward\OutboundMailer;
use App\Services\Forward\UncertainDeliveryException;
use App\Support\ErrorSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ForwardDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public int $deliveryId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('delivery-'.$this->deliveryId))->expireAfter(120)];
    }

    public function handle(OutboundMailer $mailer, ForwardMessageFactory $factory): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['sent', 'delivery_unknown'], true)) {
            return;
        }

        $delivery->forceFill([
            'status' => 'sending',
            'attempts' => $delivery->attempts + 1,
        ])->save();

        try {
            $mailer->send($factory->make($delivery));
            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (UncertainDeliveryException $e) {
            $delivery->forceFill([
                'status' => 'delivery_unknown',
                'last_error' => ErrorSanitizer::sanitize($e->getMessage()),
            ])->save();
        } catch (\Throwable $e) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => ErrorSanitizer::sanitize($e->getMessage()),
            ])->save();

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['sent', 'delivery_unknown'], true)) {
            return;
        }

        $delivery->forceFill([
            'status' => 'failed',
            'last_error' => ErrorSanitizer::sanitize($exception?->getMessage()),
        ])->save();
    }
}
