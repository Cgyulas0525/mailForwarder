<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForwardingRule;
use App\Services\Rules\RulePreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForwardingRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $rules = ForwardingRule::query()->with(['accounts:id,email', 'senders:id,email,name', 'recipients:id,email,name'])->orderBy('name')->get();

        return response()->json(['data' => $rules]);
    }

    public function show(ForwardingRule $rule): JsonResponse
    {
        return response()->json([
            'data' => $rule->load(['accounts:id,email', 'senders:id,email,name', 'recipients:id,email,name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $rule = ForwardingRule::query()->create($this->fields($request));
        $this->sync($rule, $request);

        return response()->json(['success' => true, 'data' => $rule->load('accounts', 'senders', 'recipients')], 201);
    }

    public function update(Request $request, ForwardingRule $rule): JsonResponse
    {
        $rule->update($this->fields($request));
        $this->sync($rule, $request);

        return response()->json(['success' => true, 'data' => $rule->load('accounts', 'senders', 'recipients')]);
    }

    public function destroy(ForwardingRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(['success' => true]);
    }

    public function preview(Request $request, RulePreviewService $preview): JsonResponse
    {
        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'exists:forwarding_rules,id'],
            'sample' => ['nullable', 'array'],
            'sample.from' => ['nullable', 'string'],
            'sample.html' => ['nullable', 'string'],
            'sample.text' => ['nullable', 'string'],
            'sample.subject' => ['nullable', 'string'],
            'sample.gmail_account_id' => ['nullable', 'integer'],
        ]);

        $rule = ForwardingRule::query()->findOrFail($data['rule_id']);
        if (! empty($data['sample'])) {
            return response()->json([
                'success' => true,
                'would_send' => false,
                'data' => [$preview->previewSample($rule, $data['sample'])],
            ]);
        }

        return response()->json([
            'success' => true,
            'would_send' => false,
            'data' => $preview->previewStored($rule),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'match_mode' => ['required', 'in:any,all'],
            'checks_invoice_link' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['checks_invoice_link'] = (bool) ($data['checks_invoice_link'] ?? false);

        return $data;
    }

    private function sync(ForwardingRule $rule, Request $request): void
    {
        $ids = $request->validate([
            'account_ids' => ['array'],
            'account_ids.*' => ['integer', 'exists:gmail_accounts,id'],
            'sender_ids' => ['array'],
            'sender_ids.*' => ['integer', 'exists:email_senders,id'],
            'recipient_ids' => ['array'],
            'recipient_ids.*' => ['integer', 'exists:forward_recipients,id'],
        ]);
        $rule->accounts()->sync($ids['account_ids'] ?? []);
        $rule->senders()->sync($ids['sender_ids'] ?? []);
        $rule->recipients()->sync($ids['recipient_ids'] ?? []);
    }
}
