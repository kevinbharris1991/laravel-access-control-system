<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessControlController extends Controller
{
    public function account(Request $request): JsonResponse
    {
        $groups = $request->user()->groups()->with('permissions')->get();

        return response()->json([
            'groups' => $groups->pluck('slug'),
            'permissions' => $groups->flatMap->permissions->pluck('slug')->unique()->values(),
        ]);
    }

    public function catalog(): JsonResponse
    {
        return response()->json([
            'groups' => Group::with('permissions:id,slug,name')->orderBy('name')->get(),
        ]);
    }
}
