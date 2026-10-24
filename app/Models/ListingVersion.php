<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingVersion extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['offered_items'=>'array','wanted_items'=>'array'];}
}
