<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Role;
use App\Http\Resources\DefaultCollection;

class RoleController extends Controller
{
    public function index()
    {
        return new DefaultCollection(Role::all());
    }
}
