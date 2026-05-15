<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ventaPago extends Model
{
   public function venta()
   {
    return $this->belongsTo(venta::class);
   }
}
