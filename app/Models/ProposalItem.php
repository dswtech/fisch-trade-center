<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['unit_value' => 'float'];
    }
}
