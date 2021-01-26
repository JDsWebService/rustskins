<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRustIdsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rust_ids', function (Blueprint $table) {
            $table->id();
            $table->string('fullname')->nullable();
            $table->string('shortname');
            $table->string('itemID')->nullable();
            $table->text('description')->nullable();
            $table->integer('default_stack_size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rust_ids');
    }
}
