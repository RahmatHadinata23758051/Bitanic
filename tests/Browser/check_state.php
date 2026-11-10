<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$school = School::find(1);
echo 'School 1: '.($school ? ($school->name.' | status: '.$school->status) : 'NOT FOUND')."\n";

$user = User::where('email', 'siswa@smk.id')->first();
if ($user) {
    echo 'Siswa: '.$user->name.' | school_id: '.$user->school_id."\n";
    echo 'School active? '.($user->school()->where('status', 'active')->exists() ? 'YES' : 'NO')."\n";
    echo "Password check 'password123': ".(Hash::check('password123', $user->password) ? 'YES' : 'NO')."\n";
} else {
    echo "Siswa NOT FOUND\n";
}
