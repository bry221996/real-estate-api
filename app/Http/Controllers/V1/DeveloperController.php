<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultCollection;
use App\Developer;

class DeveloperController extends Controller
{
    /**
     * List all property developers.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $developers = Developer::all();

        return new DefaultCollection($developers);
    }
}
