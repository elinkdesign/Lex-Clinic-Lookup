<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Longlist extends Model
{
    use HasFactory;

    protected $table = 'longlists';

    protected $fillable = ['NID', 'LIC', 'name'];
}
