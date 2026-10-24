<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingVersion extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['offered_items'=>'array','wanted_items'=>'array'];}

    public function items(): HasMany
    {
        return $this->hasMany(ListingVersionItem::class);
    }
}
