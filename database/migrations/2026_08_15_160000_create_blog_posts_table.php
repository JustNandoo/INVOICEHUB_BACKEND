<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->text('excerpt');
            $table->json('content');
            $table->string('category', 80)->index();
            $table->string('cover_image_url', 500);
            $table->string('cover_image_alt', 180);
            $table->string('author_name', 100);
            $table->unsignedSmallInteger('reading_time_minutes');
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
