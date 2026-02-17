<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();
    }

    private function seedCategories()
    {
        $categories = [
            [
                'name' => 'إلكترونيات وأجهزة منزلية',
                'slug' => 'electronics',
                'synonyms' => ['electronics', 'اجهزة الكترونية', 'إلكترونيات', 'كهربائيات'],
                'children' => [
                    [
                        'name' => 'موبايلات وتابلت',
                        'slug' => 'mobile-phones',
                        'synonyms' => ['جوالات', 'موبايلات', 'هواتف ذكية', 'smartphones', 'tablets', 'تابلت', 'محمول'],
                        'children' => [
                            ['name' => 'آيفون', 'slug' => 'iphone-phones', 'synonyms' => ['iphone', 'ايفونات']],
                            ['name' => 'اندرويد', 'slug' => 'android-phones', 'synonyms' => ['android', 'جوالات اندرويد']],
                        ]
                    ],
                    [
                        'name' => 'لابتوب وكمبيوتر',
                        'slug' => 'computers',
                        'synonyms' => ['لابتوب', 'حاسب آلي', 'كمبيوترات', 'laptops', 'pc', 'desktop'],
                        'children' => [
                            ['name' => 'لابتوبات ألعاب', 'slug' => 'gaming-laptops', 'synonyms' => ['gaming laptops', 'لابتوب جيمنج']],
                            ['name' => 'لابتوبات أعمال', 'slug' => 'business-laptops', 'synonyms' => ['business laptops', 'لابتوب شغل']],
                        ]
                    ],
                    ['name' => 'تلفزيونات وشاشات', 'slug' => 'tvs', 'synonyms' => ['شاشات', 'تلفزيون', 'led', 'smart tv', 'screens']],
                    ['name' => 'أجهزة منزلية كبيرة', 'slug' => 'large-appliances', 'synonyms' => ['ثلاجات', 'غسالات', 'بوتاجازات', 'تكييفات', 'refrigerators', 'washers']],
                    ['name' => 'أجهزة منزلية صغيرة', 'slug' => 'small-appliances', 'synonyms' => ['خلاطات', 'مكاوى', 'قلايات', 'blenders', 'air fryers']],
                    ['name' => 'ألعاب فيديو', 'slug' => 'gaming', 'synonyms' => ['بلاي ستيشن', 'كونسل', 'games', 'playstation', 'xbox', 'nintendo']],
                ]
            ],
            [
                'name' => 'سيارات ومركبات',
                'slug' => 'automotive',
                'synonyms' => ['سيارات', 'عربيات', 'automotive', 'cars', 'مركبات'],
                'children' => [
                    [
                        'name' => 'سيارات ركوب',
                        'slug' => 'passenger-cars',
                        'synonyms' => ['ملاكي', 'عربيات ملاكي'],
                        'children' => [
                            ['name' => 'سيدان', 'slug' => 'sedan-cars', 'synonyms' => ['sedan', 'صالون']],
                            ['name' => 'SUV', 'slug' => 'suv-cars', 'synonyms' => ['suv', 'سيارات عائلية', 'جيب']],
                        ]
                    ],
                    ['name' => 'قطع غيار', 'slug' => 'spare-parts', 'synonyms' => ['spare parts', 'قطع غيار سيارات']],
                    ['name' => 'كماليات سيارات', 'slug' => 'car-accessories', 'synonyms' => ['اكسسوارات سيارات', 'accessories']],
                    ['name' => 'موتوسيكلات', 'slug' => 'motorcycles', 'synonyms' => ['دراجات نارية', 'موتوسيكلات', 'scooters']],
                ]
            ],
            [
                'name' => 'ملابس وأزياء',
                'slug' => 'fashion',
                'synonyms' => ['fashion', 'أزياء', 'ملابس', 'clothing'],
                'children' => [
                    ['name' => 'ملابس رجالي', 'slug' => 'men-fashion', 'synonyms' => ['قمصان رجالي', 'بناطيل رجالي', 'رجالي']],
                    ['name' => 'ملابس حريمي', 'slug' => 'women-fashion', 'synonyms' => ['فساتين', 'عبايات', 'طرح', 'نسائي', 'حريمي']],
                    ['name' => 'ملابس أطفال', 'slug' => 'kids-fashion', 'synonyms' => ['ملابس بيبيهات', 'ولادي', 'بناتي']],
                    ['name' => 'أحذية', 'slug' => 'shoes', 'synonyms' => ['كوتشي', 'شوزات', 'shoes', 'footwear']],
                    ['name' => 'ساعات واكسسوارات', 'slug' => 'accessories-fashion', 'synonyms' => ['نظارات', 'محافظ', 'watches', 'glasses']],
                ]
            ],
            [
                'name' => 'أثاث وديكور منزل',
                'slug' => 'home-furniture',
                'synonyms' => ['أثاث', 'ديكور', 'furniture', 'home decor', 'موبيليا'],
                'children' => [
                    ['name' => 'غرف نوم', 'slug' => 'bedroom-furniture', 'synonyms' => ['سراير', 'دواليب']],
                    ['name' => 'غرف معيشة', 'slug' => 'living-room', 'synonyms' => ['كنب', 'انتريهات', 'صالونات']],
                    ['name' => 'مستلزمات مطبخ', 'slug' => 'kitchen-tools', 'synonyms' => ['حلل', 'اطقم عشاء', 'cookware']],
                ]
            ],
            [
                'name' => 'صحة وتجميل',
                'slug' => 'health-beauty',
                'synonyms' => ['عناية شخصية', 'صيدلية', 'beauty', 'care'],
                'children' => [
                    ['name' => 'عناية بالبشرة', 'slug' => 'skin-care', 'synonyms' => ['skincare', 'كريمات', 'غسول']],
                    ['name' => 'عناية بالشعر', 'slug' => 'hair-care', 'synonyms' => ['شامبو', 'haircare', 'صبغات']],
                    ['name' => 'عطور', 'slug' => 'perfumes', 'synonyms' => ['برفانات', 'perfumes', 'fragrances']],
                    ['name' => 'أجهزة طبية', 'slug' => 'medical-devices', 'synonyms' => ['جهاز ضغط', 'سكر', 'كمامات']],
                ]
            ],
            [
                'name' => 'سوبر ماركت',
                'slug' => 'grocery',
                'synonyms' => ['مواد غذائية', 'grocery', 'أطعمة', 'طعام', 'بزاله'],
                'children' => [
                    ['name' => 'ألبان وجبن', 'slug' => 'dairy', 'synonyms' => ['dairy', 'زبادي', 'بيض']],
                    ['name' => 'مشروبات', 'slug' => 'beverages', 'synonyms' => ['عصائر', 'مياه', 'مشروبات غازية']],
                    ['name' => 'معلبات', 'slug' => 'canned-food', 'synonyms' => ['سمن', 'زيت', 'تونا']],
                    ['name' => 'منظفات', 'slug' => 'cleaning', 'synonyms' => ['مسحوق غسيل', 'detergents', 'صابون']],
                ]
            ],
            [
                'name' => 'رياضة وهوايات',
                'slug' => 'sports-hobbies',
                'synonyms' => ['رياضة', 'sports', 'هوايات'],
                'children' => [
                    ['name' => 'أدوات رياضية', 'slug' => 'fitness-equipment', 'synonyms' => ['جيم', 'fitness', 'dumbbells']],
                    ['name' => 'ملابس رياضية', 'slug' => 'sportswear', 'synonyms' => ['ترنجات', 'sportswear']],
                ]
            ]
        ];

        foreach ($categories as $cat) {
            $parent = \App\Models\Category::updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'synonyms' => $cat['synonyms'] ?? [],
                    'active' => true,
                    'company_id' => null
                ]
            );

            if (isset($cat['children'])) {
                foreach ($cat['children'] as $child) {
                    $childModel = \App\Models\Category::updateOrCreate(
                        ['slug' => $child['slug']],
                        [
                            'name' => $child['name'],
                            'synonyms' => $child['synonyms'] ?? [],
                            'parent_id' => $parent->id,
                            'active' => true,
                            'company_id' => null
                        ]
                    );

                    if (isset($child['children'])) {
                        foreach ($child['children'] as $grandChild) {
                            \App\Models\Category::updateOrCreate(
                                ['slug' => $grandChild['slug']],
                                [
                                    'name' => $grandChild['name'],
                                    'synonyms' => $grandChild['synonyms'] ?? [],
                                    'parent_id' => $childModel->id,
                                    'active' => true,
                                    'company_id' => null
                                ]
                            );
                        }
                    }
                }
            }
        }
    }

    private function seedBrands()
    {
        $brands = [
            // Tech & Electronics
            ['name' => 'Samsung', 'slug' => 'samsung', 'synonyms' => ['سامسونج', 'samsung']],
            ['name' => 'Apple', 'slug' => 'apple', 'synonyms' => ['أبل', 'ايفون', 'iphone', 'apple']],
            ['name' => 'Huawei', 'slug' => 'huawei', 'synonyms' => ['هواوي', 'huawei']],
            ['name' => 'Xiaomi', 'slug' => 'xiaomi', 'synonyms' => ['شاومي', 'mi', 'redmi']],
            ['name' => 'Oppo', 'slug' => 'oppo', 'synonyms' => ['أوبو', 'oppo']],
            ['name' => 'Realme', 'slug' => 'realme', 'synonyms' => ['ريلمي', 'realme']],
            ['name' => 'Infinix', 'slug' => 'infinix', 'synonyms' => ['انفينكس', 'infinix']],
            ['name' => 'Sony', 'slug' => 'sony', 'synonyms' => ['سوني', 'sony']],
            ['name' => 'LG', 'slug' => 'lg', 'synonyms' => ['ال جي', 'lg']],
            ['name' => 'Dell', 'slug' => 'dell', 'synonyms' => ['ديل', 'dell']],
            ['name' => 'HP', 'slug' => 'hp', 'synonyms' => ['اتش بي', 'hp']],
            ['name' => 'Lenovo', 'slug' => 'lenovo', 'synonyms' => ['لينوفو', 'lenovo']],

            // Home Appliances - Egypt Focus
            ['name' => 'Fresh', 'slug' => 'fresh', 'synonyms' => ['فريش', 'fresh']],
            ['name' => 'Tornado', 'slug' => 'tornado', 'synonyms' => ['تورنيدو', 'tornado']],
            ['name' => 'Unionaire', 'slug' => 'unionaire', 'synonyms' => ['يونيون اير', 'يونيون اير', 'unionaire']],
            ['name' => 'Kiriazi', 'slug' => 'kiriazi', 'synonyms' => ['كريازي', 'kiriazi']],
            ['name' => 'Toshiba El Araby', 'slug' => 'toshiba-araby', 'synonyms' => ['توشيبا العربي', 'العربي']],
            ['name' => 'Sharp', 'slug' => 'sharp', 'synonyms' => ['شارب', 'sharp']],
            ['name' => 'Beko', 'slug' => 'beko', 'synonyms' => ['بيكو', 'beko']],
            ['name' => 'Black+Decker', 'slug' => 'black-decker', 'synonyms' => ['بلاك اند ديكر', 'black and decker']],
            ['name' => 'Moulinex', 'slug' => 'moulinex', 'synonyms' => ['مولينكس', 'مولينكس']],

            // Automotive
            ['name' => 'Toyota', 'slug' => 'toyota', 'synonyms' => ['تويوتا', 'toyota']],
            ['name' => 'Hyundai', 'slug' => 'hyundai', 'synonyms' => ['هيونداي', 'hyundai']],
            ['name' => 'Kia', 'slug' => 'kia', 'synonyms' => ['كيا', 'kia']],
            ['name' => 'Nissan', 'slug' => 'nissan', 'synonyms' => ['نيسان', 'nissan']],
            ['name' => 'Chevrolet', 'slug' => 'chevrolet', 'synonyms' => ['شيفروليه', 'شيفورليه']],
            ['name' => 'Renault', 'slug' => 'renault', 'synonyms' => ['رينو', 'renault']],
            ['name' => 'MG', 'slug' => 'mg', 'synonyms' => ['ام جي', 'mg']],
            ['name' => 'Chery', 'slug' => 'chery', 'synonyms' => ['شيري', 'chery']],
            ['name' => 'Mercedes-Benz', 'slug' => 'mercedes', 'synonyms' => ['مرسيدس', 'mercedes']],
            ['name' => 'BMW', 'slug' => 'bmw', 'synonyms' => ['بي ام دبليو', 'bmw']],
            ['name' => 'Fiat', 'slug' => 'fiat', 'synonyms' => ['فيات', 'fiat']],

            // Fashion & Sports
            ['name' => 'Nike', 'slug' => 'nike', 'synonyms' => ['نايك', 'نايكي', 'nike']],
            ['name' => 'Adidas', 'slug' => 'adidas', 'synonyms' => ['اديداس', 'أديداس', 'adidas']],
            ['name' => 'Puma', 'slug' => 'puma', 'synonyms' => ['بوما', 'puma']],
            ['name' => 'Zara', 'slug' => 'zara', 'synonyms' => ['زارا', 'zara']],
            ['name' => 'H&M', 'slug' => 'hm', 'synonyms' => ['اتش اند ام', 'h&m']],
            ['name' => 'LC Waikiki', 'slug' => 'lc-waikiki', 'synonyms' => ['ال سي وايكيكي', 'lc waikiki']],
            ['name' => 'DeFacto', 'slug' => 'defacto', 'synonyms' => ['ديفاكتو', 'defacto']],
            ['name' => 'Town Team', 'slug' => 'town-team', 'synonyms' => ['تاون تيم', 'town team']],
            ['name' => 'Concrete', 'slug' => 'concrete', 'synonyms' => ['كونكريت', 'concrete']],
            ['name' => 'Activ', 'slug' => 'activ', 'synonyms' => ['اكتيف', 'activ']],

            // Food & FMCG - Egypt/Arab Focus
            ['name' => 'Juhayna', 'slug' => 'juhayna', 'synonyms' => ['جهينة', 'juhayna']],
            ['name' => 'Almarai', 'slug' => 'almarai', 'synonyms' => ['المراعي', 'almarai']],
            ['name' => 'Domty', 'slug' => 'domty', 'synonyms' => ['دومتي', 'domty']],
            ['name' => 'Pepsi', 'slug' => 'pepsi', 'synonyms' => ['بيبسي', 'pepsi']],
            ['name' => 'Coca-Cola', 'slug' => 'coca-cola', 'synonyms' => ['كوكا كولا', 'كولا']],
            ['name' => 'Chipsy', 'slug' => 'chipsy', 'synonyms' => ['شيبسي', 'chipsy']],
            ['name' => 'Nestle', 'slug' => 'nestle', 'synonyms' => ['نستله', 'nestle']],
            ['name' => 'Halwani Bros', 'slug' => 'halwani', 'synonyms' => ['حلواني اخوان', 'حلواني']],
            ['name' => 'Obour Land', 'slug' => 'obour-land', 'synonyms' => ['عبور لاند']],

            // Beauty & Personal Care
            ['name' => "L'Oreal", 'slug' => 'loreal', 'synonyms' => ['لوريال', 'loreal']],
            ['name' => 'Nivea', 'slug' => 'nivea', 'synonyms' => ['نيفيا', 'nivea']],
            ['name' => 'Dove', 'slug' => 'dove', 'synonyms' => ['دوف', 'dove']],
            ['name' => 'Vatika', 'slug' => 'vatika', 'synonyms' => ['فاتيكا', 'vatika']],
        ];

        foreach ($brands as $brand) {
            \App\Models\Brand::updateOrCreate(
                ['slug' => $brand['slug']],
                [
                    'name' => $brand['name'],
                    'synonyms' => $brand['synonyms'],
                    'active' => true,
                    'company_id' => null
                ]
            );
        }
    }
}
