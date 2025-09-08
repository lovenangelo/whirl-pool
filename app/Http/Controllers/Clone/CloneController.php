<?php

namespace App\Http\Controllers\Clone;

use App\Http\Controllers\Controller;
use App\Services\CloneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CloneController extends Controller
{
    private CloneService $cloneService;

    private const NULLABLE_STRING = 'nullable|string';

    public function __construct(CloneService $cloneService)
    {
        $this->cloneService = $cloneService;
    }

    public function index(): Response
    {
        return Inertia::render('clone/index');
    }

    public function clone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sourcePath' => 'required|string',
            'targetPath' => self::NULLABLE_STRING,
            'sourceDbHost' => self::NULLABLE_STRING,
            'sourceDbName' => self::NULLABLE_STRING,
            'sourceDbUser' => self::NULLABLE_STRING,
            'sourceDbPass' => self::NULLABLE_STRING,
            'targetDbHost' => self::NULLABLE_STRING,
            'targetDbName' => self::NULLABLE_STRING,
            'targetDbUser' => self::NULLABLE_STRING,
            'targetDbPass' => self::NULLABLE_STRING,
            'newDomain' => 'nullable|url',
            'cloneType' => 'required|in:full,files,database',
        ]);

        $result = $this->cloneService->cloneWordPressSite($validated);

        return response()->json($result);
    }
}
