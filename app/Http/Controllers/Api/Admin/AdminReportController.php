<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        $query = Report::query()
            ->with(['reporter', 'reportedUser', 'reportedEvent'])
            ->withStatus($request->input('status'))
            ->latest();

        return $this->paginated(ReportResource::collection($query->paginate($this->perPage())));
    }

    public function update(UpdateReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $report->update(['status' => $request->input('status')]);

        return $this->ok(
            new ReportResource($report->refresh()->load(['reporter', 'reportedUser', 'reportedEvent'])),
            'Report updated.'
        );
    }
}
