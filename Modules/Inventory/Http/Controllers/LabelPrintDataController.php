<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\LabelTemplate;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Services\LabelTemplateAccess as Access;
use Modules\Inventory\Services\LabelTemplateDesign as Design;

class LabelPrintDataController extends Controller
{
    public function __invoke(Request $request)
    {
        Access::company();
        Access::require('products.print_labels');

        return $this->prepare($request, false);
    }

    public function preview(Request $request)
    {
        Access::company();
        abort_unless(Access::can('label_templates.manage') || Access::can('label_templates.publish'), 403);

        return $this->prepare($request, true);
    }

    private function prepare(Request $request, bool $preview)
    {
        $company = Access::company();
        $allowed = config('label_templates.company_ids', []);
        abort_unless(! $preview || (config('label_templates.enabled', true) && (! $allowed || in_array($company->id, $allowed))), 503, 'محرر قوالب الملصقات غير مفعّل لهذه الشركة بعد.');
        $request->validate(['company_id' => 'prohibited', 'template_id' => $preview ? 'nullable|integer' : 'required|integer', 'revision_id' => $preview ? 'nullable|integer' : 'required|integer',
            'items' => 'required|array|min:1|max:100', 'items.*.variant_id' => 'nullable|integer|min:1',
            'items.*.invoice_item_id' => 'nullable|integer|min:1', 'items.*.unit_id' => 'nullable|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1|max:100']);
        if (array_sum(array_column($request->input('items'), 'quantity')) > 100) {
            throw ValidationException::withMessages(['items' => 'الحد الأقصى 100 ملصق للطلب.']);
        }
        $fieldKeys = array_column(Access::fields(), 'key');
        $template = null;
        $revision = null;
        if ($preview) {
            $request->validate(['design' => 'required|array']);
            $design = Design::validate($request->input('design'), $fieldKeys);
        } else {
            $template = LabelTemplate::where('company_id', $company->id)->where('archived', false)->findOrFail($request->integer('template_id'));
            // An already-selected published revision remains stable across a concurrent publication.
            $revision = $template->revisions()->whereNotNull('published_at')->findOrFail($request->integer('revision_id'));
            $design = Design::validate($revision->design, $fieldKeys, true);
        }
        $references = Design::references($design);
        $needsInvoice = (bool) array_filter($references, fn ($field) => str_starts_with($field, 'invoice.'));
        $company->loadMissing('images');
        $invoiceIds = array_values(array_filter(array_column($request->input('items'), 'invoice_item_id')));
        $invoiceItems = collect();
        if ($invoiceIds) {
            abort_unless(Access::can('invoices.view_all') || Access::can('invoices.view_self') || Access::can('invoices.view_children'), 403, 'لا تملك صلاحية قراءة الفاتورة.');
            $invoiceQuery = InvoiceItem::withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->with('invoice')->whereIn('id', $invoiceIds);
            if (! Access::can('invoices.view_all')) {
                $invoiceQuery->whereHas('invoice', function ($q) {
                    if (Access::can('invoices.view_children')) {
                        $q->whereCreatedByUserOrChildren();
                    } else {
                        $q->where('created_by', auth()->id());
                    }
                });
            }
            $invoiceItems = $invoiceQuery->get()->keyBy('id');
        }
        $variantIds = array_unique(array_filter(array_merge(array_column($request->input('items'), 'variant_id'), $invoiceItems->pluck('variant_id')->all())));
        $variants = ProductVariant::withoutGlobalScopes()->where('company_id', $company->id)
            ->whereHas('product', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $company->id))
            ->with(['product.category', 'product.brand', 'product.images', 'images', 'attributes.attribute', 'attributes.attributeValue', 'units.unit', 'unitPrices', 'baseUnit', 'displayUnit'])
            ->whereIn('id', $variantIds)->get()->keyBy('id');
        $records = [];
        foreach ($request->input('items') as $item) {
            $invoiceItem = null;
            if (! empty($item['invoice_item_id'])) {
                abort_unless(Access::can('invoices.view_all') || Access::can('invoices.view_self') || Access::can('invoices.view_children'), 403, 'لا تملك صلاحية قراءة الفاتورة.');
                $invoiceItem = $invoiceItems->get($item['invoice_item_id']);
                abort_unless($invoiceItem, 404);
                abort_unless($invoiceItem->invoice && (int) $invoiceItem->invoice->company_id === (int) $company->id, 404);
                if (! empty($item['variant_id']) && (int) $item['variant_id'] !== (int) $invoiceItem->variant_id) {
                    throw ValidationException::withMessages(['items' => 'متغير المنتج لا يطابق بند الفاتورة.']);
                }
            }
            if ($needsInvoice && ! $invoiceItem) {
                throw ValidationException::withMessages(['items' => 'القالب يحتوي بيانات فاتورة؛ ابدأ طباعته من فاتورة أو اختر قالبًا آخر.']);
            }
            $variantId = $invoiceItem?->variant_id ?? ($item['variant_id'] ?? null);
            if (! $variantId) {
                throw ValidationException::withMessages(['items' => 'اختر متغير المنتج الذي تريد طباعته.']);
            }
            $variant = $variants->get($variantId);
            abort_unless($variant, 404, 'متغير المنتج غير متاح لهذه الشركة.');
            $product = $variant->product;
            $unitId = $item['unit_id'] ?? $variant->display_unit_id ?? $variant->base_unit_id;
            $mapping = $unitId ? $variant->units->firstWhere('unit_id', $unitId) : null;
            if ($unitId && ! $mapping && (int) $unitId !== (int) $variant->base_unit_id) {
                throw ValidationException::withMessages(['items' => 'الوحدة المختارة غير مرتبطة بهذا المتغير.']);
            }
            $unit = $mapping?->unit ?? $variant->baseUnit;
            if ($unitId && ! $unit) {
                throw ValidationException::withMessages(['items' => 'الوحدة المختارة غير متاحة لهذه الشركة.']);
            }
            $unitPrice = $variant->unitPrices->where('unit_id', $unitId)->filter(fn ($p) => (! $p->effective_from || $p->effective_from->startOfDay()->lte(now())) && (! $p->effective_to || $p->effective_to->endOfDay()->gte(now())))->sortByDesc('effective_from')->first();
            $values = [
                'company.currency' => Access::currency($company), 'company.name' => $company->name, 'company.address' => $company->address, 'company.phone' => $company->phone,
                'company.website' => $company->website, 'company.email' => $company->email, 'company.tax_number' => $company->tax_number,
                'company.logo' => $company->images->firstWhere('is_primary', true)?->url ?? $company->images->first()?->url,
                'product.name' => $product->name, 'product.slug' => $product->slug, 'product.description' => strip_tags($product->desc ?? ''),
                'product.description_long' => strip_tags($product->desc_long ?? ''), 'product.category' => $product->category?->name,
                'product.brand' => $product->brand?->name, 'product.type' => $product->product_type, 'product.image' => $product->primary_image_url,
                'variant.sku' => $variant->sku, 'variant.barcode' => $variant->barcode, 'variant.retail_price' => $variant->retail_price,
                'variant.discount' => $variant->discount, 'variant.tax' => $variant->tax, 'variant.weight' => $variant->weight,
                'variant.dimensions' => is_array($variant->dimensions) ? implode(' × ', $variant->dimensions) : $variant->dimensions,
                'variant.image' => $variant->primary_image_url, 'variant.attributes' => $variant->attributes_text,
                'unit.name' => $unit?->name, 'unit.conversion_factor' => $mapping?->conversion_factor_to_base ?? 1,
                'unit.price' => $unitPrice?->price ?? (float) $variant->retail_price * ($mapping?->conversion_factor_to_base ?? 1),
                'print.date' => now()->toDateString(),
            ];
            if (Access::can('products.view_wholesale_price')) {
                $values['variant.wholesale_price'] = $variant->wholesale_price;
            }
            if (Access::can('products.view_purchase_price')) {
                $values['variant.purchase_price'] = $variant->purchase_price;
            }
            $allowedFields = $fieldKeys;
            foreach ($variant->attributes as $attribute) {
                $key = 'attribute.'.$attribute->attribute_id;
                if (in_array($key, $allowedFields, true)) {
                    $values[$key] = $attribute->attributeValue?->name ?? '';
                }
            }
            if ($invoiceItem) {
                $values['invoice.unit_price'] = $invoiceItem->unit_price;
                $values['invoice.number'] = $invoiceItem->invoice->invoice_number ?? $invoiceItem->invoice->id;
                $values['invoice.date'] = $invoiceItem->invoice->invoice_date ?? $invoiceItem->invoice->created_at?->toDateString();
            }
            $records[] = ['variant_id' => $variant->id, 'invoice_item_id' => $invoiceItem?->id, 'unit_id' => $unitId, 'quantity' => (int) $item['quantity'],
                'values' => $values, 'units' => $variant->units->filter(fn ($m) => $m->unit !== null)->map(fn ($m) => ['id' => $m->unit_id, 'name' => $m->unit->name])
                    ->concat($variant->baseUnit ? [['id' => $variant->base_unit_id, 'name' => $variant->baseUnit->name]] : [])->unique('id')->values()];
        }

        return response()->json(['data' => ['company_id' => $company->id, 'template_id' => $template?->id, 'revision_id' => $revision?->id, 'design' => $design, 'records' => $records]]);
    }
}
