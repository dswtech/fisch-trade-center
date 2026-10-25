<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealIdentitySnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['usn' => 'encrypted', 'ign' => 'encrypted'];
    }
}
