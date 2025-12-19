<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\NusaworkLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nusawork Auth Controller
 * 
 * Controller untuk handle Nusawork SSO callback.
 */
class NusaworkCallbackController extends Controller
{
    protected NusaworkLoginService $nusaworkLoginService;

    public function __construct(NusaworkLoginService $nusaworkLoginService)
    {
        $this->nusaworkLoginService = $nusaworkLoginService;
    }

    /**
     * Handle Nusawork SSO callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'photo' => 'nullable|string',
            'company.name' => 'required|string',
            'join_code' => 'nullable|string',
        ]);

        try {
            $result = $this->nusaworkLoginService->handleCallback($validated, $request);

            $response = response()->json([
                'status' => $result['status'],
                'message' => __('Login successful'),
                'data' => [
                    'token' => $result['token'],
                    'user' => $result['user'],
                    'tenant' => $result['select_tenant'],
                ],
            ]);

            // Set cookie jika ada
            if (isset($result['cookie'])) {
                $response->cookie($result['cookie']);
            }

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 401);
        }
    }
}
