<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GmailAccount;
use App\Services\Mail\MailboxClient;
use App\Services\Sync\AccountSyncService;
use App\Support\ErrorSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GmailAccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = GmailAccount::query()->orderBy('email')->get()->map(fn (GmailAccount $account) => $this->payload($account));

        return response()->json(['data' => $accounts]);
    }

    public function show(GmailAccount $account): JsonResponse
    {
        return response()->json(['data' => $this->payload($account)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateAccount($request, true);
        $settings = GmailAccount::settingsFromInput($data);
        $account = new GmailAccount(array_merge($settings, [
            'display_name' => $data['display_name'] ?? null,
            'email' => $data['email'],
            'imap_username' => $data['imap_username'] ?? ($settings['imap_username'] ?? null),
            'password' => $data['password'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? true),
            'status' => 'pending',
        ]));
        $account->save();

        return response()->json(['success' => true, 'data' => $this->payload($account)], 201);
    }

    public function update(Request $request, GmailAccount $account): JsonResponse
    {
        $data = $this->validateAccount($request, false);
        $settings = GmailAccount::settingsFromInput(array_merge($account->only([
            'provider', 'imap_host', 'imap_port', 'imap_encryption', 'imap_mailbox', 'smtp_host', 'smtp_port', 'smtp_encryption', 'imap_username', 'email',
        ]), $data));
        $account->fill(array_merge($settings, [
            'display_name' => $data['display_name'] ?? $account->display_name,
            'email' => $data['email'] ?? $account->email,
            'imap_username' => array_key_exists('imap_username', $data) ? $data['imap_username'] : $account->imap_username,
            'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : $account->is_enabled,
        ]));
        if (filled($data['password'] ?? null)) {
            $account->password = $data['password'];
            if ($account->status === 'credentials_required') {
                $account->status = 'pending';
            }
        }
        $account->save();

        return response()->json(['success' => true, 'data' => $this->payload($account)]);
    }

    public function enable(Request $request, GmailAccount $account): JsonResponse
    {
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        $account->is_enabled = $data['is_enabled'];
        $account->save();

        return response()->json(['success' => true, 'data' => $this->payload($account)]);
    }

    public function test(Request $request, GmailAccount $account, MailboxClient $client): JsonResponse
    {
        $password = $request->input('password');
        $account->forceFill(['last_attempt_at' => now()])->save();
        if ($account->needsCredentials() && ! filled($password)) {
            return response()->json(['success' => false, 'message' => 'A fiókhoz előbb hitelesítő adat kell.'], 422);
        }

        $result = $client->testConnection($account, filled($password) ? (string) $password : null);
        $account->forceFill([
            'status' => $result['success'] ? 'active' : 'error',
            'last_error' => $result['success'] ? null : ErrorSanitizer::sanitize($result['message']),
        ])->save();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $this->payload($account),
        ], $result['success'] ? 200 : 422);
    }

    public function sync(GmailAccount $account, AccountSyncService $sync): JsonResponse
    {
        $result = $sync->sync($account);

        return response()->json([
            'success' => $result->error === null,
            'message' => $result->error ?? 'Szinkron kész.',
            'stored' => $result->stored,
            'failed' => $result->failed,
            'planned' => $result->planned,
            'data' => $this->payload($account->fresh()),
        ], $result->error ? 422 : 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(Request $request, bool $creating): array
    {
        return $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'provider' => ['nullable', 'in:gmail,custom'],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_host' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer'],
            'imap_encryption' => ['nullable', 'in:ssl,tls,none'],
            'imap_mailbox' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_encryption' => ['nullable', 'in:ssl,tls,none'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(GmailAccount $account): array
    {
        return [
            'id' => $account->id,
            'display_name' => $account->display_name,
            'email' => $account->email,
            'provider' => $account->provider,
            'imap_username' => $account->imap_username,
            'imap_host' => $account->imap_host,
            'imap_port' => $account->imap_port,
            'imap_encryption' => $account->imap_encryption,
            'imap_mailbox' => $account->imap_mailbox,
            'smtp_host' => $account->smtp_host,
            'smtp_port' => $account->smtp_port,
            'smtp_encryption' => $account->smtp_encryption,
            'is_enabled' => $account->is_enabled,
            'status' => $account->status,
            'has_password' => $account->hasStoredPassword(),
            'last_fetched_at' => optional($account->last_fetched_at)?->toIso8601String(),
            'last_attempt_at' => optional($account->last_attempt_at)?->toIso8601String(),
            'last_error' => $account->last_error,
        ];
    }
}
