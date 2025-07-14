<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCode extends Model
{
    protected $table = 'user_codes';

    protected $fillable = ['last_sequence'];

    public static function generateUserCode()
    {
        $userCode = self::firstOrCreate(['id' => 1], ['last_sequence' => 0]);
        
        // Increment the sequence
        $userCode->increment('last_sequence');
        
        // Format the code as PLAYITUSER# followed by 5-digit padded number
        return sprintf('PLAYITUSER#%05d', $userCode->last_sequence);
    }
}