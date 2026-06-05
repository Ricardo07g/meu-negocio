<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\RedeTrait;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use RedeTrait;
}
