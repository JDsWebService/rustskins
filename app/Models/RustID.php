<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RustID extends Model
{
    protected $table = 'rust_ids';

    public function skins() {
        return $this->hasMany("App\Models\RustSkin", "rust_id");
    }
}
