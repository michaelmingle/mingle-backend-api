<?php

namespace App\Http\Controllers;

use App\Support\Concerns\InteractsWithApiResponses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
    use InteractsWithApiResponses;
}
