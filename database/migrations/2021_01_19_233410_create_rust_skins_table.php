<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRustSkinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rust_skins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('skin_id');
            $table->unsignedBigInteger('rust_id')->nullable();
            $table->string('date_added');
            $table->string('author');
            $table->string('url');
            $table->string('skin_command');
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
        Schema::dropIfExists('rust_skins');
    }
}
