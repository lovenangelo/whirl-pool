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

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'sourcePath' => self::NULLABLE_STRING,
                'targetPath' => self::NULLABLE_STRING,
                'sourceDbHost' => self::NULLABLE_STRING,
                'sourceDbName' => self::NULLABLE_STRING,
                'targetDbHost' => self::NULLABLE_STRING,
                'targetDbName' => self::NULLABLE_STRING,
                'cloneType' => 'required|in:full,files,database',
            ]);

            $result = $this->cloneService->cloneWordPressSite($validated);
            if ($result['status']['type'] === 'error') {
                return back()->withErrors([
                    $result['errors']
                ]);
            }
            return Inertia::render('clone/index', [
                'data' => $result,
            ])->toResponse($request)->setStatusCode(200);
        } catch (\Throwable $th) {
            return back()->withErrors([
                'error' => $th->getMessage(),
            ]);
        }
    }
}
