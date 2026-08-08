<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InterestResource;
use App\Http\Resources\SkillResource;
use App\Models\Interest;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Reference lists the onboarding screens need to render their pickers. */
class ReferenceDataController extends Controller
{
    public function skills(Request $request): JsonResponse
    {
        return $this->ok(
            SkillResource::collection(Skill::orderBy('name')->get())->resolve($request)
        );
    }

    public function interests(Request $request): JsonResponse
    {
        return $this->ok(
            InterestResource::collection(Interest::orderBy('name')->get())->resolve($request)
        );
    }
}
