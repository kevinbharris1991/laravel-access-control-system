<?php

namespace Kevinbharris1991\LaravelAccessControlSystem\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kevinbharris1991\LaravelAccessControlSystem\Models\Group;

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
