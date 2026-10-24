<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CatalogItem extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['aliases'=>'array','provenance'=>'array','is_active'=>'boolean'];}
    protected static function booted():void{static::creating(fn(self $item)=>$item->public_id??=(string)Str::uuid());}
}
