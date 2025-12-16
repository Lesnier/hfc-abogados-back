<?php

namespace App\Widgets;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;

class CompanyDimmer extends BaseDimmer
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
        $count = Company::all()->count();

        return view('vendor.voyager.widgets.metric', array_merge($this->config, [
            'icon'   => 'voyager-company',
            'title'  => "{$count} Empresas",
            'text'   => 'Ve a Empresas para verlas todas.',
            'button' => [
                'text' => 'Ver Empresas',
                'link' => route('voyager.companies.index'),
            ],
            'image' => '/fondo_widgets.jpg',
        ]));
    }

     public function shouldBeDisplayed()
   {
        $user = Auth::user();
    return $user->hasRole('admin') || $user->hasRole('lawyer') || $user->hasRole('tech_admin');
   }

}
