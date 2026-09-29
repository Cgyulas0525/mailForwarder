<?php

namespace App\Services\Sync;

use App\Jobs\ForwardDeliveryJob;
use App\Models\Delivery;
use App\Models\ForwardingRule;
use App\Models\SourceMessage;
use App\Services\Rules\ForwardingRuleMatcher;
use Illuminate\Database\QueryException;

class ForwardPlanner
{
    public function __construct(private ForwardingRuleMatcher $matcher) {}

    public function plan(SourceMessage $message): int
    {
        $message->loadMissing('account');
        $account = $message->account;
        if (! $account || ! $account->is_enabled || $account->needsCredentials()) {
            return 0;
        }

        $rules = ForwardingRule::query()
            ->where('is_active', true)
            ->whereHas('accounts', fn ($query) => $query->where('gmail_accounts.id', $account->id))
            ->with(['senders', 'recipients', 'accounts'])
            ->get();

        $byRecipient = [];
        foreach ($rules as $rule) {
            $explained = $this->matcher->explain($rule, $message);
            if (! $explained['matched']) {
                continue;
            }

            foreach ($rule->recipients as $recipient) {
                if (! $recipient->is_active) {
                    continue;
                }
                $byRecipient[$recipient->id]['recipient'] = $recipient;
                $byRecipient[$recipient->id]['rules'][] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'conditions' => $explained['conditions'],
                ];
            }
        }

        $created = 0;
        foreach ($byRecipient as $recipientId => $bag) {
            try {
                $delivery = Delivery::query()->firstOrCreate(
                    [
                        'source_message_id' => $message->id,
                        'forward_recipient_id' => $recipientId,
                    ],
                    [
                        'to_email' => $bag['recipient']->email,
                        'status' => 'pending',
                        'matched_rules' => $bag['rules'],
                    ]
                );
            } catch (QueryException) {
                continue;
            }

            if ($delivery->wasRecentlyCreated) {
                ForwardDeliveryJob::dispatch($delivery->id)->onQueue('forwarding');
                $created++;
            }
        }

        return $created;
    }
}
