<?php

namespace App\Services\Rules;

use App\Models\ForwardingRule;
use App\Models\SourceMessage;
use App\Support\AddressParser;

class RulePreviewService
{
    public function __construct(
        private ForwardingRuleMatcher $matcher,
        private InvoiceLinkDetector $links,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function previewStored(ForwardingRule $rule, int $limit = 50): array
    {
        $rule->loadMissing('accounts', 'senders', 'recipients');
        $accountIds = $rule->accounts->pluck('id');
        $messages = SourceMessage::query()
            ->when($accountIds->isNotEmpty(), fn ($query) => $query->whereIn('gmail_account_id', $accountIds))
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get();

        $hits = [];
        foreach ($messages as $message) {
            $explained = $this->matcher->explain($rule, $message);
            if (! $explained['matched']) {
                continue;
            }
            $hits[] = $this->hit($message->subject, $message->from_email, $explained['conditions'], $rule, $message->invoice_links ?? []);
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    public function previewSample(ForwardingRule $rule, array $sample): array
    {
        $rule->loadMissing('accounts', 'senders', 'recipients');
        $html = (string) ($sample['html'] ?? '');
        $text = (string) ($sample['text'] ?? '');
        $invoiceLinks = $this->links->detect($html, $text);
        $message = new SourceMessage([
            'gmail_account_id' => $sample['gmail_account_id'] ?? $rule->accounts->first()?->id,
            'from_email' => AddressParser::extract($sample['from'] ?? ''),
            'subject' => $sample['subject'] ?? '',
            'invoice_links' => $invoiceLinks,
        ]);
        $explained = $this->matcher->explain($rule, $message);

        return [
            'matched' => $explained['matched'],
            'conditions' => $explained['conditions'],
            'invoice_links' => $invoiceLinks,
            'recipients' => $explained['matched']
                ? $rule->recipients->where('is_active', true)->pluck('email')->values()
                : [],
            'would_send' => false,
        ];
    }

    /**
     * @param  list<string>  $conditions
     * @param  list<string>  $links
     * @return array<string, mixed>
     */
    private function hit(?string $subject, ?string $from, array $conditions, ForwardingRule $rule, array $links): array
    {
        return [
            'subject' => $subject,
            'from_email' => $from,
            'conditions' => $conditions,
            'invoice_links' => $links,
            'recipients' => $rule->recipients->where('is_active', true)->pluck('email')->values(),
            'would_send' => false,
        ];
    }
}
