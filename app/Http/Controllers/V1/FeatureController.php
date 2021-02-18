<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Feature;
use App\Http\Resources\DefaultCollection;

class FeatureController extends Controller
{
    /**
     * Get all resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return new DefaultCollection(Feature::all());
    }

    /**
     * Create resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:features,name',
        ]);

        Feature::create($data);

        return response([
            'message' => 'Property feature added to list.', 
        ]);
    }
}
