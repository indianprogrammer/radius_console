<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Subscriber;
use App\Models\TaxRate;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quotations and Proforma Invoices — Billing & Invoices.
 *
 * One controller serves both documents; the `type` is bound from the route
 * (`/quotation/*` and `/proforma/*`, constrained by `whereIn`) so each gets its
 * own menu entry, numbering series and wording while sharing all behaviour. See
 * the Quote model for why they are one entity.
 *
 * Line items are edited inline with the document (unlike invoices, which are
 * generated from a subscriber's billing items) because a quote is authored by
 * hand before anything exists to generate from.
 */
final class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quotes) {}

    public function index(Request $request, string $type)
    {
        $this->assertType($type);

        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $quotes = Quote::query()
            ->where('tenant_id', tenant_id())
            ->where('type', $type)
            ->with(['subscriber', 'invoice'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhereHas('subscriber', fn ($s) => $s->where('username', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('quotes.index', [
            'type'    => $type,
            'quotes'  => $quotes,
            'search'  => $request->query('q'),
            'status'  => $request->query('status'),
            'totals'  => $this->summary($type),
        ]);
    }

    public function create(string $type)
    {
        $this->assertType($type);

        return view('quotes.create', [
            'type'  => $type,
            'quote' => new Quote([
                'type'       => $type,
                'number'     => Quote::nextNumber(tenant_id(), $type),
                'status'     => 'draft',
                'issue_date' => now()->toDateString(),
                // Quotations carry a validity window; a proforma does not by default.
                'valid_until' => $type === Quote::TYPE_QUOTATION
                    ? now()->addDays(15)->toDateString()
                    : null,
            ]),
            'subscribers' => $this->subscribers(),
            'products'    => $this->products(),
            'taxes'       => $this->taxes(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->assertType($type);

        $data = $this->validateData($request, $type);
        $items = $this->validateItems($request);

        $data['tenant_id'] = tenant_id();
        $data['type']      = $type;
        $data['number']    = ($data['number'] ?? null) ?: Quote::nextNumber(tenant_id(), $type);

        $quote = Quote::create($data);
        $this->syncItems($quote, $items);
        $quote->recomputeTotals();

        return redirect()->route('quotes.show', [$type, $quote->id])
            ->with('status', "{$quote->typeLabel()} {$quote->number} created.");
    }

    public function show(string $type, int $id)
    {
        $this->assertType($type);

        $quote = $this->find($type, $id, ['items.product', 'subscriber', 'invoice']);

        return view('quotes.show', ['type' => $type, 'quote' => $quote]);
    }

    public function edit(string $type, int $id)
    {
        $this->assertType($type);

        $quote = $this->find($type, $id, ['items', 'subscriber']);

        // A converted document is the invoice's source record; editing it would
        // make the two disagree with no audit trail.
        if ($quote->isLocked()) {
            return redirect()->route('quotes.show', [$type, $quote->id])
                ->withErrors(['quote' => "{$quote->typeLabel()} {$quote->number} was converted to an invoice and can no longer be edited."]);
        }

        return view('quotes.edit', [
            'type'        => $type,
            'quote'       => $quote,
            'subscribers' => $this->subscribers(),
            'products'    => $this->products(),
            'taxes'       => $this->taxes(),
        ]);
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->assertType($type);

        $quote = $this->find($type, $id, ['items']);

        if ($quote->isLocked()) {
            return redirect()->route('quotes.show', [$type, $quote->id])
                ->withErrors(['quote' => "{$quote->typeLabel()} {$quote->number} was converted to an invoice and can no longer be edited."]);
        }

        $data = $this->validateData($request, $type, $quote->id);
        $items = $this->validateItems($request);

        $quote->update($data);
        $this->syncItems($quote, $items);
        $quote->recomputeTotals();

        return redirect()->route('quotes.show', [$type, $quote->id])
            ->with('status', "{$quote->typeLabel()} {$quote->number} updated.");
    }

    /** Turn the document into a real invoice (the only receivable-creating path). */
    public function convert(Request $request, string $type, int $id)
    {
        $this->assertType($type);

        $quote = $this->find($type, $id, ['items']);

        try {
            $invoice = $this->quotes->convertToInvoice($quote);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->route('quotes.show', [$type, $quote->id])
                ->withErrors(['quote' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice->id)
            ->with('status', "{$quote->typeLabel()} {$quote->number} converted to invoice {$invoice->number}.");
    }

    public function destroy(Request $request, string $type, int $id)
    {
        $this->assertType($type);

        $quote = $this->find($type, $id);

        if ($quote->isLocked()) {
            $message = "{$quote->typeLabel()} {$quote->number} was converted to an invoice and cannot be deleted.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('quotes.index', $type)->withErrors(['quote' => $message]);
        }

        $number = $quote->number;
        $label  = $quote->typeLabel();
        // quote_items cascade on delete (see the migration).
        $quote->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "{$label} {$number} deleted."]);
        }

        return redirect()->route('quotes.index', $type)->with('status', "{$label} {$number} deleted.");
    }

    // ---- internals --------------------------------------------------------

    /** Reject any type not in the Quote catalogue before touching the DB. */
    private function assertType(string $type): void
    {
        abort_unless(array_key_exists($type, Quote::TYPES), 404);
    }

    private function find(string $type, int $id, array $with = []): Quote
    {
        return Quote::where('tenant_id', tenant_id())
            ->where('type', $type)
            ->with($with)
            ->findOrFail($id);
    }

    private function validateData(Request $request, string $type, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'number' => [
                'nullable', 'string', 'max:40',
                Rule::unique('quotes', 'number')
                    ->where(fn ($q) => $q->where('tenant_id', tenant_id())->where('type', $type))
                    ->ignore($ignoreId),
            ],
            'status' => 'required|in:' . implode(',', array_keys(Quote::STATUSES)),

            'subscriber_id' => [
                'nullable', 'integer',
                Rule::exists('subscribers', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'customer_name'    => 'nullable|string|max:150',
            'customer_email'   => 'nullable|email|max:150',
            'customer_phone'   => 'nullable|string|max:20',
            'customer_address' => 'nullable|string|max:500',
            'customer_gstin'   => 'nullable|string|max:20',

            'issue_date'      => 'nullable|date',
            'valid_until'     => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',

            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
        ]);

        // One of the two customer forms is required: an existing subscriber or a
        // named prospect. Without this a document could be addressed to nobody.
        if (($data['subscriber_id'] ?? null) === null && trim((string) ($data['customer_name'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'customer_name' => 'Select a subscriber or enter a customer name.',
            ]);
        }

        // `converted_*` are service-owned and never accepted from a form.
        $data['discount_amount'] = (float) ($data['discount_amount'] ?? 0);

        return $data;
    }

    /**
     * Validate the inline line-item rows.
     *
     * Blank rows are dropped rather than rejected: the row template in the view
     * can leave an empty row behind and failing on it would be user-hostile.
     */
    private function validateItems(Request $request): array
    {
        $validated = $request->validate([
            'items'                => 'nullable|array',
            'items.*.label'        => 'nullable|string|max:200',
            'items.*.description'  => 'nullable|string|max:500',
            'items.*.qty'          => 'nullable|integer|min:1|max:100000',
            'items.*.unit_price'   => 'nullable|numeric|min:0',
            'items.*.tax_rate'     => 'nullable|numeric|min:0|max:100',
            'items.*.taxable'      => 'nullable|boolean',
            'items.*.product_id'   => [
                'nullable', 'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
        ]);

        $rows = [];
        foreach ($validated['items'] ?? [] as $row) {
            if (trim((string) ($row['label'] ?? '')) === '') {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Replace the document's items with the submitted rows.
     *
     * Delete-then-insert rather than a diff: rows carry no stable identity in
     * the form (they are added/removed client-side) and the items are snapshots,
     * so there is nothing to preserve across an edit.
     */
    private function syncItems(Quote $quote, array $rows): void
    {
        $quote->items()->delete();

        foreach ($rows as $i => $row) {
            $line = QuoteItem::computeLine(
                (float) ($row['unit_price'] ?? 0),
                (int) ($row['qty'] ?? 1),
                (bool) ($row['taxable'] ?? false),
                (float) ($row['tax_rate'] ?? 0),
            );

            QuoteItem::create($line + [
                'tenant_id'   => $quote->tenant_id,
                'quote_id'    => $quote->id,
                'product_id'  => $row['product_id'] ?? null,
                'label'       => $row['label'],
                'description' => $row['description'] ?? null,
                'sort_order'  => $i,
            ]);
        }
    }

    /** Header figures for the listing. */
    private function summary(string $type): array
    {
        $base = fn () => Quote::where('tenant_id', tenant_id())->where('type', $type);

        $open = $base()->whereIn('status', ['draft', 'sent'])->get(['total', 'amount', 'valid_until', 'status', 'converted_invoice_id']);

        return [
            'open'       => $open->count(),
            'open_value' => round($open->sum(fn ($q) => $q->payableAmount()), 2),
            'accepted'   => $base()->where('status', 'accepted')->count(),
            'converted'  => $base()->whereNotNull('converted_invoice_id')->count(),
            'expiring'   => $open->filter(fn ($q) => $q->isExpired())->count(),
        ];
    }

    private function subscribers()
    {
        return Subscriber::where('tenant_id', tenant_id())
            ->orderBy('username')
            ->get(['id', 'username', 'first_name', 'last_name']);
    }

    private function products()
    {
        return Product::where('tenant_id', tenant_id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'default_amount', 'unit']);
    }

    private function taxes()
    {
        return TaxRate::where('tenant_id', tenant_id())
            ->where('type', '!=', 'fixed')
            ->orderBy('name')
            ->get(['id', 'name', 'rate']);
    }
}
