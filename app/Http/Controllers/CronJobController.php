<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronJobController extends Controller
{
    public function checkExpiredEmployees(Request $request)
    {
        $token = $request->query('token');
        $correctToken = env('DEPLOY_TOKEN', 'd8UUbndtLqnwFDkcYdpYWJR7hXBtLVwC');

        if ($token !== $correctToken) {
            return response()->json(['error' => 'Invalid token'], 403);
        }

        try {
            // Run the command
            // We force send-emails=true as per user requirement implied by the cron context
            Artisan::call('employees:check-expired', [
                '--send-emails' => 'true'
            ]);

            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Command executed successfully',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
