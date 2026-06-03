<?php

// تعليق عربي: هجرة لتغيير عمود channel في جدول خطوات الأتمتة من نص عادي إلى JSON لدعم اختيار أكثر من قناة إرسال في الخطوة الواحدة.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // تحويل القيم القديمة قبل تغيير نوع العمود
        // both => ["email","whatsapp"]
        // email => ["email"]
        // whatsapp => ["whatsapp"]
        DB::table('notification_workflow_steps')->get()->each(function ($step) {
            $channels = match ($step->channel) {
                'both'     => json_encode(['email', 'whatsapp']),
                'email'    => json_encode(['email']),
                'whatsapp' => json_encode(['whatsapp']),
                default    => json_encode(array_filter(explode(',', $step->channel))),
            };
            DB::table('notification_workflow_steps')
                ->where('id', $step->id)
                ->update(['channel' => $channels]);
        });

        // تغيير نوع العمود إلى text (لأن بعض قواعد البيانات لا تدعم تعديل إلى json مباشرة)
        Schema::table('notification_workflow_steps', function (Blueprint $table) {
            $table->text('channel')->change();
        });
    }

    public function down(): void
    {
        // العودة للقيمة القديمة: إذا كان المصفوفة تحتوي الاثنين نعيد 'both'
        DB::table('notification_workflow_steps')->get()->each(function ($step) {
            $arr = json_decode($step->channel, true) ?? [];
            $old = (count($arr) >= 2) ? 'both' : ($arr[0] ?? 'email');
            DB::table('notification_workflow_steps')
                ->where('id', $step->id)
                ->update(['channel' => $old]);
        });

        Schema::table('notification_workflow_steps', function (Blueprint $table) {
            $table->string('channel')->change();
        });
    }
};
