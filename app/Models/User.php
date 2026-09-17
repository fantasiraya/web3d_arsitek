<?php

namespace App\Models;

use App\Domains\Auth\Models\User as DomainUser;

/**
 * Backwards-compatible User model proxy pointing to Domains/Auth/Models/User.
 */
class User extends DomainUser {}
