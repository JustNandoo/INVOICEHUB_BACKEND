<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('cover_image_path', 500)->nullable()->after('cover_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn('cover_image_path');
        });
    }
};
