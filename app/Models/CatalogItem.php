<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CatalogItem extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['aliases'=>'array','provenance'=>'array','is_active'=>'boolean'];}
    protected static function booted():void{static::creating(fn(self $item)=>$item->public_id??=(string)Str::uuid());}
    public function category():BelongsTo{return $this->belongsTo(CatalogCategory::class);}
    public function rod():BelongsTo{return $this->belongsTo(Rod::class);}
}
