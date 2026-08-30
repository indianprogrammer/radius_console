<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tax_rate', function (Blueprint $t) {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tax_rate_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->primary(['product_id', 'tax_rate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tax_rate');
    }
};
