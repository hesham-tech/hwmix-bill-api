<?php
namespace Modules\Store\Services;
final class StoreGeography {
    public static function governorates(): array {
        return ['cairo'=>'القاهرة','giza'=>'الجيزة','alexandria'=>'الإسكندرية','dakahlia'=>'الدقهلية',
            'red_sea'=>'البحر الأحمر','beheira'=>'البحيرة','fayoum'=>'الفيوم','gharbia'=>'الغربية',
            'ismailia'=>'الإسماعيلية','monufia'=>'المنوفية','minya'=>'المنيا','qalyubia'=>'القليوبية',
            'new_valley'=>'الوادي الجديد','suez'=>'السويس','aswan'=>'أسوان','assiut'=>'أسيوط',
            'beni_suef'=>'بني سويف','port_said'=>'بورسعيد','damietta'=>'دمياط','sharqia'=>'الشرقية',
            'south_sinai'=>'جنوب سيناء','kafr_el_sheikh'=>'كفر الشيخ','matrouh'=>'مطروح',
            'luxor'=>'الأقصر','qena'=>'قنا','north_sinai'=>'شمال سيناء','sohag'=>'سوهاج'];
    }
}
