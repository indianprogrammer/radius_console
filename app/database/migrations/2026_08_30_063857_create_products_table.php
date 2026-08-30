<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->enum('category', ['one-time', 'recurring'])->default('one-time');
            $t->decimal('default_amount', 10, 2)->default(0);
            $t->string('unit', 30)->default('pcs');   // pcs, meter, month, etc.
            $t->decimal('gst_percentage', 5, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['tenant_id', 'category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
