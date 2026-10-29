<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_item_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_item_id')->constrained()->cascadeOnDelete();
            $table->string('lot_no');
            $table->date('expires_on');
            $table->unsignedInteger('qty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_item_lots');
    }
};
