<?php

namespace App\Model\User;

use Illuminate\Database\Eloquent\Model;

class WxUserModel extends Model
{
    protected $table = 'wx_users';
    public $timestamps = false;
    public $primaryKey = 'id';
}
