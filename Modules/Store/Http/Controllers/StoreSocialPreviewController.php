<?php

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Models\Product;

class StoreSocialPreviewController extends Controller
{
    /**
     * إرجاع صفحة HTML مخصصة لروبوتات السوشيال ميديا (WhatsApp/Facebook)
     * لكي تقرأ عنوان وصورة المنتج
     */
    public function productPreview($id)
    {
        $product = Product::with('images')->find($id);

        if (!$product) {
            return $this->defaultPreview();
        }

        $title = htmlspecialchars($product->name . ' - المتجر');
        $description = htmlspecialchars(mb_substr($product->desc ?? 'تسوق الآن من متجرنا', 0, 160));
        
        $imageUrl = $product->images->first()?->url ?? 'https://bill.hwnix.com/loader.css';
        $url = "https://bill.hwnix.com/store/product/{$id}";

        return response($this->buildHtml($title, $description, $imageUrl, $url))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function storeIndexPreview()
    {
        return $this->defaultPreview();
    }

    private function defaultPreview()
    {
        $title = "المتجر - HWNix ERP";
        $description = "تسوق الآن واكتشف أفضل العروض والمنتجات من متاجرنا";
        $imageUrl = "https://bill.hwnix.com/favicon.ico"; 
        $url = "https://bill.hwnix.com/store";

        return response($this->buildHtml($title, $description, $imageUrl, $url))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function buildHtml($title, $description, $imageUrl, $url)
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    
    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{$url}">
    <meta property="og:title" content="{$title}">
    <meta property="og:description" content="{$description}">
    <meta property="og:image" content="{$imageUrl}">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{$url}">
    <meta property="twitter:title" content="{$title}">
    <meta property="twitter:description" content="{$description}">
    <meta property="twitter:image" content="{$imageUrl}">
</head>
<body>
    <p>جاري التوجيه...</p>
    <script>window.location.replace("{$url}");</script>
</body>
</html>
HTML;
    }
}
