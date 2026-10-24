<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InventoryEntry extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['attributes'=>'array','archived_at'=>'immutable_datetime'];}
    protected static function booted():void{static::creating(fn(self $entry)=>$entry->public_id??=(string)Str::uuid());}
}
