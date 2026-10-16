<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('focus')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['group_id', 'user_id']);
        });

        Schema::create('group_permission', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['group_id', 'permission_id']);
        });

        // A persistent lock prevents concurrent signups or user deletion from reassigning ownership.
        Schema::create('access_control_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('first_user_assigned')->default(false);
        });

        DB::table('access_control_state')->insert([
            'id' => 1,
            'first_user_assigned' => false,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('access_control_state');
        Schema::dropIfExists('group_permission');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('groups');
    }
};
