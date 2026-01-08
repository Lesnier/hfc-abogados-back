<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronJobController extends Controller
{
    public function checkExpiredEmployees(Request $request)
    {
        // Log start
        Log::info('CRON START: Check Expired Employees');

        $token = $request->query('token');
        $correctToken = env('DEPLOY_TOKEN', 'd8UUbndtLqnwFDkcYdpYWJR7hXBtLVwC');

        if ($token !== $correctToken) {
            Log::warning('CRON WARNING: Invalid token attempt.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid token'], 403);
        }

        try {
            // Run the command
            Artisan::call('employees:check-expired', [
                '--send-emails' => 'true'
            ]);

            $output = Artisan::output();
            
            // Log success (without heavy output)
            Log::info('CRON END: Check Expired Employees - Success');

            return response()->json([
                'success' => true,
                'message' => 'Command executed successfully',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            Log::error('CRON ERROR: Check Expired Employees', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
