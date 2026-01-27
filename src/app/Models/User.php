<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Proxy User model for JWT token verification.
 * User data is stored in Users microservice, not in Blog database.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 */
class User extends Authenticatable
{
    protected $fillable = [
        'id',
        'name',
        'email',
    ];

    public $exists = true;

    public $timestamps = false;
}
