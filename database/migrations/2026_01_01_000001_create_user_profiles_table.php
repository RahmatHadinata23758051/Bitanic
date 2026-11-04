<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['guru', 'siswa'])->index();
            $table->string('identifier_number')->nullable()->index(); // NIP/NUPTK atau NISN/NIS
            $table->string('department')->nullable(); // Jurusan / Kejuruan / Mapel
            $table->string('semester', 20)->nullable(); // Kelas / Semester (khusus siswa)
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->string('gender', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
