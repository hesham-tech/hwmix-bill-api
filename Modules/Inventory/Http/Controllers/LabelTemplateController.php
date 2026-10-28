<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\LabelTemplate;
use Modules\Inventory\Models\LabelTemplateRevision;
use Modules\Inventory\Services\LabelTemplateAccess as Access;
use Modules\Inventory\Services\LabelTemplateDesign as Design;
use Modules\Media\Models\MediaFile;
use Modules\Media\Services\MediaStorageService;

class LabelTemplateController extends Controller
{
    private function editorEnabled(int $companyId): bool
    {
        $allowed = config('label_templates.company_ids', []);

        return (bool) config('label_templates.enabled', true) && (! $allowed || in_array($companyId, $allowed));
    }

    private function context(bool $publishedRead = false): \App\Models\Company
    {
        $company = Access::company();
        // Phased rollout disables editing, never an existing published print workflow.
        abort_unless($publishedRead || $this->editorEnabled($company->id), 503, 'محرر قوالب الملصقات غير مفعّل لهذه الشركة بعد.');

        return $company;
    }

    private function readable(): void
    {
        abort_unless(Access::can('products.print_labels') || Access::can('label_templates.manage') || Access::can('label_templates.publish') || Access::can('label_templates.set_default'), 403, 'لا تملك صلاحية استخدام قوالب الملصقات.');
    }

    private function find(int $companyId, int $id, bool $lock = false): LabelTemplate
    {
        $query = LabelTemplate::where('company_id', $companyId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }

    private function checkVersion(Request $request, LabelTemplate $template): void
    {
        $request->validate(['lock_version' => 'required|integer|min:1']);
        abort_unless((int) $request->input('lock_version') === $template->lock_version, 409, 'عدّل مستخدم آخر هذا القالب. أعد تحميل النسخة قبل الحفظ؛ تعديلاتك الحالية لم تُستبدل.');
    }

    private function present(LabelTemplate $template, int $defaultId, bool $editing): array
    {
        $data = ['id' => $template->id, 'name' => $template->name, 'description' => $template->description, 'company_id' => $template->company_id,
            'archived' => $template->archived, 'lock_version' => $template->lock_version, 'is_default' => $defaultId === $template->id,
            'has_draft' => (bool) $template->draft_revision_id, 'published_revision_id' => $template->published_revision_id,
            'published' => $template->published?->only(['id', 'version', 'design', 'published_at']), 'updated_at' => $template->updated_at];
        if ($editing) {
            $data['draft'] = $template->draft?->only(['id', 'version', 'design', 'updated_at']);
            $data['revisions'] = $template->revisions()->whereNotNull('published_at')->latest('version')->get(['id', 'version', 'published_at']);
        }

        return $data;
    }

    private function audit(string $action, LabelTemplate $template, array $metadata = []): void
    {
        ActivityLog::create(['action' => $action, 'model' => LabelTemplate::class, 'row_id' => $template->id,
            'description' => 'قوالب الملصقات: '.$action.' — '.$template->name, 'company_id' => $template->company_id,
            'user_id' => auth()->id(), 'created_by' => auth()->id(), 'new_values' => $metadata,
            'ip_address' => request()->ip(), 'url' => request()->path()]);
    }

    private function validateDesign(Request $request, int $companyId, bool $publishing = false, ?array $design = null): array
    {
        if ($design === null) {
            $request->validate(['design' => 'required|array']);
        }
        $design = Design::validate($design ?? $request->input('design'), array_column(Access::fields(), 'key'), $publishing);
        foreach ($design['elements'] as $element) {
            if (! empty($element['mediaId'])) {
                $media = MediaFile::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at')->find($element['mediaId']);
                if (! $media || ! in_array($media->mime_type, ['image/png', 'image/jpeg', 'image/webp'])) {
                    throw ValidationException::withMessages(['design' => 'الصورة غير متاحة لهذه الشركة.']);
                }
            }
        }

        return $design;
    }

    private function assets(LabelTemplateRevision $revision): void
    {
        $ids = array_values(array_unique(array_filter(array_column($revision->design['elements'], 'mediaId'))));
        DB::table('label_template_asset_references')->where('label_template_revision_id', $revision->id)->delete();
        foreach ($ids as $id) {
            DB::table('label_template_asset_references')->insert(['label_template_revision_id' => $revision->id, 'media_file_id' => $id]);
        }
    }

    public function fields()
    {
        $company = $this->context(true);
        $this->readable();
        $enabled = $this->editorEnabled($company->id);

        return response()->json(['data' => Access::fields(), 'fonts' => Design::FONTS,
            'editor_enabled' => $enabled, 'currency_symbol' => Access::currency($company),
            'permissions' => ['manage' => $enabled && Access::can('label_templates.manage'), 'publish' => $enabled && Access::can('label_templates.publish'), 'set_default' => $enabled && Access::can('label_templates.set_default')]]);
    }

    public function products(Request $request)
    {
        $company = $this->context(true);
        $this->readable();
        $request->validate(['ids' => 'sometimes|array|min:1|max:100', 'ids.*' => 'integer|min:1',
            'variant_ids' => 'sometimes|array|min:1|max:100', 'variant_ids.*' => 'integer|min:1', 'search' => 'sometimes|nullable|string|max:100']);
        $ids = array_unique($request->input('ids', []));
        $variantIds = array_unique($request->input('variant_ids', []));
        abort_if($ids && $variantIds, 422, 'اختر المنتجات أو المتغيرات في الطلب نفسه، وليس كليهما.');
        $query = \Modules\Inventory\Models\Product::withoutGlobalScopes()->where('company_id', $company->id);
        if ($ids) {
            $query->whereIn('id', $ids);
        } elseif ($variantIds) {
            $query->whereHas('variants', fn ($q) => $q->where('company_id', $company->id)->whereIn('id', $variantIds));
        } else {
            $query->where('name', 'like', '%'.$request->input('search', '').'%')->limit(20);
        }
        $products = $query->with(['variants' => function ($q) use ($company, $variantIds) {
            $q->where('company_id', $company->id)->select(['id', 'company_id', 'product_id', 'sku', 'barcode']);
            if ($variantIds) {
                $q->whereIn('id', $variantIds);
            }
            $q->with(['attributes.attribute', 'attributes.attributeValue'])->orderBy('id');
        }])->orderBy('name')->get();
        if ($ids) {
            abort_unless($products->count() === count($ids), 404);
        }
        if ($variantIds) {
            abort_unless($products->sum(fn ($p) => $p->variants->count()) === count($variantIds), 404);
        }

        return response()->json(['data' => $products->map(fn ($product) => ['id' => $product->id, 'company_id' => $company->id, 'name' => $product->name,
            'variants' => $product->variants->map(fn ($variant) => ['id' => $variant->id, 'sku' => $variant->sku, 'barcode' => $variant->barcode, 'attributes_text' => $variant->attributes_text])])]);
    }

    public function index(Request $request)
    {
        $company = $this->context($request->boolean('published_only'));
        $this->readable();
        $editing = ! $request->boolean('published_only') && (Access::can('label_templates.manage') || Access::can('label_templates.publish'));
        $query = LabelTemplate::where('company_id', $company->id)->with(['published', 'draft']);
        if (! $editing) {
            $query->where('archived', false)->whereNotNull('published_revision_id');
        } elseif (! $request->boolean('include_archived')) {
            $query->where('archived', false);
        }
        $items = $query->orderByDesc('id')->get()->map(fn ($template) => $this->present($template, (int) $company->default_label_template_id, $editing));

        return response()->json(['data' => $items, 'default_id' => $company->default_label_template_id, 'company_id' => $company->id]);
    }

    public function show(int $id)
    {
        $company = $this->context(true);
        $this->readable();
        $template = $this->find($company->id, $id);
        $editing = $this->editorEnabled($company->id) && (Access::can('label_templates.manage') || Access::can('label_templates.publish'));
        abort_if(! $editing && ($template->archived || ! $template->published_revision_id), 404);

        return response()->json(['data' => $this->present($template, (int) $company->default_label_template_id, $editing)]);
    }

    public function store(Request $request)
    {
        $company = $this->context();
        Access::require('label_templates.manage');
        $request->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:1000', 'company_id' => 'prohibited']);
        $design = $this->validateDesign($request, $company->id);
        $template = DB::transaction(function () use ($request, $company, $design) {
            $template = LabelTemplate::create(['company_id' => $company->id, 'name' => $request->string('name')->trim()->toString(), 'description' => $request->input('description'), 'created_by' => auth()->id()]);
            $revision = $template->revisions()->create(['version' => 1, 'design' => $design, 'created_by' => auth()->id()]);
            $this->assets($revision);
            $template->update(['draft_revision_id' => $revision->id]);
            $this->audit('created', $template);

            return $template->fresh();
        });

        return response()->json(['data' => $this->present($template, (int) $company->default_label_template_id, true)], 201);
    }

    public function update(Request $request, int $id)
    {
        $company = $this->context();
        Access::require('label_templates.manage');
        $request->validate(['name' => 'sometimes|required|string|max:150', 'company_id' => 'prohibited']);
        $design = $this->validateDesign($request, $company->id);
        $template = DB::transaction(function () use ($request, $company, $id, $design) {
            $template = $this->find($company->id, $id, true);
            $this->checkVersion($request, $template);
            abort_if($template->archived, 409, 'القالب مؤرشف؛ انسخه لإنشاء قالب قابل للتعديل.');
            $draft = $template->draft;
            if (! $draft) {
                $draft = $template->revisions()->create(['version' => ($template->revisions()->max('version') ?? 0) + 1, 'design' => $design, 'created_by' => auth()->id()]);
            } else {
                $draft->update(['design' => $design]);
            }
            $this->assets($draft);
            $template->update(['name' => $request->has('name') ? trim($request->input('name')) : $template->name, 'draft_revision_id' => $draft->id, 'lock_version' => $template->lock_version + 1]);
            $this->audit('draft_saved', $template, ['version' => $draft->version]);

            return $template->fresh();
        });

        return response()->json(['data' => $this->present($template, (int) $company->default_label_template_id, true)]);
    }

    public function metadata(Request $request, int $id)
    {
        $company = $this->context();
        $request->validate(['name' => 'sometimes|required|string|max:150', 'description' => 'sometimes|nullable|string|max:1000',
            'make_default' => 'sometimes|boolean', 'company_id' => 'prohibited']);
        if ($request->hasAny(['name', 'description'])) {
            Access::require('label_templates.manage');
        }
        if ($request->boolean('make_default')) {
            Access::require('label_templates.set_default');
        }
        abort_unless($request->hasAny(['name', 'description']) || $request->boolean('make_default'), 422, 'لم تُحدد بيانات للتعديل.');
        $template = DB::transaction(function () use ($request, $company, $id) {
            $lockedCompany = DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            $template = $this->find($company->id, $id, true);
            $this->checkVersion($request, $template);
            abort_if($template->archived, 409, 'القالب مؤرشف؛ انسخه قبل التعديل.');
            if ($request->boolean('make_default')) {
                abort_unless($template->published_revision_id, 422, 'انشر القالب قبل تعيينه افتراضيًا.');
                DB::table('companies')->where('id', $company->id)->update(['default_label_template_id' => $id]);
                $this->audit('default_changed', $template);
                $company->default_label_template_id = $id;
            } else {
                $company->default_label_template_id = $lockedCompany->default_label_template_id;
            }
            $values = ['lock_version' => $template->lock_version + 1];
            foreach (['name', 'description'] as $key) {
                if ($request->has($key)) {
                    $values[$key] = $key === 'name' ? trim($request->input($key)) : $request->input($key);
                }
            }
            $template->update($values);
            $this->audit('metadata_updated', $template);

            return $template->fresh();
        });

        return response()->json(['data' => $this->present($template, (int) $company->default_label_template_id, Access::can('label_templates.manage') || Access::can('label_templates.publish'))]);
    }

    public function publish(Request $request, int $id)
    {
        $company = $this->context();
        Access::require('label_templates.publish');
        $template = DB::transaction(function () use ($request, $company, $id) {
            $template = $this->find($company->id, $id, true);
            $this->checkVersion($request, $template);
            abort_if($template->archived || ! $template->draft, 409, 'لا توجد مسودة قابلة للنشر.');
            $draft = $template->draft;
            $this->validateDesign($request, $company->id, true, $draft->design);
            $draft->update(['published_at' => now()]);
            $template->update(['published_revision_id' => $draft->id, 'draft_revision_id' => null, 'lock_version' => $template->lock_version + 1]);
            $this->audit('published', $template, ['revision_id' => $draft->id]);

            return $template->fresh();
        });

        return response()->json(['data' => $this->present($template, (int) $company->default_label_template_id, true)]);
    }

    public function duplicate(Request $request, int $id)
    {
        $company = $this->context();
        Access::require('label_templates.manage');
        $source = $this->find($company->id, $id);
        $request->merge(['name' => $request->input('name') ?: mb_substr($source->name, 0, 135).' — نسخة', 'design' => ($source->draft ?? $source->published)?->design]);

        return $this->store($request);
    }

    public function restore(Request $request, int $id)
    {
        $company = $this->context();
        Access::require('label_templates.manage');
        $template = $this->find($company->id, $id);
        $request->validate(['revision_id' => 'required|integer']);
        $revision = $template->revisions()->whereNotNull('published_at')->findOrFail($request->integer('revision_id'));
        $request->merge(['name' => $template->name, 'design' => $revision->design]);

        return $this->update($request, $id);
    }

    public function setDefault(Request $request, int $id)
    {
        $company = $this->context();
        Access::require('label_templates.set_default');
        DB::transaction(function () use ($company, $id) {
            DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            $template = $this->find($company->id, $id, true);
            abort_if($template->archived || ! $template->published_revision_id, 422, 'اختر قالبًا منشورًا وغير مؤرشف.');
            DB::table('companies')->where('id', $company->id)->update(['default_label_template_id' => $id]);
            $this->audit('default_changed', $template);
        });

        return response()->json(['data' => ['default_id' => $id]]);
    }

    public function destroy(Request $request, int $id)
    {
        $company = $this->context();
        Access::require('label_templates.manage');
        DB::transaction(function () use ($company, $request, $id) {
            $locked = DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            $template = $this->find($company->id, $id, true);
            $this->checkVersion($request, $template);
            if ((int) $locked->default_label_template_id === $id) {
                Access::require('label_templates.set_default');
                $request->validate(['replacement_id' => 'required|integer|min:1']);
                $replacement = $this->find($company->id, $request->integer('replacement_id'), true);
                abort_if($replacement->id === $id || $replacement->archived || ! $replacement->published_revision_id, 422, 'اختر بديلًا منشورًا للقالب الافتراضي.');
                DB::table('companies')->where('id', $company->id)->update(['default_label_template_id' => $replacement->id]);
                $this->audit('default_changed', $replacement);
            }
            $template->update(['archived' => true, 'lock_version' => $template->lock_version + 1]);
            $this->audit('archived', $template);
        });

        return response()->json(['data' => null]);
    }

    public function uploadAsset(Request $request, MediaStorageService $storage)
    {
        $company = $this->context();
        Access::require('label_templates.manage');
        $request->validate(['file' => 'required|file|mimes:png,jpg,jpeg,webp|max:5120|dimensions:max_width=2048,max_height=2048']);
        $media = $storage->store($request->file('file'), $company->id, auth()->id(), true);

        return response()->json(['data' => ['id' => $media->id]], 201);
    }

    public function asset(int $id)
    {
        $company = $this->context();
        $this->readable();
        $media = MediaFile::withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->findOrFail($id);
        abort_unless(in_array($media->mime_type, ['image/png', 'image/jpeg', 'image/webp']), 404);
        $disk = str_starts_with($media->file_path, 'label-template-assets/') ? 'local' : 'public';

        return response()->file(\Illuminate\Support\Facades\Storage::disk($disk)->path($media->file_path), ['Cache-Control' => 'private, no-store']);
    }
}
