<?php

namespace App\Support;

use App\Models\BusinessAccount;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use Illuminate\Support\Collection;

/**
 * Account-level reporting for the admin's business account view.
 *
 * Reads through `BusinessAccount::matchedInquiries()`, so a customer's guest
 * history counts towards their numbers rather than showing an empty account.
 *
 * Money is summed without converting: every inquiry records a currency, but it
 * is stamped from the shop's single default at creation, so a mixed-currency
 * account is not a case that exists today. If multi-currency ever ships, these
 * sums need grouping by currency before they can be trusted.
 */
class AccountInsights
{
    /** @var array<int, self> */
    private static array $instances = [];

    /** @var array<string, mixed>|null */
    private ?array $summary = null;

    public function __construct(private readonly BusinessAccount $account) {}

    /**
     * Memoised per account.
     *
     * The admin view asks for these numbers once per field on the page; without
     * this, a dozen entries would each re-run the same aggregate queries.
     */
    public static function for(BusinessAccount $account): self
    {
        return self::$instances[$account->id] ??= new self($account);
    }

    /** Drops the memo. Tests roll the database back underneath these caches. */
    public static function flush(): void
    {
        self::$instances = [];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return $this->summary ??= $this->computeSummary();
    }

    /** @return array<string, mixed> */
    private function computeSummary(): array
    {
        $inquiries = $this->account->matchedInquiries()
            ->get(['id', 'status', 'quoted_total', 'created_at', 'order_confirmed_at']);

        $confirmed = $inquiries->where('status', Inquiry::STATUS_ORDER_CONFIRMED);
        $confirmedValue = (float) $confirmed->sum(fn ($i) => (float) $i->quoted_total);

        return [
            'total' => $inquiries->count(),
            'confirmed' => $confirmed->count(),
            'open' => $inquiries->whereNotIn('status', [Inquiry::STATUS_ORDER_CONFIRMED])->count(),
            'conversion' => $inquiries->isEmpty()
                ? null
                : (int) round($confirmed->count() / $inquiries->count() * 100),
            'quoted_value' => (float) $inquiries->sum(fn ($i) => (float) $i->quoted_total),
            'confirmed_value' => $confirmedValue,
            'average_order' => $confirmed->isEmpty() ? null : $confirmedValue / $confirmed->count(),
            'first_at' => $inquiries->min('created_at'),
            'last_at' => $inquiries->max('created_at'),
            'linked' => $this->account->inquiries()->count(),
        ];
    }

    /**
     * Where this customer's inquiries currently sit in the pipeline.
     *
     * @return Collection<string, int>
     */
    public function pipeline(): Collection
    {
        $counts = $this->account->matchedInquiries()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Rendered in pipeline order, not whatever order the database returned.
        return collect(Inquiry::STATUSES)
            ->map(fn (string $label, string $status) => (int) ($counts[$status] ?? 0))
            ->filter(fn (int $total) => $total > 0);
    }

    /**
     * What this customer actually buys, by units requested.
     *
     * @return Collection<int, object{title: string, sku: ?string, quantity: int, orders: int}>
     */
    public function topProducts(int $limit = 5): Collection
    {
        return InquiryItem::query()
            ->whereIn('inquiry_id', $this->account->matchedInquiries()->select('id'))
            ->selectRaw('product_title, MIN(sku) as sku, SUM(quantity) as quantity, COUNT(DISTINCT inquiry_id) as orders')
            ->groupBy('product_title')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'title' => (string) $row->product_title,
                'sku' => $row->sku,
                'quantity' => (int) $row->quantity,
                'orders' => (int) $row->orders,
            ]);
    }
}
