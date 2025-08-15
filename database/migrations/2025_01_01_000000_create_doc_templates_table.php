<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('doc_templates', function (Blueprint $t) {
            $t->id();
            $t->string('template_key')->unique();
            $t->json('descriptor');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('doc_templates');
    }
};
