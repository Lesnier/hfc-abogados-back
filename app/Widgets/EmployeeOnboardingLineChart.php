<?php

namespace App\Widgets;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;

class EmployeeOnboardingLineChart extends BaseDimmer
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Treat this method as a controller action.
     * Return view() or other content to display.
     */
    public function run()
    {

        $startDate = Carbon::now()->subMonths(6)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $employeeData = Employee::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_year'),
            DB::raw('COUNT(*) as count'),
            DB::raw("SUM(CASE WHEN `approval_status` = 'Aprobado' THEN 1 ELSE 0 END) as contracted_count"),
            DB::raw("SUM(CASE WHEN `approval_status` = 'Baja' THEN 1 ELSE 0 END) as separated_count")
        )
            ->whereIn('approval_status', ['Aprobado', 'Baja'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month_year')
            ->orderBy('month_year')
            ->get()
            ->keyBy('month_year');

        $labels = [];
        $contractedData = [];
        $currentDate = Carbon::now()->subMonths(6)->startOfMonth();

        for ($i = 0; $i < 7; $i++) {
            $monthYear = $currentDate->format('Y-m');
            $labels[] = $currentDate->isoFormat('MMMM'); // Use isoFormat for localized month names
            $contractedData[] = $employeeData->get($monthYear)->contracted_count ?? 0;
            $separatedData[] = $employeeData->get($monthYear)->separated_count ?? 0;
            $currentDate->addMonth();
        }
        $chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Ingresos',
                    'data' => $contractedData,
                    'backgroundColor' => '#162e47',
                    'borderColor' => '#162e47',
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Separaciones',
                    'data' => $separatedData,
                    'backgroundColor' => '#6c757d',
                    'borderColor' => '#6c757d',
                    'borderWidth' => 2,
                ]
            ]
        ];

        return view('vendor.voyager.widgets.employee-onboarding-line-chart', [
            'chartData' => $chartData
        ]);
    }

     public function shouldBeDisplayed()
   {
        $user = Auth::user();
    return $user->hasRole('admin') || $user->hasRole('lawyer') || $user->hasRole('tech_admin');
   }

}