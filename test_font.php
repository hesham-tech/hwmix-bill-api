<?php
require 'vendor/autoload.php';
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

$manager = new ImageManager(new Driver());
$canvas = $manager->createImage(800, 800)->fill('ffffff');
try {
    $canvas->text('hwnix.com', 780, 780, function($font) {
        $font->file(__DIR__ . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $font->color('#555555');
        $font->size(28);
        $font->stroke('#ffffff', 3);
        $font->align('right', 'bottom');
    });
    $canvas->save('storage/test_font_output3.jpg');
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
