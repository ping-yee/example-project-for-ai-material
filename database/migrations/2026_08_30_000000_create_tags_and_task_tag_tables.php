<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $databaseDriver = Schema::getConnection()->getDriverName();

        Schema::create('tags', function (Blueprint $table) use ($databaseDriver) {
            $table->id();
            $table->text('name');
            $normalizedName = $table->text('normalized_name');

            if ($databaseDriver === 'mysql') {
                $normalizedName->collation('utf8mb4_bin');
                $table->char('normalized_name_hash', 64)
                    ->storedAs('sha2(`normalized_name`, 256)')
                    ->unique();
            } else {
                $table->unique('normalized_name');
            }

            $table->timestamps();
        });

        Schema::create('tag_task', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_task');
        Schema::dropIfExists('tags');
    }
};
