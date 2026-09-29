<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardDeliveryJob;
use App\Models\Delivery;
use App\Models\MessageAttachment;
use App\Services\Mime\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Delivery::query()
            ->with(['message:id,gmail_account_id,subject,from_email,received_at,invoice_links', 'message.account:id,email'])
            ->orderByDesc('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($accountId = $request->integer('account_id')) {
            $query->whereHas('message', fn ($inner) => $inner->where('gmail_account_id', $accountId));
        }
        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($inner) use ($search) {
                $inner->where('to_email', 'like', "%{$search}%")
                    ->orWhereHas('message', function ($message) use ($search) {
                        $message->where('subject', 'like', "%{$search}%")
                            ->orWhere('from_email', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json($query->paginate(20)->through(function (Delivery $delivery) {
            return [
                'id' => $delivery->id,
                'to_email' => $delivery->to_email,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'last_error' => $delivery->last_error,
                'matched_rules' => $delivery->matched_rules,
                'sent_at' => optional($delivery->sent_at)?->toIso8601String(),
                'subject' => $delivery->message?->subject,
                'from_email' => $delivery->message?->from_email,
                'received_at' => optional($delivery->message?->received_at)?->toIso8601String(),
                'account_email' => $delivery->message?->account?->email,
                'invoice_links' => $delivery->message?->invoice_links ?? [],
            ];
        }));
    }

    public function show(Delivery $delivery, HtmlSanitizer $sanitizer): JsonResponse
    {
        $delivery->load('message.account', 'message.attachments');
        $message = $delivery->message;

        return response()->json([
            'id' => $delivery->id,
            'to_email' => $delivery->to_email,
            'status' => $delivery->status,
            'attempts' => $delivery->attempts,
            'last_error' => $delivery->last_error,
            'matched_rules' => $delivery->matched_rules,
            'subject' => $message?->subject,
            'from_email' => $message?->from_email,
            'from_raw' => $message?->from_raw,
            'text_body' => $message?->text_body,
            'html_safe' => $sanitizer->sanitize($message?->html_body),
            'invoice_links' => $message?->invoice_links ?? [],
            'attachments' => $message?->attachments?->map(fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'downloadable' => filled($attachment->stored_path),
                'skipped_reason' => $attachment->skipped_reason,
            ]),
        ]);
    }

    public function retry(Delivery $delivery): JsonResponse
    {
        if (! in_array($delivery->status, ['failed', 'delivery_unknown'], true)) {
            return response()->json(['success' => false, 'message' => 'Csak sikertelen vagy bizonytalan kézbesítés próbálható újra.'], 422);
        }

        $delivery->forceFill(['status' => 'pending', 'last_error' => null])->save();
        ForwardDeliveryJob::dispatch($delivery->id)->onQueue('forwarding');

        return response()->json(['success' => true, 'message' => 'Az újraküldés sorba került.']);
    }

    public function attachment(MessageAttachment $attachment): StreamedResponse|JsonResponse
    {
        if (! $attachment->stored_path || ! Storage::disk('local')->exists($attachment->stored_path)) {
            return response()->json(['success' => false, 'message' => 'A melléklet nem elérhető.'], 404);
        }

        return Storage::disk('local')->download($attachment->stored_path, $attachment->filename);
    }
}
