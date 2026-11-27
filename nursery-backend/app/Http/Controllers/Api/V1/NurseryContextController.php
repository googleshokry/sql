<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\CurrentNursery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The selected nursery, the user's roles in it, and its branches.
 * Branch::all() is filtered by the BelongsToNursery scope.
 */
class NurseryContextController extends Controller
{
    public function __invoke(Request $request, CurrentNursery $current): JsonResponse
    {
        $nursery = $current->get();

        return response()->json(['data' => [
            'id' => $nursery->id,
            'name' => $nursery->name,
            'logo_url' => $nursery->logo_path ? asset('storage/'.$nursery->logo_path) : null,
            'timezone' => $nursery->timezone,
            'roles' => $request->user()->rolesIn($nursery),
            'branches' => Branch::all()->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name]),
        ]]);
    }
}
