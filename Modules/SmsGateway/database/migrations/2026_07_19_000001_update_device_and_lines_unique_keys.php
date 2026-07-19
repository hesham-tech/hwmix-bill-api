<?php
// ترحيل لتحديث معمارية قاعدة البيانات بجعل معرف الأندرويد فريداً ومفتاحاً أجنبياً للخطوط.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. جعل حقل android_id فريداً في جدول الأجهزة (مع تجنب أخطاء الفهارس غير الموجودة)
        try {
            Schema::table('smsgate_devices', function (Blueprint $table) {
                $table->dropIndex('smsgate_devices_android_id_index');
            });
        } catch (\Throwable $e) {
            // تجاهل خطأ عدم وجود الفهرس لتفادي فشل الترحيل بالسيرفر
        }

        try {
            Schema::table('smsgate_devices', function (Blueprint $table) {
                $table->string('android_id')->unique()->change();
            });
        } catch (\Throwable $e) {
            // تجاهل إذا تم تعيينه فريداً بالفعل
        }

        // 2. إضافة العمود الجديد مؤقتاً كـ nullable لترحيل البيانات (فقط إذا لم يكن موجوداً)
        if (!Schema::hasColumn('smsgate_lines', 'device_android_id')) {
            Schema::table('smsgate_lines', function (Blueprint $table) {
                $table->string('device_android_id')->nullable()->after('id')->index();
            });
        }

        // 3. ترحيل البيانات القديمة بربطها بمعرف الأندرويد للخطوط الحالية
        if (Schema::hasColumn('smsgate_lines', 'sms_device_id') && Schema::hasColumn('smsgate_lines', 'device_android_id')) {
            try {
                \DB::table('smsgate_lines')
                    ->join('smsgate_devices', 'smsgate_lines.sms_device_id', '=', 'smsgate_devices.id')
                    ->update(['smsgate_lines.device_android_id' => \DB::raw('smsgate_devices.android_id')]);
            } catch (\Throwable $e) {
                // تجاهل أخطاء التحديث
            }
        }

        // 4. حذف قيد المفتاح الأجنبي القديم والعمود القديم
        if (Schema::hasColumn('smsgate_lines', 'sms_device_id')) {
            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->dropForeign('smsgate_lines_sms_device_id_foreign');
                });
            } catch (\Throwable $e) {
                // تجاهل إذا لم يكن القيد موجوداً بالسيرفر
            }

            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->dropColumn('sms_device_id');
                });
            } catch (\Throwable $e) {
                // تجاهل إذا تم حذفه مسبقاً
            }
        }

        // 5. تعيين العمود الجديد كـ NOT NULL ومفتاح أجنبي
        if (Schema::hasColumn('smsgate_lines', 'device_android_id')) {
            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->string('device_android_id')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                // تجاهل
            }

            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->foreign('device_android_id')
                        ->references('android_id')
                        ->on('smsgate_devices')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                });
            } catch (\Throwable $e) {
                // تجاهل إذا كان القيد موجوداً بالفعل
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('smsgate_lines', 'device_android_id')) {
            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->dropForeign(['device_android_id']);
                });
            } catch (\Throwable $e) {
                // تجاهل
            }
        }

        if (!Schema::hasColumn('smsgate_lines', 'sms_device_id')) {
            Schema::table('smsgate_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('sms_device_id')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('smsgate_lines', 'device_android_id') && Schema::hasColumn('smsgate_lines', 'sms_device_id')) {
            try {
                \DB::table('smsgate_lines')
                    ->join('smsgate_devices', 'smsgate_lines.device_android_id', '=', 'smsgate_devices.android_id')
                    ->update(['smsgate_lines.sms_device_id' => \DB::raw('smsgate_devices.id')]);
            } catch (\Throwable $e) {
                // تجاهل
            }
        }

        if (Schema::hasColumn('smsgate_lines', 'device_android_id')) {
            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->dropColumn('device_android_id');
                });
            } catch (\Throwable $e) {
                // تجاهل
            }
        }

        if (Schema::hasColumn('smsgate_lines', 'sms_device_id')) {
            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->unsignedBigInteger('sms_device_id')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                // تجاهل
            }

            try {
                Schema::table('smsgate_lines', function (Blueprint $table) {
                    $table->foreign('sms_device_id')
                        ->references('id')
                        ->on('smsgate_devices')
                        ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // تجاهل
            }
        }

        Schema::table('smsgate_devices', function (Blueprint $table) {
            try {
                $table->dropUnique('smsgate_devices_android_id_unique');
            } catch (\Throwable $e) {
                // تجاهل
            }
            try {
                $table->string('android_id')->index()->change();
            } catch (\Throwable $e) {
                // تجاهل
            }
        });
    }
};
