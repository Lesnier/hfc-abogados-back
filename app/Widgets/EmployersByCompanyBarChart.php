<?php

namespace App\Widgets;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;
use App\Models\Company;
use App\Models\Employee;

class EmployersByCompanyBarChart extends BaseDimmer
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
        $companies = Company::with(['suppliers.employees' => function ($query) {
            $query->select('id', 'supplier_id', 'approval_status'); // Select only necessary columns
        }])->get();

        $companies->each(function ($company) {
            $company->revision_count = 0;
            $company->rejected_count = 0;
            $company->approved_count = 0;
            $company->dismissed_count = 0;

            foreach ($company->suppliers as $supplier) {
                $company->revision_count += $supplier->employees->where('approval_status', 'Revisión')->count();
                $company->rejected_count += $supplier->employees->where('approval_status', 'Rechazado')->count();
                $company->approved_count += $supplier->employees->where('approval_status', 'Aprobado')->count();
                $company->dismissed_count += $supplier->employees->where('approval_status', 'Baja')->count();
            }
        });

        $chartData = [
            'labels' => $companies->pluck('name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Revisión',
                    'data' => $companies->pluck('revision_count')->toArray(),
                    'backgroundColor' => '#e3c06d',
                ],
                [
                    'label' => 'Rechazado',
                    'data' => $companies->pluck('rejected_count')->toArray(),
                    'backgroundColor' => '#a53d3d',
                ],
                [
                    'label' => 'Aprobado',
                    'data' => $companies->pluck('approved_count')->toArray(),
                    'backgroundColor' => '#2c784f',
                ],
                [
                    'label' => 'Baja',
                    'data' => $companies->pluck('dismissed_count')->toArray(),
                    'backgroundColor' => '#c9c7c2',
                ]
            ]
        ];

        return view('vendor.voyager.widgets.employers-by-company-bar-chart', [
            'chartData' => $chartData
        ]);
    }

 public function shouldBeDisplayed()
   {
        $user = Auth::user();
    return $user->hasRole('admin') || $user->hasRole('lawyer') || $user->hasRole('tech_admin');
   }

}