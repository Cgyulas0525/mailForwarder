<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailSender;
use App\Models\ForwardRecipient;
use App\Support\AddressParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function senders(): JsonResponse
    {
        return response()->json(['data' => EmailSender::query()->orderBy('email')->get()]);
    }

    public function showSender(EmailSender $sender): JsonResponse
    {
        return response()->json(['data' => $sender]);
    }

    public function storeSender(Request $request): JsonResponse
    {
        $data = $this->validateParty($request);
        $sender = EmailSender::query()->create($data);

        return response()->json(['success' => true, 'data' => $sender], 201);
    }

    public function updateSender(Request $request, EmailSender $sender): JsonResponse
    {
        $sender->update($this->validateParty($request, false));

        return response()->json(['success' => true, 'data' => $sender]);
    }

    public function destroySender(EmailSender $sender): JsonResponse
    {
        $sender->delete();

        return response()->json(['success' => true]);
    }

    public function recipients(): JsonResponse
    {
        return response()->json(['data' => ForwardRecipient::query()->orderBy('email')->get()]);
    }

    public function showRecipient(ForwardRecipient $recipient): JsonResponse
    {
        return response()->json(['data' => $recipient]);
    }

    public function storeRecipient(Request $request): JsonResponse
    {
        $data = $this->validateParty($request);
        $recipient = ForwardRecipient::query()->create($data);

        return response()->json(['success' => true, 'data' => $recipient], 201);
    }

    public function updateRecipient(Request $request, ForwardRecipient $recipient): JsonResponse
    {
        $recipient->update($this->validateParty($request, false));

        return response()->json(['success' => true, 'data' => $recipient]);
    }

    public function destroyRecipient(ForwardRecipient $recipient): JsonResponse
    {
        $recipient->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array{name: ?string, email: string, is_active: bool}
     */
    private function validateParty(Request $request, bool $creating = true): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($data['email'])) {
            $data['email'] = AddressParser::normalize($data['email']);
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
