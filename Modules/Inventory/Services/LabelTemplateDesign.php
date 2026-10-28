<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LabelTemplateDesign
{
    public const FONTS = ['Tahoma', 'Arial', 'Noto Sans Arabic'];

    public static function starter(): array
    {
        $text = fn ($id, $content, $y, $h, $size) => [
            'id' => $id, 'type' => 'text', 'x' => 3, 'y' => $y, 'width' => 44, 'height' => $h,
            'content' => $content, 'fontFamily' => 'Tahoma', 'fontSize' => $size, 'minFontSize' => $size,
            'bold' => true, 'align' => 'center', 'direction' => 'auto', 'color' => '#000000',
            'lineHeight' => 1.2, 'overflow' => 'ellipsis', 'visible' => true, 'rotation' => 0,
        ];

        return [
            'schemaVersion' => 1, 'widthMm' => 50, 'heightMm' => 60, 'marginMm' => 1,
            'background' => '#ffffff',
            'sheet' => ['columns' => 3, 'rows' => 4, 'marginTop' => 10, 'marginBottom' => 10, 'marginLeft' => 10, 'marginRight' => 10, 'gapX' => 2, 'gapY' => 2, 'orientation' => 'portrait', 'direction' => 'ltr'],
            'elements' => [
                $text('company', '{{company.name}}', 14, 4, 7),
                $text('name', '{{product.name}}', 18, 8, 10.5),
                $text('price', '{{variant.retail_price}} ج.م', 26, 5, 9),
                ['id' => 'barcode', 'type' => 'barcode', 'x' => 3, 'y' => 32, 'width' => 44, 'height' => 11,
                    'field' => 'variant.barcode', 'format' => 'CODE128', 'showText' => true, 'fontSize' => 7.8,
                    'fontFamily' => 'Tahoma', 'visible' => true, 'rotation' => 0],
                $text('sku', '{{variant.sku}}', 47, 4, 6.4),
            ],
        ];
    }

    public static function references(array $design): array
    {
        $fields = [];
        foreach ($design['elements'] ?? [] as $element) {
            if (isset($element['field']) && $element['field'] !== '') {
                $fields[] = $element['field'];
            }
            preg_match_all('/\{\{([a-zA-Z0-9_.-]+)\}\}/', $element['content'] ?? '', $matches);
            $fields = array_merge($fields, $matches[1]);
        }

        return array_values(array_unique($fields));
    }

    public static function validate(array $design, array $allowedFields, bool $publishing = false): array
    {
        Validator::make($design, [
            'schemaVersion' => 'required|integer|in:1,2', 'widthMm' => 'required|numeric|min:5|max:210',
            'heightMm' => 'required|numeric|min:5|max:1000', 'marginMm' => 'required|numeric|min:0|max:30',
            'background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements' => 'present|array|min:'.($publishing ? '1' : '0').'|max:100',
            'elements.*.id' => 'required|string|max:80|distinct',
            'elements.*.type' => ['required', Rule::in(['text', 'barcode', 'qr', 'image', 'line', 'rect', 'ellipse', 'row'])],
            'elements.*.x' => 'required|numeric|min:0|max:1000', 'elements.*.y' => 'required|numeric|min:0|max:1000',
            'elements.*.width' => 'required|numeric|min:0.1|max:1000', 'elements.*.height' => 'required|numeric|min:0.1|max:1000',
            'elements.*.rotation' => 'nullable|numeric|min:0|max:359',
            'elements.*.fontFamily' => ['sometimes', Rule::in(self::FONTS)],
            'elements.*.fontSize' => 'sometimes|numeric|min:3|max:150', 'elements.*.minFontSize' => 'sometimes|numeric|min:3|max:150',
            'elements.*.lineHeight' => 'sometimes|numeric|min:0.8|max:3',
            'elements.*.color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements.*.fill' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements.*.strokeWidth' => 'sometimes|numeric|min:0.05|max:10',
            'elements.*.content' => 'sometimes|nullable|string|max:4000', 'elements.*.field' => 'sometimes|nullable|string|max:100',
            'elements.*.format' => ['sometimes', Rule::in(['CODE128', 'EAN13', 'EAN8', 'UPC'])],
            'elements.*.align' => ['sometimes', Rule::in(['left', 'center', 'right'])],
            'elements.*.direction' => ['sometimes', Rule::in(['rtl', 'ltr', 'auto'])],
            'elements.*.overflow' => ['sometimes', Rule::in(['shrink', 'ellipsis'])],
            'elements.*.valueFormat' => ['sometimes', Rule::in(['text', 'number', 'money', 'date'])],
            'elements.*.visible' => 'sometimes|boolean', 'elements.*.locked' => 'sometimes|boolean',
            'elements.*.bold' => 'sometimes|boolean', 'elements.*.showText' => 'sometimes|boolean',
            'elements.*.groupId' => 'sometimes|nullable|string|max:80', 'elements.*.mediaId' => 'sometimes|nullable|integer|min:1',
            'elements.*.parentId' => 'sometimes|nullable|string|max:80', 'elements.*.order' => 'sometimes|integer|min:0|max:100',
            'elements.*.flexWeight' => 'sometimes|numeric|min:0.1|max:100',
            'elements.*.layout' => 'sometimes|array',
            'elements.*.layout.direction' => 'sometimes|in:rtl,ltr', 'elements.*.layout.mode' => 'sometimes|in:auto,equal,ratio',
            'elements.*.layout.align' => 'sometimes|in:start,center,end', 'elements.*.layout.wrap' => 'sometimes|boolean',
            'elements.*.layout.paddingMm' => 'sometimes|numeric|min:0|max:30', 'elements.*.layout.gapMm' => 'sometimes|numeric|min:0|max:50', 'elements.*.layout.rowGapMm' => 'sometimes|numeric|min:0|max:50',
            'elements.*.priceFormat' => 'sometimes|array', 'elements.*.priceFormat.decimals' => 'required_with:elements.*.priceFormat|integer|min:0|max:6',
            'elements.*.priceFormat.showCurrency' => 'sometimes|boolean', 'elements.*.priceFormat.trimZeros' => 'sometimes|boolean', 'elements.*.priceFormat.groupThousands' => 'sometimes|boolean',
            'elements.*.priceFormat.currencySymbol' => 'sometimes|nullable|string|max:20', 'elements.*.priceFormat.currencyPosition' => 'sometimes|in:before,after',
            'overlapApprovals' => 'sometimes|array|max:200', 'overlapApprovals.*.key' => 'required|string|max:170', 'overlapApprovals.*.signature' => 'required|string|max:20000',
            'elements.*.prefix' => 'sometimes|nullable|string|max:100', 'elements.*.suffix' => 'sometimes|nullable|string|max:100',
            'sheet' => 'required|array', 'sheet.columns' => 'required|integer|min:1|max:30', 'sheet.rows' => 'required|integer|min:1|max:60',
            'sheet.marginTop' => 'required|numeric|min:0|max:100', 'sheet.marginLeft' => 'required|numeric|min:0|max:100',
            'sheet.gapX' => 'required|numeric|min:0|max:50', 'sheet.gapY' => 'required|numeric|min:0|max:50',
            'sheet.marginRight' => 'sometimes|numeric|min:0|max:100', 'sheet.marginBottom' => 'sometimes|numeric|min:0|max:100',
            'sheet.orientation' => ['sometimes', Rule::in(['portrait', 'landscape'])], 'sheet.direction' => ['sometimes', Rule::in(['rtl', 'ltr'])],
        ])->validate();
        foreach (self::references($design) as $field) {
            if (! in_array($field, $allowedFields, true)) {
                throw ValidationException::withMessages(['design' => "الحقل «{$field}» غير متاح أو غير مسموح لك باستخدامه."]);
            }
        }
        $rows = collect($design['elements'])->where('type', 'row')->keyBy('id');
        if ($publishing && $design['schemaVersion'] === 2 && ! collect($design['elements'])->contains(fn ($e) => $e['type'] !== 'row' && ($e['visible'] ?? true) && (empty($e['parentId']) || ($rows->get($e['parentId'])['visible'] ?? true)))) {
            throw ValidationException::withMessages(['design' => 'أضف محتوى ظاهرًا إلى الملصق قبل النشر.']);
        }

        foreach ($design['elements'] as $element) {
            if (($element['type'] === 'row' || isset($element['priceFormat']) || ! empty($element['parentId'])) && $design['schemaVersion'] !== 2) {
                throw ValidationException::withMessages(['design' => 'هذه الميزة تحتاج الإصدار الثاني من التصميم.']);
            }
            if ($element['type'] === 'row' && (! empty($element['parentId']) || ($element['rotation'] ?? 0) != 0)) {
                throw ValidationException::withMessages(['design' => 'الصفوف المتداخلة أو المدورة غير مدعومة.']);
            }
            if (! empty($element['parentId']) && (! $rows->has($element['parentId']) || ! in_array($element['type'], ['text', 'image', 'barcode', 'qr']))) {
                throw ValidationException::withMessages(['design' => 'صف العنصر غير صالح.']);
            }
            if (isset($element['priceFormat'])) {
                $refs = self::references(['elements' => [$element]]);
                $money = ['variant.retail_price', 'variant.wholesale_price', 'variant.purchase_price', 'unit.price', 'invoice.unit_price'];
                if ($element['type'] !== 'text' || count(array_intersect($refs, $money)) !== 1) {
                    throw ValidationException::withMessages(['design' => 'تنسيق السعر يحتاج حقلًا ماليًا واحدًا.']);
                }
            }
        }
        foreach ($design['elements'] as $element) {
            $literal = preg_replace('/\{\{[a-zA-Z0-9_.-]+\}\}/', '', $element['content'] ?? '');
            if (str_contains($literal, '{{') || str_contains($literal, '}}')) {
                throw ValidationException::withMessages(['design' => 'استخدم حقول البيانات من القائمة؛ الصيغ الحسابية والحقول غير المكتملة غير مدعومة.']);
            }
            $imageFields = ['company.logo', 'product.image', 'variant.image'];
            if (! empty($element['field']) && (($element['type'] === 'image' && ! in_array($element['field'], $imageFields, true)) || (in_array($element['type'], ['barcode', 'qr']) && in_array($element['field'], $imageFields, true)))) {
                throw ValidationException::withMessages(['design' => 'نوع حقل البيانات لا يتوافق مع نوع العنصر.']);
            }
            if (in_array($element['type'], ['barcode', 'qr']) && fmod((float) ($element['rotation'] ?? 0), 90) !== 0.0) {
                throw ValidationException::withMessages(['design' => 'دوران الباركود وQR يجب أن يكون بمضاعفات 90 درجة.']);
            }
            if ($publishing && empty($element['parentId']) && ($element['visible'] ?? true)) {
                $angle = deg2rad((float) ($element['rotation'] ?? 0));
                $bw = abs(cos($angle)) * $element['width'] + abs(sin($angle)) * $element['height'];
                $bh = abs(sin($angle)) * $element['width'] + abs(cos($angle)) * $element['height'];
                $cx = $element['x'] + $element['width'] / 2;
                $cy = $element['y'] + $element['height'] / 2;
                if ($cx - $bw / 2 < -0.01 || $cy - $bh / 2 < -0.01 || $cx + $bw / 2 > $design['widthMm'] + 0.01 || $cy + $bh / 2 > $design['heightMm'] + 0.01) {
                    throw ValidationException::withMessages(['design' => 'يوجد عنصر يتجاوز مساحة الملصق. عدّل موضعه أو حجمه قبل النشر.']);
                }
            }
        }
        // Keep only declared properties: no executable expressions, URLs or renderer-specific state.
        $elementKeys = ['id', 'type', 'x', 'y', 'width', 'height', 'rotation', 'fontFamily', 'fontSize', 'minFontSize', 'lineHeight', 'color', 'fill', 'strokeWidth', 'content', 'field', 'format', 'align', 'direction', 'overflow', 'valueFormat', 'visible', 'locked', 'bold', 'showText', 'groupId', 'mediaId', 'prefix', 'suffix', 'parentId', 'order', 'flexWeight', 'layout', 'priceFormat'];
        foreach ($design['elements'] as &$item) {
            if (isset($item['layout'])) {
                $item['layout'] = array_intersect_key($item['layout'], array_flip(['direction', 'mode', 'align', 'wrap', 'paddingMm', 'gapMm', 'rowGapMm']));
            }
            if (isset($item['priceFormat'])) {
                $item['priceFormat'] = array_intersect_key($item['priceFormat'], array_flip(['decimals', 'showCurrency', 'trimZeros', 'groupThousands', 'currencySymbol', 'currencyPosition']));
            }
        }
        unset($item);
        $design['elements'] = array_map(fn ($e) => array_intersect_key($e, array_flip($elementKeys)), $design['elements']);

        return array_intersect_key($design, array_flip(['schemaVersion', 'widthMm', 'heightMm', 'marginMm', 'background', 'sheet', 'elements', 'overlapApprovals']));
    }
}
