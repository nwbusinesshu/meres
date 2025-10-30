<?php
// app/Http/Controllers/Api/V1/BaseApiController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    protected $organizationId;
    protected $apiKey;

    /**
     * Get organization ID from request
     * This must be called in controller methods, not in constructor
     */
    protected function getOrganizationId(Request $request)
    {
        if (!$this->organizationId) {
            $this->organizationId = $request->attributes->get('organization_id');
            $this->apiKey = $request->attributes->get('api_key');
        }
        return $this->organizationId;
    }

    protected function successResponse($data, $message = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function errorResponse($message, $code = 400)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code
            ]
        ], $code);
    }

    protected function paginatedResponse($query, $perPage = 50)
    {
        $paginated = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage()
            ]
        ]);
    }
}