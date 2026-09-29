<?php

namespace App\Services\Rules;

use App\Models\ForwardingRule;
use App\Models\SourceMessage;

class ForwardingRuleMatcher
{
    /**
     * Üres szabály (nincs aktív feladó és nincs linkfeltétel) nem illeszkedik.
     *
     * @return array{matched: bool, conditions: list<string>}
     */
    public function explain(ForwardingRule $rule, SourceMessage $message): array
    {
        if (! $rule->is_active) {
            return ['matched' => false, 'conditions' => []];
        }

        if (! $rule->relationLoaded('accounts') || ! $rule->accounts->contains('id', $message->gmail_account_id)) {
            return ['matched' => false, 'conditions' => []];
        }

        $checks = [];
        $activeSenders = $rule->relationLoaded('senders')
            ? $rule->senders->where('is_active', true)
            : collect();

        if ($activeSenders->isNotEmpty()) {
            $checks['sender'] = $message->from_email !== null && $activeSenders->contains(
                fn ($sender) => strcasecmp($sender->email, $message->from_email) === 0
            );
        }

        if ($rule->checks_invoice_link) {
            $checks['invoice_link'] = count($message->invoice_links ?? []) > 0;
        }

        if ($checks === []) {
            return ['matched' => false, 'conditions' => []];
        }

        $matched = $rule->match_mode === 'all'
            ? ! in_array(false, $checks, true)
            : in_array(true, $checks, true);

        $passed = array_keys(array_filter($checks));

        return [
            'matched' => $matched,
            'conditions' => $matched ? array_values($passed) : [],
        ];
    }
}
